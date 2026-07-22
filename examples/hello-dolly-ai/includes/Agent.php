<?php

declare(strict_types=1);

namespace HelloDollyAI;

use Throwable;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

final class Agent
{
    private const MAX_TOOL_STEPS = 3;
    private const MAX_HISTORY_MESSAGES = 10;

    /**
     * Anthropic 1.0.3 cannot round-trip the signed thinking blocks emitted by
     * Claude Sonnet 5 during tool use. A model preference remains portable —
     * WordPress falls back to any compatible model or provider when absent.
     */
    private const TOOL_MODEL_PREFERENCES = ['claude-sonnet-4-6'];

    private const SYSTEM_INSTRUCTION = <<<'PROMPT'
You are Hello Dolly, a warm, precise learning guide focused only on Dolly Parton's life, career, songs, business work, and philanthropy.

Rules:
- Ground factual claims in the supplied WordPress abilities. Use an ability whenever the user asks a factual question that it can answer.
- Treat the ability results as the only factual source for your answer. Do not supplement them with facts from memory.
- Never invent dates, awards, quotations, family details, or song facts. If the curated abilities do not contain an answer, say so plainly.
- Do not provide non-user-supplied song lyrics. You may summarize a song's themes and discuss its context.
- If asked about a different person or unrelated subject, briefly steer the conversation back to Dolly Parton.
- Answer only what was asked. Do not add a follow-up question, emoji, or unrelated biographical detail.
- Keep answers conversational and concise: normally one or two short paragraphs.
- When ability output includes a source, use it to ground the answer. The interface will display source links separately, so do not print raw URLs.
PROMPT;

    /**
     * @param list<array{role?: string, content?: string}> $history
     * @return array<string, mixed>|\WP_Error
     */
    public function respond(string $message, array $history = [])
    {
        if (!function_exists('wp_ai_client_prompt') || !class_exists('WP_AI_Client_Ability_Function_Resolver')) {
            return new \WP_Error(
                'hello_dolly_ai_unavailable',
                __('The WordPress 7.0 AI Client is unavailable.', 'hello-dolly-ai'),
                ['status' => 503]
            );
        }

        $historyMessages = $this->historyMessages($history);
        $userMessage = new UserMessage([new MessagePart($message)]);
        $conversation = array_merge($historyMessages, [$userMessage]);
        $resolver = new \WP_AI_Client_Ability_Function_Resolver(...Abilities::names());
        $tools = [];
        $sources = [];
        $usage = ['input' => 0, 'output' => 0, 'total' => 0, 'thinking' => 0];

        $builder = wp_ai_client_prompt($message)
            ->using_system_instruction(self::SYSTEM_INSTRUCTION)
            ->using_model_preference(...self::TOOL_MODEL_PREFERENCES)
            ->using_abilities(...Abilities::names());

        if ([] !== $historyMessages) {
            $builder = $builder->with_history(...$historyMessages);
        }

        if (!$builder->is_supported_for_text_generation()) {
            return new \WP_Error(
                'hello_dolly_ai_no_connector',
                __('No configured AI connector supports text generation with WordPress Ability function calls.', 'hello-dolly-ai'),
                ['status' => 503]
            );
        }

        for ($step = 0; $step <= self::MAX_TOOL_STEPS; ++$step) {
            $result = $builder->generate_text_result();
            if (is_wp_error($result)) {
                return $result;
            }

            $this->addUsage($usage, $result);
            $assistantMessage = $result->toMessage();

            if (!$resolver->has_ability_calls($assistantMessage)) {
                try {
                    return [
                        'answer' => $result->toText(),
                        'provider' => $result->getProviderMetadata()->getId(),
                        'model' => $result->getModelMetadata()->getId(),
                        'tokens' => $usage,
                        'tools' => array_values(array_unique($tools)),
                        'sources' => array_values($sources),
                    ];
                } catch (Throwable $error) {
                    return new \WP_Error(
                        'hello_dolly_ai_empty_response',
                        __('The AI connector returned no readable answer.', 'hello-dolly-ai'),
                        ['status' => 502]
                    );
                }
            }

            if (self::MAX_TOOL_STEPS === $step) {
                break;
            }

            $this->collectToolNames($assistantMessage, $tools);
            $functionResponses = $resolver->execute_abilities($assistantMessage);
            $this->collectSources($functionResponses, $sources);
            $conversation[] = $assistantMessage;

            $builder = wp_ai_client_prompt()
                ->with_history(...$conversation)
                ->with_message_parts(...$functionResponses->getParts())
                ->using_system_instruction(self::SYSTEM_INSTRUCTION)
                ->using_model_preference(...self::TOOL_MODEL_PREFERENCES)
                ->using_abilities(...Abilities::names());

            $conversation[] = $functionResponses;
        }

        return new \WP_Error(
            'hello_dolly_ai_step_limit',
            __('The chat agent reached its tool-call limit before producing an answer.', 'hello-dolly-ai'),
            ['status' => 502]
        );
    }

    /**
     * @param list<array{role?: string, content?: string}> $history
     * @return list<Message>
     */
    private function historyMessages(array $history): array
    {
        $messages = [];
        $history = array_slice($history, -self::MAX_HISTORY_MESSAGES);

        foreach ($history as $item) {
            if (!is_array($item)) {
                continue;
            }
            $role = isset($item['role']) ? (string) $item['role'] : '';
            $content = isset($item['content']) ? trim((string) $item['content']) : '';
            if ('' === $content || !in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $part = new MessagePart($content);
            $messages[] = 'user' === $role ? new UserMessage([$part]) : new ModelMessage([$part]);
        }

        return $messages;
    }

    /** @param array<string, int> $usage @param object $result */
    private function addUsage(array &$usage, $result): void
    {
        $tokens = $result->getTokenUsage();
        $usage['input'] += $tokens->getPromptTokens();
        $usage['output'] += $tokens->getCompletionTokens();
        $usage['total'] += $tokens->getTotalTokens();
        $usage['thinking'] += $tokens->getThoughtTokens() ?? 0;
    }

    /** @param list<string> $tools */
    private function collectToolNames(Message $message, array &$tools): void
    {
        foreach ($message->getParts() as $part) {
            $call = $part->getFunctionCall();
            if (null === $call || null === $call->getName()) {
                continue;
            }
            $tools[] = \WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name($call->getName());
        }
    }

    /** @param array<string, array{url: string, label: string}> $sources */
    private function collectSources(Message $message, array &$sources): void
    {
        foreach ($message->getParts() as $part) {
            $response = $part->getFunctionResponse();
            if (!$response instanceof FunctionResponse) {
                continue;
            }

            $this->findSources($response->getResponse(), $sources);
        }
    }

    /** @param mixed $value @param array<string, array{url: string, label: string}> $sources */
    private function findSources($value, array &$sources): void
    {
        if (!is_array($value)) {
            return;
        }

        if (isset($value['source']) && is_string($value['source'])) {
            $url = $value['source'];
            $sources[$url] = [
                'url' => $url,
                'label' => isset($value['source_label']) ? (string) $value['source_label'] : $url,
            ];
        }

        foreach ($value as $child) {
            if (is_array($child)) {
                $this->findSources($child, $sources);
            }
        }
    }
}
