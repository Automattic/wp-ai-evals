<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use Automattic\AiEvals\ReportedCost;
use PHPUnit\Framework\TestCase;

final class ReportedCostTest extends TestCase {

	public function testNormalizesReportedCostMetadata(): void {
		$cost = ReportedCost::from(
			array(
				'amount'   => '0.00125',
				'currency' => 'usd',
				'source'   => 'provider',
			)
		);

		self::assertNotNull( $cost );
		self::assertSame( 0.00125, $cost->getAmount() );
		self::assertSame( 'USD', $cost->getCurrency() );
		self::assertSame( 'provider', $cost->getSource() );
	}

	public function testReadsCostFromAdditionalResultData(): void {
		$result = new class() {
			/** @return array<string, mixed> */
			public function getAdditionalData(): array {
				return array(
					'cost' => array(
						'amount'   => 0.004,
						'currency' => 'USD',
					),
				);
			}
		};

		$cost = ReportedCost::fromAiResult( $result );

		self::assertNotNull( $cost );
		self::assertSame(
			array(
				'amount'   => 0.004,
				'currency' => 'USD',
			),
			$cost->jsonSerialize()
		);
	}

	public function testRejectsUnnormalizedOrInvalidCosts(): void {
		self::assertNull( ReportedCost::from( array( 'amount' => 1 ) ) );
		self::assertNull(
			ReportedCost::from(
				array(
					'amount'   => -1,
					'currency' => 'USD',
				)
			)
		);
		self::assertNull(
			ReportedCost::from(
				array(
					'amount'   => 1,
					'currency' => '$',
				)
			)
		);
	}
}
