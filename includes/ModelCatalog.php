<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Throwable;
use WordPress\AiClient\AiClient;

final class ModelCatalog
{
    /** @return array<string, mixed> */
    public function discover(): array
    {
        $models = [];
        $providers = [];
        $errors = [];

        if (!class_exists(AiClient::class)) {
            return [
                'generated_at' => gmdate('c'),
                'models' => [],
                'providers' => [],
                'errors' => ['The WordPress PHP AI Client registry is unavailable.'],
                'default_judge_target' => null,
                'judge_model_preferences' => JudgeModelPreferences::targets(),
            ];
        }

        $registry = AiClient::defaultRegistry();
        foreach ($registry->getRegisteredProviderIds() as $providerId) {
            try {
                $className = $registry->getProviderClassName($providerId);
                $providerMetadata = $className::metadata();
                $configured = $registry->isProviderConfigured($providerId);
                $providerName = method_exists($providerMetadata, 'getName')
                    ? (string) $providerMetadata->getName()
                    : (string) $providerId;
                $providers[] = [
                    'id' => $providerId,
                    'name' => $providerName,
                    'configured' => $configured,
                ];

                if (!$configured) {
                    continue;
                }

                foreach ($className::modelMetadataDirectory()->listModelMetadata() as $metadata) {
                    if (!is_object($metadata) || !method_exists($metadata, 'getId')) {
                        continue;
                    }
                    $modelId = (string) $metadata->getId();
                    $capabilities = [];
                    if (method_exists($metadata, 'getSupportedCapabilities')) {
                        foreach ($metadata->getSupportedCapabilities() as $capability) {
                            if (is_object($capability) && isset($capability->value)) {
                                $capabilities[] = (string) $capability->value;
                            }
                        }
                    }

                    $target = new ModelTarget((string) $providerId, $modelId);
                    $models[] = [
                        'target' => $target->getId(),
                        'provider' => $providerId,
                        'provider_name' => $providerName,
                        'model' => $modelId,
                        'name' => method_exists($metadata, 'getName')
                            ? (string) $metadata->getName()
                            : $modelId,
                        'capabilities' => array_values(array_unique($capabilities)),
                    ];
                }
            } catch (Throwable $error) {
                $errors[] = sprintf('%s: %s', $providerId, $error->getMessage());
            }
        }

        usort($models, static function (array $left, array $right): int {
            return strcasecmp(
                (string) $left['provider_name'] . ' ' . (string) $left['name'],
                (string) $right['provider_name'] . ' ' . (string) $right['name']
            );
        });

        $catalog = [
            'generated_at' => gmdate('c'),
            'models' => $models,
            'providers' => $providers,
            'errors' => $errors,
            'default_judge_target' => JudgeModelPreferences::select($models),
            'judge_model_preferences' => JudgeModelPreferences::targets(),
        ];

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('wp_ai_evals_model_catalog', $catalog);
            if (is_array($filtered)) {
                return $filtered;
            }
        }

        return $catalog;
    }
}
