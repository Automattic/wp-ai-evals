<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Evaluator\ExactMatch;
use Automattic\AiEvals\Registry;
use Automattic\AiEvals\RunReport;
use Automattic\AiEvals\Runner;
use Automattic\AiEvals\Selection;
use Automattic\AiEvals\Storage\HistoryStore;
use Automattic\AiEvals\Storage\RunSessionStore;
use Automattic\AiEvals\Suite;

final class RunSessionStoreTest extends TestCase
{
    protected function setUp(): void
    {
        wp_ai_evals_test_reset_state();
    }

    public function testAdvancesOneCasePerCallThenCompletesAndPersistsAReport(): void
    {
        $registry = (new Registry())->register(
            Suite::make('suite')
                ->addCase(
                    EvaluationCase::make('first')
                        ->task(static fn(): string => 'ok')
                        ->expected('ok')
                        ->evaluateWith(new ExactMatch())
                )
                ->addCase(
                    EvaluationCase::make('second')
                        ->task(static fn(): string => 'ok')
                        ->expected('ok')
                        ->evaluateWith(new ExactMatch())
                )
        );

        $store = new RunSessionStore();
        $session = $store->start($registry, Selection::all(), new Runner());
        $runId = (string) $session['id'];

        self::assertSame(2, $session['total']);
        self::assertSame(0, $session['completed']);
        self::assertSame(2, $session['remaining']);
        self::assertFalse($session['complete']);

        $first = $store->advance($runId, $registry, new Runner());
        self::assertNull($first['report']);
        self::assertSame(1, $first['session']['completed']);
        self::assertSame(1, $first['session']['remaining']);
        self::assertFalse($first['session']['complete']);

        $second = $store->advance($runId, $registry, new Runner());
        self::assertInstanceOf(RunReport::class, $second['report']);
        self::assertSame(2, $second['report']->getTotal());
        self::assertSame(2, $second['report']->getPassed());
        self::assertTrue($second['session']['complete']);
        self::assertSame(0, $second['session']['remaining']);
    }

    public function testDropsTheSessionAndRecordsHistoryOnCompletion(): void
    {
        $registry = (new Registry())->register(
            Suite::make('suite')->addCase(
                EvaluationCase::make('only')
                    ->task(static fn(): string => 'ok')
                    ->expected('ok')
                    ->evaluateWith(new ExactMatch())
            )
        );

        $store = new RunSessionStore();
        $runId = (string) $store->start($registry, Selection::all(), new Runner())['id'];
        $store->advance($runId, $registry, new Runner());

        self::assertNull($store->status($runId), 'The session transient is cleared once the run completes.');

        $history = (new HistoryStore())->all();
        self::assertCount(1, $history);
        self::assertSame($runId, $history[0]['id']);
    }

    public function testStatusReturnsNullForAnUnknownRun(): void
    {
        self::assertNull((new RunSessionStore())->status('does-not-exist'));
    }
}
