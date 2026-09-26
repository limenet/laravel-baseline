/**
 * Parse an .npmrc into a key => value map. Comments (# and ;) and lines without
 * an `=` are skipped, mirroring AbstractCheck::getNpmrc() in the PHP runner.
 */
export function parseNpmrc(contents: string | null): Record<string, string> {
    const config: Record<string, string> = {}

    for (const rawLine of (contents ?? '').split('\n')) {
        const line = rawLine.trim()

        if (line === '' || line.startsWith('#') || line.startsWith(';') || !line.includes('=')) {
            continue
        }

        const separator = line.indexOf('=')

        config[line.slice(0, separator).trim()] = line.slice(separator + 1).trim()
    }

    return config
}

/**
 * Set each key, updating an existing line in place or appending it, so all other
 * lines and comments survive.
 */
export function upsertNpmrc(contents: string | null, entries: Record<string, string>): string {
    const existing = contents ?? ''
    const lines = existing === '' ? [] : existing.replace(/\n+$/, '').split('\n')

    for (const [key, value] of Object.entries(entries)) {
        const line = `${key}=${value}`
        const index = lines.findIndex((candidate) =>
            new RegExp(`^\\s*${key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*=`).test(candidate),
        )

        if (index === -1) {
            lines.push(line)
        } else {
            lines[index] = line
        }
    }

    return `${lines.join('\n')}\n`
}

/**
 * Every value of a list-valued key, written either `key[]=value` (npm's form) or
 * as a repeated `key=value`. parseNpmrc() keeps only the last of those.
 */
export function npmrcList(contents: string | null, key: string): string[] {
    const values: string[] = []

    for (const rawLine of (contents ?? '').split('\n')) {
        const line = rawLine.trim()
        const separator = line.indexOf('=')

        if (line.startsWith('#') || line.startsWith(';') || separator === -1) {
            continue
        }

        const lineKey = line.slice(0, separator).trim()

        if (lineKey === key || lineKey === `${key}[]`) {
            values.push(line.slice(separator + 1).trim())
        }
    }

    return values
}

/** Append `key[]=value` for each value, after whatever the file already holds. */
export function appendNpmrcList(contents: string, key: string, values: string[]): string {
    const lines = contents.replace(/\n+$/, '').split('\n')

    return `${[...lines, ...values.map((value) => `${key}[]=${value}`)].join('\n')}\n`
}
