<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Tests\Rector;

use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class RemoveNotificationDocBlocksRectorTest extends AbstractRectorTestCase
{
    public function test_removes_default_laravel_notification_docblocks(): void
    {
        $this->doTestFile(__DIR__.'/Fixtures/remove_notification_doc_blocks.php.inc');
    }

    public function test_preserves_custom_docblocks(): void
    {
        $this->doTestFile(__DIR__.'/Fixtures/remove_notification_doc_blocks_no_change.php.inc');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__.'/config/configured_rule_notification.php';
    }
}
