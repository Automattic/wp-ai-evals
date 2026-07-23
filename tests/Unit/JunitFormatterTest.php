<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Evaluator\ExactMatch;
use Automattic\AiEvals\Registry;
use Automattic\AiEvals\Report\JunitFormatter;
use Automattic\AiEvals\RunConfiguration;
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

    public function testProducesWellFormedXmlWhenErrorsContainControlCharacters(): void
    {
        $case = EvaluationCase::make('control-char-case')
            ->task(static function (): string {
                throw new \RuntimeException("boom\x08 with a backspace and a \x00 null");
            })
            ->evaluateWith(new ExactMatch());
        $report = (new Runner())->run((new Registry())->register(Suite::make('suite')->addCase($case)));

        $xml = (new JunitFormatter())->format($report);

        self::assertStringContainsString('errors="1"', $xml);

        // libxml_use_internal_errors() mutates process-global state; save and
        // restore it so this test cannot influence others or mask their warnings.
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        try {
            libxml_clear_errors();
            $parsed = simplexml_load_string($xml);
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }

        self::assertNotFalse($parsed, 'JUnit XML with control characters must remain parseable.');
        self::assertSame([], $errors);
    }

    public function testIncludesModelVariantInTestCaseName(): void
    {
        $case = EvaluationCase::make('model-case')
            ->modelTask(static fn($input, $context): \Automattic\AiEvals\TaskResult =>
                \Automattic\AiEvals\TaskResult::fromOutput('ok', [
                    'provider' => $context->getModelTarget()->getProviderId(),
                    'model' => $context->getModelTarget()->getModelId(),
                ]))
            ->expected('ok')
            ->evaluateWith(new ExactMatch());
        $report = (new Runner())->run(
            (new Registry())->register(Suite::make('suite')->addCase($case)),
            null,
            RunConfiguration::fromStrings(['openai:gpt-test'])
        );

        self::assertStringContainsString('model-case#1@openai:gpt-test', (new JunitFormatter())->format($report));
    }
}
