<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RemoveMigrationDocBlocksRector extends AbstractRector
{
    private const COMMENTS_TO_REMOVE = [
        'Run the migrations.',
        'Reverse the migrations.',
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove default Laravel migration PHPDoc comments from up() and down() methods',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {}

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
CODE_SAMPLE
                ),
            ]
        );
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /** @param ClassMethod $node */
    public function refactor(Node $node): ?Node
    {
        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return null;
        }

        foreach (self::COMMENTS_TO_REMOVE as $text) {
            if (str_contains($docComment->getText(), $text)) {
                $node->setAttribute('comments', []);

                return $node;
            }
        }

        return null;
    }
}
