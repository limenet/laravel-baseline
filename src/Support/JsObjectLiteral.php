<?php

namespace Limenet\LaravelBaseline\Support;

/**
 * Reads the object literal a JS/TS config file exports — `export default {…}`,
 * `export default defineConfig({…})` or `defineConfig(() => ({…}))` — keeping
 * the source offsets of every member, so a fix can edit the file in place.
 *
 * This is a scanner, not a JavaScript parser: it understands comments, strings,
 * template literals, regex literals and bracket nesting, and nothing else.
 * Whatever it cannot place it reports as {@see JsNode::OTHER} or, for the file
 * as a whole, as null. It never guesses, because the caller writes to a file a
 * developer curated by hand.
 *
 * The npm runner carries the same scanner in js/src/support/js-object-literal.ts.
 */
final class JsObjectLiteral
{
    private readonly int $length;

    private function __construct(private readonly string $source)
    {
        $this->length = strlen($source);
    }

    /**
     * The exported config object, or null when the file exports something this
     * scanner does not follow (a variable, a block-bodied function, `mergeConfig()`).
     */
    public static function config(string $source): ?JsNode
    {
        $scanner = new self($source);

        try {
            return $scanner->findConfig();
        } catch (\UnexpectedValueException) {
            return null;
        }
    }

    /**
     * Appends one item to an object or array literal, as its last member.
     *
     * A container that spans lines gets the item on a line of its own, indented
     * like the member before it, with a trailing comma exactly when that member
     * had one; a single-line container gets it inline.
     *
     * @param  callable(string $indent, bool $trailing, bool $inline): string  $render  The item's text, without a trailing comma.
     * @param  string  $unit  One level of indentation, for a container that is still empty.
     */
    public static function append(string $source, JsNode $container, string $unit, callable $render): string
    {
        $spans = $container->spans();
        $multiLine = str_contains(substr($source, $container->start, $container->end - $container->start), "\n");

        if ($spans === []) {
            $outer = self::lineIndent($source, $container->start);
            $item = "\n{$outer}{$unit}".$render($outer.$unit, true, false).',';

            if ($multiLine) {
                return substr_replace($source, $item, $container->start + 1, 0);
            }

            $inner = substr($source, $container->start + 1, $container->end - $container->start - 2);

            // Anything but whitespace between the brackets is a comment, which stays.
            return trim($inner) === ''
                ? substr_replace($source, "{$item}\n{$outer}", $container->start + 1, strlen($inner))
                : substr_replace($source, "{$item}\n{$outer}", $container->end - 1, 0);
        }

        [$lastStart, $lastEnd] = $spans[array_key_last($spans)];

        if (!$multiLine) {
            return substr_replace($source, ', '.$render('', false, true), $lastEnd, 0);
        }

        $indent = self::lineIndent($source, $lastStart);

        // A line comment after the last member describes that member, so the new
        // one goes on the next line rather than between the two.
        if (preg_match('#\G[ \t]*(,)?[ \t]*(//[^\r\n]*)?(?=\n|\z)#', $source, $tail, 0, $lastEnd) !== 1) {
            return substr_replace($source, ",\n{$indent}".$render($indent, false, false), $lastEnd, 0);
        }

        $lineEnd = strpos($source, "\n", $lastEnd);
        $lineEnd = $lineEnd === false ? strlen($source) : $lineEnd;

        if (($tail[1] ?? '') === ',') {
            return substr_replace($source, "\n{$indent}".$render($indent, true, false).',', $lineEnd, 0);
        }

        return substr_replace(
            substr_replace($source, "\n{$indent}".$render($indent, false, false), $lineEnd, 0),
            ',',
            $lastEnd,
            0,
        );
    }

    /**
     * The leading whitespace of the line an offset is on.
     */
    public static function lineIndent(string $source, int $offset): string
    {
        $newline = strrpos(substr($source, 0, $offset), "\n");
        $lineStart = $newline === false ? 0 : $newline + 1;

        return substr($source, $lineStart, strspn($source, " \t", $lineStart));
    }

    private function findConfig(): ?JsNode
    {
        $i = 0;
        $depth = 0;
        $previous = '';

        while (true) {
            $i = $this->skipTrivia($i);

            if ($i >= $this->length) {
                return null;
            }

            if ($depth === 0 && $this->wordAt($i, 'export')) {
                $next = $this->skipTrivia($i + 6);

                if ($this->wordAt($next, 'default')) {
                    return $this->configAfter($this->skipTrivia($next + 7));
                }
            }

            $char = $this->source[$i];

            if (str_contains('([{', $char)) {
                $depth++;
            } elseif (str_contains(')]}', $char)) {
                $depth--;
            }

            $i = $this->skipToken($i, $previous);
            $previous = $this->source[$i - 1];
        }
    }

