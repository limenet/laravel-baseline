<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCiJobCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class HasTrivyConfigCheck extends AbstractCiJobCheck
{
    public function check(): CheckResult
    {
        $result = $this->checkRequiredCiJobs();

        if ($result !== CheckResult::PASS) {
            return $result;
        }

        $trivyConfig = $this->getTrivyConfig();

        if ($trivyConfig === null) {
            return CheckResult::FAIL;
        }

        $scanners = $trivyConfig['scan']['scanners'] ?? [];

        if (!in_array('secret', $scanners, true) || !in_array('vuln', $scanners, true)) {
            $this->addComment("Missing required scanners in trivy.yaml: scan.scanners must include 'secret' and 'vuln'");

            return CheckResult::FAIL;
        }

        $severity = $trivyConfig['severity'] ?? [];

        if (!in_array('CRITICAL', $severity, true) || !in_array('HIGH', $severity, true)) {
            $this->addComment("Missing required severity levels in trivy.yaml: severity must include 'CRITICAL' and 'HIGH'");

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    protected function requiredCiJobs(): array
    {
        return ['security' => '.lint_security'];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function getTrivyConfig(): ?array
    {
        return $this->loadYamlConfig('trivy.yaml');
    }
}
