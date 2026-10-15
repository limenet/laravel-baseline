<?php

use Limenet\LaravelBaseline\Checks\Checks\PhpstanHasTmpDirCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

it('phpstanHasTmpDir fails when phpstan.neon is missing', function (): void {
    $this->withTempBasePath(['.gitignore' => "/.cache/phpstan/\n"]);

    $check = makeCheck(PhpstanHasTmpDirCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('PHPStan configuration missing: Create phpstan.neon in project root');
});

it('phpstanHasTmpDir passes with the cache directory', function (string $tmpDir): void {
    $this->withTempBasePath([
        'phpstan.neon' => "parameters:\n    level: 8\n    tmpDir: {$tmpDir}\n",
        '.gitignore' => ".cache/phpstan\n",
    ]);

    expect(makeCheck(PhpstanHasTmpDirCheck::class)->check())->toBe(CheckResult::PASS);
})->with(['.cache/phpstan', './.cache/phpstan/', '%currentWorkingDirectory%/.cache/phpstan']);

it('phpstanHasTmpDir fails when tmpDir is absent or elsewhere', function (string $neon): void {
    $this->withTempBasePath(['phpstan.neon' => $neon, '.gitignore' => "/.cache/phpstan/\n"]);

    $check = makeCheck(PhpstanHasTmpDirCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $check->getComments()))->toContain('Set "tmpDir: .cache/phpstan" in the parameters section of phpstan.neon');
})->with([
    'absent' => "parameters:\n    level: 8\n",
    'elsewhere' => "parameters:\n    tmpDir: build/phpstan\n",
]);

it('phpstanHasTmpDir fails when .gitignore does not ignore the cache', function (): void {
    $this->withTempBasePath(['phpstan.neon' => "parameters:\n    tmpDir: .cache/phpstan\n", '.gitignore' => "/vendor\n"]);

    expect(makeCheck(PhpstanHasTmpDirCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('phpstanHasTmpDir fix sets tmpDir and the .gitignore entry', function (): void {
    $this->withTempBasePath([
        'phpstan.neon' => "includes:\n\t- vendor/larastan/larastan/extension.neon\n\nparameters:\n\tlevel: 8 # strict\n\ttmpDir: build/phpstan\n",
        '.gitignore' => '/vendor',
    ]);

    expect(makeCheck(PhpstanHasTmpDirCheck::class)->fix())->toBe(CheckResult::PASS);

    expect((string) file_get_contents(base_path('phpstan.neon')))
        ->toBe("includes:\n\t- vendor/larastan/larastan/extension.neon\n\nparameters:\n\tlevel: 8 # strict\n\ttmpDir: .cache/phpstan\n");
    expect((string) file_get_contents(base_path('.gitignore')))->toBe("/vendor\n/.cache/phpstan/\n");
});

it('phpstanHasTmpDir fix edits the .dist config PHPStan would load', function (): void {
    $this->withTempBasePath(['phpstan.neon.dist' => "parameters:\n    level: 8\n", '.gitignore' => "/.cache/phpstan/\n"]);

    expect(makeCheck(PhpstanHasTmpDirCheck::class)->fix())->toBe(CheckResult::PASS);
    expect((string) file_get_contents(base_path('phpstan.neon.dist')))->toBe("parameters:\n    level: 8\n    tmpDir: .cache/phpstan\n");
});

it('phpstanHasTmpDir runs in standalone projects', function (Profile $profile): void {
    $project = makeProject($profile, ['phpstan.neon' => "parameters:\n    level: 8\n"]);

    expect(makeCheck(PhpstanHasTmpDirCheck::class, $project)->check())->toBe(CheckResult::FAIL);
    expect(makeCheck(PhpstanHasTmpDirCheck::class, $project)->fix())->toBe(CheckResult::PASS);
})->with([Profile::Php, Profile::WordPress]);
