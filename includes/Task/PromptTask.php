<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Task;

use Closure;
use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\Exception\RuntimeException;
use Automattic\AiEvals\TaskResult;

final class PromptTask implements ModelTargetAwareTaskInterface
{
    /** @var string|Closure */
    private $prompt;

    private ?Closure $configure;
    private string $modality;

    /** @param string|callable $prompt */
    public function __construct($prompt, string $modality = 'text', ?callable $configure = null)
    {
        if (!is_string($prompt) && !is_callable($prompt)) {
            throw new InvalidArgumentException('A prompt task requires a prompt string or callable.');
        }

        $supported = ['text', 'image', 'speech', 'video', 'multimodal'];
        if (!in_array($modality, $supported, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported prompt task modality "%s".', $modality));
        }

        $this->prompt = is_callable($prompt) ? Closure::fromCallable($prompt) : $prompt;
        $this->configure = null !== $configure ? Closure::fromCallable($configure) : null;
        $this->modality = $modality;
    }

    /** @param string|callable $prompt */
    public static function text($prompt, ?callable $configure = null): self
    {
        return new self($prompt, 'text', $configure);
    }

    /** {@inheritDoc} */
    public function run($input, EvaluationContext $context): TaskResult
    {
        if (!function_exists('wp_ai_client_prompt')) {
            throw new RuntimeException('The WordPress AI Client is unavailable. WordPress 7.0 or newer is required.');
        }

        $prompt = $this->prompt instanceof Closure
            ? ($this->prompt)($input, $context)
            : $this->prompt;

        if (!is_string($prompt)) {
            throw new RuntimeException('The prompt callback must return a string.');
        }

        $builder = wp_ai_client_prompt($prompt);
        if (null !== $this->configure) {
            $configured = ($this->configure)($builder, $input, $context);
            if (null !== $configured) {
                $builder = $configured;
            }
        }

        if (null !== $context->getModelTarget()) {
            $builder = $context->getModelTarget()->apply($builder);
        }

        $supportMethod = 'is_supported_for_' . ('multimodal' === $this->modality ? 'text' : $this->modality) . '_generation';
        if (method_exists($builder, $supportMethod) && !$builder->{$supportMethod}()) {
            throw new RuntimeException(sprintf('No configured connector supports %s generation.', $this->modality));
        }

        $method = [
            'text' => 'generate_text_result',
            'image' => 'generate_image_result',
            'speech' => 'generate_speech_result',
            'video' => 'generate_video_result',
            'multimodal' => 'generate_result',
        ][$this->modality];

        $result = $builder->{$method}();
        if (function_exists('is_wp_error') && is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }

        return AiResultAdapter::adapt($result, $this->modality);
    }

    public function getType(): string
    {
        return 'prompt:' . $this->modality;
    }
}
