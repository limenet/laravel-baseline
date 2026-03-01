<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Rector\Rules;

use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RemoveMailableDocBlocksRector extends AbstractRemoveDocBlocksRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove default Laravel mailable PHPDoc comments from envelope(), content(), and attachments() methods',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class OrderShipped extends Mailable
{
    /**
     * Create a new message instance.
     */
    public function __construct() {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope {}

    /**
     * Get the message content definition.
     */
    public function content(): Content {}

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array {}
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class OrderShipped extends Mailable
{
    public function __construct() {}

    public function envelope(): Envelope {}

    public function content(): Content {}

    public function attachments(): array {}
}
CODE_SAMPLE
                ),
            ],
        );
    }

    protected function commentsToRemove(): array
    {
        return [
            'Create a new message instance.',
            'Get the message envelope.',
            'Get the message content definition.',
            'Get the attachments for the message.',
        ];
    }
}
