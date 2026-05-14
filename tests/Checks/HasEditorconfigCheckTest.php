<?php

use Limenet\LaravelBaseline\Checks\Checks\HasEditorconfigCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('hasEditorconfig passes with valid .editorconfig', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.editorconfig' => implode("\n", [
            'root = true',
            '',
            '[*]',
            'charset = utf-8',
            'end_of_line = lf',
            'indent_style = space',
            'insert_final_newline = true',
            'trim_trailing_whitespace = true',
        ]),
    ]);

    expect(makeCheck(HasEditorconfigCheck::class)->check())->toBe(CheckResult::PASS);
});

it('hasEditorconfig fails when .editorconfig is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    [$check, $collector] = makeCheckWithCollector(HasEditorconfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Editorconfig missing: Create .editorconfig in project root');
});

it('hasEditorconfig fails when .editorconfig is empty', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['.editorconfig' => '']);

    [$check, $collector] = makeCheckWithCollector(HasEditorconfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Editorconfig empty: Add content to .editorconfig');
});

it('hasEditorconfig fails when root = true is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.editorconfig' => implode("\n", [
            '[*]',
            'charset = utf-8',
            'end_of_line = lf',
            'indent_style = space',
            'insert_final_newline = true',
            'trim_trailing_whitespace = true',
        ]),
    ]);

    [$check, $collector] = makeCheckWithCollector(HasEditorconfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Editorconfig incomplete: Add "root = true" to .editorconfig');
});

it('hasEditorconfig fails when charset is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.editorconfig' => implode("\n", [
            'root = true',
            '',
            '[*]',
            'end_of_line = lf',
            'indent_style = space',
            'insert_final_newline = true',
            'trim_trailing_whitespace = true',
        ]),
    ]);

    [$check, $collector] = makeCheckWithCollector(HasEditorconfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Editorconfig incomplete: Add "charset = utf-8" to .editorconfig');
});

it('hasEditorconfig fails when insert_final_newline is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.editorconfig' => implode("\n", [
            'root = true',
            '[*]',
            'charset = utf-8',
            'end_of_line = lf',
            'indent_style = space',
            'trim_trailing_whitespace = true',
        ]),
    ]);

    [$check, $collector] = makeCheckWithCollector(HasEditorconfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Editorconfig incomplete: Add "insert_final_newline = true" to .editorconfig');
});

it('hasEditorconfig passes with additional custom sections', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.editorconfig' => implode("\n", [
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
            '',
            '[*.md]',
            'trim_trailing_whitespace = false',
        ]),
    ]);

    expect(makeCheck(HasEditorconfigCheck::class)->check())->toBe(CheckResult::PASS);
});
