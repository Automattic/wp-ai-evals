<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\Registry;

final class DirectoryLoaderTest extends TestCase
{
    public function testLoadsStandaloneAndModularSuitesInStableOrder(): void
    {
        $registry = (new Registry())->loadDirectory(dirname(__DIR__) . '/Fixtures/evals');

        self::assertSame(['standalone', 'modular'], array_keys($registry->all()));
        self::assertSame(['first', 'second', 'third'], array_keys($registry->get('modular')->getCases()));
    }

    public function testRejectsAnUnreadableDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Registry())->loadDirectory(__DIR__ . '/missing');
    }
}
