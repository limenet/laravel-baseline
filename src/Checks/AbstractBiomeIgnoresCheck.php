<?php

namespace Limenet\LaravelBaseline\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Support\Jsonc;
use Limenet\LaravelBaseline\Support\JsoncNode;

/**
 * A set of files Biome must not check, kept out through `files.includes`.
 *
 * `files.includes` is evaluated the way Biome does: in order, the last pattern
 * matching the file deciding, and no `includes` at all meaning every file. A
 * pattern matching one of the file's directories counts as matching the file,
 * so `!dist` keeps `dist/app.js` out. A project whose includes are an
 * allowlist that never reaches the files is fine as it is.
 *
 * The fix edits the text rather than re-encoding the document: Biome formats
 * biome.json itself, and the file may carry comments.
 */
abstract class AbstractBiomeIgnoresCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $configFile = $this->policy()->string('biome.configFile');
        $file = $this->path($configFile);

        if (!file_exists($file)) {
            return CheckResult::PASS;
        }

        $contents = (string) file_get_contents($file);
        $root = Jsonc::parse($contents);

        if ($root?->kind !== JsoncNode::OBJECT) {
            $this->addComment("{$configFile} is not valid JSON");

            return CheckResult::FAIL;
        }

        $includes = $root->get('files')?->get('includes');

        if ($includes !== null && $includes->kind !== JsoncNode::ARRAY) {
            $this->addComment("Invalid files.includes in {$configFile}: must be a list of glob patterns");

            return CheckResult::FAIL;
        }

        $exposed = array_values(array_filter(
            $this->ignored(),
            fn (string $pattern): bool => array_filter(
                $this->probes($pattern),
                fn (string $probe): bool => $this->processes($includes, $probe),
            ) !== [],
        ));

        if ($exposed === []) {
            return CheckResult::PASS;
        }

        $listed = implode(', ', $exposed);
        $entries = implode(', ', array_map(fn (string $pattern): string => $this->encode('!'.$pattern), $exposed));
        $describe = $this->describe();

        if ($includes === null) {
            $this->addComment("Biome checks {$describe} ({$listed}) because {$configFile} has no files.includes: add \"files\": { \"includes\": [\"**\", {$entries}] }");

            return CheckResult::FAIL;
        }

        $consequence = $this->consequence() === '' ? '' : ', '.$this->consequence();

        $this->addComment("Biome checks {$describe} ({$listed}) through files.includes in {$configFile}{$consequence}: add {$entries} at the end of files.includes");

        if ($dry) {
            return CheckResult::FAIL;
        }

        foreach ($exposed as $pattern) {
            $contents = $this->appendExclusion($contents, $pattern);
        }

        file_put_contents($file, $contents);

        return $this->fix(dry: true);
    }

    /**
     * The patterns to append, negated, to files.includes.
     *
     * @return list<string>
     */
    abstract protected function ignored(): array;

    /**
     * What the files are, for the comment (e.g. "CI runner artifacts").
     */
    abstract protected function describe(): string;

    /**
     * Why checking them hurts, appended to the comment after a comma; empty
     * for none.
     */
    protected function consequence(): string
    {
        return '';
    }

    /**
     * The project-relative paths standing for a pattern, any of which being
     * processed means the pattern still has to be excluded.
     *
     * @return list<string>
     */
    protected function probes(string $pattern): array
    {
        return [$pattern];
    }

    /**
     * Whether Biome processes a file: no includes means everything, otherwise
     * the last matching pattern decides.
     */
    private function processes(?JsoncNode $includes, string $file): bool
    {
        if ($includes === null) {
            return true;
        }

        $processed = false;

        foreach ($includes->children as $pattern) {
            if (!is_string($pattern->value)) {
                continue;
            }

            $negated = str_starts_with($pattern->value, '!');

            if ($this->matches(ltrim($pattern->value, '!'), $file)) {
                $processed = !$negated;
            }
        }

        return $processed;
    }

    /**
     * Biome's glob syntax: `*` stays within a path segment, `**` crosses them.
     * Matching a parent directory matches everything inside it.
     */
    private function matches(string $glob, string $file): bool
    {
        $glob = (string) preg_replace('#^\./#', '', $glob);
        $regex = '';

        for ($i = 0, $length = strlen($glob); $i < $length; $i++) {
            if (substr($glob, $i, 3) === '**/') {
                $regex .= '(?:.*/)?';
                $i += 2;
            } elseif (substr($glob, $i, 2) === '**') {
                $regex .= '.*';
                $i++;
            } else {
                $regex .= match ($glob[$i]) {
                    '*' => '[^/]*',
                    '?' => '[^/]',
                    default => preg_quote($glob[$i], '#'),
                };
            }
        }

        return preg_match("#^{$regex}(?:/.*)?$#", $file) === 1;
    }

    /**
     * Appends `"!<pattern>"` as the last entry of files.includes — last, because
     * a later positive pattern would otherwise take the file back — on its own
     * line, indented like the entry before it, when the array spans lines.
     */
    private function appendExclusion(string $contents, string $pattern): string
    {
        $includes = Jsonc::parse($contents)?->get('files')?->get('includes');

        // Only reached for an array that processes the file, so it has a
        // positive pattern and therefore at least one entry.
        if ($includes === null || $includes->children === []) {
            return $contents;
        }

        $last = $includes->children[array_key_last($includes->children)];

        $entry = $this->encode('!'.$pattern);

        if (!str_contains(substr($contents, $includes->start, $includes->end - $includes->start), "\n")) {
            return substr_replace($contents, ", {$entry}", $last->end, 0);
        }

        $lineStart = (int) strrpos(substr($contents, 0, $last->start), "\n") + 1;
        $indent = substr($contents, $lineStart, strspn($contents, " \t", $lineStart));
        $line = "\n{$indent}{$entry}";

        // A line comment after the last entry describes that entry, so the new
        // one goes on the next line rather than between the two.
        $lineEnd = strpos($contents, "\n", $last->end);
        $lineEnd = $lineEnd === false ? strlen($contents) : $lineEnd;

        if (preg_match('#\G[ \t]*(,)?[ \t]*(//[^\n]*)?$#m', $contents, $tail, 0, $last->end) !== 1) {
            return substr_replace($contents, ','.$line, $last->end, 0);
        }

        if (($tail[1] ?? '') === ',') {
            return substr_replace($contents, $line.',', $lineEnd, 0);
        }

        return substr_replace(substr_replace($contents, $line, $lineEnd, 0), ',', $last->end, 0);
    }

    private function encode(string $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES);
    }
}
