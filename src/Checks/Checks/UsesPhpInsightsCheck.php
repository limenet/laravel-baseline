<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\PhpFile\PhpFileWriter;
use PhpParser\Node;
use PhpParser\NodeFinder;

class UsesPhpInsightsCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->checkComposerPackages('nunomaduro/phpinsights')) {
            return CheckResult::FAIL;
        }

        $scriptsOk = $this->checkComposerScript('ci-lint', 'insights --summary --no-interaction')
            && $this->checkComposerScript('ci-lint', 'insights -n --ansi --format=codeclimate > codeclimate-report.json 2>/dev/null');

        $configOk = $this->hasDisableSecurityCheck();

        if ($scriptsOk && $configOk) {
            return CheckResult::PASS;
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        if (!$scriptsOk) {
            $this->addToComposerScript('ci-lint', '@php artisan insights --summary --no-interaction');
            $this->addToComposerScript('ci-lint', '@php artisan insights -n --ansi --format=codeclimate > codeclimate-report.json 2>/dev/null');
        }

        if (!$configOk) {
            $this->fixDisableSecurityCheck();
        }

        return $this->fix(dry: true);
    }

    private function fixDisableSecurityCheck(): void
    {
        $file = base_path('config/insights.php');

        if (!file_exists($file)) {
            return;
        }

        $writer = PhpFileWriter::open($file);
        $finder = new NodeFinder();

        $return = $finder->findFirst($writer->stmts, fn ($n): bool => $n instanceof Node\Stmt\Return_);

        if (!$return instanceof Node\Stmt\Return_ || !$return->expr instanceof Node\Expr\Array_) {
            return;
        }

        foreach ($return->expr->items as $item) {
            if (!$item instanceof Node\ArrayItem
                || !$item->key instanceof Node\Scalar\String_
                || $item->key->value !== 'requirements'
                || !$item->value instanceof Node\Expr\Array_
            ) {
                continue;
            }

            foreach ($item->value->items as $reqItem) {
                if (!$reqItem instanceof Node\ArrayItem
                    || !$reqItem->key instanceof Node\Scalar\String_
                    || $reqItem->key->value !== 'disable-security-check'
                ) {
                    continue;
                }

                $reqItem->value = new Node\Expr\ConstFetch(new Node\Name('true'));
                $writer->save();

                return;
            }
        }
    }

    private function hasDisableSecurityCheck(): bool
    {
        $config = $this->parsePhpConfigFile('config/insights.php');

        if ($config === null) {
            return false;
        }

        if (($config['requirements']['disable-security-check'] ?? null) !== true) {
            $this->addComment("Set 'disable-security-check' => true in the requirements section of config/insights.php");

            return false;
        }

        return true;
    }
}
