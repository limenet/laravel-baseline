/**
 * Reads the object literal a JS/TS config file exports — `export default {…}`,
 * `export default defineConfig({…})` or `defineConfig(() => ({…}))` — keeping
 * the source offsets of every member, so a fix can edit the file in place.
 *
 * This is a scanner, not a JavaScript parser: it understands comments, strings,
 * template literals, regex literals and bracket nesting, and nothing else.
 * Whatever it cannot place it reports as an `other` node or, for the file as a
 * whole, as null. It never guesses, because the caller writes to a file a
 * developer curated by hand.
 *
 * Deliberately not a real parser: the PHP runner has to read the same files
 * and has none, so both carry this scanner (src/Support/JsObjectLiteral.php)
 * and the shared fixtures hold them to the same answers.
 */

export interface JsNode {
    kind: 'object' | 'array' | 'string' | 'other'
    /** Offset of the value's first character. */
    start: number
    /** Offset just past the value's last character. */
    end: number
    /** The members of an object, in source order. */
    members: JsMember[]
    /** The elements of an array, in source order. */
    elements: JsNode[]
    /** The decoded value of a string; null otherwise. */
    value: string | null
    /** The quote character a string was written with. */
    quote: string | null
}

export interface JsMember {
    start: number
    /** Offset just past the member's last character, before any comma. */
    end: number
    /** The key, or null for a spread, a computed key, a method or an accessor. */
    key: string | null
    /** The value after the colon, or null for a shorthand member or a keyless one. */
    value: JsNode | null
}

/** The item's text, without a trailing comma. */
export type Render = (indent: string, trailing: boolean, inline: boolean) => string

class ScanError extends Error {}

function node(kind: JsNode['kind'], start: number, end: number, extra: Partial<JsNode> = {}): JsNode {
    return { kind, start, end, members: [], elements: [], value: null, quote: null, ...extra }
}

/** Every member written under a plain key of that name. */
export function membersNamed(object: JsNode, key: string): JsMember[] {
    return object.members.filter((member) => member.key === key)
}

/**
 * Whether the object has a member whose key cannot be read off the source — a
 * spread or a computed key — and which might therefore define any key.
 */
export function hasOpaqueMembers(object: JsNode): boolean {
    return object.members.some((member) => member.key === null)
}

/**
 * The exported config object, or null when the file exports something this
 * scanner does not follow (a variable, a block-bodied function, `mergeConfig()`).
 */
export function configObject(source: string): JsNode | null {
    try {
        return new Scanner(source).findConfig()
    } catch (error) {
        if (error instanceof ScanError) {
            return null
        }

        throw error
    }
}

/** The leading whitespace of the line an offset is on. */
export function lineIndent(source: string, offset: number): string {
    const lineStart = offset === 0 ? 0 : source.lastIndexOf('\n', offset - 1) + 1

    return /^[ \t]*/.exec(source.slice(lineStart))?.[0] ?? ''
}

function insert(source: string, at: number, text: string, remove = 0): string {
    return source.slice(0, at) + text + source.slice(at + remove)
}

/**
 * Appends one item to an object or array literal, as its last member.
 *
 * A container that spans lines gets the item on a line of its own, indented
 * like the member before it, with a trailing comma exactly when that member had
 * one; a single-line container gets it inline.
 *
 * @param unit One level of indentation, for a container that is still empty.
 */
export function append(source: string, container: JsNode, unit: string, render: Render): string {
    const spans = container.kind === 'object' ? container.members : container.elements
    const multiLine = source.slice(container.start, container.end).includes('\n')
    const last = spans.at(-1)

    if (last === undefined) {
        const outer = lineIndent(source, container.start)
        const item = `\n${outer}${unit}${render(outer + unit, true, false)},`

        if (multiLine) {
            return insert(source, container.start + 1, item)
        }

        const inner = source.slice(container.start + 1, container.end - 1)

        // Anything but whitespace between the brackets is a comment, which stays.
        return inner.trim() === ''
            ? insert(source, container.start + 1, `${item}\n${outer}`, inner.length)
            : insert(source, container.end - 1, `${item}\n${outer}`)
    }

    if (!multiLine) {
        return insert(source, last.end, `, ${render('', false, true)}`)
    }

    const indent = lineIndent(source, last.start)

    // A line comment after the last member describes that member, so the new
    // one goes on the next line rather than between the two.
    const pattern = /[ \t]*(,)?[ \t]*(\/\/[^\r\n]*)?(?=\n|$)/y
    pattern.lastIndex = last.end
    const tail = pattern.exec(source)

    if (tail === null) {
        return insert(source, last.end, `,\n${indent}${render(indent, false, false)}`)
    }

    const newline = source.indexOf('\n', last.end)
    const lineEnd = newline === -1 ? source.length : newline

    if (tail[1] === ',') {
        return insert(source, lineEnd, `\n${indent}${render(indent, true, false)},`)
    }

    return insert(insert(source, lineEnd, `\n${indent}${render(indent, false, false)}`), last.end, ',')
}

