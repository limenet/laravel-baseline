<?php

use Limenet\LaravelBaseline\Checks\Checks\HasEditorconfigCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('hasEditorconfig implements FixableInterface', function (): void {
    expect(makeCheck(HasEditorconfigCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('hasEditorconfig fix creates .editorconfig when missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    $check = makeCheck(HasEditorconfigCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect(file_exists(base_path('.editorconfig')))->toBeTrue();
});

it('hasEditorconfig fix creates .editorconfig with required properties', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    $check = makeCheck(HasEditorconfigCheck::class);
    $check->fix();

    $content = file_get_contents(base_path('.editorconfig'));
    expect($content)->toContain('root = true');
    expect($content)->toContain('charset = utf-8');
    expect($content)->toContain('end_of_line = lf');
    expect($content)->toContain('indent_style = space');
    expect($content)->toContain('insert_final_newline = true');
    expect($content)->toContain('trim_trailing_whitespace = true');
});

it('hasEditorconfig fix overwrites empty .editorconfig', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['.editorconfig' => '']);

    $check = makeCheck(HasEditorconfigCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $content = file_get_contents(base_path('.editorconfig'));
    expect($content)->toContain('root = true');
});

it('hasEditorconfig fix overwrites incomplete .editorconfig', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['.editorconfig' => '[*]'."\n".'indent_style = space']);

    $check = makeCheck(HasEditorconfigCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $content = file_get_contents(base_path('.editorconfig'));
    expect($content)->toContain('root = true');
    expect($content)->toContain('charset = utf-8');
});

it('hasEditorconfig fix is idempotent', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    $check = makeCheck(HasEditorconfigCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect($check->fix())->toBe(CheckResult::PASS);
});

it('hasEditorconfig fix does not overwrite valid .editorconfig', function (): void {
    bindFakeComposer([]);
    $content = implode("\n", [
        'root = true',
        '',
        '[*]',
        'charset = utf-8',
        'end_of_line = lf',
        'indent_style = space',
        'insert_final_newline = true',
        'trim_trailing_whitespace = true',
        '',
        '[*.php]',
        'indent_size = 4',
    ]);
    $this->withTempBasePath(['.editorconfig' => $content]);

    $check = makeCheck(HasEditorconfigCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $result = file_get_contents(base_path('.editorconfig'));
    expect($result)->toContain('[*.php]');
});
