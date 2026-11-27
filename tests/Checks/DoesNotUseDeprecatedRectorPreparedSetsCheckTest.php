<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotUseDeprecatedRectorPreparedSetsCheck;
use Limenet\LaravelBaseline\Checks\Checks\HasRectorConfigWithPreparedSetsCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

// What hasRectorConfigWithPreparedSets wrote before Rector 2.9 stopped applying instanceOf and earlyReturn.
$written = <<<'PHP'
<?php

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPhpSets()
    ->withPreparedSets(deadCode: true, codeQuality: true, codingStyle: true, typeDeclarations: true, privatization: true, instanceOf: true, earlyReturn: true);
PHP;

it('doesNotUseDeprecatedRectorPreparedSets implements FixableInterface', function (): void {
    expect(makeCheck(DoesNotUseDeprecatedRectorPreparedSetsCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('doesNotUseDeprecatedRectorPreparedSets passes when rector.php is missing', function (): void {
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(DoesNotUseDeprecatedRectorPreparedSetsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotUseDeprecatedRectorPreparedSets passes when withPreparedSets uses no deprecated argument', function (): void {
    $rector = "<?php\n\nuse Rector\\Config\\RectorConfig;\n\nreturn RectorConfig::configure()->withPreparedSets(deadCode: true, codeQuality: true);\n";
    $this->withTempBasePath(['rector.php' => $rector]);

    expect(makeCheck(DoesNotUseDeprecatedRectorPreparedSetsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotUseDeprecatedRectorPreparedSets fails on the call the old fix wrote', function () use ($written): void {
    $this->withTempBasePath(['rector.php' => $written]);

    $check = makeCheck(DoesNotUseDeprecatedRectorPreparedSetsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('withPreparedSets()')->toContain('instanceOf')->toContain('codeQuality');
});

it('doesNotUseDeprecatedRectorPreparedSets fails whatever value a deprecated argument passes', function (): void {
    $rector = "<?php\n\nuse Rector\\Config\\RectorConfig;\n\nreturn RectorConfig::configure()->withPreparedSets(deadCode: true, naming: false, if: true);\n";
    $this->withTempBasePath(['rector.php' => $rector]);

    expect(makeCheck(DoesNotUseDeprecatedRectorPreparedSetsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('doesNotUseDeprecatedRectorPreparedSets fix strips only the deprecated arguments', function () use ($written): void {
    $this->withTempBasePath(['rector.php' => $written]);

    expect(makeCheck(DoesNotUseDeprecatedRectorPreparedSetsCheck::class)->fix())->toBe(CheckResult::PASS);

    $result = (string) file_get_contents(base_path('rector.php'));
    expect($result)->not->toContain('instanceOf')
        ->not->toContain('earlyReturn')
        ->toContain('withPreparedSets(deadCode: true, codeQuality: true, codingStyle: true, typeDeclarations: true, privatization: true)')
        ->toContain('withPhpSets()');

    // and what is left still satisfies the check that mandates the call
    expect(makeCheck(HasRectorConfigWithPreparedSetsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotUseDeprecatedRectorPreparedSets fix drops a call it empties', function (): void {
    $rector = <<<'PHP'
<?php

use Rector\Config\RectorConfig;

return static function (RectorConfig $config): void {
    $config->withPhpSets();
    $config->withPreparedSets(instanceOf: true, earlyReturn: true);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector]);

    expect(makeCheck(DoesNotUseDeprecatedRectorPreparedSetsCheck::class)->fix())->toBe(CheckResult::PASS);

    expect((string) file_get_contents(base_path('rector.php')))
        ->not->toContain('withPreparedSets')
        ->not->toMatch('/^\s*\$config;/m')
        ->toContain('withPhpSets()');
});

it('doesNotUseDeprecatedRectorPreparedSets fix is idempotent', function () use ($written): void {
    $this->withTempBasePath(['rector.php' => $written]);

    $check = makeCheck(DoesNotUseDeprecatedRectorPreparedSetsCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    $first = (string) file_get_contents(base_path('rector.php'));

    expect($check->fix())->toBe(CheckResult::PASS);
    expect((string) file_get_contents(base_path('rector.php')))->toBe($first);
});

it('doesNotUseDeprecatedRectorPreparedSets runs outside Laravel', function (Profile $profile) use ($written): void {
    $project = makeProject($profile, ['rector.php' => $written]);

    expect(makeCheck(DoesNotUseDeprecatedRectorPreparedSetsCheck::class, $project)->check())->toBe(CheckResult::FAIL);
    expect(makeCheck(DoesNotUseDeprecatedRectorPreparedSetsCheck::class, $project)->fix())->toBe(CheckResult::PASS);
    expect((string) file_get_contents($project->path('rector.php')))->not->toContain('instanceOf');
})->with([Profile::Php, Profile::WordPress]);
