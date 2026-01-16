<?php

namespace Limenet\LaravelBaseline\Concerns;

use Limenet\LaravelBaseline\Checks\CommentCollector;

trait CommentManagement
{
    protected CommentCollector $commentCollector;

    public function setCommentCollector(CommentCollector $collector): void
    {
        $this->commentCollector = $collector;
    }

    public function addComment(string $comment): void
    {
        $this->commentCollector->add($comment);
    }

    /** @return list<string> */
    public function getComments(): array
    {
        return $this->commentCollector->all();
    }

    public function resetComments(): void
    {
        $this->commentCollector->reset();
    }
}
