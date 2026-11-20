<?php

use Limenet\LaravelBaseline\Support\YamlTextEditor;
use Symfony\Component\Yaml\Yaml;

function editYaml(string $contents, callable $edit): string
{
    $editor = new YamlTextEditor($contents);
    $edit($editor);

    expect($editor->matches(Yaml::parse($editor->contents()) ?? []))->toBeTrue();

    return $editor->contents();
}

it('replaces a scalar in place and keeps its comment', function (): void {
    expect(editYaml("# header\ncache:\n  dir: old # why\n", fn (YamlTextEditor $e) => $e->set(['cache', 'dir'], '.trivycache')))
        ->toBe("# header\ncache:\n  dir: .trivycache # why\n");
});

it('adds a missing key at the end of its parent block', function (): void {
    expect(editYaml("scan:\n    skip-dirs:\n        - .ddev/\n\nother: 1\n", fn (YamlTextEditor $e) => $e->set(['scan', 'disable-telemetry'], true)))
        ->toBe("scan:\n    skip-dirs:\n        - .ddev/\n    disable-telemetry: true\n\nother: 1\n");
});

it('adds missing parents in the file\'s indent', function (): void {
    expect(editYaml("scan:\n    scanners:\n        - vuln\n", fn (YamlTextEditor $e) => $e->set(['pkg', 'include-dev-deps'], true)))
        ->toBe("scan:\n    scanners:\n        - vuln\npkg:\n    include-dev-deps: true\n");
});

it('appends to a block sequence in its own style', function (string $before, string $after): void {
    expect(editYaml($before, fn (YamlTextEditor $e) => $e->append(['scan', 'scanners'], ['vuln'])))->toBe($after);
})->with([
    'indented' => ["scan:\n  scanners:\n    - misconfig # first\n    - secret\n", "scan:\n  scanners:\n    - misconfig # first\n    - secret\n    - vuln\n"],
    'flush' => ["scan:\n  scanners:\n  - secret\n", "scan:\n  scanners:\n  - secret\n  - vuln\n"],
    'flow' => ["scan:\n  scanners: [secret] # c\n", "scan:\n  scanners: [secret, vuln] # c\n"],
]);

it('creates a missing sequence with its parents', function (): void {
    expect(editYaml("sync:\n  defaults:\n    mode: two-way\n", fn (YamlTextEditor $e) => $e->append(['sync', 'defaults', 'ignore', 'paths'], ['/node_modules'])))
        ->toBe("sync:\n  defaults:\n    mode: two-way\n    ignore:\n      paths:\n        - /node_modules\n");
});

it('removes a key with its nested block and keeps the rest', function (): void {
    expect(editYaml("severity:\n  - HIGH\n# keep me\ncache:\n  dir: x\n", fn (YamlTextEditor $e) => $e->remove(['severity'])))
        ->toBe("# keep me\ncache:\n  dir: x\n");
});

it('reports a result that does not parse to the expected data', function (): void {
    $editor = new YamlTextEditor("cache:\n  dir: x\n");
    $editor->set(['cache', 'dir'], 'y');

    expect($editor->matches(['cache' => ['dir' => 'y']]))->toBeTrue();
    expect($editor->matches(['cache' => ['dir' => 'x']]))->toBeFalse();
});

it('addresses keys that contain a colon, as GitLab job names do', function (): void {
    expect(editYaml("sbom:server:\n  extends:\n    - .sbom_server\n", fn (YamlTextEditor $e) => $e->set(['sbom:server', 'environment', 'name'], 'production')))
        ->toBe("sbom:server:\n  extends:\n    - .sbom_server\n  environment:\n    name: production\n");
});

it('appends a mapping item quoted per key like its siblings', function (): void {
    $before = "include:\n  - project: \"group/templates\"\n    ref: master\n    file: \"deploy.yml\"\n\nstages: [build]\n";

    expect(editYaml($before, fn (YamlTextEditor $e) => $e->appendMapping(['include'], ['project' => 'group/templates', 'ref' => 'master', 'file' => 'sbom.yml'])))
        ->toBe("include:\n  - project: \"group/templates\"\n    ref: master\n    file: \"deploy.yml\"\n  - project: \"group/templates\"\n    ref: master\n    file: \"sbom.yml\"\n\nstages: [build]\n");
});

it('creates a missing sequence for a mapping item', function (): void {
    expect(editYaml("stages:\n  - build\n", fn (YamlTextEditor $e) => $e->appendMapping(['include'], ['local' => 'ci.yml'])))
        ->toBe("stages:\n  - build\ninclude:\n  - local: ci.yml\n");
});

it('reports how a scalar is quoted', function (string $line, ?string $quote): void {
    expect((new YamlTextEditor("job:\n  variables:\n    {$line}\n"))->quoteAt(['job', 'variables', 'HOST']))->toBe($quote);
})->with([
    ['HOST: "1.2.3.4"', '"'],
    ["HOST: '1.2.3.4'", "'"],
    ['HOST: 1.2.3.4', null],
]);
