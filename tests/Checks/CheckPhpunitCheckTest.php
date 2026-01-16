<?php

use Limenet\LaravelBaseline\Checks\Checks\CheckPhpunitCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('checkPhpunit fails when cobertura or junit or APP_KEY is missing', function (): void {
    bindFakeComposer([]);
    $xmlMissing = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <coverage><report></report></coverage>
  <logging></logging>
  <php></php>
</phpunit>
XML;

    $this->withTempBasePath(['phpunit.xml' => $xmlMissing, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(CheckPhpunitCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('checkPhpunit passes when cobertura, junit and APP_KEY are configured', function (): void {
    bindFakeComposer([]);
    $phpunitXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <coverage>
        <report>
            <cobertura outputFile="cobertura.xml"/>
        </report>
    </coverage>
    <logging>
        <junit outputFile="report.xml"/>
    </logging>
    <php>
        <env name="APP_KEY" value="base64:somekey"/>
    </php>
    <source>
        <include>
            <directory suffix=".php">./app</directory>
        </include>
    </source>
</phpunit>
XML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'phpunit.xml' => $phpunitXml,
    ]);

    $check = makeCheck(CheckPhpunitCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('checkPhpunit fails when phpunit.xml is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(CheckPhpunitCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('checkPhpunit fails when cobertura is missing', function (): void {
    bindFakeComposer([]);
    $phpunitXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <logging>
        <junit outputFile="report.xml"/>
    </logging>
    <php>
        <env name="APP_KEY" value="base64:somekey"/>
    </php>
    <source>
        <include>
            <directory suffix=".php">./app</directory>
        </include>
    </source>
</phpunit>
XML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'phpunit.xml' => $phpunitXml,
    ]);

    $check = makeCheck(CheckPhpunitCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('checkPhpunit fails when junit is missing', function (): void {
    bindFakeComposer([]);
    $phpunitXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <coverage>
        <report>
            <cobertura outputFile="cobertura.xml"/>
        </report>
    </coverage>
    <php>
        <env name="APP_KEY" value="base64:somekey"/>
    </php>
    <source>
        <include>
            <directory suffix=".php">./app</directory>
        </include>
    </source>
</phpunit>
XML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'phpunit.xml' => $phpunitXml,
    ]);

    $check = makeCheck(CheckPhpunitCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('checkPhpunit fails when APP_KEY is missing', function (): void {
    bindFakeComposer([]);
    $phpunitXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <coverage>
        <report>
            <cobertura outputFile="cobertura.xml"/>
        </report>
    </coverage>
    <logging>
        <junit outputFile="report.xml"/>
    </logging>
    <php>
    </php>
    <source>
        <include>
            <directory suffix=".php">./app</directory>
        </include>
    </source>
</phpunit>
XML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'phpunit.xml' => $phpunitXml,
    ]);

    $check = makeCheck(CheckPhpunitCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('checkPhpunit fails when source configuration is missing', function (): void {
    bindFakeComposer([]);
    $phpunitXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <coverage>
        <report>
            <cobertura outputFile="cobertura.xml"/>
        </report>
    </coverage>
    <logging>
        <junit outputFile="report.xml"/>
    </logging>
    <php>
        <env name="APP_KEY" value="base64:somekey"/>
    </php>
</phpunit>
XML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'phpunit.xml' => $phpunitXml,
    ]);

    $check = makeCheck(CheckPhpunitCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('checkPhpunit fails when source directory is incorrect', function (): void {
    bindFakeComposer([]);
    $phpunitXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <coverage>
        <report>
            <cobertura outputFile="cobertura.xml"/>
        </report>
    </coverage>
    <logging>
        <junit outputFile="report.xml"/>
    </logging>
    <php>
        <env name="APP_KEY" value="base64:somekey"/>
    </php>
    <source>
        <include>
            <directory suffix=".php">./src</directory>
        </include>
    </source>
</phpunit>
XML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'phpunit.xml' => $phpunitXml,
    ]);

    $check = makeCheck(CheckPhpunitCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('checkPhpunit throws on invalid XML', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath(['phpunit.xml' => '<phpunit>', 'composer.json' => json_encode(['name' => 'tmp'])]);

    expect(fn () => makeCheck(CheckPhpunitCheck::class)->check())->toThrow(Exception::class);
});

it('checkPhpunit fails with error comment when phpunit.xml is empty', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath(['phpunit.xml' => '', 'composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(CheckPhpunitCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('PHPUnit configuration invalid: Check phpunit.xml for XML syntax errors');
});
