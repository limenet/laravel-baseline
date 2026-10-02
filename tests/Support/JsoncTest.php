<?php

use Limenet\LaravelBaseline\Support\Jsonc;
use Limenet\LaravelBaseline\Support\JsoncNode;

it('reads comments and trailing commas', function (): void {
    $root = Jsonc::parse(<<<'JSONC'
    {
      // line comment
      "files": { /* block */ "includes": ["**", "!dist",], },
    }
    JSONC);

    expect($root?->kind)->toBe(JsoncNode::OBJECT)
        ->and(array_map(fn (JsoncNode $node): mixed => $node->value, $root?->get('files')?->get('includes')?->children ?? []))
        ->toBe(['**', '!dist']);
});

it('keeps the source offsets of every value', function (): void {
    $source = '{ "a": [1, "two"] }';
    $array = Jsonc::parse($source)?->get('a');

    expect(substr($source, $array->start, $array->end - $array->start))->toBe('[1, "two"]')
        ->and(substr($source, $array->children[1]->start, $array->children[1]->end - $array->children[1]->start))->toBe('"two"');
});

it('does not mistake comment markers inside strings for comments', function (): void {
    $root = Jsonc::parse('{ "url": "https://example.com/*not-a-comment*/" }');

    expect($root?->get('url')?->value)->toBe('https://example.com/*not-a-comment*/');
});

it('decodes escapes and scalars', function (): void {
    $root = Jsonc::parse('{ "s": "a\"b\\\\c", "n": -1.5e2, "t": true, "z": null }');

    expect($root?->get('s')?->value)->toBe('a"b\\c')
        ->and($root?->get('n')?->value)->toBe(-150.0)
        ->and($root?->get('t')?->value)->toBeTrue()
        ->and($root?->get('z')?->kind)->toBe(JsoncNode::SCALAR);
});

it('returns null for invalid input', function (string $source): void {
    expect(Jsonc::parse($source))->toBeNull();
})->with([
    'empty' => '',
    'unterminated object' => '{ "a": 1',
    'missing comma' => '[1 2]',
    'trailing garbage' => '{} {}',
    'unquoted key' => '{ a: 1 }',
    'leading comma' => '[, 1]',
]);

it('answers get() only on objects', function (): void {
    expect(Jsonc::parse('[1]')?->get('0'))->toBeNull();
});
