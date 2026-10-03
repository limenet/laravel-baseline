<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

class HasEditorconfigCheck extends AbstractFixableCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    /**
     * Adds one required property to an existing .editorconfig without touching
     * the project's own sections, mirroring mergeProperty() in the npm runner.
     * `root` belongs in the preamble; everything else in `[*]`, where a line for
     * the same key is replaced and a new one is appended. A file with no `[*]`
     * gets one ahead of its first section, so the specific sections still
     * override it.
     */
    public static function mergeProperty(string $content, string $property): string
    {
        $lines = explode("\n", rtrim($content, "\n"));
        $key = trim(explode('=', $property, 2)[0]);
        $keyLine = '/^\s*'.preg_quote($key, '/').'\s*=/';

        if ($key === 'root') {
            $preambleEnd = self::nextSection($lines, 0);
            $existing = self::findLine($lines, $keyLine, 0, $preambleEnd);

            if ($existing !== null) {
                $lines[$existing] = $property;
            } else {
                array_splice($lines, 0, 0, $preambleEnd === 0 ? [$property, ''] : [$property]);
            }

            return implode("\n", $lines)."\n";
        }

        $header = null;

        foreach ($lines as $index => $line) {
            if (trim($line) === '[*]') {
                $header = $index;
                break;
            }
        }

        if ($header === null) {
            $at = self::nextSection($lines, 0);
            $block = ['[*]', $property];

            if ($at < count($lines)) {
                $block[] = '';
            }

            if ($at > 0 && trim($lines[$at - 1]) !== '') {
                array_unshift($block, '');
            }

            array_splice($lines, $at, 0, $block);

            return implode("\n", $lines)."\n";
        }

        $end = self::nextSection($lines, $header + 1);
        $existing = self::findLine($lines, $keyLine, $header + 1, $end);

        if ($existing !== null) {
            $lines[$existing] = $property;
        } else {
            $last = $end - 1;

            while ($last > $header && trim($lines[$last]) === '') {
                $last--;
            }

            array_splice($lines, $last + 1, 0, [$property]);
        }

        return implode("\n", $lines)."\n";
    }

    public function fix(bool $dry = false): CheckResult
    {
        $editorconfigFile = $this->path('.editorconfig');

        if (!file_exists($editorconfigFile)) {
            $this->addComment('Editorconfig missing: Create .editorconfig in project root');

            if ($dry) {
                return CheckResult::FAIL;
            }

            file_put_contents($editorconfigFile, $this->canonicalContent());

            return $this->fix(dry: true);
        }

        $content = file_get_contents($editorconfigFile);

        if ($content === false || trim($content) === '') {
            $this->addComment('Editorconfig empty: Add content to .editorconfig');

            if ($dry) {
                return CheckResult::FAIL;
            }

            file_put_contents($editorconfigFile, $this->canonicalContent());

            return $this->fix(dry: true);
        }

        if ($dry) {
            foreach ($this->requiredProperties() as $property) {
                if (!str_contains($content, $property)) {
                    $this->addComment("Editorconfig incomplete: Add \"{$property}\" to .editorconfig");

                    return CheckResult::FAIL;
                }
            }

            return CheckResult::PASS;
        }

        $merged = $content;

        foreach ($this->requiredProperties() as $property) {
            if (!str_contains($merged, $property)) {
                $merged = self::mergeProperty($merged, $property);
            }
        }

        if ($merged !== $content) {
            file_put_contents($editorconfigFile, $merged);
        }

        return $this->fix(dry: true);
    }

    /**
     * @param  list<string>  $lines
     */
    private static function nextSection(array $lines, int $from): int
    {
        for ($i = $from, $count = count($lines); $i < $count; $i++) {
            if (preg_match('/^\s*\[/', $lines[$i]) === 1) {
                return $i;
            }
        }

        return count($lines);
    }

    /**
     * @param  list<string>  $lines
     */
    private static function findLine(array $lines, string $pattern, int $from, int $to): ?int
    {
        for ($i = $from; $i < $to; $i++) {
            if (preg_match($pattern, $lines[$i]) === 1) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Substrings that must appear in an existing .editorconfig for it to count
     * as complete — a subset of the canonical file, so a project may add its own
     * sections without failing.
     *
     * @return list<string>
     */
    private function requiredProperties(): array
    {
        return $this->policy()->strings('editorconfig.requiredProperties');
    }

    private function canonicalContent(): string
    {
        return $this->policy()->template($this->policy()->string('editorconfig.template'));
    }
}
