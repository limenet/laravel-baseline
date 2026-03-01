<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Rector\Rules;

use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RemovePolicyDocBlocksRector extends AbstractRemoveDocBlocksRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove default Laravel policy PHPDoc comments from authorization methods',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class PostPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool {}

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Post $post): bool {}
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class PostPolicy
{
    public function viewAny(User $user): bool {}

    public function update(User $user, Post $post): bool {}
}
CODE_SAMPLE
                ),
            ],
        );
    }

    protected function commentsToRemove(): array
    {
        return [
            'Determine whether the user can view any models.',
            'Determine whether the user can view the model.',
            'Determine whether the user can create models.',
            'Determine whether the user can update the model.',
            'Determine whether the user can delete the model.',
            'Determine whether the user can restore the model.',
            'Determine whether the user can permanently delete the model.',
        ];
    }
}
