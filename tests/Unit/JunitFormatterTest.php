<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Evaluator\ExactMatch;
use Automattic\AiEvals\Registry;
use Automattic\AiEvals\Report\JunitFormatter;
use Automattic\AiEvals\Runner;
use Automattic\AiEvals\Suite;

final class JunitFormatterTest extends TestCase
{
    public function testFormatsFailuresAsJunitXml(): void
    {
        $case = EvaluationCase::make('xml-case')
            ->task(static fn(): string => '<actual>')
            ->expected('<expected>')
            ->evaluateWith(new ExactMatch());
        $report = (new Runner())->run((new Registry())->register(Suite::make('suite')->addCase($case)));

        $xml = (new JunitFormatter())->format($report);

        self::assertStringContainsString('<testsuite', $xml);
        self::assertStringContainsString('failures="1"', $xml);
        self::assertStringContainsString('<failure', $xml);
    }
}