    /**
     * The object literal an `export default` at this offset evaluates to.
     */
    private function configAfter(int $i): ?JsNode
    {
        if ($this->wordAt($i, 'defineConfig')) {
            $i = $this->skipTrivia($i + 12);

            if ($this->char($i) !== '(') {
                return null;
            }

            $i = $this->skipTrivia($i + 1);

            if ($this->char($i) !== '{') {
                $i = $this->skipArrowHead($i);

                if ($i === null || $this->char($i) !== '(') {
                    return null;
                }

                $i = $this->skipTrivia($i + 1);
            }
        }

        if ($this->char($i) !== '{') {
            return null;
        }

        $node = $this->value($i);

        return $node->kind === JsNode::OBJECT ? $node : null;
    }

    /**
     * Skips `(…) =>`, `name =>` or either after `async`, returning the offset of
     * what the arrow function returns, or null when this is not an arrow function.
     */
    private function skipArrowHead(int $i): ?int
    {
        if ($this->wordAt($i, 'async')) {
            $i = $this->skipTrivia($i + 5);
        }

        if ($this->char($i) === '(') {
            $i = $this->skipBalanced($i);
        } elseif ($this->isWordChar($this->char($i))) {
            $i = $this->skipToken($i, '');
        } else {
            return null;
        }

        $i = $this->skipTrivia($i);

        return substr($this->source, $i, 2) === '=>' ? $this->skipTrivia($i + 2) : null;
    }

    /**
     * The value starting at this offset. An object, array or string only counts
     * as one when nothing is done to it afterwards — `{…}.x`, `[…].concat()` and
     * `'a' + b` are expressions.
     */
    private function value(int $i): JsNode
    {
        $char = $this->char($i);

        $node = match (true) {
            $char === '{' => $this->object($i),
            $char === '[' => $this->array($i),
            $char === '"', $char === "'", $char === '`' => $this->string($i),
            default => null,
        };

        if ($node !== null) {
            $after = $this->skipTrivia($node->end);

            if ($after >= $this->length || str_contains(',}]);', $this->source[$after])) {
                return $node;
            }
        }

        return new JsNode(JsNode::OTHER, $i, $this->skipExpression($i));
    }

    private function object(int $start): JsNode
    {
        $members = [];
        $i = $start + 1;

        while (true) {
            $i = $this->skipTrivia($i);

            if ($this->char($i) === '}') {
                return new JsNode(JsNode::OBJECT, $start, $i + 1, members: $members);
            }

            $members[] = $member = $this->member($i);
            $i = $this->skipTrivia($member->end);

            if ($this->char($i) === ',') {
                $i++;
            } elseif ($this->char($i) !== '}') {
                throw new \UnexpectedValueException('Expected "," or "}"');
            }
        }
    }

    private function member(int $start): JsMember
    {
        [$key, $afterKey] = $this->key($start);

        if ($key === null) {
            return new JsMember($start, $this->skipExpression($start), null, null);
        }

        $i = $this->skipTrivia($afterKey);

        if ($this->char($i) === ':') {
            $value = $this->value($this->skipTrivia($i + 1));

            return new JsMember($start, $value->end, $key, $value);
        }

        // `server,` — a shorthand member, whose value lives somewhere else.
        if ($this->char($i) === ',' || $this->char($i) === '}') {
            return new JsMember($start, $afterKey, $key, null);
        }

        // A method or an accessor: `get server() {…}` is not a key called "get".
        return new JsMember($start, $this->skipExpression($start), null, null);
    }

    /**
     * A plain member key — an identifier or a quoted name — and the offset after
     * it; a null key for a spread, a computed key or anything else.
     *
     * @return array{?string, int}
     */
    private function key(int $i): array
    {
        $char = $this->char($i);

        if ($char === '"' || $char === "'") {
            $node = $this->string($i);

            return [$node->value, $node->end];
        }

        if ($this->isWordChar($char) && !ctype_digit($char)) {
            $end = $this->skipToken($i, '');

            return [substr($this->source, $i, $end - $i), $end];
        }

        return [null, $i];
    }

    private function array(int $start): JsNode
    {
        $elements = [];
        $i = $start + 1;

        while (true) {
            $i = $this->skipTrivia($i);

            if ($this->char($i) === ']') {
                return new JsNode(JsNode::ARRAY, $start, $i + 1, elements: $elements);
            }

            if ($this->char($i) === ',') {
                throw new \UnexpectedValueException('Array holes are not supported');
            }

            $elements[] = $element = $this->value($i);
            $i = $this->skipTrivia($element->end);

            if ($this->char($i) === ',') {
                $i++;
            } elseif ($this->char($i) !== ']') {
                throw new \UnexpectedValueException('Expected "," or "]"');
            }
        }
    }

    private function string(int $start): JsNode
    {
        $quote = $this->source[$start];
        $end = $quote === '`' ? $this->skipTemplate($start) : $this->skipString($start);
        $raw = substr($this->source, $start + 1, $end - $start - 2);

        // A template literal that interpolates is an expression, not a string.
        if ($quote === '`' && str_contains($raw, '${')) {
            return new JsNode(JsNode::OTHER, $start, $end);
        }

        return new JsNode(
            JsNode::STRING,
            $start,
            $end,
            value: (string) preg_replace('/\\\\(.)/s', '$1', $raw),
            quote: $quote,
        );
    }

