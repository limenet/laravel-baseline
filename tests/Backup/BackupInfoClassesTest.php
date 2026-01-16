<?php

use Limenet\LaravelBaseline\Backup\ClassConstInfo;
use Limenet\LaravelBaseline\Backup\FuncCallInfo;
use Limenet\LaravelBaseline\Backup\StaticPropertyInfo;
use Limenet\LaravelBaseline\Backup\UnparsedNode;

it('ClassConstInfo stores class and constant', function (): void {
    $info = new ClassConstInfo('App\\Models\\User', 'STATUS_ACTIVE');

    expect($info->class)->toBe('App\\Models\\User');
    expect($info->constant)->toBe('STATUS_ACTIVE');
});

it('StaticPropertyInfo stores class and property', function (): void {
    $info = new StaticPropertyInfo('App\\Services\\Cache', 'instance');

    expect($info->class)->toBe('App\\Services\\Cache');
    expect($info->property)->toBe('instance');
});

it('UnparsedNode stores node type', function (): void {
    $info = new UnparsedNode('Expr_Variable');

    expect($info->nodeType)->toBe('Expr_Variable');
});

it('FuncCallInfo stores name and args', function (): void {
    $info = new FuncCallInfo('env', ['APP_NAME', 'Laravel']);

    expect($info->name)->toBe('env');
    expect($info->args)->toBe(['APP_NAME', 'Laravel']);
});

it('FuncCallInfo isCall returns true for matching function name', function (): void {
    $info = new FuncCallInfo('env', ['APP_NAME', 'Laravel']);

    expect($info->isCall('env'))->toBeTrue();
    expect($info->isCall('config'))->toBeFalse();
});

it('FuncCallInfo isCall returns true for matching function name and first arg', function (): void {
    $info = new FuncCallInfo('env', ['APP_NAME', 'Laravel']);

    expect($info->isCall('env', 'APP_NAME'))->toBeTrue();
    expect($info->isCall('env', 'DB_HOST'))->toBeFalse();
});

it('FuncCallInfo isCall returns false when function name matches but first arg does not', function (): void {
    $info = new FuncCallInfo('env', ['APP_NAME', 'Laravel']);

    expect($info->isCall('env', 'OTHER_VAR'))->toBeFalse();
});

it('FuncCallInfo getFirstArg returns first argument', function (): void {
    $info = new FuncCallInfo('env', ['APP_NAME', 'Laravel']);

    expect($info->getFirstArg())->toBe('APP_NAME');
});

it('FuncCallInfo getFirstArg returns null when no args', function (): void {
    $info = new FuncCallInfo('now', []);

    expect($info->getFirstArg())->toBeNull();
});

it('FuncCallInfo getSecondArg returns second argument', function (): void {
    $info = new FuncCallInfo('env', ['APP_NAME', 'Laravel']);

    expect($info->getSecondArg())->toBe('Laravel');
});

it('FuncCallInfo getSecondArg returns null when only one arg', function (): void {
    $info = new FuncCallInfo('env', ['APP_NAME']);

    expect($info->getSecondArg())->toBeNull();
});
