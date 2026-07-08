<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Composer\Semver\Intervals;
use Composer\Semver\VersionParser;
use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Yaml\Yaml;

class NodeVersionMatchesDdevCheck extends AbstractFixableCheck
{
    /**
     * Node version to establish when the project declares none at all.
     */
    private const DEFAULT_NODE_VERSION = '26';

    public function fix(bool $dry = false): CheckResult
    {
        $packageJson = $this->getPackageJson();

        if ($packageJson === null) {
            return CheckResult::FAIL;
        }

        $engines = $this->getPackageJsonNodeVersion();
        $nvmrc = $this->getNvmrcNodeVersion();

        // Two declared-but-conflicting versions need a human decision — never auto-fixed.
        if ($engines !== null && $nvmrc !== null && !$this->nodeVersionsCompatible($engines, $nvmrc)) {
            $this->addComment(sprintf(
                'Node version mismatch: package.json engines.node (%s) and .nvmrc (%s) disagree',
                $engines,
                $nvmrc,
            ));

            return CheckResult::FAIL;
        }

        $major = $this->resolveNodeMajor($engines, $nvmrc);

        if ($engines === null) {
            $this->addComment(sprintf(
                'package.json missing engines.node: add "engines": { "node": "^%s" }',
                $major,
            ));

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        if ($nvmrc === null) {
            $this->addComment(sprintf('.nvmrc missing: create a .nvmrc pinning Node "%s"', $major));

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        $ddevConfig = $this->getDdevConfig();

        if ($ddevConfig === null) {
            return CheckResult::FAIL;
        }

        $nodejsVersion = $ddevConfig['nodejs_version'] ?? null;

        if ($nodejsVersion !== 'auto') {
            $this->addComment('DDEV nodejs_version should be "auto" to derive the Node version from the project: set "nodejs_version: auto" in .ddev/config.yaml');

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        if ($dry) {
            return CheckResult::PASS;
        }

        // Apply fixes
        if ($engines === null) {
            $packageJson['engines']['node'] = '^'.$major;
            $this->writePackageJson($packageJson);
        }

        if ($nvmrc === null) {
            file_put_contents(base_path('.nvmrc'), $major."\n");
        }

        if ($nodejsVersion !== 'auto') {
            $ddevConfigFile = base_path('.ddev/config.yaml');

            if (file_exists($ddevConfigFile)) {
                $config = Yaml::parseFile($ddevConfigFile) ?? [];
                $config['nodejs_version'] = 'auto';
                file_put_contents($ddevConfigFile, Yaml::dump($config, 4, 2));
            }
        }

        return $this->fix(dry: true);
    }

    private function nodeVersionsCompatible(string $a, string $b): bool
    {
        $parser = new VersionParser();

        try {
            return Intervals::haveIntersections(
                $parser->parseConstraints($a),
                $parser->parseConstraints($b),
            );
        } catch (\UnexpectedValueException) {
            return false;
        }
    }

    /**
     * The major version to enforce: the .nvmrc value, then engines.node, then the default.
     */
    private function resolveNodeMajor(?string $engines, ?string $nvmrc): string
    {
        foreach ([$nvmrc, $engines] as $candidate) {
            if ($candidate === null) {
                continue;
            }

            $major = $this->nodeMajor($candidate);

            if ($major !== null) {
                return $major;
            }
        }

        return self::DEFAULT_NODE_VERSION;
    }

    private function nodeMajor(string $raw): ?string
    {
        $parser = new VersionParser();

        try {
            $lowerBound = $parser->parseConstraints($raw)->getLowerBound()->getVersion();
        } catch (\UnexpectedValueException) {
            return null;
        }

        return explode('.', $lowerBound)[0];
    }
}
