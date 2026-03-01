<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Rector\Rules;

use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RemoveListenerDocBlocksRector extends AbstractRemoveDocBlocksRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove default Laravel event listener PHPDoc comments from __construct() and handle() methods',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class SendShipmentNotification
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(OrderShipped $event): void {}
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class SendShipmentNotification
{
    public function __construct() {}

    public function handle(OrderShipped $event): void {}
}
CODE_SAMPLE
                ),
            ],
        );
    }

    protected function commentsToRemove(): array
    {
        return [
            'Create the event listener.',
            'Handle the event.',
        ];
    }
}
