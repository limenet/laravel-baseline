<?php

namespace Limenet\LaravelBaseline\Support;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Line-based edits to a block-style YAML mapping that leave every line they
 * do not touch exactly as it was: comments, quoting, blank lines and the
 * file's own indentation. Symfony's dumper rebuilds the whole file instead,
 * dropping every comment and re-indenting it.
 *
 * It follows block mappings, block sequences (indented under their key or
 * flush with it) and one-line flow sequences, which is the shape of trivy.yaml
 * and mutagen.yml. Anything else may come out wrong, so callers confirm the
 * result with matches() and fall back to a full dump when it does not.
 */
final class YamlTextEditor
{
    /** @var list<string> */
    private array $lines;

    private int $indent;

    private int $seqOffset;

    public function __construct(string $contents)
    {
        $this->lines = explode("\n", $contents);
        [$this->indent, $this->seqOffset] = $this->detectStyle();
    }

    public function contents(): string
    {
        return implode("\n", $this->lines);
    }

    /**
     * Whether the edited text parses to $expected, key order aside.
     *
     * @param  array<array-key, mixed>  $expected
     */
    public function matches(array $expected): bool
    {
        try {
            $parsed = Yaml::parse($this->contents());
        } catch (ParseException) {
            return false;
        }

        return self::normalise(is_array($parsed) ? $parsed : []) === self::normalise($expected);
    }

    /**
     * Sets the scalar at $path, replacing its value in place (an inline comment
     * survives) or adding the key, and any missing parents, at the end of the
     * deepest parent that exists.
     *
     * @param  list<string>  $path
     */
    public function set(array $path, mixed $value): void
    {
        $rendered = Yaml::dump($value);
        $hit = $this->find($path);

        if ($hit === null) {
            $this->insert($path, fn (string $pad, string $key): array => ["{$pad}{$key}: {$rendered}"]);

            return;
        }

        [$line, $end] = $hit;
        $last = $this->lastSignificant($line, $end);

        if ($last !== $line) {
            array_splice($this->lines, $line + 1, $last - $line);
        }

        [$key, $rest] = $this->splitKeyLine($this->lines[$line]);
        $comment = preg_match('/(\s+#.*)$/', $rest, $matches) === 1 ? $matches[1] : '';

        array_splice($this->lines, $line, 1, ["{$key} {$rendered}{$comment}"]);
    }

    /**
     * Appends $items to the sequence at $path in the style it already has,
     * creating the key (and any missing parents) when it is absent.
     *
     * @param  list<string>  $path
     * @param  list<string>  $items
     */
    public function append(array $path, array $items): void
    {
        $rendered = array_map(fn (string $item): string => Yaml::dump($item), $items);
        $hit = $this->find($path);

        if ($hit === null) {
            $this->insert($path, fn (string $pad, string $key): array => [
                "{$pad}{$key}:",
                ...array_map(fn (string $item): string => $pad.str_repeat(' ', $this->seqOffset)."- {$item}", $rendered),
            ]);

            return;
        }

        [$line, $end] = $hit;
        $last = $this->lastSignificant($line, $end);

        if ($last !== $line) {
            $dashWidth = $this->width($this->lines[$this->firstSignificant($line, $end)]);
            $new = array_map(fn (string $item): string => str_repeat(' ', $dashWidth)."- {$item}", $rendered);
            array_splice($this->lines, $last + 1, 0, $new);

            return;
        }

        [$key, $rest] = $this->splitKeyLine($this->lines[$line]);

        if (preg_match('/^(\s*)\[(\s*)(.*?)(\s*)\](\s*#.*)?$/', $rest, $flow) === 1) {
            $inner = $flow[3] === '' ? implode(', ', $rendered) : $flow[3].', '.implode(', ', $rendered);
            array_splice($this->lines, $line, 1, ["{$key}{$flow[1]}[{$flow[2]}{$inner}{$flow[4]}]".($flow[5] ?? '')]);

            return;
        }

        if (preg_match('/^\s*(#.*)?$/', $rest) === 1) {
            $pad = str_repeat(' ', $this->width($this->lines[$line]) + $this->seqOffset);
            array_splice($this->lines, $line + 1, 0, array_map(fn (string $item): string => "{$pad}- {$item}", $rendered));
        }
    }

    /**
     * Removes the key at $path together with its nested block.
     *
     * @param  list<string>  $path
     */
    public function remove(array $path): void
    {
        $hit = $this->find($path);

        if ($hit === null) {
            return;
        }

        [$line, $end] = $hit;

        array_splice($this->lines, $line, $this->lastSignificant($line, $end) - $line + 1);
    }

