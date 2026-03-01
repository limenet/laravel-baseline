<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Tests\Rector;

use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class RemoveFactoryDocBlocksRectorTest extends AbstractRectorTestCase
{
    public function test_removes_default_laravel_factory_docblocks(): void
    {
        $this->doTestFile(__DIR__.'/Fixtures/remove_factory_doc_blocks.php.inc');
    }

    public function test_preserves_custom_docblocks(): void
    {
        $this->doTestFile(__DIR__.'/Fixtures/remove_factory_doc_blocks_no_change.php.inc');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__.'/config/configured_rule_factory.php';
    }
}
