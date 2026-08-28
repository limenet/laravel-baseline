<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class HasEditorconfigCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $editorconfigFile = base_path('.editorconfig');

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

        $needsFix = false;

        foreach ($this->requiredProperties() as $property) {
            if (!str_contains($content, $property)) {
                $needsFix = true;
                break;
            }
        }

        if ($needsFix) {
            file_put_contents($editorconfigFile, $this->canonicalContent());
        }

        return $this->fix(dry: true);
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
