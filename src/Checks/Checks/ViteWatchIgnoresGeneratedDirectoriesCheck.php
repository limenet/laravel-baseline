<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Support\JsNode;
use Limenet\LaravelBaseline\Support\JsObjectLiteral;

/**
 * Vite's dev server watches the whole project root and skips only `.git`,
 * `node_modules`, `test-results`, its cache and its build output. Everything
 * else a project git-ignores — `vendor/`, `storage/`, coverage reports, tool
 * caches — is watched too: thousands of file handles nobody needs, and a reload
 * whenever a test run or a cache write touches one of them.
 *
 * So `server.watch.ignored` has to list those directories. Vite appends the
 * option to its own defaults and matches each glob against absolute paths,
 * which is why every entry is a directory name between two double-star
 * segments rather than a path anchored to the project root.
 *
 * A project without a Vite config is none of this check's business: PASS.
 *
 * The config is JavaScript, which neither runner parses. The check reads the
 * exported object literal with {@see JsObjectLiteral} and edits it as text; a
 * config it cannot follow — a block-bodied function, `server` built from a
 * variable or a spread — is reported and left alone.
 */
class ViteWatchIgnoresGeneratedDirectoriesCheck extends AbstractFixableCheck
{
    private const PATH = ['server', 'watch', 'ignored'];

    public static function profiles(): array
    {
        return Profile::cases();
    }

    public function fix(bool $dry = false): CheckResult
    {
        $configFile = $this->configFile();

        if ($configFile === null) {
            return CheckResult::PASS;
        }

        $contents = (string) file_get_contents($this->path($configFile));
        $required = $this->requiredGlobs();
        $target = $this->locate($contents);

        if (is_string($target)) {
            $this->addComment("Cannot verify server.watch.ignored in {$configFile} ({$target}): make sure it lists ".$this->quoted($required).', so the dev server does not watch generated directories');

            return CheckResult::FAIL;
        }

        $missing = $this->missing($target, $required);

        if ($missing === []) {
            return CheckResult::PASS;
        }

        $this->addComment("Missing in {$configFile}: server.watch.ignored must list ".$this->quoted($missing).', so the dev server does not watch generated directories');

        if ($dry) {
            return CheckResult::FAIL;
        }

        $fixed = $this->withGlobs($contents, $missing);

        // Written only when a second reading agrees the edit did what it was
        // meant to — the file is hand-written code, not a generated config.
        $verified = $this->locate($fixed);

        if (is_string($verified) || $this->missing($verified, $required) !== []) {
            return CheckResult::FAIL;
        }

        file_put_contents($this->path($configFile), $fixed);

        return CheckResult::PASS;
    }

