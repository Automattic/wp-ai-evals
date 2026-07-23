<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\JudgeModelPreferences;
use Automattic\AiEvals\ModelTarget;
use Automattic\AiEvals\RunConfiguration;
use PHPUnit\Framework\TestCase;

final class ModelTargetTest extends TestCase {

	public function testParsesAndSerializesExactTarget(): void {
		$target = ModelTarget::fromString( 'openai:gpt-5.4' );

		self::assertSame( 'openai', $target->getProviderId() );
		self::assertSame( 'gpt-5.4', $target->getModelId() );
		self::assertSame( 'openai:gpt-5.4', $target->get_id() );
		self::assertTrue(
			$target->matchesMetadata(
				array(
					'provider' => 'openai',
					'model'    => 'gpt-5.4',
				)
			)
		);
		self::assertFalse(
			$target->matchesMetadata(
				array(
					'provider' => 'openai',
					'model'    => 'gpt-5-mini',
				)
			)
		);
	}

	public function testRequiresProviderModelFormat(): void {
		$this->expectException( InvalidArgumentException::class );
		ModelTarget::fromString( 'gpt-5.4' );
	}

	public function testRoundTripsThroughPhpSerialization(): void {
		$restored = unserialize( serialize( ModelTarget::fromString( 'openai:gpt-5.4' ) ) );

		self::assertInstanceOf( ModelTarget::class, $restored );
		self::assertSame( 'openai:gpt-5.4', $restored->get_id() );
	}

	public function testRestoresLegacySerializedPrivateProperties(): void {
		$class_name     = ModelTarget::class;
		$provider_key   = "\0{$class_name}\0providerId";
		$model_key      = "\0{$class_name}\0modelId";
		$legacy_payload = sprintf(
			'O:%d:"%s":2:{s:%d:"%s";s:6:"openai";s:%d:"%s";s:7:"gpt-5.4";}',
			strlen( $class_name ),
			$class_name,
			strlen( $provider_key ),
			$provider_key,
			strlen( $model_key ),
			$model_key
		);

		$restored = unserialize( $legacy_payload );

		self::assertInstanceOf( ModelTarget::class, $restored );
		self::assertSame( 'openai:gpt-5.4', $restored->get_id() );
	}

	public function testRunConfigurationDeduplicatesTargetsAndKeepsJudgeSeparate(): void {
		$configuration = RunConfiguration::fromStrings(
			array( 'openai:gpt-5.4', 'openai:gpt-5.4', 'anthropic:claude-sonnet-4-6' ),
			'google:gemini-3.1-pro-preview'
		);

		self::assertTrue( $configuration->isComparison() );
		self::assertCount( 2, $configuration->getModelTargets() );
		self::assertSame(
			'google:gemini-3.1-pro-preview',
			$configuration->get_judge_model_target()->get_id()
		);
	}

	public function testRunConfigurationRoundTripsThroughItsSerializedArray(): void {
		$original = RunConfiguration::fromStrings(
			array( 'openai:gpt-5.4', 'anthropic:claude-sonnet-4-6' ),
			'google:gemini-3.1-pro-preview'
		);

		$serialized = $original->jsonSerialize();
		$restored   = RunConfiguration::fromArray( $serialized );

		self::assertIsArray( $serialized['model_targets'][0] );
		self::assertIsArray( $serialized['judge_model_target'] );
		self::assertSame(
			array( 'openai:gpt-5.4', 'anthropic:claude-sonnet-4-6' ),
			array_map(
				static fn( ModelTarget $target ): string => $target->get_id(),
				$restored->getModelTargets()
			)
		);
		self::assertSame(
			'google:gemini-3.1-pro-preview',
			$restored->get_judge_model_target()->get_id()
		);
		self::assertTrue( $restored->isComparison() );
	}

	public function testSelectsTheFirstAvailablePreferredJudgeAcrossProviders(): void {
		$selected = JudgeModelPreferences::select(
			array(
				array( 'target' => 'openai:gpt-5.4' ),
				array( 'target' => 'anthropic:claude-sonnet-4-6' ),
			)
		);

		self::assertSame( 'anthropic:claude-sonnet-4-6', $selected );
		self::assertSame(
			array( 'claude-sonnet-4-6', 'gemini-3.1-pro-preview', 'gpt-5.4' ),
			JudgeModelPreferences::model_ids()
		);
	}
}
