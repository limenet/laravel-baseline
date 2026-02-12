<?php

use Limenet\LaravelBaseline\Backup\BackupConfigVisitor;
use Limenet\LaravelBaseline\Backup\ClassConstInfo;
use Limenet\LaravelBaseline\Backup\FuncCallInfo;
use Limenet\LaravelBaseline\Backup\StaticPropertyInfo;
use Limenet\LaravelBaseline\Backup\UnparsedNode;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

function parseConfigWith(string $code): array
{
    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $ast = $parser->parse($code);
    $visitor = new BackupConfigVisitor();
    $traverser = new NodeTraverser();
    $traverser->addVisitor($visitor);
    $traverser->traverse($ast);

    return $visitor->getConfig();
}

it('parses integer keys in arrays', function (): void {
    $code = <<<'PHP'
<?php
return [
    0 => 'first',
    1 => 'second',
];
PHP;

    $config = parseConfigWith($code);
    expect($config)->toBe([0 => 'first', 1 => 'second']);
});

it('parses arrays without explicit keys', function (): void {
    $code = <<<'PHP'
<?php
return [
    'alpha',
    'beta',
];
PHP;

    $config = parseConfigWith($code);
    expect($config)->toBe(['alpha', 'beta']);
});

it('parses float values', function (): void {
    $code = <<<'PHP'
<?php
return [
    'rate' => 3.14,
];
PHP;

    $config = parseConfigWith($code);
    expect($config['rate'])->toBe(3.14);
});

it('parses boolean and null constants', function (): void {
    $code = <<<'PHP'
<?php
return [
    'enabled' => true,
    'disabled' => false,
    'nothing' => null,
];
PHP;

    $config = parseConfigWith($code);
    expect($config['enabled'])->toBeTrue();
    expect($config['disabled'])->toBeFalse();
    expect($config['nothing'])->toBeNull();
});

it('parses class constant fetch', function (): void {
    $code = <<<'PHP'
<?php
return [
    'type' => ZipArchive::CREATE,
];
PHP;

    $config = parseConfigWith($code);
    expect($config['type'])->toBeInstanceOf(ClassConstInfo::class);
    expect($config['type']->class)->toBe('ZipArchive');
    expect($config['type']->constant)->toBe('CREATE');
});

it('parses static property fetch', function (): void {
    $code = <<<'PHP'
<?php
return [
    'value' => SomeClass::$property,
];
PHP;

    $config = parseConfigWith($code);
    expect($config['value'])->toBeInstanceOf(StaticPropertyInfo::class);
    expect($config['value']->class)->toBe('SomeClass');
    expect($config['value']->property)->toBe('property');
});

it('returns UnparsedNode for unhandled node types', function (): void {
    $code = <<<'PHP'
<?php
return [
    'computed' => 1 + 2,
];
PHP;

    $config = parseConfigWith($code);
    expect($config['computed'])->toBeInstanceOf(UnparsedNode::class);
});

it('parses function calls with arguments', function (): void {
    $code = <<<'PHP'
<?php
return [
    'name' => env('APP_NAME', 'default'),
    'path' => base_path(),
];
PHP;

    $config = parseConfigWith($code);

    expect($config['name'])->toBeInstanceOf(FuncCallInfo::class);
    expect($config['name']->name)->toBe('env');
    expect($config['name']->args)->toBe(['APP_NAME', 'default']);

    expect($config['path'])->toBeInstanceOf(FuncCallInfo::class);
    expect($config['path']->name)->toBe('base_path');
    expect($config['path']->args)->toBe([]);
});

it('parses nested arrays', function (): void {
    $code = <<<'PHP'
<?php
return [
    'outer' => [
        'inner' => 'value',
    ],
];
PHP;

    $config = parseConfigWith($code);
    expect($config['outer'])->toBe(['inner' => 'value']);
});

it('parses integer values', function (): void {
    $code = <<<'PHP'
<?php
return [
    'count' => 42,
];
PHP;

    $config = parseConfigWith($code);
    expect($config['count'])->toBe(42);
});

it('returns empty array for non-return statements', function (): void {
    $code = <<<'PHP'
<?php
$x = 'hello';
PHP;

    $config = parseConfigWith($code);
    expect($config)->toBe([]);
});

it('handles non-string non-int keys gracefully', function (): void {
    // A key that is neither String_ nor LNumber falls through to return null
    $code = <<<'PHP'
<?php
return [
    SOME_CONST => 'value',
];
PHP;

    $config = parseConfigWith($code);
    // The key is a ConstFetch (not String_ or LNumber), so parseKey returns null, meaning it gets pushed
    expect($config[0])->toBe('value');
});
