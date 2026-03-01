<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Rector\Rules;

use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RemoveFactoryDocBlocksRector extends AbstractRemoveDocBlocksRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove default Laravel factory PHPDoc comments from definition() method',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array {}
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class UserFactory extends Factory
{
    public function definition(): array {}
}
CODE_SAMPLE
                ),
            ],
        );
    }

    protected function commentsToRemove(): array
    {
        return [
            "Define the model's default state.",
        ];
    }
}
