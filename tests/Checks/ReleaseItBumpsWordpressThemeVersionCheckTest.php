<?php

use Limenet\LaravelBaseline\Checks\Checks\ReleaseItBumpsWordpressThemeVersionCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Policy\Policy;
use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Project\Project;

function releaseItThemeProject(?array $releaseIt): Project
{
    return makeProject(Profile::WordPress, [
        'style.css' => "/*\nTheme Name: Acme\nVersion: 1.0.0\n*/\n",
        ...($releaseIt === null ? [] : ['.release-it.json' => json_encode($releaseIt)]),
    ]);
}

it('releaseItBumpsWordpressThemeVersion passes when an after:bump hook rewrites style.css', function (array|string $hooks): void {
    $project = releaseItThemeProject(['hooks' => ['after:bump' => $hooks]]);

    expect(makeCheck(ReleaseItBumpsWordpressThemeVersionCheck::class, $project)->check())->toBe(CheckResult::PASS);
})->with([
    'policy one-liner' => [[Policy::fromDirectory()->string('wordpress.themeVersionHook')]],
    'own script as a string' => ['./bin/bump-theme ${version} style.css'],
    'own script in a list' => [['npm run build', './bin/bump-theme ${version} style.css']],
]);

it('releaseItBumpsWordpressThemeVersion adds the hook and keeps existing ones', function (): void {
    $project = releaseItThemeProject([
        'git' => ['tagName' => 'v${version}'],
        'hooks' => ['after:bump' => 'npm run build', 'before:init' => 'npm test'],
    ]);

    $check = makeCheck(ReleaseItBumpsWordpressThemeVersionCheck::class, $project);

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($check->fix())->toBe(CheckResult::PASS);

    $config = json_decode((string) file_get_contents($project->path('.release-it.json')), true);

    expect($config['hooks']['after:bump'])->toBe(['npm run build', Policy::fromDirectory()->string('wordpress.themeVersionHook')])
        ->and($config['hooks']['before:init'])->toBe('npm test')
        ->and($config['git'])->toBe(['tagName' => 'v${version}']);
});

it('releaseItBumpsWordpressThemeVersion warns before release-it is set up', function (): void {
    expect(makeCheck(ReleaseItBumpsWordpressThemeVersionCheck::class, releaseItThemeProject(null))->check())->toBe(CheckResult::WARN);
});

it('releaseItBumpsWordpressThemeVersion warns outside a theme', function (): void {
    $project = makeProject(Profile::WordPress, ['.release-it.json' => '{}', 'plugin.php' => "<?php\n/* Plugin Name: X */\n"]);

    expect(makeCheck(ReleaseItBumpsWordpressThemeVersionCheck::class, $project)->check())->toBe(CheckResult::WARN);
});

it('releaseItBumpsWordpressThemeVersion ships a hook that rewrites only the Version header', function (string $before, string $after): void {
    $project = makeProject(Profile::WordPress, ['style.css' => $before]);

    // What release-it runs after interpolating ${version}.
    $command = str_replace('${version}', '2.3.4', Policy::fromDirectory()->string('wordpress.themeVersionHook'));

    exec('cd '.escapeshellarg($project->path()).' && '.$command.' 2>&1', $output, $exitCode);

    expect($exitCode)->toBe(0, implode("\n", $output))
        ->and(file_get_contents($project->path('style.css')))->toBe($after);
})->with([
    'header block' => [
        "/*\nTheme Name: Acme\n * Version: 1.0.0\nRequires PHP Version: 8.5\n*/\n.v { content: 'Version: x'; }\n",
        "/*\nTheme Name: Acme\n * Version: 2.3.4\nRequires PHP Version: 8.5\n*/\n.v { content: 'Version: x'; }\n",
    ],
    'comment closed on the version line' => ["/* Theme Name: Acme\nVersion: 1.0.0 */\nbody{}\n", "/* Theme Name: Acme\nVersion: 2.3.4 */\nbody{}\n"],
    'CRLF' => ["/*\r\nTheme Name: Acme\r\nVersion: 1.0.0\r\n*/\r\n", "/*\r\nTheme Name: Acme\r\nVersion: 2.3.4\r\n*/\r\n"],
])->skip(fn (): bool => trim((string) shell_exec('command -v node')) === '', 'node is not installed');

it('releaseItBumpsWordpressThemeVersion applies to WordPress only', function (): void {
    expect(ReleaseItBumpsWordpressThemeVersionCheck::profiles())->toBe([Profile::WordPress]);
});
