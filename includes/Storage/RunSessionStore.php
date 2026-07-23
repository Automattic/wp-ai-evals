<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Storage;

use Automattic\AiEvals\CaseResult;
use Automattic\AiEvals\Exception\RuntimeException;
use Automattic\AiEvals\Registry;
use Automattic\AiEvals\RunConfiguration;
use Automattic\AiEvals\RunReport;
use Automattic\AiEvals\Runner;
use Automattic\AiEvals\Selection;

final class RunSessionStore {

	private const TRANSIENT_PREFIX = 'wp_ai_evals_session_';
	private int $ttl;

	public function __construct( int $ttl = 3600 ) {
		if ( function_exists( 'apply_filters' ) ) {
			$ttl = (int) apply_filters( 'wp_ai_evals_run_session_ttl', $ttl );
		}

		$this->ttl = max( 60, $ttl );
	}

	/** @return array<string, mixed> */
	public function start(
		Registry $registry,
		Selection $selection,
		Runner $runner,
		?RunConfiguration $configuration = null
	): array {
		$this->assert_available();
		$configuration = $configuration ?? new RunConfiguration();
		$queue         = array();
		$repetitions   = $selection->get_repetitions();

		foreach ( $registry->all() as $suite ) {
			foreach ( $suite->get_cases() as $evaluation_case ) {
				if ( ! $selection->matches( $suite, $evaluation_case ) ) {
					continue;
				}

				foreach ( $runner->targets_for_case( $evaluation_case, $configuration ) as $model_target ) {
					for ( $iteration = 1; $iteration <= $repetitions; ++$iteration ) {
						$queue[] = array(
							'suite'        => $suite->get_id(),
							'case'         => $evaluation_case->get_id(),
							'iteration'    => $iteration,
							'model_target' => null !== $model_target ? $model_target->jsonSerialize() : null,
						);
					}
				}
			}
		}

		$session = array(
			'id'                => $runner->make_run_id(),
			'started_at'        => gmdate( 'c' ),
			'started_microtime' => microtime( true ),
			'total'             => count( $queue ),
			'queue'             => $queue,
			'results'           => array(),
			'configuration'     => $configuration->jsonSerialize(),
		);

		$this->write( $session );

		return $this->normalize( $session, false );
	}

	/** @return array<string, mixed>|null */
	public function status( string $run_id ): ?array {
		$session = $this->read( $run_id );

		return null === $session ? null : $this->normalize( $session, false );
	}

	/**
	 * Advances a live run by one case variant.
	 *
	 * This performs an unlocked read-modify-write on the session transient. The
	 * Admin app awaits each `/next` request before issuing the following one, so
	 * calls are serialized in practice. Concurrent advances of the same run (for
	 * example, the same session driven from two browser tabs) can race and
	 * double-process a queue item; callers that cannot guarantee serialization
	 * should add their own locking.
	 *
	 * @return array{session: array<string, mixed>, report: \Automattic\AiEvals\RunReport|null}
	 */
	public function advance( string $run_id, Registry $registry, Runner $runner ): array {
		$session = $this->read( $run_id );
		if ( null === $session ) {
			throw new RuntimeException(
				sprintf( 'Unknown or expired evaluation run session "%s".', esc_html( $run_id ) )
			);
		}

		if ( array() !== $session['queue'] ) {
			$next    = array_shift( $session['queue'] );
			$suite   = $registry->get( (string) $next['suite'] );
			$cases   = $suite->get_cases();
			$case_id = (string) $next['case'];
			if ( ! isset( $cases[ $case_id ] ) ) {
				throw new RuntimeException(
					sprintf(
						'Unknown evaluation case "%s/%s".',
						esc_html( $suite->get_id() ),
						esc_html( $case_id )
					)
				);
			}

			$configuration        = $this->configuration( $session );
			$model_target         = isset( $next['model_target'] ) && is_array( $next['model_target'] )
				? \Automattic\AiEvals\ModelTarget::fromArray( $next['model_target'] )
				: null;
			$session['results'][] = $runner->run_case(
				$suite,
				$cases[ $case_id ],
				(int) $next['iteration'],
				$model_target,
				$configuration
			);
		}

		if ( array() !== $session['queue'] ) {
			$this->write( $session );

			return array(
				'session' => $this->normalize( $session, false ),
				'report'  => null,
			);
		}

		$report = $this->report( $session );
		( new HistoryStore() )->save( $report );
		delete_transient( $this->key( $run_id ) );

		return array(
			'session' => $this->normalize( $session, true ),
			'report'  => $report,
		);
	}

	/** @param array<string, mixed> $session */
	private function write( array $session ): void {
		set_transient( $this->key( (string) $session['id'] ), $session, $this->ttl );
	}

	/** @return array<string, mixed>|null */
	private function read( string $run_id ): ?array {
		$this->assert_available();
		if ( 1 !== preg_match( '/^[a-zA-Z0-9._-]+$/', $run_id ) ) {
			return null;
		}

		$session = get_transient( $this->key( $run_id ) );

		return is_array( $session ) ? $session : null;
	}

	/** @param array<string, mixed> $session @return array<string, mixed> */
	private function normalize( array $session, bool $complete ): array {
		$report = $this->report( $session );

		return array(
			'id'            => $session['id'],
			'started_at'    => $session['started_at'],
			'total'         => $session['total'],
			'completed'     => count( $session['results'] ),
			'remaining'     => count( $session['queue'] ),
			'complete'      => $complete,
			'duration_ms'   => $report->getDurationMilliseconds(),
			'configuration' => $report->getConfiguration()->jsonSerialize(),
			'summary'       => $report->jsonSerialize()['summary'],
			'variants'      => $report->getVariants(),
			'results'       => array_map(
				static fn( CaseResult $result ): array => $result->jsonSerialize(),
				$session['results']
			),
		);
	}

	/** @param array<string, mixed> $session */
	private function report( array $session ): RunReport {
		return new RunReport(
			(string) $session['id'],
			(string) $session['started_at'],
			( microtime( true ) - (float) $session['started_microtime'] ) * 1000,
			$session['results'],
			$this->configuration( $session )
		);
	}

	/** @param array<string, mixed> $session */
	private function configuration( array $session ): RunConfiguration {
		return isset( $session['configuration'] ) && is_array( $session['configuration'] )
			? RunConfiguration::fromArray( $session['configuration'] )
			: new RunConfiguration();
	}

	private function key( string $run_id ): string {
		return self::TRANSIENT_PREFIX . $run_id;
	}

	private function assert_available(): void {
		if ( ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
			throw new RuntimeException( 'WordPress transient storage is unavailable for live evaluation runs.' );
		}
	}
}
