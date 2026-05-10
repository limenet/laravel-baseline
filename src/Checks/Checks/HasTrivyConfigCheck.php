<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCiJobCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Yaml\Yaml;

class HasTrivyConfigCheck extends AbstractCiJobCheck implements FixableInterface
{
    public function check(): CheckResult
    {
        return $this->fix(dry: true);
    }

    public function fix(bool $dry = false): CheckResult
    {
        // Check CI job first
        $ciResult = $this->checkRequiredCiJobs();

        if ($ciResult !== CheckResult::PASS) {
            if ($dry) {
                return $ciResult;
            }

            // Fix: add security job to .gitlab-ci.yml
            $ciFile = base_path('.gitlab-ci.yml');

            if (file_exists($ciFile)) {
                $ciData = $this->getGitlabCiData() ?? [];

                if (!isset($ciData['security'])) {
                    $ciData['security'] = ['extends' => ['.lint_security']];
                    file_put_contents($ciFile, Yaml::dump($ciData, 4, 2));
                }
            }
        }

        // Check trivy.yaml
        $trivyConfig = $this->loadYamlConfig('trivy.yaml') ?? [];

        $scanners = $trivyConfig['scan']['scanners'] ?? [];
        $missingScanners = array_diff(['secret', 'vuln'], $scanners);

        if ($missingScanners !== []) {
            $this->addComment("Missing required scanners in trivy.yaml: scan.scanners must include 'secret' and 'vuln'");

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        $severity = $trivyConfig['severity'] ?? [];
        $missingSeverity = array_diff(['CRITICAL', 'HIGH'], $severity);

        if ($missingSeverity !== []) {
            $this->addComment("Missing required severity levels in trivy.yaml: severity must include 'CRITICAL' and 'HIGH'");

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        $skipDirs = $trivyConfig['scan']['skip-dirs'] ?? [];
        $missingDirs = array_diff(['.ddev', 'node_modules', 'storage/logs', 'vendor'], $skipDirs);

        if ($missingDirs !== []) {
            $this->addComment('Missing skip-dirs in trivy.yaml: scan.skip-dirs must include '.implode(', ', $missingDirs));

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        if ($dry) {
            return CheckResult::PASS;
        }

        // Apply trivy.yaml fixes
        $trivyFile = base_path('trivy.yaml');

        foreach (['secret', 'vuln'] as $scanner) {
            if (!in_array($scanner, $trivyConfig['scan']['scanners'] ?? [], true)) {
                $trivyConfig['scan']['scanners'][] = $scanner;
            }
        }

        foreach (['CRITICAL', 'HIGH'] as $level) {
            if (!in_array($level, $trivyConfig['severity'] ?? [], true)) {
                $trivyConfig['severity'][] = $level;
            }
        }

        foreach (['.ddev', 'node_modules', 'storage/logs', 'vendor'] as $dir) {
            if (!in_array($dir, $trivyConfig['scan']['skip-dirs'] ?? [], true)) {
                $trivyConfig['scan']['skip-dirs'][] = $dir;
            }
        }

        file_put_contents($trivyFile, Yaml::dump($trivyConfig, 4, 2));

        return $this->fix(dry: true);
    }

    protected function requiredCiJobs(): array
    {
        return ['security' => '.lint_security'];
    }
}