    /**
     * The offset just past the expression starting here: up to the next comma,
     * semicolon or closing bracket that is not nested inside it.
     */
    private function skipExpression(int $i): int
    {
        $depth = 0;
        $previous = '';
        $end = $i;

        while (true) {
            $i = $this->skipTrivia($i);

            if ($i >= $this->length) {
                return $end;
            }

            $char = $this->source[$i];

            if ($depth === 0 && str_contains(',;)]}', $char)) {
                return $end;
            }

            if (str_contains('([{', $char)) {
                $depth++;
            } elseif (str_contains(')]}', $char)) {
                $depth--;
            }

            $i = $end = $this->skipToken($i, $previous);
            $previous = $this->source[$i - 1];
        }
    }

    /**
     * The offset just past the bracket that closes the one at this offset.
     */
    private function skipBalanced(int $i): int
    {
        $depth = 0;
        $previous = '';

        while (true) {
            $i = $this->skipTrivia($i);

            if ($i >= $this->length) {
                throw new \UnexpectedValueException('Unbalanced bracket');
            }

            $char = $this->source[$i];

            if (str_contains('([{', $char)) {
                $depth++;
            } elseif (str_contains(')]}', $char)) {
                $depth--;
            }

            $i = $this->skipToken($i, $previous);
            $previous = $this->source[$i - 1];

            if ($depth === 0) {
                return $i;
            }
        }
    }

    /**
     * Skips one token: a string, a template literal, a regex literal, a word, or
     * a single character of anything else.
     *
     * @param  string  $previous  The last character of the token before, which is
     *                            what tells a regex literal from a division.
     */
    private function skipToken(int $i, string $previous): int
    {
        $char = $this->source[$i];

        if ($char === '"' || $char === "'") {
            return $this->skipString($i);
        }

        if ($char === '`') {
            return $this->skipTemplate($i);
        }

        if ($char === '/' && ($previous === '' || str_contains('(,=:[!&|?{};+-*%<>~^', $previous))) {
            return $this->skipRegex($i);
        }

        if ($this->isWordChar($char)) {
            while ($i < $this->length && $this->isWordChar($this->source[$i])) {
                $i++;
            }

            return $i;
        }

        return $i + 1;
    }

    private function skipString(int $i): int
    {
        $quote = $this->source[$i];

        for ($i++; $i < $this->length; $i++) {
            $char = $this->source[$i];

            if ($char === '\\') {
                $i++;
            } elseif ($char === $quote) {
                return $i + 1;
            } elseif ($char === "\n") {
                break;
            }
        }

        throw new \UnexpectedValueException('Unterminated string');
    }

    private function skipTemplate(int $i): int
    {
        for ($i++; $i < $this->length; $i++) {
            $char = $this->source[$i];

            if ($char === '\\') {
                $i++;
            } elseif ($char === '`') {
                return $i + 1;
            } elseif ($char === '$' && $this->char($i + 1) === '{') {
                $i = $this->skipBalanced($i + 1) - 1;
            }
        }

        throw new \UnexpectedValueException('Unterminated template literal');
    }

    private function skipRegex(int $i): int
    {
        $inClass = false;

        for ($i++; $i < $this->length; $i++) {
            $char = $this->source[$i];

            if ($char === '\\') {
                $i++;
            } elseif ($char === "\n") {
                break;
            } elseif ($char === '[') {
                $inClass = true;
            } elseif ($char === ']') {
                $inClass = false;
            } elseif ($char === '/' && !$inClass) {
                $i++;

                while ($i < $this->length && ctype_alpha($this->source[$i])) {
                    $i++;
                }

                return $i;
            }
        }

        throw new \UnexpectedValueException('Unterminated regex literal');
    }

    /**
     * Skips whitespace and comments.
     */
    private function skipTrivia(int $i): int
    {
        while ($i < $this->length) {
            $char = $this->source[$i];

            if (ctype_space($char)) {
                $i++;
            } elseif ($char === '/' && $this->char($i + 1) === '/') {
                $end = strpos($this->source, "\n", $i);
                $i = $end === false ? $this->length : $end;
            } elseif ($char === '/' && $this->char($i + 1) === '*') {
                $end = strpos($this->source, '*/', $i + 2);

                if ($end === false) {
                    throw new \UnexpectedValueException('Unterminated comment');
                }

                $i = $end + 2;
            } else {
                break;
            }
        }

        return $i;
    }

    private function wordAt(int $i, string $word): bool
    {
        return substr($this->source, $i, strlen($word)) === $word
            && !$this->isWordChar($this->char($i + strlen($word)));
    }

    /**
     * Identifier characters, digits included; every non-ASCII byte counts as one.
     */
    private function isWordChar(string $char): bool
    {
        return $char !== '' && (ctype_alnum($char) || $char === '_' || $char === '$' || ord($char) >= 0x80);
    }

    /**
     * The character at an offset, or an empty string past the end.
     */
    private function char(int $i): string
    {
        return $this->source[$i] ?? '';
    }
}
