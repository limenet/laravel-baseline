<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Support\Jsonc;
use Limenet\LaravelBaseline\Support\JsoncNode;

/**
 * Biome must not check the files a CI runner leaves in the project root. The
 * GitLab runner extracts a `metadata.json` holding the cache key next to the
 * checkout whenever it restores a cache; a `files.includes` that starts from
 * `**` picks it up, and `biome ci` fails on a file nobody committed — but only
 * on the runs that happened to restore a cache.
 *
 * `files.includes` is evaluated the way Biome does: in order, the last pattern
 * matching the file deciding, and no `includes` at all meaning every file. A
 * project whose includes are an allowlist that never reaches the root is fine
 * as it is.
 *
 * Like biomeUsesLocalSchema, the fix edits the text rather than re-encoding the
 * document: Biome formats biome.json itself, and the file may carry comments.
 */
class BiomeIgnoresCiArtifactsCheck extends AbstractFixableCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

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
            $this->policy()->strings('biome.ciArtifacts'),
            fn (string $artifact): bool => $this->processes($includes, $artifact),
        ));

        if ($exposed === []) {
            return CheckResult::PASS;
        }

        $listed = implode(', ', $exposed);
        $entries = implode(', ', array_map(fn (string $artifact): string => $this->encode('!'.$artifact), $exposed));

        if ($includes === null) {
            $this->addComment("Biome checks CI runner artifacts ({$listed}) because {$configFile} has no files.includes: add \"files\": { \"includes\": [\"**\", {$entries}] }");

            return CheckResult::FAIL;
        }

        $this->addComment("Biome checks CI runner artifacts ({$listed}) through files.includes in {$configFile}, failing `biome ci` whenever the runner restores a cache: add {$entries} at the end of files.includes");

        if ($dry) {
            return CheckResult::FAIL;
        }

        foreach ($exposed as $artifact) {
            $contents = $this->appendExclusion($contents, $artifact);
        }

        file_put_contents($file, $contents);

        return $this->fix(dry: true);
    }

    /**
     * Whether Biome processes a root-level file: no includes means everything,
     * otherwise the last matching pattern decides.
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

        return preg_match("#^{$regex}$#", $file) === 1;
    }

    /**
     * Appends `"!<artifact>"` as the last entry of files.includes — last, because
     * a later positive pattern would otherwise take the file back — on its own
     * line, indented like the entry before it, when the array spans lines.
     */
    private function appendExclusion(string $contents, string $artifact): string
    {
        $includes = Jsonc::parse($contents)?->get('files')?->get('includes');

        // Only reached for an array that processes the artifact, so it has a
        // positive pattern and therefore at least one entry.
        if ($includes === null || $includes->children === []) {
            return $contents;
        }

        $last = $includes->children[array_key_last($includes->children)];

        $entry = $this->encode('!'.$artifact);

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
