<?php

namespace Limenet\LaravelBaseline\Checks;

class CommentCollector
{
    /** @var list<string> */
    private array $comments = [];

    public function add(string $comment): void
    {
        $this->comments[] = $comment;
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->comments;
    }

    public function reset(): void
    {
        $this->comments = [];
    }
}
