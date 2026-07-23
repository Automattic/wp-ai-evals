<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Report;

use Automattic\AiEvals\CaseResult;
use Automattic\AiEvals\EvaluatorResult;
use Automattic\AiEvals\RunReport;

final class JunitFormatter
{
    public function format(RunReport $report): string
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $lines[] = sprintf(
            '<testsuite name="WordPress AI Evals" tests="%d" failures="%d" errors="%d" time="%s">',
            $report->getTotal(),
            $this->countStatus($report, 'failed'),
            $this->countStatus($report, 'error'),
            $this->seconds($report->getDurationMilliseconds())
        );

        foreach ($report->getResults() as $result) {
            $lines[] = sprintf(
                '  <testcase classname="%s" name="%s" time="%s">',
                $this->escape($result->getSuiteId()),
                $this->escape(
                    $result->getCaseId()
                    . '#'
                    . $result->getIteration()
                    . (null !== $result->getModelTarget() ? '@' . $result->getModelTarget()->getId() : '')
                ),
                $this->seconds($result->getDurationMilliseconds())
            );

            if ('error' === $result->getStatus()) {
                $message = $this->escape($result->getError());
                $lines[] = sprintf('    <error message="%s">%s</error>', $message, $message);
            } elseif ('failed' === $result->getStatus()) {
                $reasons = array_map(
                    static fn(EvaluatorResult $evaluation): string => $evaluation->getName() . ': ' . $evaluation->getReason(),
                    array_filter(
                        $result->getEvaluatorResults(),
                        static fn(EvaluatorResult $evaluation): bool => !$evaluation->hasPassed()
                    )
                );
                $message = $this->escape(implode("\n", $reasons));
                $lines[] = sprintf('    <failure message="Evaluation failed">%s</failure>', $message);
            }

            $lines[] = '  </testcase>';
        }

        $lines[] = '</testsuite>';

        return implode("\n", $lines) . "\n";
    }

    private function countStatus(RunReport $report, string $status): int
    {
        return count(array_filter(
            $report->getResults(),
            static fn(CaseResult $result): bool => $status === $result->getStatus()
        ));
    }

    /**
     * Formats a millisecond duration as locale-independent seconds.
     *
     * sprintf('%f') honors LC_NUMERIC, which can emit a comma decimal
     * separator and produce invalid JUnit XML on some locales.
     */
    private function seconds(float $milliseconds): string
    {
        return number_format($milliseconds / 1000, 6, '.', '');
    }

    private function escape(string $value): string
    {
        // Strip control characters that are illegal in XML 1.0 even when escaped
        // (everything below U+0020 except tab, line feed, and carriage return).
        // Task output and exception messages can contain these bytes, and leaving
        // them in produces a document that JUnit/CI parsers reject.
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
