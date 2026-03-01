<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Rector\Rules;

use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RemoveSeederDocBlocksRector extends AbstractRemoveDocBlocksRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove default Laravel seeder PHPDoc comments from run() method',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void {}
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class DatabaseSeeder extends Seeder
{
    public function run(): void {}
}
CODE_SAMPLE
                ),
            ],
        );
    }

    protected function commentsToRemove(): array
    {
        return [
            'Run the database seeds.',
        ];
    }
}