    private static function renderKey(string $key): string
    {
        return preg_match('/^[A-Za-z0-9_.\/-]+$/', $key) === 1 ? $key : Yaml::dump($key);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private static function normalise(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $child): mixed => is_array($child) ? self::normalise($child) : $child, $value);
    }

    /**
     * The line of the key at $path and the end (exclusive) of its block.
     *
     * @param  list<string>  $path
     * @return array{0: int, 1: int}|null
     */
    private function find(array $path): ?array
    {
        $from = 0;
        $to = count($this->lines);
        $found = null;

        foreach ($path as $segment) {
            $found = null;
            $childWidth = null;

            for ($i = $from; $i < $to; $i++) {
                $line = $this->lines[$i];

                if (!$this->significant($line) || $this->isItem($line)) {
                    continue;
                }

                $childWidth ??= $this->width($line);

                if ($this->width($line) === $childWidth && $this->keyOf($line) === $segment) {
                    $found = $i;
                    break;
                }
            }

            if ($found === null) {
                return null;
            }

            $from = $found + 1;
            $to = $this->blockEnd($found);
        }

        return $found === null ? null : [$found, $to];
    }

    /**
     * Adds the missing tail of $path at the end of the deepest parent that
     * exists; $leaf renders the last key at the given padding.
     *
     * @param  list<string>  $path
     * @param  callable(string, string): list<string>  $leaf
     */
    private function insert(array $path, callable $leaf): void
    {
        $parent = null;
        $depth = count($path) - 1;

        for (; $depth > 0; $depth--) {
            $parent = $this->find(array_slice($path, 0, $depth));

            if ($parent !== null) {
                break;
            }
        }

        if ($parent === null) {
            $width = 0;
            $at = $this->lastSignificant(-1, count($this->lines)) + 1;
        } else {
            [$line, $end] = $parent;
            $width = $this->width($this->lines[$line]) + $this->childOffset($line, $end);
            $at = $this->lastSignificant($line, $end) + 1;
        }

        $new = [];

        foreach (array_slice($path, $depth, -1) as $segment) {
            $new[] = str_repeat(' ', $width).self::renderKey($segment).':';
            $width += $this->indent;
        }

        $last = $path[count($path) - 1];
        array_splice($this->lines, $at, 0, [...$new, ...$leaf(str_repeat(' ', $width), self::renderKey($last))]);
    }

    /** Where the block under the key on $line ends (exclusive). */
    private function blockEnd(int $line): int
    {
        $keyWidth = $this->width($this->lines[$line]);

        for ($i = $line + 1, $count = count($this->lines); $i < $count; $i++) {
            if (!$this->significant($this->lines[$i])) {
                continue;
            }

            $width = $this->width($this->lines[$i]);

            // Dashes flush with the key still belong to it.
            if ($width < $keyWidth || ($width === $keyWidth && !$this->isItem($this->lines[$i]))) {
                return $i;
            }
        }

        return count($this->lines);
    }

    /** The last line with content in ($line, $end), or $line when there is none. */
    private function lastSignificant(int $line, int $end): int
    {
        for ($i = $end - 1; $i > $line; $i--) {
            if ($this->significant($this->lines[$i])) {
                return $i;
            }
        }

        return $line;
    }

    /** The first line with content in ($line, $end), or $line when there is none. */
    private function firstSignificant(int $line, int $end): int
    {
        for ($i = $line + 1; $i < $end; $i++) {
            if ($this->significant($this->lines[$i])) {
                return $i;
            }
        }

        return $line;
    }

    /** How far the children of the key on $line sit below it. */
    private function childOffset(int $line, int $end): int
    {
        for ($i = $line + 1; $i < $end; $i++) {
            if ($this->significant($this->lines[$i]) && !$this->isItem($this->lines[$i])) {
                return $this->width($this->lines[$i]) - $this->width($this->lines[$line]);
            }
        }

        return $this->indent;
    }

    /**
     * The mapping indent and the sequence offset under a key, read off the
     * first nested mapping and the first nested sequence.
     *
     * @return array{0: int, 1: int}
     */
    private function detectStyle(): array
    {
        $mapOffset = null;
        $seqOffset = null;
        $parent = null;

        foreach ($this->lines as $line) {
            if (!$this->significant($line)) {
                continue;
            }

            $width = $this->width($line);
            $isItem = $this->isItem($line);

            if ($parent !== null && $parent['opensBlock'] && $width >= $parent['width']) {
                if ($isItem) {
                    $seqOffset ??= $width - $parent['width'];
                } elseif ($width > $parent['width']) {
                    $mapOffset ??= $width - $parent['width'];
                }
            }

            $parent = [
                'width' => $width,
                'opensBlock' => !$isItem && preg_match('/^[^\s#-][^#]*:\s*(#.*)?$/', ltrim($line)) === 1,
            ];

            if ($mapOffset !== null && $seqOffset !== null) {
                break;
            }
        }

        $indent = $mapOffset ?? ($seqOffset !== null && $seqOffset > 0 ? $seqOffset : 2);

        return [$indent, $seqOffset ?? $indent];
    }

    /**
     * The key part of a mapping line (indent, key and colon) and the rest.
     *
     * @return array{0: string, 1: string}
     */
    private function splitKeyLine(string $line): array
    {
        if (preg_match('/^(\s*(?:"[^"]*"|\'[^\']*\'|[^\s#\'"][^:#]*?)\s*:)(.*)$/', $line, $matches) !== 1) {
            return [$line, ''];
        }

        return [$matches[1], $matches[2]];
    }

    private function keyOf(string $line): ?string
    {
        if (preg_match('/^\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s#\'"][^:#]*?))\s*:(?:\s|$)/', $line, $matches) !== 1) {
            return null;
        }

        return $matches[1] !== '' ? $matches[1] : (($matches[2] ?? '') !== '' ? $matches[2] : ($matches[3] ?? null));
    }

    private function significant(string $line): bool
    {
        $trimmed = trim($line);

        return $trimmed !== '' && !str_starts_with($trimmed, '#');
    }

    private function isItem(string $line): bool
    {
        return preg_match('/^\s*-(\s|$)/', $line) === 1;
    }

    private function width(string $line): int
    {
        return strlen($line) - strlen(ltrim($line, ' '));
    }
}
