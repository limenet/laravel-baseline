<?php

use Limenet\LaravelBaseline\Support\JsNode;
use Limenet\LaravelBaseline\Support\JsObjectLiteral;

function jsSlice(string $source, JsNode $node): string
{
    return substr($source, $node->start, $node->end - $node->start);
}

it('finds the exported object literal', function (string $source): void {
    $config = JsObjectLiteral::config($source);

    expect($config?->kind)->toBe(JsNode::OBJECT)
        ->and($config?->membersNamed('base')[0]->value?->value)->toBe('/app/');
})->with([
    'bare object' => "export default { base: '/app/' }",
    'defineConfig' => "import { defineConfig } from 'vite';\n\nexport default defineConfig({ base: '/app/' });",
    'arrow function' => "export default defineConfig(({ mode }) => ({ base: '/app/' }))",
    'async arrow function' => "export default defineConfig(async (env) => ({ base: '/app/' }))",
    'single parameter' => "export default defineConfig(env => ({ base: '/app/' }))",
    'quoted key' => "export default { \"base\": '/app/' }",
    'after other exports' => "export const a = { base: 'x' };\nexport default { base: '/app/' }",
    'comments around' => "/* export default { base: 'no' } */\nexport default /* here */ { // why\n base: '/app/' }",
]);

it('does not follow what it cannot read off the source', function (string $source): void {
    expect(JsObjectLiteral::config($source))->toBeNull();
})->with([
    'empty' => '',
    'no default export' => "export const config = { base: '/' }",
    'a variable' => "const config = { base: '/' };\nexport default config;",
    'block-bodied function' => "export default defineConfig(() => { return { base: '/' } })",
    'another helper' => "export default mergeConfig(base, { base: '/' })",
    'a type assertion' => "export default { base: '/' } satisfies UserConfig",
    'commonjs' => "module.exports = { base: '/' }",
    'unterminated' => "export default { base: '/'",
    'nested export' => "function f() { export default { base: '/' } }",
]);

it('keeps the offsets and kinds of members', function (): void {
    $source = "export default { plugins: [vue(), 'x'], server: { port: 5173 }, name, ...rest, [key]: 1, run() {} }";
    $config = JsObjectLiteral::config($source);

    expect(array_map(fn ($member) => $member->key, $config->members))->toBe(['plugins', 'server', 'name', null, null, null])
        ->and(jsSlice($source, $config->membersNamed('plugins')[0]->value))->toBe("[vue(), 'x']")
        ->and(array_map(fn (JsNode $node) => $node->kind, $config->membersNamed('plugins')[0]->value->elements))->toBe([JsNode::OTHER, JsNode::STRING])
        ->and($config->membersNamed('server')[0]->value->kind)->toBe(JsNode::OBJECT)
        ->and($config->membersNamed('name')[0]->value)->toBeNull()
        ->and($config->hasOpaqueMembers())->toBeTrue();
});

it('treats a literal that is operated on as an expression', function (): void {
    $source = "export default { a: ['x'].concat(y), b: 'x' + y, c: `\${y}/x`, d: `plain`, e: { f: 1 }.f }";
    $config = JsObjectLiteral::config($source);
    $kind = fn (string $key): string => $config->membersNamed($key)[0]->value->kind;

    expect($kind('a'))->toBe(JsNode::OTHER)
        ->and($kind('b'))->toBe(JsNode::OTHER)
        ->and($kind('c'))->toBe(JsNode::OTHER)
        ->and($kind('d'))->toBe(JsNode::STRING)
        ->and($kind('e'))->toBe(JsNode::OTHER);
});

it('is not thrown off by brackets and quotes inside strings, templates, regexes and comments', function (): void {
    $source = <<<'JS'
    export default {
        a: '}{', // }
        b: `${fn({ x: '}' })} }`,
        c: /[/'"}]+\/}/g,
        d: 4 / 2, /* } */
        e: (x) => { return x['}'] },
        last: 'yes',
    }
    JS;

    $config = JsObjectLiteral::config($source);

    expect(count($config->members))->toBe(6)
        ->and($config->membersNamed('last')[0]->value->value)->toBe('yes');
});

it('reports the same key twice as two members', function (): void {
    expect(JsObjectLiteral::config('export default { server: {}, server: {} }')->membersNamed('server'))->toHaveCount(2);
});

it('appends to a multi-line container in the style of its last member', function (string $before, string $after): void {
    $array = JsObjectLiteral::config($before)->membersNamed('a')[0]->value;

    expect(JsObjectLiteral::append($before, $array, '  ', fn (): string => "'new'"))->toBe($after);
})->with([
    'trailing comma' => ["export default {\n  a: [\n    'x',\n  ],\n}", "export default {\n  a: [\n    'x',\n    'new',\n  ],\n}"],
    'no trailing comma' => ["export default {\n  a: [\n    'x'\n  ],\n}", "export default {\n  a: [\n    'x',\n    'new'\n  ],\n}"],
    'comment after the comma' => ["export default {\n  a: [\n    'x', // why\n  ],\n}", "export default {\n  a: [\n    'x', // why\n    'new',\n  ],\n}"],
    'comment without a comma' => ["export default {\n  a: [\n    'x' // why\n  ],\n}", "export default {\n  a: [\n    'x', // why\n    'new'\n  ],\n}"],
    'closing bracket on the last line' => ["export default {\n  a: [\n    'x']\n}", "export default {\n  a: [\n    'x',\n    'new']\n}"],
    'single line' => ["export default { a: ['x'] }", "export default { a: ['x', 'new'] }"],
    'empty' => ["export default {\n  a: [],\n}", "export default {\n  a: [\n    'new',\n  ],\n}"],
    'empty across lines' => ["export default {\n  a: [\n  ],\n}", "export default {\n  a: [\n    'new',\n  ],\n}"],
    'empty but for a comment' => ["export default {\n  a: [/* none yet */],\n}", "export default {\n  a: [/* none yet */\n    'new',\n  ],\n}"],
    'tabs' => ["export default {\n\ta: [\n\t\t'x',\n\t],\n}", "export default {\n\ta: [\n\t\t'x',\n\t\t'new',\n\t],\n}"],
]);

it('tells the renderer the indent and whether a trailing comma is in use', function (): void {
    $source = "export default {\n    a: 1,\n}";
    $seen = [];

    JsObjectLiteral::append($source, JsObjectLiteral::config($source), '    ', function (string $indent, bool $trailing, bool $inline) use (&$seen): string {
        $seen = [$indent, $trailing, $inline];

        return 'b: 2';
    });

    expect($seen)->toBe(['    ', true, false]);
});
