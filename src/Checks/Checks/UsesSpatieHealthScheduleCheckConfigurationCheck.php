<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Health\HealthScheduleCheckConfigVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

class UsesSpatieHealthScheduleCheckConfigurationCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->checkComposerPackages(['spatie/laravel-health'])) {
            return CheckResult::WARN;
        }

        $file = base_path('app/Providers/AppServiceProvider.php');

        if (!file_exists($file)) {
            $this->addComment($this->failComment());

            return CheckResult::FAIL;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse(file_get_contents($file) ?: '') ?? [];
        } catch (\Throwable) {
            return CheckResult::FAIL;
        }

        $visitor = new HealthScheduleCheckConfigVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        if (!$visitor->isValid()) {
            $this->addComment($this->failComment());

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    private function failComment(): string
    {
        return 'ScheduleCheck not configured correctly: Use ScheduleCheck::new()->heartbeatMaxAgeInMinutes(2) in Health::checks() in AppServiceProvider to prevent false positives';
    }
}