    /**
     * The config Vite loads: the first candidate that exists, in Vite's own order.
     */
    private function configFile(): ?string
    {
        foreach ($this->policy()->strings('vite.configFiles') as $candidate) {
            if (is_file($this->path($candidate))) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function requiredGlobs(): array
    {
        return [
            ...$this->policy()->strings('vite.watchIgnored.shared'),
            ...$this->policy()->strings('vite.watchIgnored.composer'),
            ...($this->profile() === Profile::Laravel ? $this->policy()->strings('vite.watchIgnored.laravel') : []),
        ];
    }

    /**
     * Where the globs live or would go: the `ignored` array when the config has
     * one, otherwise the innermost object that exists and the keys still to be
     * written into it — or, as a string, why the config cannot be followed.
     *
     * @return array{JsNode, list<string>}|string
     */
    private function locate(string $contents): array|string
    {
        $object = JsObjectLiteral::config($contents);

        if ($object === null) {
            return 'it does not export a plain object literal';
        }

        foreach (self::PATH as $depth => $key) {
            $path = implode('.', array_slice(self::PATH, 0, $depth + 1));
            $members = $object->membersNamed($key);

            if (count($members) > 1) {
                return "{$path} is declared more than once";
            }

            if ($members === []) {
                return $object->hasOpaqueMembers()
                    ? "{$path} may come from a spread or a computed key"
                    : [$object, array_slice(self::PATH, $depth)];
            }

            $value = $members[0]->value;

            if ($value?->kind !== ($key === 'ignored' ? JsNode::ARRAY : JsNode::OBJECT)) {
                return $key === 'ignored' ? "{$path} is not an array literal" : "{$path} is not an object literal";
            }

            $object = $value;
        }

        return [$object, []];
    }

    /**
     * @param  array{JsNode, list<string>}  $target
     * @param  list<string>  $required
     * @return list<string>
     */
    private function missing(array $target, array $required): array
    {
        [$node, $keys] = $target;

        if ($keys !== []) {
            return $required;
        }

        $present = array_map(static fn (JsNode $element): string => $element->value ?? '', $node->elements);

        return array_values(array_diff($required, $present));
    }

    /**
     * @param  list<string>  $globs
     */
    private function withGlobs(string $contents, array $globs): string
    {
        $unit = preg_match('/^([ \t]+)[^\s*]/m', $contents, $matches) === 1 ? $matches[1] : '    ';
        $target = $this->locate($contents);

        if (is_string($target)) {
            return $contents;
        }

        [$node, $keys] = $target;

        if ($keys !== []) {
            $quote = $this->quoteFor($contents, null);

            return JsObjectLiteral::append(
                $contents,
                $node,
                $unit,
                fn (string $indent, bool $trailing, bool $inline): string => $this->render($keys, $globs, $quote, $unit, $indent, $trailing, $inline),
            );
        }

        // One at a time, re-reading in between: every append moves the offsets.
        foreach ($globs as $glob) {
            $target = $this->locate($contents);

            if (is_string($target)) {
                break;
            }

            $quote = $this->quoteFor($contents, $target[0]);
            $contents = JsObjectLiteral::append($contents, $target[0], $unit, static fn (): string => $quote.$glob.$quote);
        }

        return $contents;
    }

    /**
     * `server: { watch: { ignored: […] } }` from the first key still missing,
     * without a comma after it — the caller knows whether one belongs there.
     *
     * @param  list<string>  $keys
     * @param  list<string>  $globs
     */
    private function render(array $keys, array $globs, string $quote, string $unit, string $indent, bool $trailing, bool $inline): string
    {
        $entries = array_map(static fn (string $glob): string => $quote.$glob.$quote, $globs);
        $objects = array_slice($keys, 0, -1);

        if ($inline) {
            return implode('', array_map(static fn (string $key): string => "{$key}: { ", $objects))
                .'ignored: ['.implode(', ', $entries).']'
                .str_repeat(' }', count($objects));
        }

        $comma = $trailing ? ',' : '';
        $lines = [];

        foreach ($objects as $level => $key) {
            $lines[] = str_repeat($unit, $level).$key.': {';
        }

        $depth = count($objects);
        $lines[] = str_repeat($unit, $depth).'ignored: [';

        foreach ($entries as $index => $entry) {
            $lines[] = str_repeat($unit, $depth + 1).$entry.($index === count($entries) - 1 ? $comma : ',');
        }

        $lines[] = str_repeat($unit, $depth).']'.($depth > 0 ? $comma : '');

        for ($level = $depth - 1; $level >= 0; $level--) {
            $lines[] = str_repeat($unit, $level).'}'.($level > 0 ? $comma : '');
        }

        return implode("\n".$indent, $lines);
    }

    /**
     * The quote the new entries are written with: the one their siblings use,
     * otherwise the one the file uses, otherwise a single quote.
     */
    private function quoteFor(string $contents, ?JsNode $array): string
    {
        foreach ($array->elements ?? [] as $element) {
            if ($element->quote === "'" || $element->quote === '"') {
                return $element->quote;
            }
        }

        return str_contains($contents, '"') && !str_contains($contents, "'") ? '"' : "'";
    }

    /**
     * @param  list<string>  $globs
     */
    private function quoted(array $globs): string
    {
        return implode(', ', array_map(static fn (string $glob): string => "'{$glob}'", $globs));
    }
}
