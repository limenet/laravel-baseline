<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Rector\Rules;

use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class RemoveNotificationDocBlocksRector extends AbstractRemoveDocBlocksRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove default Laravel notification PHPDoc comments from via(), toMail(), and toArray() methods',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class InvoicePaid extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct() {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array {}

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage {}

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array {}
}
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
class InvoicePaid extends Notification
{
    public function __construct() {}

    public function via(object $notifiable): array {}

    public function toMail(object $notifiable): MailMessage {}

    public function toArray(object $notifiable): array {}
}
CODE_SAMPLE
                ),
            ],
        );
    }

    protected function commentsToRemove(): array
    {
        return [
            'Create a new notification instance.',
            "Get the notification's delivery channels.",
            'Get the mail representation of the notification.',
            'Get the array representation of the notification.',
        ];
    }
}
