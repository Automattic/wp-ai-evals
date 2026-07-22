<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Selection;
use Automattic\AiEvals\Suite;

final class SelectionTest extends TestCase
{
    public function testFiltersBySuiteQualifiedCaseAndAnyTag(): void
    {
        $suite = Suite::make('content');
        $case = EvaluationCase::make('summary')->tag('smoke', 'quality');

        self::assertTrue((new Selection(['content'], ['content/summary'], ['smoke']))->matches($suite, $case));
        self::assertTrue((new Selection([], [], ['missing', 'quality']))->matches($suite, $case));
        self::assertFalse((new Selection(['other']))->matches($suite, $case));
        self::assertFalse((new Selection([], ['other']))->matches($suite, $case));
        self::assertFalse((new Selection([], [], ['slow']))->matches($suite, $case));
    }
}
