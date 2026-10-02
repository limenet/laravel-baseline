<?php

use Limenet\LaravelBaseline\Policy\Policy;

/**
 * Validates $value against the subset of JSON Schema policy.schema.json uses,
 * returning one message per violation. Small enough to keep in the test rather
 * than pulling in a validator dependency for a single closed document.
 *
 * @param  array<string,mixed>  $schema
 * @param  array<string,mixed>  $root
 * @return list<string>
 */
function policySchemaViolations(mixed $value, array $schema, array $root, string $at = '$'): array
{
    if (isset($schema['$ref'])) {
        $name = substr((string) $schema['$ref'], strlen('#/$defs/'));

        return policySchemaViolations($value, $root['$defs'][$name], $root, $at);
    }

    if (isset($schema['oneOf'])) {
        $matching = array_filter($schema['oneOf'], fn (array $option): bool => policySchemaViolations($value, $option, $root, $at) === []);

        return count($matching) === 1 ? [] : ["{$at}: matches ".count($matching).' oneOf branches'];
    }

    $type = match (true) {
        is_array($value) && array_is_list($value) && ($value !== [] || ($schema['type'] ?? null) === 'array') => 'array',
        is_array($value) => 'object',
        is_int($value) => 'integer',
        is_bool($value) => 'boolean',
        is_string($value) => 'string',
        default => get_debug_type($value),
    };

    if (isset($schema['type']) && !in_array($type, (array) $schema['type'], true)) {
        return ["{$at}: expected {$schema['type']}, got {$type}"];
    }

    $errors = [];

    if ($type === 'object') {
        foreach ($schema['required'] ?? [] as $key) {
            if (!array_key_exists($key, $value)) {
                $errors[] = "{$at}: missing required key {$key}";
            }
        }

        if (isset($schema['minProperties']) && count($value) < $schema['minProperties']) {
            $errors[] = "{$at}: fewer than {$schema['minProperties']} properties";
        }

        foreach ($value as $key => $child) {
            $childSchema = $schema['properties'][$key] ?? $schema['additionalProperties'] ?? true;

            if ($childSchema === false) {
                $errors[] = "{$at}: unexpected key {$key}";
            } elseif (is_array($childSchema)) {
                array_push($errors, ...policySchemaViolations($child, $childSchema, $root, "{$at}.{$key}"));
            }
        }
    }

    if ($type === 'array') {
        if (isset($schema['minItems']) && count($value) < $schema['minItems']) {
            $errors[] = "{$at}: fewer than {$schema['minItems']} items";
        }

        foreach ($value as $i => $item) {
            if (isset($schema['items'])) {
                array_push($errors, ...policySchemaViolations($item, $schema['items'], $root, "{$at}[{$i}]"));
            }
        }
    }

    if ($type === 'string' && isset($schema['pattern']) && preg_match('/'.$schema['pattern'].'/u', $value) !== 1) {
        $errors[] = "{$at}: does not match {$schema['pattern']}";
    }

    if ($type === 'integer' && isset($schema['minimum']) && $value < $schema['minimum']) {
        $errors[] = "{$at}: below {$schema['minimum']}";
    }

    return $errors;
}

/** @return array<string,mixed> */
function policyJsonFile(string $name): array
{
    return json_decode((string) file_get_contents(Policy::defaultDirectory().'/'.$name), true, flags: JSON_THROW_ON_ERROR);
}

it('only uses schema keywords the validator understands', function (): void {
    $supported = [
        '$schema', '$id', '$defs', '$ref', 'title', 'description', 'type', 'properties', 'required',
        'additionalProperties', 'items', 'oneOf', 'pattern', 'minItems', 'minimum', 'minProperties',
    ];

    $keywords = [];
    $walk = function (array $schema) use (&$walk, &$keywords): void {
        foreach ($schema as $key => $child) {
            $keywords[] = $key;

            match ($key) {
                'properties', '$defs' => array_map($walk, $child),
                'items', 'additionalProperties' => is_array($child) ? $walk($child) : null,
                'oneOf' => array_map($walk, $child),
                default => null,
            };
        }
    };
    $walk(policyJsonFile('policy.schema.json'));

    expect(array_diff(array_unique($keywords), $supported))->toBe([]);
});

it('ships a policy.json that satisfies policy.schema.json', function (): void {
    $schema = policyJsonFile('policy.schema.json');

    expect(policySchemaViolations(policyJsonFile('policy.json'), $schema, $schema))->toBe([]);
});

it('rejects a policy with an unknown engine key', function (): void {
    $schema = policyJsonFile('policy.schema.json');
    $policy = policyJsonFile('policy.json');
    $policy['ciLint']['required']['php'] = ['phpstan'];

    expect(policySchemaViolations($policy, $schema, $schema))->toContain('$.ciLint.required: unexpected key php');
});
