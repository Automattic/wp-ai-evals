<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Throwable;
use WordPress\AiClient\AiClient;

final class ModelCatalog {

	/** @return array<string, mixed> */
	public function discover(): array {
		$models    = array();
		$providers = array();
		$errors    = array();

		if ( ! class_exists( AiClient::class ) ) {
			return array(
				'generated_at'            => gmdate( 'c' ),
				'models'                  => array(),
				'providers'               => array(),
				'errors'                  => array( 'The WordPress PHP AI Client registry is unavailable.' ),
				'default_judge_target'    => null,
				'judge_model_preferences' => JudgeModelPreferences::targets(),
			);
		}

		$registry = AiClient::defaultRegistry();
		foreach ( $registry->getRegisteredProviderIds() as $provider_id ) {
			try {
				$class_name        = $registry->getProviderClassName( $provider_id );
				$provider_metadata = $class_name::metadata();
				$configured        = $registry->isProviderConfigured( $provider_id );
				$provider_name     = method_exists( $provider_metadata, 'get_name' )
					? (string) $provider_metadata->get_name()
					: (string) $provider_id;
				$providers[]       = array(
					'id'         => $provider_id,
					'name'       => $provider_name,
					'configured' => $configured,
				);

				if ( ! $configured ) {
					continue;
				}

				foreach ( $class_name::modelMetadataDirectory()->listModelMetadata() as $metadata ) {
					if ( ! is_object( $metadata ) || ! method_exists( $metadata, 'get_id' ) ) {
						continue;
					}
					$model_id     = (string) $metadata->get_id();
					$capabilities = array();
					if ( method_exists( $metadata, 'getSupportedCapabilities' ) ) {
						foreach ( $metadata->getSupportedCapabilities() as $capability ) {
							if ( ! is_object( $capability ) || ! isset( $capability->value ) ) {
								continue;
							}

							$capabilities[] = (string) $capability->value;
						}
					}

					$target   = new ModelTarget( (string) $provider_id, $model_id );
					$models[] = array(
						'target'        => $target->get_id(),
						'provider'      => $provider_id,
						'provider_name' => $provider_name,
						'model'         => $model_id,
						'name'          => method_exists( $metadata, 'get_name' )
							? (string) $metadata->get_name()
							: $model_id,
						'capabilities'  => array_values( array_unique( $capabilities ) ),
					);
				}
			} catch ( Throwable $error ) {
				$errors[] = sprintf( '%s: %s', $provider_id, $error->getMessage() );
			}
		}

		usort(
			$models,
			static function ( array $left, array $right ): int {
				return strcasecmp(
					(string) $left['provider_name'] . ' ' . (string) $left['name'],
					(string) $right['provider_name'] . ' ' . (string) $right['name']
				);
			}
		);

		$catalog = array(
			'generated_at'            => gmdate( 'c' ),
			'models'                  => $models,
			'providers'               => $providers,
			'errors'                  => $errors,
			'default_judge_target'    => JudgeModelPreferences::select( $models ),
			'judge_model_preferences' => JudgeModelPreferences::targets(),
		);

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wp_ai_evals_model_catalog', $catalog );
			if ( is_array( $filtered ) ) {
				return $filtered;
			}
		}

		return $catalog;
	}
}
