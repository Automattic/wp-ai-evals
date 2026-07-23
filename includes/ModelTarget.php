<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\Exception\RuntimeException;
use JsonSerializable;
use Throwable;
use WordPress\AiClient\AiClient;

final class ModelTarget implements JsonSerializable {

	private string $provider_id;
	private string $model_id;

	public function __construct( string $provider_id, string $model_id ) {
		$provider_id = strtolower( trim( $provider_id ) );
		$model_id    = trim( $model_id );

		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]*$/', $provider_id ) ) {
			throw new InvalidArgumentException( sprintf( 'Invalid AI provider ID "%s".', esc_html( $provider_id ) ) );
		}
		if ( '' === $model_id || strlen( $model_id ) > 255 || 1 === preg_match( '/[\x00-\x1F\x7F]/', $model_id ) ) {
			throw new InvalidArgumentException( 'An AI model target requires a valid model ID.' );
		}

		$this->provider_id = $provider_id;
		$this->model_id    = $model_id;
	}

	/** @return array{provider: string, model: string} */
	public function __serialize(): array {
		return array(
			'provider' => $this->provider_id,
			'model'    => $this->model_id,
		);
	}

	/**
	 * Restores both the current serialized shape and objects persisted before
	 * private properties were normalized to WordPress naming conventions.
	 *
	 * @param array<string, mixed> $data Serialized object data.
	 */
	public function __unserialize( array $data ): void {
		$class_prefix = "\0" . self::class . "\0";
		$provider_id  = $data['provider']
			?? $data['provider_id']
			?? $data['providerId']
			?? $data[ $class_prefix . 'provider_id' ]
			?? $data[ $class_prefix . 'providerId' ]
			?? '';
		$model_id     = $data['model']
			?? $data['model_id']
			?? $data['modelId']
			?? $data[ $class_prefix . 'model_id' ]
			?? $data[ $class_prefix . 'modelId' ]
			?? '';

		$target            = new self( (string) $provider_id, (string) $model_id );
		$this->provider_id = $target->provider_id;
		$this->model_id    = $target->model_id;
	}

	public static function fromString( string $target ): self {
		$parts = explode( ':', trim( $target ), 2 );
		if ( 2 !== count( $parts ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Invalid model target "%s". Use the provider:model format.', esc_html( $target ) )
			);
		}

		return new self( $parts[0], $parts[1] );
	}

	/** @param array<string, mixed> $target */
	public static function fromArray( array $target ): self {
		return new self(
			isset( $target['provider'] ) ? (string) $target['provider'] : '',
			isset( $target['model'] ) ? (string) $target['model'] : ''
		);
	}

	public function getProviderId(): string {
		return $this->provider_id;
	}

	public function getModelId(): string {
		return $this->model_id;
	}

	public function get_id(): string {
		return $this->provider_id . ':' . $this->model_id;
	}

	/**
	 * Applies this exact provider/model target to a WordPress AI Client prompt builder.
	 *
	 * @param object $builder
	 * @return object
	 */
	public function apply( $builder ) {
		if ( ! class_exists( AiClient::class ) ) {
			throw new RuntimeException( 'The WordPress PHP AI Client registry is unavailable.' );
		}
		try {
			$registry = AiClient::defaultRegistry();
			if ( ! $registry->hasProvider( $this->provider_id ) ) {
				throw new RuntimeException(
					sprintf( 'AI provider "%s" is not registered.', $this->provider_id )
				);
			}
			if ( ! $registry->isProviderConfigured( $this->provider_id ) ) {
				throw new RuntimeException(
					sprintf( 'AI provider "%s" is not configured.', $this->provider_id )
				);
			}

			return $builder->using_model(
				$registry->getProviderModel( $this->provider_id, $this->model_id )
			);
		} catch ( RuntimeException $error ) {
			throw $error;
		} catch ( Throwable $error ) {
			throw new RuntimeException(
				sprintf(
					'Model target "%s" is unavailable: %s',
					esc_html( $this->get_id() ),
					esc_html( $error->getMessage() )
				)
			);
		}
	}

	/** @param array<string, mixed> $metadata */
	public function matchesMetadata( array $metadata ): bool {
		return isset( $metadata['provider'], $metadata['model'] )
			&& $this->provider_id === (string) $metadata['provider']
			&& $this->model_id === (string) $metadata['model'];
	}

	/** @return array{id: string, provider: string, model: string} */
	public function jsonSerialize(): array {
		return array(
			'id'       => $this->get_id(),
			'provider' => $this->provider_id,
			'model'    => $this->model_id,
		);
	}
}
