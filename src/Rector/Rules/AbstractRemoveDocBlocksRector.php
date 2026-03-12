<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Rector\Rules;

use PhpParser\Comment\Doc;
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

        $originalText = $docComment->getText();
        $newText = $originalText;

        foreach ($this->commentsToRemove() as $text) {
            $newText = preg_replace('/^\s*\*[^\n]*'.preg_quote($text, '/').'[^\n]*\n?/m', '', $newText) ?? $newText;
        }

        if ($newText === $originalText) {
            return null;
        }

        // If only the docblock shell remains (no meaningful content lines), remove it entirely
        // A meaningful line starts with * but is not the closing */
        if (!preg_match('/^\s*\*(?!\/)(?!\s*$)/m', $newText)) {
            $node->setAttribute('comments', []);
        } else {
            $node->setDocComment(new Doc($newText, $docComment->getStartLine(), $docComment->getStartFilePos(), $docComment->getStartTokenPos()));
        }

        return $node;
    }

    /** @return string[] */
    abstract protected function commentsToRemove(): array;
}
