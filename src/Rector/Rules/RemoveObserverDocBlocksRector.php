<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Rector\Rules;

use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RemoveObserverDocBlocksRector extends AbstractRemoveDocBlocksRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove default Laravel observer PHPDoc comments from event handler methods',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void {}

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void {}
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class UserObserver
{
    public function created(User $user): void {}

    public function deleted(User $user): void {}
}
CODE_SAMPLE
                ),
            ],
        );
    }

    protected function commentsToRemove(): array
    {
        // Observer docblocks include the model name, e.g. 'Handle the User "created" event.'
        // Matching ' event.' covers all variants without hardcoding every model name.
        return [
            ' event.',
        ];
    }
}
