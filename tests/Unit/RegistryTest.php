<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Evaluator\ExactMatch;
use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\Registry;
use Automattic\AiEvals\Suite;

final class RegistryTest extends TestCase
{
    public function testRegistersMultipleSuitesAndCollectsTags(): void
    {
        $first = Suite::make('first')->addCase(
            EvaluationCase::make('one')
                ->task(static fn(): string => 'ok')
                ->expected('ok')
                ->evaluateWith(new ExactMatch())
                ->tag('smoke', 'fast')
        );
        $second = Suite::make('second')->addCase(
            EvaluationCase::make('two')
                ->task(static fn(): string => 'ok')
                ->expected('ok')
                ->evaluateWith(new ExactMatch())
                ->tag('regression', 'smoke')
        );

        $registry = (new Registry())->register($first)->register($second);

        self::assertSame(['first', 'second'], array_keys($registry->all()));
        self::assertSame(['fast', 'regression', 'smoke'], $registry->tags());
    }

    public function testRejectsDuplicateSuiteIds(): void
    {
        $registry = (new Registry())->register(Suite::make('duplicate'));

        $this->expectException(InvalidArgumentException::class);
        $registry->register(Suite::make('duplicate'));
    }

    public function testAddsParameterizedCasesFromAnIterable(): void
    {
        $cases = (static function (): iterable {
            yield EvaluationCase::make('first')->task(static fn(): string => 'ok');
            yield EvaluationCase::make('second')->task(static fn(): string => 'ok');
        })();

        $suite = Suite::make('dataset')->addCases($cases);

        self::assertSame(['first', 'second'], array_keys($suite->getCases()));
    }
}
