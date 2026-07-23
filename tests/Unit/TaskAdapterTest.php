<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\Exception\RuntimeException;
use Automattic\AiEvals\Suite;
use Automattic\AiEvals\Task\AbilityTask;
use Automattic\AiEvals\Task\PromptTask;
use PHPUnit\Framework\TestCase;

final class TaskAdapterTest extends TestCase {

	protected function setUp(): void {
		wp_ai_evals_test_reset_state();
	}

	public function testPromptTaskBuildsAPromptFromInputAndRunsConfiguration(): void {
		$GLOBALS['wp_ai_evals_test_judge_response'] = 'Hello Dolly';
		$task                                       = PromptTask::text(
			static fn( array $input ): string => 'Answer about ' . $input['topic'],
			static fn( $builder ) => $builder->using_temperature( 0.2 )
		);

		$result = $task->run( array( 'topic' => 'Dolly' ), $this->context() );

		self::assertSame( 'prompt:text', $task->get_type() );
		self::assertSame( 'Answer about Dolly', $GLOBALS['wp_ai_evals_test_last_prompt'] );
		self::assertSame( 'Hello Dolly', $result->getOutput() );
		self::assertSame( 'using_temperature', $GLOBALS['wp_ai_evals_test_builder_calls'][0]['name'] );
		self::assertSame( array( 0.2 ), $GLOBALS['wp_ai_evals_test_builder_calls'][0]['arguments'] );
	}

	public function testPromptTaskRejectsANonStringPromptCallbackResult(): void {
		$task = PromptTask::text( static fn(): array => array( 'not a prompt' ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'prompt callback must return a string' );

		$task->run( null, $this->context() );
	}

	public function testAbilityTaskExecutesARegisteredAbilityAndRecordsItsName(): void {
		$GLOBALS['wp_ai_evals_test_abilities']['hello-dolly/get-fact'] = new class() {
			/** @param array<string, string> $input @return array<string, string> */
			public function execute( array $input ): array {
				return array( 'answer' => 'Fact about ' . $input['topic'] );
			}
		};
		$task = new AbilityTask( 'hello-dolly/get-fact' );

		$result = $task->run( array( 'topic' => 'birth' ), $this->context() );

		self::assertSame( 'ability', $task->get_type() );
		self::assertSame( array( 'answer' => 'Fact about birth' ), $result->getOutput() );
		self::assertSame( 'hello-dolly/get-fact', $result->get_metadata()['ability'] );
	}

	public function testAbilityTaskRejectsAnUnknownAbility(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'is not registered' );

		( new AbilityTask( 'hello-dolly/missing' ) )->run( array(), $this->context() );
	}

	private function context(): EvaluationContext {
		return new EvaluationContext( Suite::make( 'suite' ), EvaluationCase::make( 'case' ), 1 );
	}
}
