<?php

use Limenet\LaravelBaseline\Checks\Checker;
use Limenet\LaravelBaseline\Commands\LaravelBaselineCommand;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('registers all public check methods from Checker class', function (): void {
    // Get all public methods from Checker that return CheckResult
    $reflection = new ReflectionClass(Checker::class);
    $checkerMethods = [];

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        // Skip constructor and helper methods
        if ($method->getName() === '__construct') {
            continue;
        }

        // Only include methods that return CheckResult
        $returnType = $method->getReturnType();
        if ($returnType instanceof ReflectionNamedType && $returnType->getName() === CheckResult::class) {
            $checkerMethods[] = $method->getName();
        }
    }

    sort($checkerMethods);

    // Get the command class source code
    $commandReflection = new ReflectionClass(LaravelBaselineCommand::class);
    $commandFile = $commandReflection->getFileName();
    $commandSource = file_get_contents($commandFile);

    // Extract all checker method calls from the command
    preg_match_all('/\$checker->([a-zA-Z]+)\(\.\.\./', $commandSource, $matches);
    $registeredMethods = $matches[1] ?? [];
    sort($registeredMethods);

    // Compare the two lists
    $missingMethods = array_diff($checkerMethods, $registeredMethods);

    // Create a helpful error message if methods are missing
    if (!empty($missingMethods)) {
        $message = sprintf(
            "The following check methods are not registered in LaravelBaselineCommand:\n  - %s\n\nPlease add them to the foreach loop in the handle() method.",
            implode("\n  - ", $missingMethods),
        );
        expect($missingMethods)->toBeEmpty($message);
    }

    expect($missingMethods)
        ->toBeEmpty()
        ->and($checkerMethods)
        ->not->toBeEmpty();
})->group('command');
