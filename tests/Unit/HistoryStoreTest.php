<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Automattic\AiEvals\RunReport;
use Automattic\AiEvals\Storage\HistoryStore;

final class HistoryStoreTest extends TestCase
{
    protected function setUp(): void
    {
        wp_ai_evals_test_reset_state();
    }

    public function testBoundsHistoryAndPrunesEvictedFullReports(): void
    {
        $store = new HistoryStore(2);
        $store->save($this->report('run-1'));
        $store->save($this->report('run-2'));
        $store->save($this->report('run-3'));

        $history = $store->all();
        self::assertCount(2, $history);
        self::assertSame('run-3', $history[0]['id']);
        self::assertSame('run-2', $history[1]['id']);

        // The evicted run's full report is deleted; retained runs are inspectable.
        self::assertNull($store->find('run-1'));
        self::assertNotNull($store->find('run-2'));
        self::assertNotNull($store->find('run-3'));
    }

    public function testAppliesTheReportBeforeStoreFilterToPersistedReports(): void
    {
        add_filter(
            'wp_ai_evals_report_before_store',
            /**
             * @param array<string, mixed> $normalized
             * @return array<string, mixed>
             */
            static function (array $normalized): array {
                $normalized['redacted'] = true;
                unset($normalized['results']);

                return $normalized;
            }
        );

        (new HistoryStore(5))->save($this->report('run-secret'));

        $stored = (new HistoryStore(5))->find('run-secret');
        self::assertIsArray($stored);
        self::assertTrue($stored['redacted']);
        self::assertArrayNotHasKey('results', $stored);

        remove_all_filters('wp_ai_evals_report_before_store');
    }

    public function testFindRejectsMalformedRunIds(): void
    {
        self::assertNull((new HistoryStore())->find('../../etc/passwd'));
    }

    private function report(string $id): RunReport
    {
        return new RunReport($id, '2020-01-01T00:00:00+00:00', 12.5, []);
    }
}
