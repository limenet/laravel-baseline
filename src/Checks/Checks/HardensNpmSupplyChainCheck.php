<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Composer\Semver\Intervals;
use Composer\Semver\VersionParser;
use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class HardensNpmSupplyChainCheck extends AbstractFixableCheck
{
    /**
     * npm 12 blocks dependency lifecycle scripts by default and defaults
     * --allow-git/--allow-remote to none, so requiring it hardens installs for free.
     */
    private const DEFAULT_NPM_CONSTRAINT = '^12';

    /**
     * Minimum install-time cooldown, in days: npm skips versions younger than this.
     */
    private const MIN_RELEASE_AGE_DAYS = 7;

    public function fix(bool $dry = false): CheckResult
    {
        $packageJson = $this->getPackageJson();

        if ($packageJson === null) {
            return CheckResult::FAIL;
        }

        $npm = $this->getPackageJsonNpmVersion();
        $npmTooLow = $npm !== null && !$this->constraintGuaranteesAtLeast($npm, '12');

        if ($npm === null) {
            $this->addComment('package.json missing engines.npm: add "engines": { "npm": "^12" } (npm 12 blocks dependency lifecycle scripts by default)');

            if ($dry) {
                return CheckResult::FAIL;
            }
        } elseif ($npmTooLow) {
            $this->addComment(sprintf('engines.npm (%s) allows npm < 12; require npm >= 12 (e.g. "^12")', $npm));

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        $npmrc = $this->getNpmrc();

        $engineStrict = ($npmrc['engine-strict'] ?? null) === 'true';

        if (!$engineStrict) {
            $this->addComment('.npmrc missing engine-strict=true: add "engine-strict=true" so the required npm version is enforced, not just advised');

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        $minReleaseAge = isset($npmrc['min-release-age']) && $npmrc['min-release-age'] !== ''
            ? (int) $npmrc['min-release-age']
            : null;

        if ($minReleaseAge === null) {
            $this->addComment(sprintf('.npmrc missing min-release-age: add "min-release-age=%d" for a %d-day dependency install cooldown', self::MIN_RELEASE_AGE_DAYS, self::MIN_RELEASE_AGE_DAYS));

            if ($dry) {
                return CheckResult::FAIL;
            }
        } elseif ($minReleaseAge < self::MIN_RELEASE_AGE_DAYS) {
            $this->addComment(sprintf('.npmrc min-release-age (%d) is below the recommended %d-day cooldown', $minReleaseAge, self::MIN_RELEASE_AGE_DAYS));

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        if ($dry) {
            return CheckResult::PASS;
        }

        // Apply fixes
        if ($npm === null || $npmTooLow) {
            $packageJson['engines']['npm'] = self::DEFAULT_NPM_CONSTRAINT;
            $this->writePackageJson($packageJson);
        }

        $this->upsertNpmrc([
            'engine-strict' => 'true',
            'min-release-age' => (string) self::MIN_RELEASE_AGE_DAYS,
        ]);

        return $this->fix(dry: true);
    }

    /**
     * True when every version the constraint allows is >= $floor (i.e. it never
     * intersects "< $floor"). Unparseable input is treated as not guaranteeing it.
     */
    private function constraintGuaranteesAtLeast(string $constraint, string $floor): bool
    {
        $parser = new VersionParser;

        try {
            return !Intervals::haveIntersections(
                $parser->parseConstraints($constraint),
                $parser->parseConstraints('<'.$floor),
            );
        } catch (\UnexpectedValueException) {
            return false;
        }
    }

    /**
     * Set each key in the project's .npmrc, updating an existing line in place or
     * appending it, preserving all other lines and comments.
     *
     * @param  array<string,string>  $entries
     */
    private function upsertNpmrc(array $entries): void
    {
        $npmrcFile = base_path('.npmrc');
        $content = file_exists($npmrcFile) ? (file_get_contents($npmrcFile) ?: '') : '';
        $lines = $content === '' ? [] : explode("\n", rtrim($content, "\n"));

        foreach ($entries as $key => $value) {
            $line = $key.'='.$value;
            $matched = false;

            foreach ($lines as $index => $existing) {
                if (preg_match('/^\s*'.preg_quote($key, '/').'\s*=/', $existing) === 1) {
                    $lines[$index] = $line;
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $lines[] = $line;
            }
        }

        file_put_contents($npmrcFile, implode("\n", $lines)."\n");
    }
}