class Scanner {
    constructor(private readonly source: string) {}

    findConfig(): JsNode | null {
        let i = 0
        let depth = 0
        let previous = ''

        while (true) {
            i = this.skipTrivia(i)

            if (i >= this.source.length) {
                return null
            }

            if (depth === 0 && this.wordAt(i, 'export')) {
                const next = this.skipTrivia(i + 6)

                if (this.wordAt(next, 'default')) {
                    return this.configAfter(this.skipTrivia(next + 7))
                }
            }

            const char = this.char(i)

            if ('([{'.includes(char)) {
                depth++
            } else if (')]}'.includes(char)) {
                depth--
            }

            i = this.skipToken(i, previous)
            previous = this.char(i - 1)
        }
    }

    /** The object literal an `export default` at this offset evaluates to. */
    private configAfter(start: number): JsNode | null {
        let i = start

        if (this.wordAt(i, 'defineConfig')) {
            i = this.skipTrivia(i + 12)

            if (this.char(i) !== '(') {
                return null
            }

            i = this.skipTrivia(i + 1)

            if (this.char(i) !== '{') {
                const body = this.skipArrowHead(i)

                if (body === null || this.char(body) !== '(') {
                    return null
                }

                i = this.skipTrivia(body + 1)
            }
        }

        if (this.char(i) !== '{') {
            return null
        }

        const value = this.value(i)

        return value.kind === 'object' ? value : null
    }

    /**
     * Skips `(…) =>`, `name =>` or either after `async`, returning the offset of
     * what the arrow function returns, or null when this is not an arrow function.
     */
    private skipArrowHead(start: number): number | null {
        let i = start

        if (this.wordAt(i, 'async')) {
            i = this.skipTrivia(i + 5)
        }

        if (this.char(i) === '(') {
            i = this.skipBalanced(i)
        } else if (isWordChar(this.char(i))) {
            i = this.skipToken(i, '')
        } else {
            return null
        }

        i = this.skipTrivia(i)

        return this.source.startsWith('=>', i) ? this.skipTrivia(i + 2) : null
    }

    /**
     * The value starting at this offset. An object, array or string only counts
     * as one when nothing is done to it afterwards — `{…}.x`, `[…].concat()` and
     * `'a' + b` are expressions.
     */
    private value(i: number): JsNode {
        const char = this.char(i)
        let found: JsNode | null = null

        if (char === '{') {
            found = this.object(i)
        } else if (char === '[') {
            found = this.array(i)
        } else if (char === '"' || char === "'" || char === '`') {
            found = this.string(i)
        }

        if (found !== null) {
            const after = this.skipTrivia(found.end)

            if (after >= this.source.length || ',}]);'.includes(this.char(after))) {
                return found
            }
        }

        return node('other', i, this.skipExpression(i))
    }

    private object(start: number): JsNode {
        const members: JsMember[] = []
        let i = start + 1

        while (true) {
            i = this.skipTrivia(i)

            if (this.char(i) === '}') {
                return node('object', start, i + 1, { members })
            }

            const member = this.member(i)
            members.push(member)
            i = this.skipTrivia(member.end)

            if (this.char(i) === ',') {
                i++
            } else if (this.char(i) !== '}') {
                throw new ScanError('Expected "," or "}"')
            }
        }
    }

    private member(start: number): JsMember {
        const [key, afterKey] = this.key(start)

        if (key === null) {
            return { start, end: this.skipExpression(start), key: null, value: null }
        }

        const i = this.skipTrivia(afterKey)

        if (this.char(i) === ':') {
            const value = this.value(this.skipTrivia(i + 1))

            return { start, end: value.end, key, value }
        }

        // `server,` — a shorthand member, whose value lives somewhere else.
        if (this.char(i) === ',' || this.char(i) === '}') {
            return { start, end: afterKey, key, value: null }
        }

        // A method or an accessor: `get server() {…}` is not a key called "get".
        return { start, end: this.skipExpression(start), key: null, value: null }
    }

    /**
     * A plain member key — an identifier or a quoted name — and the offset after
     * it; a null key for a spread, a computed key or anything else.
     */
    private key(i: number): [string | null, number] {
        const char = this.char(i)

        if (char === '"' || char === "'") {
            const name = this.string(i)

            return [name.value, name.end]
        }

        if (isWordChar(char) && !/[0-9]/.test(char)) {
            const end = this.skipToken(i, '')

            return [this.source.slice(i, end), end]
        }

        return [null, i]
    }

    private array(start: number): JsNode {
        const elements: JsNode[] = []
        let i = start + 1

        while (true) {
            i = this.skipTrivia(i)

            if (this.char(i) === ']') {
                return node('array', start, i + 1, { elements })
            }

            if (this.char(i) === ',') {
                throw new ScanError('Array holes are not supported')
            }

            const element = this.value(i)
            elements.push(element)
            i = this.skipTrivia(element.end)

            if (this.char(i) === ',') {
                i++
            } else if (this.char(i) !== ']') {
                throw new ScanError('Expected "," or "]"')
            }
        }
    }

