<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\Exception\RuntimeException;
use JsonSerializable;
use Throwable;
use WordPress\AiClient\AiClient;

final class ModelTarget implements JsonSerializable
{
    private string $providerId;
    private string $modelId;

    public function __construct(string $providerId, string $modelId)
    {
        $providerId = strtolower(trim($providerId));
        $modelId = trim($modelId);

        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]*$/', $providerId)) {
            throw new InvalidArgumentException(sprintf('Invalid AI provider ID "%s".', $providerId));
        }
        if ('' === $modelId || strlen($modelId) > 255 || 1 === preg_match('/[\x00-\x1F\x7F]/', $modelId)) {
            throw new InvalidArgumentException('An AI model target requires a valid model ID.');
        }

        $this->providerId = $providerId;
        $this->modelId = $modelId;
    }

    public static function fromString(string $target): self
    {
        $parts = explode(':', trim($target), 2);
        if (2 !== count($parts)) {
            throw new InvalidArgumentException(
                sprintf('Invalid model target "%s". Use the provider:model format.', $target)
            );
        }

        return new self($parts[0], $parts[1]);
    }

    /** @param array<string, mixed> $target */
    public static function fromArray(array $target): self
    {
        return new self(
            isset($target['provider']) ? (string) $target['provider'] : '',
            isset($target['model']) ? (string) $target['model'] : ''
        );
    }

    public function getProviderId(): string
    {
        return $this->providerId;
    }

    public function getModelId(): string
    {
        return $this->modelId;
    }

    public function getId(): string
    {
        return $this->providerId . ':' . $this->modelId;
    }

    /**
     * Applies this exact provider/model target to a WordPress AI Client prompt builder.
     *
     * @param object $builder
     * @return object
     */
    public function apply($builder)
    {
        if (!class_exists(AiClient::class)) {
            throw new RuntimeException('The WordPress PHP AI Client registry is unavailable.');
        }
        try {
            $registry = AiClient::defaultRegistry();
            if (!$registry->hasProvider($this->providerId)) {
                throw new RuntimeException(
                    sprintf('AI provider "%s" is not registered.', $this->providerId)
                );
            }
            if (!$registry->isProviderConfigured($this->providerId)) {
                throw new RuntimeException(
                    sprintf('AI provider "%s" is not configured.', $this->providerId)
                );
            }

            return $builder->using_model(
                $registry->getProviderModel($this->providerId, $this->modelId)
            );
        } catch (RuntimeException $error) {
            throw $error;
        } catch (Throwable $error) {
            throw new RuntimeException(
                sprintf('Model target "%s" is unavailable: %s', $this->getId(), $error->getMessage())
            );
        }
    }

    /** @param array<string, mixed> $metadata */
    public function matchesMetadata(array $metadata): bool
    {
        return isset($metadata['provider'], $metadata['model'])
            && $this->providerId === (string) $metadata['provider']
            && $this->modelId === (string) $metadata['model'];
    }

    /** @return array{id: string, provider: string, model: string} */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'provider' => $this->providerId,
            'model' => $this->modelId,
        ];
    }
}
