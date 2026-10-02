<?php

namespace Limenet\LaravelBaseline\Support;

/**
 * A reader for JSON with comments and trailing commas — the dialect Biome reads
 * its own config in — that keeps the source offsets of every value.
 *
 * json_decode() can do neither: it rejects comments, and it throws away where a
 * value was, so a check that wants to add one entry to an array would have to
 * re-encode the whole file and fight the formatter that owns it.
 */
final class Jsonc
{
    private int $pos = 0;

    private function __construct(private readonly string $source) {}

    /**
     * The document's root value, or null when the source is not valid JSONC.
     */
    public static function parse(string $source): ?JsoncNode
    {
        $parser = new self($source);

        try {
            $parser->skip();
            $root = $parser->value();
            $parser->skip();
        } catch (\UnexpectedValueException) {
            return null;
        }

        return $parser->pos === strlen($source) ? $root : null;
    }

    private function value(): JsoncNode
    {
        return match ($this->source[$this->pos] ?? '') {
            '{' => $this->object(),
            '[' => $this->array(),
            '"' => $this->string(),
            default => $this->literal(),
        };
    }

    private function object(): JsoncNode
    {
        $start = $this->pos++;
        $children = [];

        while (true) {
            $this->skip();

            if ($this->peek() === '}') {
                break;
            }

            $key = $this->string()->value;
            $this->skip();
            $this->expect(':');
            $this->skip();
            $children[is_string($key) ? $key : ''] = $this->value();

            if (!$this->separator('}')) {
                break;
            }
        }

        $this->expect('}');

        return new JsoncNode(JsoncNode::OBJECT, $start, $this->pos, $children);
    }

    private function array(): JsoncNode
    {
        $start = $this->pos++;
        $children = [];

        while (true) {
            $this->skip();

            if ($this->peek() === ']') {
                break;
            }

            $children[] = $this->value();

            if (!$this->separator(']')) {
                break;
            }
        }

        $this->expect(']');

        return new JsoncNode(JsoncNode::ARRAY, $start, $this->pos, $children);
    }

    /**
     * Consumes the comma after a member, if there is one. A trailing comma is
     * accepted: the caller's loop then finds the closing bracket.
     */
    private function separator(string $close): bool
    {
        $this->skip();

        if ($this->peek() === ',') {
            $this->pos++;

            return true;
        }

        if ($this->peek() !== $close) {
            throw new \UnexpectedValueException("Expected ',' or '{$close}' at offset {$this->pos}");
        }

        return false;
    }

    private function string(): JsoncNode
    {
        return $this->scalar('/\G"(?:[^"\\\\\x00-\x1f]|\\\\.)*"/');
    }

    private function literal(): JsoncNode
    {
        return $this->scalar('/\G(?:true|false|null|-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?)/');
    }

    private function scalar(string $pattern): JsoncNode
    {
        if (preg_match($pattern, $this->source, $matches, 0, $this->pos) !== 1) {
            throw new \UnexpectedValueException("Unexpected token at offset {$this->pos}");
        }

        $start = $this->pos;
        $this->pos += strlen($matches[0]);

        try {
            $value = json_decode($matches[0], flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \UnexpectedValueException($e->getMessage(), previous: $e);
        }

        return new JsoncNode(JsoncNode::SCALAR, $start, $this->pos, value: $value);
    }

    /**
     * Skips whitespace, `// line` and `/* block *\/` comments.
     */
    private function skip(): void
    {
        preg_match('#\G(?:\s+|//[^\n]*|/\*.*?\*/)*#s', $this->source, $matches, 0, $this->pos);
        $this->pos += strlen($matches[0] ?? '');
    }

    private function peek(): string
    {
        return $this->source[$this->pos] ?? '';
    }

    private function expect(string $char): void
    {
        if ($this->peek() !== $char) {
            throw new \UnexpectedValueException("Expected '{$char}' at offset {$this->pos}");
        }

        $this->pos++;
    }
}
