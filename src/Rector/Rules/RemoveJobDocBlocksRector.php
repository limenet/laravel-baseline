<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Rector\Rules;

use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RemoveJobDocBlocksRector extends AbstractRemoveDocBlocksRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove default Laravel job PHPDoc comments from __construct() and handle() methods',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class ProcessPodcast implements ShouldQueue
{
    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(): void {}
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class ProcessPodcast implements ShouldQueue
{
    public function __construct() {}

    public function handle(): void {}
}
CODE_SAMPLE
                ),
            ],
        );
    }

    protected function commentsToRemove(): array
    {
        return [
            'Create a new job instance.',
            'Execute the job.',
        ];
    }
}
