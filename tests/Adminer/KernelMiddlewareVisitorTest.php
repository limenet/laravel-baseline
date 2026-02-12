<?php

use Limenet\LaravelBaseline\Adminer\KernelMiddlewareVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

function parseKernelWith(string $code): ?array
{
    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $ast = $parser->parse($code);
    $visitor = new KernelMiddlewareVisitor();
    $traverser = new NodeTraverser();
    $traverser->addVisitor($visitor);
    $traverser->traverse($ast);

    return $visitor->getMiddlewareGroups();
}

it('returns null when no middlewareGroups property exists', function (): void {
    $code = <<<'PHP'
<?php
class Kernel
{
    protected $middleware = [];
}
PHP;

    expect(parseKernelWith($code))->toBeNull();
});

it('parses integer keys in middleware groups', function (): void {
    $code = <<<'PHP'
<?php
class Kernel
{
    protected $middlewareGroups = [
        0 => ['value'],
    ];
}
PHP;

    $result = parseKernelWith($code);
    expect($result)->toHaveKey(0);
    expect($result[0])->toBe(['value']);
});

it('parses groups without explicit keys', function (): void {
    $code = <<<'PHP'
<?php
class Kernel
{
    protected $middlewareGroups = [
        ['item1', 'item2'],
    ];
}
PHP;

    $result = parseKernelWith($code);
    expect($result[0])->toBe(['item1', 'item2']);
});

it('returns null for unhandled value types', function (): void {
    $code = <<<'PHP'
<?php
class Kernel
{
    protected $middlewareGroups = [
        'group' => [
            1 + 2,
        ],
    ];
}
PHP;

    $result = parseKernelWith($code);
    expect($result['group'][0])->toBeNull();
});

it('handles class constant fetch for non-class constants', function (): void {
    $code = <<<'PHP'
<?php
class Kernel
{
    protected $middlewareGroups = [
        'group' => [
            SomeClass::CONSTANT,
        ],
    ];
}
PHP;

    $result = parseKernelWith($code);
    // Non-::class constants return null
    expect($result['group'][0])->toBeNull();
});
