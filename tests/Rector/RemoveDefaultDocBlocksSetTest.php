<?php

declare(strict_types=1);

namespace Limenet\LaravelBaseline\Tests\Rector;

use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class RemoveDefaultDocBlocksSetTest extends AbstractRectorTestCase
{
    public function test_set_removes_default_docblocks_across_multiple_file_types(): void
    {
        $this->doTestFile(__DIR__.'/Fixtures/remove_default_docblocks_set.php.inc');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__.'/config/configured_set_remove_default_docblocks.php';
    }
}
