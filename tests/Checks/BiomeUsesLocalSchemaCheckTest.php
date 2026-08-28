<?php

use Limenet\LaravelBaseline\Checks\Checks\BiomeUsesLocalSchemaCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Policy\Policy;

function biomeLocalSchema(): string
{
    return Policy::fromDirectory()->string('biome.schema');
}

it('biomeUsesLocalSchema passes when the project has no biome.json', function (): void {
    $this->withTempBasePath(['package.json' => '{}']);

    expect(makeCheck(BiomeUsesLocalSchemaCheck::class)->check())->toBe(CheckResult::PASS);
});

it('biomeUsesLocalSchema passes when $schema points at node_modules', function (): void {
    $this->withTempBasePath([
        'biome.json' => json_encode(['$schema' => biomeLocalSchema()], JSON_UNESCAPED_SLASHES),
    ]);

    expect(makeCheck(BiomeUsesLocalSchemaCheck::class)->check())->toBe(CheckResult::PASS);
});

it('biomeUsesLocalSchema accepts the escaped-slash spelling of the same path', function (): void {
    // json_encode() without JSON_UNESCAPED_SLASHES writes ".\/node_modules\/…",
    // which is the same string once decoded.
    $this->withTempBasePath(['biome.json' => json_encode(['$schema' => biomeLocalSchema()])]);

    expect(makeCheck(BiomeUsesLocalSchemaCheck::class)->check())->toBe(CheckResult::PASS);
});

it('biomeUsesLocalSchema fails on a version-pinned remote $schema', function (): void {
    $this->withTempBasePath([
        'biome.json' => json_encode(['$schema' => 'https://biomejs.dev/schemas/2.3.14/schema.json'], JSON_UNESCAPED_SLASHES),
    ]);

    [$check, $collector] = makeCheckWithCollector(BiomeUsesLocalSchemaCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $collector->all()))->toContain('Invalid "$schema" in biome.json');
});

it('biomeUsesLocalSchema fails when $schema is absent', function (): void {
    $this->withTempBasePath(['biome.json' => json_encode(['linter' => ['enabled' => true]])]);

    [$check, $collector] = makeCheckWithCollector(BiomeUsesLocalSchemaCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $collector->all()))->toContain('Missing "$schema" in biome.json');
});

it('biomeUsesLocalSchema fails on an empty biome.json without writing to it', function (): void {
    $this->withTempBasePath(['biome.json' => '']);

    expect(makeCheck(BiomeUsesLocalSchemaCheck::class)->fix())->toBe(CheckResult::FAIL);
    expect(file_get_contents(base_path('biome.json')))->toBe('');
});

it('biomeUsesLocalSchema rewrites the remote $schema in place, comments and all', function (): void {
    // Biome reads its config as JSONC, so a commented file is valid input and a
    // fix must survive it — which a decode/re-encode round trip would not.
    $before = <<<'JSON'
    {
      // pinned by hand, and forgotten ever since
      "$schema": "https://biomejs.dev/schemas/2.3.14/schema.json",
      "linter": { "enabled": true }
    }

    JSON;

    $this->withTempBasePath(['biome.json' => $before]);

    expect(makeCheck(BiomeUsesLocalSchemaCheck::class)->fix())->toBe(CheckResult::PASS);
    expect(file_get_contents(base_path('biome.json')))->toBe(
        str_replace('https://biomejs.dev/schemas/2.3.14/schema.json', biomeLocalSchema(), $before),
    );
});

it('biomeUsesLocalSchema inserts a missing $schema as the first key', function (): void {
    $this->withTempBasePath(['biome.json' => "{\n  \"linter\": {\n    \"enabled\": true\n  }\n}\n"]);

    expect(makeCheck(BiomeUsesLocalSchemaCheck::class)->fix())->toBe(CheckResult::PASS);
    expect(file_get_contents(base_path('biome.json')))->toBe(
        "{\n  \"\$schema\": \"".biomeLocalSchema()."\",\n  \"linter\": {\n    \"enabled\": true\n  }\n}\n",
    );
});

it('biomeUsesLocalSchema inserts into an otherwise empty config', function (): void {
    $this->withTempBasePath(['biome.json' => "{}\n"]);

    expect(makeCheck(BiomeUsesLocalSchemaCheck::class)->fix())->toBe(CheckResult::PASS);
    expect(json_decode((string) file_get_contents(base_path('biome.json')), true))
        ->toBe(['$schema' => biomeLocalSchema()]);
});
