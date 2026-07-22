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
            '<testsuite name="WordPress AI Evals" tests="%d" failures="%d" errors="%d" time="%.6f">',
            $report->getTotal(),
            $this->countStatus($report, 'failed'),
            $this->countStatus($report, 'error'),
            $report->getDurationMilliseconds() / 1000
        );

        foreach ($report->getResults() as $result) {
            $lines[] = sprintf(
                '  <testcase classname="%s" name="%s" time="%.6f">',
                $this->escape($result->getSuiteId()),
                $this->escape($result->getCaseId() . '#' . $result->getIteration()),
                $result->getDurationMilliseconds() / 1000
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

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
