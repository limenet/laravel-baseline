<?php

use Limenet\LaravelBaseline\Checks\Checks\GitignoresLspFilesCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('gitignoresLspFiles implements FixableInterface', function (): void {
    expect(makeCheck(GitignoresLspFilesCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('gitignoresLspFiles fails when .gitignore is missing', function (): void {
    $this->withTempBasePath();

    [$check, $collector] = makeCheckWithCollector(GitignoresLspFilesCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing .gitignore in project root: create it and add 'storage/framework/lsp-*.php'");
});

it('gitignoresLspFiles fails when the entry is missing', function (): void {
    $this->withTempBasePath(['.gitignore' => "/vendor\n/node_modules\n"]);

    [$check, $collector] = makeCheckWithCollector(GitignoresLspFilesCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing entry in .gitignore: add 'storage/framework/lsp-*.php' to ignore the Laravel language server files generated in storage/framework");
});

it('gitignoresLspFiles passes when the entry is present', function (): void {
    $this->withTempBasePath(['.gitignore' => "/vendor\nstorage/framework/lsp-*.php\n"]);

    expect(makeCheck(GitignoresLspFilesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('gitignoresLspFiles passes when the entry is written with a leading slash', function (): void {
    $this->withTempBasePath(['.gitignore' => "/vendor\n/storage/framework/lsp-*.php\n"]);

    expect(makeCheck(GitignoresLspFilesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('gitignoresLspFiles fix appends the entry without clobbering existing lines', function (): void {
    $this->withTempBasePath(['.gitignore' => "/vendor\n/node_modules\n"]);

    expect(makeCheck(GitignoresLspFilesCheck::class)->fix())->toBe(CheckResult::PASS);

    expect(file_get_contents(base_path('.gitignore')))
        ->toBe("/vendor\n/node_modules\nstorage/framework/lsp-*.php\n");
});

it('gitignoresLspFiles fix adds a newline before appending to an unterminated file', function (): void {
    $this->withTempBasePath(['.gitignore' => '/vendor']);

    makeCheck(GitignoresLspFilesCheck::class)->fix();

    expect(file_get_contents(base_path('.gitignore')))
        ->toBe("/vendor\nstorage/framework/lsp-*.php\n");
});

it('gitignoresLspFiles fix creates .gitignore when missing', function (): void {
    $this->withTempBasePath();

    expect(makeCheck(GitignoresLspFilesCheck::class)->fix())->toBe(CheckResult::PASS);

    expect(file_get_contents(base_path('.gitignore')))->toBe("storage/framework/lsp-*.php\n");
    expect(makeCheck(GitignoresLspFilesCheck::class)->check())->toBe(CheckResult::PASS);
});
