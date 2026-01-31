<?php

namespace Limenet\LaravelBaseline\Checks;

use Limenet\LaravelBaseline\Checks\Checks\BumpsComposerCheck;
use Limenet\LaravelBaseline\Checks\Checks\CallsBaselineCheck;
use Limenet\LaravelBaseline\Checks\Checks\CallsSentryHookCheck;
use Limenet\LaravelBaseline\Checks\Checks\CheckPhpunitCheck;
use Limenet\LaravelBaseline\Checks\Checks\DdevHasPcovPackageCheck;
use Limenet\LaravelBaseline\Checks\Checks\DdevMutagenIgnoresNodeModulesCheck;
use Limenet\LaravelBaseline\Checks\Checks\DoesNotUseHorizonWatcherCheck;
use Limenet\LaravelBaseline\Checks\Checks\DoesNotUseIgnitionCheck;
use Limenet\LaravelBaseline\Checks\Checks\DoesNotUseSailCheck;
use Limenet\LaravelBaseline\Checks\Checks\HasCiJobsCheck;
use Limenet\LaravelBaseline\Checks\Checks\HasClaudeSettingsWithLaravelSimplifierCheck;
use Limenet\LaravelBaseline\Checks\Checks\HasCompleteRectorConfigurationCheck;
use Limenet\LaravelBaseline\Checks\Checks\HasEncryptedEnvFileCheck;
use Limenet\LaravelBaseline\Checks\Checks\HasGuidelinesUpdateScriptCheck;
use Limenet\LaravelBaseline\Checks\Checks\HasNpmScriptsCheck;
use Limenet\LaravelBaseline\Checks\Checks\IsCiLintCompleteCheck;
use Limenet\LaravelBaseline\Checks\Checks\IsLaravelVersionMaintainedCheck;
use Limenet\LaravelBaseline\Checks\Checks\PhpstanLevelAtLeastEightCheck;
use Limenet\LaravelBaseline\Checks\Checks\PhpVersionMatchesCiCheck;
use Limenet\LaravelBaseline\Checks\Checks\PhpVersionMatchesDdevCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesIdeHelpersCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesLarastanCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesLaravelAdminerCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesLaravelBoostCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesLaravelHorizonCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesLaravelPennantCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesLaravelPulseCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesLaravelTelescopeCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesLimenetPintConfigCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesPestCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesPhpInsightsCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesPhpstanExtensionsCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesPredisCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesRectorCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesReleaseItCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieBackupCheck;
use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieHealthCheck;

class CheckRegistry
{
    /** @var list<class-string<CheckInterface>> */
    private static array $checks = [
        BumpsComposerCheck::class,
        CallsBaselineCheck::class,
        CallsSentryHookCheck::class,
        CheckPhpunitCheck::class,
        DdevHasPcovPackageCheck::class,
        DdevMutagenIgnoresNodeModulesCheck::class,
        DoesNotUseHorizonWatcherCheck::class,
        DoesNotUseIgnitionCheck::class,
        DoesNotUseSailCheck::class,
        HasCiJobsCheck::class,
        HasClaudeSettingsWithLaravelSimplifierCheck::class,
        HasCompleteRectorConfigurationCheck::class,
        HasEncryptedEnvFileCheck::class,
        HasGuidelinesUpdateScriptCheck::class,
        HasNpmScriptsCheck::class,
        IsCiLintCompleteCheck::class,
        IsLaravelVersionMaintainedCheck::class,
        PhpstanLevelAtLeastEightCheck::class,
        PhpVersionMatchesCiCheck::class,
        PhpVersionMatchesDdevCheck::class,
        UsesIdeHelpersCheck::class,
        UsesLarastanCheck::class,
        UsesLaravelAdminerCheck::class,
        UsesLaravelBoostCheck::class,
        UsesLaravelHorizonCheck::class,
        UsesLaravelPennantCheck::class,
        UsesLaravelPulseCheck::class,
        UsesLaravelTelescopeCheck::class,
        UsesLimenetPintConfigCheck::class,
        UsesPestCheck::class,
        UsesPhpInsightsCheck::class,
        UsesPhpstanExtensionsCheck::class,
        UsesPredisCheck::class,
        UsesRectorCheck::class,
        UsesReleaseItCheck::class,
        UsesSpatieBackupCheck::class,
        UsesSpatieHealthCheck::class,
    ];

    /** @return list<class-string<CheckInterface>> */
    public static function all(): array
    {
        return self::$checks;
    }

    /**
     * Create instances of all checks with the given comment collector.
     *
     * @return list<CheckInterface>
     */
    public static function createAll(CommentCollector $collector): array
    {
        return array_map(
            fn (string $class) => new $class($collector),
            self::$checks,
        );
    }
}
