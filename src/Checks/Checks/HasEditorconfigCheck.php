<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class HasEditorconfigCheck extends AbstractFixableCheck
{
    private const REQUIRED_PROPERTIES = [
        'root = true',
        'charset = utf-8',
        'end_of_line = lf',
        'indent_style = space',
        'insert_final_newline = true',
        'trim_trailing_whitespace = true',
    ];

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
            foreach (self::REQUIRED_PROPERTIES as $property) {
                if (!str_contains($content, $property)) {
                    $this->addComment("Editorconfig incomplete: Add \"{$property}\" to .editorconfig");

                    return CheckResult::FAIL;
                }
            }

            return CheckResult::PASS;
        }

        $needsFix = false;

        foreach (self::REQUIRED_PROPERTIES as $property) {
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

    private function canonicalContent(): string
    {
        return implode("\n", [
            'root = true',
            '',
            '[*]',
            'charset = utf-8',
            'end_of_line = lf',
            'indent_style = space',
            'indent_size = 4',
            'insert_final_newline = true',
            'trim_trailing_whitespace = true',
            '',
            '[*.md]',
            'trim_trailing_whitespace = false',
            '',
            '[*.{yml,yaml}]',
            'indent_size = 2',
            '',
            '[*.{js,css,json}]',
            'indent_size = 2',
            '',
        ]);
    }
}
