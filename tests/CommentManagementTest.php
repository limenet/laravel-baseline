<?php

use Limenet\LaravelBaseline\Checks\CommentCollector;
use Limenet\LaravelBaseline\Concerns\CommentManagement;

it('can set a comment collector and add comments', function (): void {
    $instance = new class()
    {
        use CommentManagement;
    };

    $collector = new CommentCollector();
    $instance->setCommentCollector($collector);

    $instance->addComment('test comment');

    expect($instance->getComments())->toBe(['test comment']);
    expect($collector->all())->toBe(['test comment']);
});

it('delegates getComments to the collector', function (): void {
    $instance = new class()
    {
        use CommentManagement;
    };

    $collector = new CommentCollector();
    $instance->setCommentCollector($collector);

    expect($instance->getComments())->toBe([]);

    $instance->addComment('first');
    $instance->addComment('second');

    expect($instance->getComments())->toBe(['first', 'second']);
});

it('can reset comments', function (): void {
    $instance = new class()
    {
        use CommentManagement;
    };

    $collector = new CommentCollector();
    $instance->setCommentCollector($collector);

    $instance->addComment('first');
    $instance->addComment('second');
    expect($instance->getComments())->toHaveCount(2);

    $instance->resetComments();

    expect($instance->getComments())->toBe([]);
});

it('shares state with the underlying collector', function (): void {
    $instance = new class()
    {
        use CommentManagement;
    };

    $collector = new CommentCollector();
    $instance->setCommentCollector($collector);

    $collector->add('added via collector');
    $instance->addComment('added via trait');

    expect($instance->getComments())->toBe(['added via collector', 'added via trait']);
    expect($collector->all())->toBe(['added via collector', 'added via trait']);
});
