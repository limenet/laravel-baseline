<?php

use Limenet\LaravelBaseline\Checks\CommentCollector;

it('can add comments', function (): void {
    $collector = new CommentCollector();

    $collector->add('First comment');
    $collector->add('Second comment');

    expect($collector->all())->toBe(['First comment', 'Second comment']);
});

it('returns empty array when no comments added', function (): void {
    $collector = new CommentCollector();

    expect($collector->all())->toBe([]);
});

it('can reset comments', function (): void {
    $collector = new CommentCollector();

    $collector->add('First comment');
    $collector->add('Second comment');

    expect($collector->all())->toHaveCount(2);

    $collector->reset();

    expect($collector->all())->toBe([]);
});
