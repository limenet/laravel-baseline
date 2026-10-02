<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Support\PhpSourceFiles;

/**
 * Shared by the checks asserting that a tool looks at every PHP file the
 * project owns. Outside Laravel there is no conventional layout to mandate
 * (app/, routes/ …), so instead of prescribing paths these compare what the
 * tool is configured to read against what is actually there — a theme whose
 * linter only reads an empty app/ while functions.php goes unchecked is
 * exactly the failure this catches.
 */
abstract class AbstractCoversAllPhpFilesCheck extends AbstractCheck
{
    private const LISTED = 10;

    public static function profiles(): array
    {
        return [Profile::Php, Profile::WordPress];
    }

    /**
     * The project's PHP files that none of $paths covers, skipping the files
     * the policy exempts and the ones matched by $deliberatelySkipped.
     *
     * @param  list<string>  $paths
     * @param  list<string>  $deliberatelySkipped
     * @return list<string>
     */
    protected function uncoveredFiles(array $paths, array $deliberatelySkipped = []): array
    {
        $files = PhpSourceFiles::in(
            $this->path(),
            $this->policy()->strings('phpFileCoverage.ignoredDirectories'),
        );
        $skipped = [...$this->policy()->strings('phpFileCoverage.exempt'), ...$deliberatelySkipped];

        return array_values(array_filter(
            $files,
            fn (string $file): bool => !$this->coveredByAny($skipped, $file) && !$this->coveredByAny($paths, $file),
        ));
    }

    /**
     * @param  list<string>  $files
     */
    protected function reportUncovered(string $tool, array $files, string $remedy): void
    {
        $listed = array_slice($files, 0, self::LISTED);
        $more = count($files) - count($listed);

        $this->addComment(sprintf(
            '%s does not cover %s%s: %s',
            $tool,
            implode(', ', $listed),
            $more > 0 ? " and {$more} more" : '',
            $remedy,
        ));
    }

    /**
     * @param  list<string>  $paths
     */
    private function coveredByAny(array $paths, string $file): bool
    {
        foreach ($paths as $path) {
            if (PhpSourceFiles::covers($path, $file)) {
                return true;
            }
        }

        return false;
    }
}
