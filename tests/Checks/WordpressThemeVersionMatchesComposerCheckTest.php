<?php

use Limenet\LaravelBaseline\Checks\Checks\WordpressThemeVersionMatchesComposerCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Project\Project;

function themeVersionProject(?string $themeVersion, ?string $composerVersion, string $themeName = 'Acme'): Project
{
    $header = "/*\nTheme Name: {$themeName}\n".($themeVersion === null ? '' : "Version: {$themeVersion}\n")."*/\nbody{}\n";

    return makeProject(Profile::WordPress, [
        'style.css' => $header,
        'composer.json' => json_encode(array_filter(['name' => 'acme/theme', 'version' => $composerVersion])),
    ]);
}

it('wordpressThemeVersionMatchesComposer passes when the versions agree', function (): void {
    expect(makeCheck(WordpressThemeVersionMatchesComposerCheck::class, themeVersionProject('1.2.0', '1.2.0'))->check())
        ->toBe(CheckResult::PASS);
});

it('wordpressThemeVersionMatchesComposer rewrites the header from composer.json', function (): void {
    $project = themeVersionProject('1.0.0', '1.2.0');

    [$check, $collector] = makeCheckWithCollector(WordpressThemeVersionMatchesComposerCheck::class, $project);

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toContain('style.css declares Version: 1.0.0, composer.json 1.2.0: set the style.css header to 1.2.0')
        ->and($check->fix())->toBe(CheckResult::PASS)
        ->and(file_get_contents($project->path('style.css')))->toBe("/*\nTheme Name: Acme\nVersion: 1.2.0\n*/\nbody{}\n");
});

it('wordpressThemeVersionMatchesComposer seeds composer.json from the theme', function (): void {
    $project = themeVersionProject('1.0.0', null);

    $check = makeCheck(WordpressThemeVersionMatchesComposerCheck::class, $project);

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($check->fix())->toBe(CheckResult::PASS)
        ->and(json_decode((string) file_get_contents($project->path('composer.json')), true)['version'])->toBe('1.0.0');
});

it('wordpressThemeVersionMatchesComposer does not invent a missing header', function (): void {
    $project = themeVersionProject(null, '1.2.0');
    $before = file_get_contents($project->path('style.css'));

    [$check, $collector] = makeCheckWithCollector(WordpressThemeVersionMatchesComposerCheck::class, $project);

    expect($check->fix())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toContain('style.css has no Version: header: add "Version: 1.2.0" to it')
        ->and(file_get_contents($project->path('style.css')))->toBe($before);
});

it('wordpressThemeVersionMatchesComposer fails when neither declares a version', function (): void {
    expect(makeCheck(WordpressThemeVersionMatchesComposerCheck::class, themeVersionProject(null, null))->fix())
        ->toBe(CheckResult::FAIL);
});

it('wordpressThemeVersionMatchesComposer warns outside a theme', function (): void {
    $project = makeProject(Profile::WordPress, [
        'my-plugin.php' => "<?php\n/* Plugin Name: Acme */\n",
        'composer.json' => json_encode(['version' => '1.0.0']),
    ]);

    expect(makeCheck(WordpressThemeVersionMatchesComposerCheck::class, $project)->check())->toBe(CheckResult::WARN);
});

it('wordpressThemeVersionMatchesComposer applies to WordPress only', function (): void {
    expect(WordpressThemeVersionMatchesComposerCheck::profiles())->toBe([Profile::WordPress]);
});
