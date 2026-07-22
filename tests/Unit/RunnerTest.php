<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Evaluator\ContainsText;
use Automattic\AiEvals\Evaluator\ExactMatch;
use Automattic\AiEvals\Registry;
use Automattic\AiEvals\Runner;
use Automattic\AiEvals\Selection;
use Automattic\AiEvals\Suite;
use Automattic\AiEvals\TaskResult;

final class RunnerTest extends TestCase
{
    public function testRunsSelectedCasesAndAggregatesResults(): void
    {
        $suite = Suite::make('plugin')
            ->addCase(
                EvaluationCase::make('pass')
                    ->input('WordPress')
                    ->task(static fn(string $input): TaskResult => TaskResult::fromOutput(
                        "Hello {$input}",
                        [
                            'tokens' => ['input' => 4, 'output' => 2, 'total' => 6, 'thinking' => 0],
                            'tools' => ['plugin/search'],
                            'provider' => 'test-provider',
                            'model' => 'test-model',
                        ]
                    ))
                    ->expected('WordPress')
                    ->evaluateWith(new ContainsText())
                    ->tag('smoke')
            )
            ->addCase(
                EvaluationCase::make('fail')
                    ->task(static fn(): string => 'actual')
                    ->expected('expected')
                    ->evaluateWith(new ExactMatch())
                    ->tag('regression')
            );

        $report = (new Runner())->run(
            (new Registry())->register($suite),
            new Selection([], [], ['smoke'], 2)
        );

        self::assertSame(2, $report->getTotal());
        self::assertSame(2, $report->getPassed());
        self::assertSame(1.0, $report->getScore());
        self::assertSame(2, $report->getResults()[1]->getIteration());
        self::assertArrayHasKey('duration_ms', $report->getResults()[0]->getTaskResult()->getMetadata());
        self::assertArrayHasKey(
            'duration_ms',
            $report->getResults()[0]->getEvaluatorResults()[0]->getMetadata()
        );
        self::assertGreaterThanOrEqual(
            $report->getResults()[0]->getTaskResult()->getMetadata()['duration_ms'],
            $report->getResults()[0]->getDurationMilliseconds()
        );
        self::assertSame(12, $report->getDiagnostics()['tokens']['total']);
        self::assertSame(['plugin/search'], $report->getDiagnostics()['tools']);

        $serialized = $report->jsonSerialize();
        self::assertSame('WordPress', $serialized['results'][0]->jsonSerialize()['input']);
        self::assertSame(['smoke'], $serialized['results'][0]->jsonSerialize()['tags']);
    }

    public function testCapturesTaskErrorsWithoutStoppingTheRun(): void
    {
        $suite = Suite::make('plugin')
            ->addCase(
                EvaluationCase::make('broken')
                    ->task(static function (): void {
                        throw new RuntimeException('Task exploded.');
                    })
                    ->evaluateWith(new ExactMatch())
            )
            ->addCase(
                EvaluationCase::make('healthy')
                    ->task(static fn(): string => 'ok')
                    ->expected('ok')
                    ->evaluateWith(new ExactMatch())
            );

        $report = (new Runner())->run((new Registry())->register($suite));

        self::assertSame(2, $report->getTotal());
        self::assertSame('error', $report->getResults()[0]->getStatus());
        self::assertSame('Task exploded.', $report->getResults()[0]->getError());
        self::assertSame('passed', $report->getResults()[1]->getStatus());
    }

    public function testCapturesInvalidCaseConfigurationAsAnError(): void
    {
        $suite = Suite::make('plugin')->addCase(
            EvaluationCase::make('missing-task')->evaluateWith(new ExactMatch())
        );

        $report = (new Runner())->run((new Registry())->register($suite));

        self::assertSame('error', $report->getResults()[0]->getStatus());
        self::assertStringContainsString('has no task', $report->getResults()[0]->getError());
    }
}