    private string(start: number): JsNode {
        const quote = this.char(start)
        const end = quote === '`' ? this.skipTemplate(start) : this.skipString(start)
        const raw = this.source.slice(start + 1, end - 1)

        // A template literal that interpolates is an expression, not a string.
        if (quote === '`' && raw.includes('${')) {
            return node('other', start, end)
        }

        return node('string', start, end, { value: raw.replace(/\\([\s\S])/g, '$1'), quote })
    }

    /**
     * The offset just past the expression starting here: up to the next comma,
     * semicolon or closing bracket that is not nested inside it.
     */
    private skipExpression(start: number): number {
        let i = start
        let end = start
        let depth = 0
        let previous = ''

        while (true) {
            i = this.skipTrivia(i)

            if (i >= this.source.length) {
                return end
            }

            const char = this.char(i)

            if (depth === 0 && ',;)]}'.includes(char)) {
                return end
            }

            if ('([{'.includes(char)) {
                depth++
            } else if (')]}'.includes(char)) {
                depth--
            }

            i = this.skipToken(i, previous)
            end = i
            previous = this.char(i - 1)
        }
    }

    /** The offset just past the bracket that closes the one at this offset. */
    private skipBalanced(start: number): number {
        let i = start
        let depth = 0
        let previous = ''

        while (true) {
            i = this.skipTrivia(i)

            if (i >= this.source.length) {
                throw new ScanError('Unbalanced bracket')
            }

            const char = this.char(i)

            if ('([{'.includes(char)) {
                depth++
            } else if (')]}'.includes(char)) {
                depth--
            }

            i = this.skipToken(i, previous)
            previous = this.char(i - 1)

            if (depth === 0) {
                return i
            }
        }
    }

    /**
     * Skips one token: a string, a template literal, a regex literal, a word, or
     * a single character of anything else.
     *
     * @param previous The last character of the token before, which is what
     *                 tells a regex literal from a division.
     */
    private skipToken(start: number, previous: string): number {
        const char = this.char(start)

        if (char === '"' || char === "'") {
            return this.skipString(start)
        }

        if (char === '`') {
            return this.skipTemplate(start)
        }

        if (char === '/' && (previous === '' || '(,=:[!&|?{};+-*%<>~^'.includes(previous))) {
            return this.skipRegex(start)
        }

        if (isWordChar(char)) {
            let i = start

            while (i < this.source.length && isWordChar(this.char(i))) {
                i++
            }

            return i
        }

        return start + 1
    }

    private skipString(start: number): number {
        const quote = this.char(start)

        for (let i = start + 1; i < this.source.length; i++) {
            const char = this.char(i)

            if (char === '\\') {
                i++
            } else if (char === quote) {
                return i + 1
            } else if (char === '\n') {
                break
            }
        }

        throw new ScanError('Unterminated string')
    }

    private skipTemplate(start: number): number {
        for (let i = start + 1; i < this.source.length; i++) {
            const char = this.char(i)

            if (char === '\\') {
                i++
            } else if (char === '`') {
                return i + 1
            } else if (char === '$' && this.char(i + 1) === '{') {
                i = this.skipBalanced(i + 1) - 1
            }
        }

        throw new ScanError('Unterminated template literal')
    }

    private skipRegex(start: number): number {
        let inClass = false

        for (let i = start + 1; i < this.source.length; i++) {
            const char = this.char(i)

            if (char === '\\') {
                i++
            } else if (char === '\n') {
                break
            } else if (char === '[') {
                inClass = true
            } else if (char === ']') {
                inClass = false
            } else if (char === '/' && !inClass) {
                i++

                while (i < this.source.length && /[A-Za-z]/.test(this.char(i))) {
                    i++
                }

                return i
            }
        }

        throw new ScanError('Unterminated regex literal')
    }

    /** Skips whitespace and comments. */
    private skipTrivia(start: number): number {
        let i = start

        while (i < this.source.length) {
            const char = this.char(i)

            if (/\s/.test(char)) {
                i++
            } else if (char === '/' && this.char(i + 1) === '/') {
                const end = this.source.indexOf('\n', i)
                i = end === -1 ? this.source.length : end
            } else if (char === '/' && this.char(i + 1) === '*') {
                const end = this.source.indexOf('*/', i + 2)

                if (end === -1) {
                    throw new ScanError('Unterminated comment')
                }

                i = end + 2
            } else {
                break
            }
        }

        return i
    }

    private wordAt(i: number, word: string): boolean {
        return this.source.startsWith(word, i) && !isWordChar(this.char(i + word.length))
    }

    /** The character at an offset, or an empty string past the end. */
    private char(i: number): string {
        return this.source[i] ?? ''
    }
}

/** Identifier characters, digits included; every non-ASCII character counts as one. */
function isWordChar(char: string): boolean {
    return char !== '' && (/[A-Za-z0-9_$]/.test(char) || char.charCodeAt(0) >= 0x80)
}
