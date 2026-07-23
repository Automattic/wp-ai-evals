<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Task;

use Automattic\AiEvals\ReportedCost;
use Automattic\AiEvals\TaskResult;

final class AiResultAdapter {

	/** @param object $result */
	public static function adapt( $result, string $modality ): TaskResult {
		$output = $result;

		if ( 'text' === $modality && method_exists( $result, 'toText' ) ) {
			$output = $result->toText();
		} elseif ( in_array( $modality, array( 'image', 'speech', 'video' ), true ) && method_exists( $result, 'toFile' ) ) {
			$output = $result->toFile();
		} elseif ( 'text' !== $modality && method_exists( $result, 'toMessage' ) ) {
			$output = $result->toMessage();
		}

		// Text evaluators expect a string. If a text result exposed no toText(),
		// coerce a stringable result rather than handing evaluators a message or
		// result object they cannot score.
		if ( 'text' === $modality && ! is_string( $output ) ) {
			if ( is_object( $output ) && method_exists( $output, '__toString' ) ) {
				$output = (string) $output;
			} elseif ( is_scalar( $output ) ) {
				$output = (string) $output;
			}
		}

		$metadata = array();
		if ( method_exists( $result, 'get_id' ) ) {
			$metadata['request_id'] = $result->get_id();
		}
		if ( method_exists( $result, 'getProviderMetadata' ) ) {
			$provider = $result->getProviderMetadata();
			if ( is_object( $provider ) && method_exists( $provider, 'get_id' ) ) {
				$metadata['provider'] = $provider->get_id();
			}
		}
		if ( method_exists( $result, 'getModelMetadata' ) ) {
			$model = $result->getModelMetadata();
			if ( is_object( $model ) && method_exists( $model, 'get_id' ) ) {
				$metadata['model'] = $model->get_id();
			}
		}
		if ( method_exists( $result, 'getTokenUsage' ) ) {
			$usage = $result->getTokenUsage();
			if ( is_object( $usage ) ) {
				$metadata['tokens'] = array(
					'input'    => method_exists( $usage, 'getPromptTokens' ) ? $usage->getPromptTokens() : null,
					'output'   => method_exists( $usage, 'getCompletionTokens' ) ? $usage->getCompletionTokens() : null,
					'total'    => method_exists( $usage, 'getTotalTokens' ) ? $usage->getTotalTokens() : null,
					'thinking' => method_exists( $usage, 'getThoughtTokens' ) ? $usage->getThoughtTokens() : null,
				);
			}
		}
		$cost = ReportedCost::fromAiResult( $result );
		if ( null !== $cost ) {
			$metadata['cost'] = $cost->jsonSerialize();
		}

		return TaskResult::fromOutput( $output, $metadata );
	}
}
