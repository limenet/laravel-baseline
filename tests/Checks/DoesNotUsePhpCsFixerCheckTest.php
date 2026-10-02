<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotUsePhpCsFixerCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

it('doesNotUsePhpCsFixer passes without any PHP CS Fixer leftovers', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => ['ci-lint' => ['pint --parallel']]])]);

    expect(makeCheck(DoesNotUsePhpCsFixerCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotUsePhpCsFixer fails while the package is required', function (): void {
    bindFakeComposer(['friendsofphp/php-cs-fixer' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['require-dev' => ['friendsofphp/php-cs-fixer' => '^3.0']])]);

    [$check, $collector] = makeCheckWithCollector(DoesNotUsePhpCsFixerCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toContain('Remove friendsofphp/php-cs-fixer from composer.json — Pint replaces PHP CS Fixer (run `composer update` afterward to sync composer.lock)');
});

it('doesNotUsePhpCsFixer fails on a ci-lint entry or a config file alone', function (array $files): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => ['ci-lint' => ['pint']]]), ...$files]);

    expect(makeCheck(DoesNotUsePhpCsFixerCheck::class)->check())->toBe(CheckResult::FAIL);
})->with([
    'ci-lint' => [['composer.json' => json_encode(['scripts' => ['ci-lint' => ['./vendor/bin/php-cs-fixer fix --diff']]])]],
    'ci-lint as a string' => [['composer.json' => json_encode(['scripts' => ['ci-lint' => './vendor/bin/php-cs-fixer fix']])]],
    'config' => [['.php-cs-fixer.php' => "<?php\n"]],
    'dist config' => [['.php-cs-fixer.dist.php' => "<?php\n"]],
    'cache' => [['.php-cs-fixer.cache' => '{}']],
]);

it('doesNotUsePhpCsFixer fix removes the package, the ci-lint entry, the config and the cache', function (Profile $profile): void {
    $project = makeProject($profile, [
        'composer.json' => json_encode([
            'require-dev' => ['friendsofphp/php-cs-fixer' => '^3.0', 'phpstan/phpstan' => '^2.0'],
            'scripts' => ['ci-lint' => ['./vendor/bin/rector', './vendor/bin/php-cs-fixer fix --diff', './vendor/bin/phpstan analyse']],
        ]),
        '.php-cs-fixer.php' => "<?php\nreturn (new PhpCsFixer\\Config);\n",
        '.php-cs-fixer.cache' => '{}',
        'pint.json' => '{"preset": "per"}',
        'functions.php' => "<?php\n",
    ]);

    $check = makeCheck(DoesNotUsePhpCsFixerCheck::class, $project);

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($check->fix())->toBe(CheckResult::PASS);

    $composer = json_decode((string) file_get_contents($project->path('composer.json')), true);

    expect($composer['require-dev'])->toBe(['phpstan/phpstan' => '^2.0'])
        ->and($composer['scripts']['ci-lint'])->toBe(['./vendor/bin/rector', './vendor/bin/phpstan analyse'])
        ->and(file_exists($project->path('.php-cs-fixer.php')))->toBeFalse()
        ->and(file_exists($project->path('.php-cs-fixer.cache')))->toBeFalse()
        ->and(file_exists($project->path('functions.php')))->toBeTrue();
})->with([Profile::Php, Profile::WordPress]);

it('doesNotUsePhpCsFixer is fixable and applies to every profile', function (): void {
    expect(DoesNotUsePhpCsFixerCheck::profiles())->toBe(Profile::cases());
});

it('doesNotUsePhpCsFixer leaves an emptied require-dev as an object Composer accepts', function (): void {
    $project = makeProject(Profile::Php, ['composer.json' => json_encode([
        'name' => 'acme/lib',
        'require-dev' => ['friendsofphp/php-cs-fixer' => '^3.0'],
        'config' => new stdClass,
    ])]);

    makeCheck(DoesNotUsePhpCsFixerCheck::class, $project)->fix();

    $composer = (string) file_get_contents($project->path('composer.json'));

    expect($composer)->toContain('"require-dev": {}')
        ->toContain('"config": {}');
});

it('doesNotUsePhpCsFixer keeps the config until its rules are ported to pint.json', function (): void {
    $project = makeProject(Profile::WordPress, [
        'composer.json' => json_encode(['require-dev' => ['friendsofphp/php-cs-fixer' => '^3.0', 'phpstan/phpstan' => '^2.0']]),
        '.php-cs-fixer.php' => "<?php\nreturn (new PhpCsFixer\\Config);\n",
        '.php-cs-fixer.cache' => '{}',
    ]);

    [$check, $collector] = makeCheckWithCollector(DoesNotUsePhpCsFixerCheck::class, $project);

    expect($check->fix())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toContain('Port the rules in .php-cs-fixer.php to pint.json, then remove .php-cs-fixer.php')
        ->and(file_exists($project->path('.php-cs-fixer.php')))->toBeTrue()
        ->and(file_exists($project->path('.php-cs-fixer.cache')))->toBeFalse()
        ->and(json_decode((string) file_get_contents($project->path('composer.json')), true)['require-dev'])->toBe(['phpstan/phpstan' => '^2.0']);
});
