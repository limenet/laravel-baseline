<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Composer\Semver\Intervals;
use Composer\Semver\VersionParser;
use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class NodeVersionMatchesDdevCheck extends AbstractFixableCheck
{
    /**
     * Minimum Node major: 24 is the current LTS. Anything older is bumped to it;
     * newer lines are left alone. Also the version established when none is declared.
     */
    private const MIN_NODE_VERSION = '24';

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

        $enginesTooLow = $engines !== null && !$this->guaranteesMinNodeVersion($engines);
        $nvmrcTooLow = $nvmrc !== null && !$this->guaranteesMinNodeVersion($nvmrc);

        // A below-floor declaration is bumped to the floor; otherwise keep the project's own line.
        $major = ($enginesTooLow || $nvmrcTooLow)
            ? self::MIN_NODE_VERSION
            : $this->resolveNodeMajor($engines, $nvmrc);

        if ($engines === null) {
            $this->addComment(sprintf(
                'package.json missing engines.node: add "engines": { "node": "^%s" }',
                $major,
            ));

            if ($dry) {
                return CheckResult::FAIL;
            }
        } elseif ($enginesTooLow) {
            $this->addComment(sprintf(
                'engines.node (%s) allows Node < %s; require Node >= %s (e.g. "^%s")',
                $engines,
                self::MIN_NODE_VERSION,
                self::MIN_NODE_VERSION,
                self::MIN_NODE_VERSION,
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
        } elseif ($nvmrcTooLow) {
            $this->addComment(sprintf(
                '.nvmrc (%s) pins Node < %s: set it to "%s"',
                $nvmrc,
                self::MIN_NODE_VERSION,
                self::MIN_NODE_VERSION,
            ));

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
        if ($engines === null || $enginesTooLow) {
            $packageJson['engines']['node'] = '^'.$major;
            $this->writePackageJson($packageJson);
        }

        if ($nvmrc === null || $nvmrcTooLow) {
            file_put_contents(base_path('.nvmrc'), $major."\n");
        }

        if ($nodejsVersion !== 'auto') {
            $ddevConfigFile = base_path('.ddev/config.yaml');

            if (file_exists($ddevConfigFile)) {
                $this->setYamlScalarKey($ddevConfigFile, 'nodejs_version', 'auto');
            }
        }

        return $this->fix(dry: true);
    }

    /**
     * True when every version the constraint allows is >= the floor (i.e. it never
     * intersects "< 24"). Unparseable input is treated as not guaranteeing it.
     */
    private function guaranteesMinNodeVersion(string $constraint): bool
    {
        $parser = new VersionParser;

        try {
            return !Intervals::haveIntersections(
                $parser->parseConstraints($constraint),
                $parser->parseConstraints('<'.self::MIN_NODE_VERSION),
            );
        } catch (\UnexpectedValueException) {
            return false;
        }
    }

    private function nodeVersionsCompatible(string $a, string $b): bool
    {
        $parser = new VersionParser;

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
     * The major version to establish: the .nvmrc value, then engines.node, then the floor.
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

        return self::MIN_NODE_VERSION;
    }

    private function nodeMajor(string $raw): ?string
    {
        $parser = new VersionParser;

        try {
            $lowerBound = $parser->parseConstraints($raw)->getLowerBound()->getVersion();
        } catch (\UnexpectedValueException) {
            return null;
        }

        return explode('.', $lowerBound)[0];
    }
}
