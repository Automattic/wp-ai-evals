<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use InvalidArgumentException;
use JsonSerializable;

final class ReportedCost implements JsonSerializable {

	private float $amount;
	private string $currency;
	private ?string $source;

	public function __construct( float $amount, string $currency, ?string $source = null ) {
		$currency = strtoupper( trim( $currency ) );
		if ( ! is_finite( $amount ) || $amount < 0 ) {
			throw new InvalidArgumentException( 'A reported cost amount must be a finite, non-negative number.' );
		}
		if ( 1 !== preg_match( '/^[A-Z0-9_-]{2,12}$/', $currency ) ) {
			throw new InvalidArgumentException( 'A reported cost currency must be a short currency or billing-unit code.' );
		}

		$this->amount   = $amount;
		$this->currency = $currency;
		$this->source   = null !== $source && '' !== trim( $source ) ? trim( $source ) : null;
	}

	/** @param mixed $value */
	public static function from( $value ): ?self {
		if ( $value instanceof self ) {
			return clone $value;
		}

		if ( is_object( $value ) && method_exists( $value, 'getAmount' ) && method_exists( $value, 'getCurrency' ) ) {
			$value = array(
				'amount'   => $value->getAmount(),
				'currency' => $value->getCurrency(),
				'source'   => method_exists( $value, 'getSource' ) ? $value->getSource() : null,
			);
		}

		if (
			! is_array( $value )
			|| ! isset( $value['amount'], $value['currency'] )
			|| ! is_numeric( $value['amount'] )
			|| ! is_string( $value['currency'] )
		) {
			return null;
		}

		try {
			return new self(
				(float) $value['amount'],
				$value['currency'],
				isset( $value['source'] ) && is_string( $value['source'] ) ? $value['source'] : null
			);
		} catch ( InvalidArgumentException $error ) {
			return null;
		}
	}

	/**
	 * Reads only a cost explicitly reported by the client, provider, or an integration.
	 *
	 * The harness never calculates a cost from model pricing. Integrations can return
	 * a ReportedCost or an array with amount, currency, and optional source through
	 * the wp_ai_evals_ai_result_reported_cost filter.
	 *
	 * @param object $result
	 */
	public static function fromAiResult( $result ): ?self {
		$additional_data = array();
		if ( method_exists( $result, 'getAdditionalData' ) ) {
			$candidate = $result->getAdditionalData();
			if ( is_array( $candidate ) ) {
				$additional_data = $candidate;
			}
		}

		$value = null;
		if ( method_exists( $result, 'getCost' ) ) {
			$value = $result->getCost();
		} elseif ( method_exists( $result, 'getTokenUsage' ) ) {
			$usage = $result->getTokenUsage();
			if ( is_object( $usage ) && method_exists( $usage, 'getCost' ) ) {
				$value = $usage->getCost();
			}
		}

		if ( null === $value && isset( $additional_data['cost'] ) ) {
			$value = $additional_data['cost'];
		}

		if ( function_exists( 'apply_filters' ) ) {
			$value = apply_filters(
				'wp_ai_evals_ai_result_reported_cost',
				$value,
				$result,
				$additional_data
			);
		}

		return self::from( $value );
	}

	public function getAmount(): float {
		return $this->amount;
	}

	public function getCurrency(): string {
		return $this->currency;
	}

	public function getSource(): ?string {
		return $this->source;
	}

	/** @return array{amount: float, currency: string, source?: string} */
	public function jsonSerialize(): array {
		$data = array(
			'amount'   => $this->amount,
			'currency' => $this->currency,
		);
		if ( null !== $this->source ) {
			$data['source'] = $this->source;
		}

		return $data;
	}
}
