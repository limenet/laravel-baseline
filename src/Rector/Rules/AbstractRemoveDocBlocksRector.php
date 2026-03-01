<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;

abstract class AbstractRemoveDocBlocksRector extends AbstractRector
{
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

        foreach ($this->commentsToRemove() as $text) {
            if (str_contains($docComment->getText(), $text)) {
                $node->setAttribute('comments', []);

                return $node;
            }
        }

        return null;
    }

    /** @return string[] */
    abstract protected function commentsToRemove(): array;
}
