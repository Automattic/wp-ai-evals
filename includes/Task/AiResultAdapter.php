<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Task;

use Automattic\AiEvals\ReportedCost;
use Automattic\AiEvals\TaskResult;

final class AiResultAdapter
{
    /** @param object $result */
    public static function adapt($result, string $modality): TaskResult
    {
        $output = $result;

        if ('text' === $modality && method_exists($result, 'toText')) {
            $output = $result->toText();
        } elseif (in_array($modality, ['image', 'speech', 'video'], true) && method_exists($result, 'toFile')) {
            $output = $result->toFile();
        } elseif (method_exists($result, 'toMessage')) {
            $output = $result->toMessage();
        }

        $metadata = [];
        if (method_exists($result, 'getId')) {
            $metadata['request_id'] = $result->getId();
        }
        if (method_exists($result, 'getProviderMetadata')) {
            $provider = $result->getProviderMetadata();
            if (is_object($provider) && method_exists($provider, 'getId')) {
                $metadata['provider'] = $provider->getId();
            }
        }
        if (method_exists($result, 'getModelMetadata')) {
            $model = $result->getModelMetadata();
            if (is_object($model) && method_exists($model, 'getId')) {
                $metadata['model'] = $model->getId();
            }
        }
        if (method_exists($result, 'getTokenUsage')) {
            $usage = $result->getTokenUsage();
            if (is_object($usage)) {
                $metadata['tokens'] = [
                    'input' => method_exists($usage, 'getPromptTokens') ? $usage->getPromptTokens() : null,
                    'output' => method_exists($usage, 'getCompletionTokens') ? $usage->getCompletionTokens() : null,
                    'total' => method_exists($usage, 'getTotalTokens') ? $usage->getTotalTokens() : null,
                    'thinking' => method_exists($usage, 'getThoughtTokens') ? $usage->getThoughtTokens() : null,
                ];
            }
        }
        $cost = ReportedCost::fromAiResult($result);
        if (null !== $cost) {
            $metadata['cost'] = $cost->jsonSerialize();
        }

        return TaskResult::fromOutput($output, $metadata);
    }
}
