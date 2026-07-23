<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\JudgeModelPreferences;
use Automattic\AiEvals\ModelTarget;
use Automattic\AiEvals\RunConfiguration;
use PHPUnit\Framework\TestCase;

final class ModelTargetTest extends TestCase
{
    public function testParsesAndSerializesExactTarget(): void
    {
        $target = ModelTarget::fromString('openai:gpt-5.4');

        self::assertSame('openai', $target->getProviderId());
        self::assertSame('gpt-5.4', $target->getModelId());
        self::assertSame('openai:gpt-5.4', $target->getId());
        self::assertTrue($target->matchesMetadata(['provider' => 'openai', 'model' => 'gpt-5.4']));
        self::assertFalse($target->matchesMetadata(['provider' => 'openai', 'model' => 'gpt-5-mini']));
    }

    public function testRequiresProviderModelFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ModelTarget::fromString('gpt-5.4');
    }

    public function testRunConfigurationDeduplicatesTargetsAndKeepsJudgeSeparate(): void
    {
        $configuration = RunConfiguration::fromStrings(
            ['openai:gpt-5.4', 'openai:gpt-5.4', 'anthropic:claude-sonnet-4-6'],
            'google:gemini-3.1-pro-preview'
        );

        self::assertTrue($configuration->isComparison());
        self::assertCount(2, $configuration->getModelTargets());
        self::assertSame(
            'google:gemini-3.1-pro-preview',
            $configuration->getJudgeModelTarget()->getId()
        );
    }

    public function testRunConfigurationRoundTripsThroughItsSerializedArray(): void
    {
        $original = RunConfiguration::fromStrings(
            ['openai:gpt-5.4', 'anthropic:claude-sonnet-4-6'],
            'google:gemini-3.1-pro-preview'
        );

        $serialized = $original->jsonSerialize();
        $restored = RunConfiguration::fromArray($serialized);

        self::assertIsArray($serialized['model_targets'][0]);
        self::assertIsArray($serialized['judge_model_target']);
        self::assertSame(
            ['openai:gpt-5.4', 'anthropic:claude-sonnet-4-6'],
            array_map(
                static fn(ModelTarget $target): string => $target->getId(),
                $restored->getModelTargets()
            )
        );
        self::assertSame(
            'google:gemini-3.1-pro-preview',
            $restored->getJudgeModelTarget()->getId()
        );
        self::assertTrue($restored->isComparison());
    }

    public function testSelectsTheFirstAvailablePreferredJudgeAcrossProviders(): void
    {
        $selected = JudgeModelPreferences::select([
            ['target' => 'openai:gpt-5.4'],
            ['target' => 'anthropic:claude-sonnet-4-6'],
        ]);

        self::assertSame('anthropic:claude-sonnet-4-6', $selected);
        self::assertSame(
            ['claude-sonnet-4-6', 'gemini-3.1-pro-preview', 'gpt-5.4'],
            JudgeModelPreferences::modelIds()
        );
    }
}
