<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Cli;

use Automattic\AiEvals\Kernel;
use Automattic\AiEvals\ModelCatalog;
use Automattic\AiEvals\Report\JunitFormatter;
use Automattic\AiEvals\RunConfiguration;
use Automattic\AiEvals\Runner;
use Automattic\AiEvals\Selection;
use Automattic\AiEvals\Storage\HistoryStore;

/** Commands for discovering and running WordPress AI evaluations. */
final class Command {

	/**
	 * Lists models exposed by configured WordPress AI Client providers.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table or json. Default: table.
	 *
	 * @param list<string>         $args
	 * @param array<string, mixed> $assocArgs
	 */
	public function models( array $args, array $assoc_args ): void {
		Kernel::instance()->initialize();
		$catalog = ( new ModelCatalog() )->discover();
		$format  = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';

		if ( 'json' === $format ) {
			\WP_CLI::line( $this->json( $catalog ) );
			return;
		}

		$items = array_map(
			static fn( array $model ): array => array(
				'target'       => $model['target'],
				'provider'     => $model['provider_name'],
				'model'        => $model['name'],
				'capabilities' => implode( ',', $model['capabilities'] ),
			),
			isset( $catalog['models'] ) && is_array( $catalog['models'] ) ? $catalog['models'] : array()
		);
		\WP_CLI\Utils\format_items( 'table', $items, array( 'target', 'provider', 'model', 'capabilities' ) );

		foreach ( isset( $catalog['errors'] ) && is_array( $catalog['errors'] ) ? $catalog['errors'] : array() as $error ) {
			\WP_CLI::warning( (string) $error );
		}
	}

	/**
	 * Lists registered suites and cases.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table or json. Default: table.
	 *
	 * @subcommand list
	 *
	 * @param list<string>         $args
	 * @param array<string, mixed> $assocArgs
	 */
	public function list_( array $args, array $assoc_args ): void {
		Kernel::instance()->initialize();
		$items = array();

		foreach ( Kernel::instance()->get_registry()->all() as $suite ) {
			foreach ( $suite->get_cases() as $evaluation_case ) {
				$items[] = array(
					'suite'      => $suite->get_id(),
					'case'       => $evaluation_case->get_id(),
					'label'      => $evaluation_case->get_label(),
					'type'       => $evaluation_case->get_task()->get_type(),
					'tags'       => implode( ',', $evaluation_case->get_tags() ),
					'evaluators' => count( $evaluation_case->get_evaluators() ),
				);
			}
		}

		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
		if ( 'json' === $format ) {
			\WP_CLI::line( $this->json( $items ) );
			return;
		}

		\WP_CLI\Utils\format_items( 'table', $items, array( 'suite', 'case', 'label', 'type', 'tags', 'evaluators' ) );
	}

	/**
	 * Runs registered evaluation cases.
	 *
	 * ## OPTIONS
	 *
	 * [<suite>...]
	 * : Suite IDs to run.
	 *
	 * [--suite=<suites>]
	 * : Comma-separated suite IDs.
	 *
	 * [--case=<cases>]
	 * : Comma-separated case IDs or suite/case IDs.
	 *
	 * [--tag=<tags>]
	 * : Comma-separated tags. A case matching any tag is selected.
	 *
	 * [--repeat=<count>]
	 * : Number of times to run each selected case. Default: 1.
	 *
	 * [--model=<targets>]
	 * : Comma-separated exact provider:model targets. Multiple targets run as a comparison.
	 *
	 * [--judge-model=<target>]
	 * : Exact provider:model target for LLM judges. Kept independent from candidate targets.
	 *
	 * [--format=<format>]
	 * : table, json, or junit. Default: table.
	 *
	 * [--fail-under=<score>]
	 * : Fail if aggregate score is below this 0..1 value. By default, only failed or errored cases produce a non-zero exit.
	 *
	 * [--store]
	 * : Add the run to WP Admin history. Defaults to true; use --no-store to disable.
	 *
	 * @param list<string>         $args
	 * @param array<string, mixed> $assocArgs
	 */
	public function run( array $args, array $assoc_args ): void {
		Kernel::instance()->initialize();
		$suites = array_merge( $args, $this->split( $assoc_args['suite'] ?? '' ) );
		$cases  = $this->split( $assoc_args['case'] ?? '' );
		$tags   = $this->split( $assoc_args['tag'] ?? '' );
		$repeat = isset( $assoc_args['repeat'] ) ? (int) $assoc_args['repeat'] : 1;
		try {
			$configuration = RunConfiguration::fromStrings(
				$this->split( $assoc_args['model'] ?? '' ),
				isset( $assoc_args['judge-model'] ) ? (string) $assoc_args['judge-model'] : ''
			);
		} catch ( \Throwable $error ) {
			\WP_CLI::error( $error->getMessage() );
			return;
		}

		$report = ( new Runner() )->run(
			Kernel::instance()->get_registry(),
			new Selection( $suites, $cases, $tags, $repeat ),
			$configuration
		);

		if ( ! array_key_exists( 'store', $assoc_args ) || false !== $assoc_args['store'] ) {
			( new HistoryStore() )->save( $report );
		}

		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
		if ( 'json' === $format ) {
			\WP_CLI::line( $this->json( $report ) );
		} elseif ( 'junit' === $format ) {
			\WP_CLI::line( ( new JunitFormatter() )->format( $report ) );
		} else {
			$items = array();
			foreach ( $report->getResults() as $result ) {
				$items[] = array(
					'case'        => $result->getQualifiedId(),
					'model'       => null !== $result->get_model_target()
						? $result->get_model_target()->get_id()
						: 'default',
					'iteration'   => $result->get_iteration(),
					'status'      => $result->getStatus(),
					'score'       => number_format( $result->getScore(), 3 ),
					'duration_ms' => number_format( $result->getDurationMilliseconds(), 1 ),
					'error'       => $result->getError(),
				);
			}
			\WP_CLI\Utils\format_items(
				'table',
				$items,
				array( 'case', 'model', 'iteration', 'status', 'score', 'duration_ms', 'error' )
			);
			\WP_CLI::log(
				sprintf(
					'Run %s: %d/%d passed; aggregate score %.3f.',
					$report->get_id(),
					$report->getPassed(),
					$report->getTotal(),
					$report->getScore()
				)
			);

			if ( $configuration->isComparison() ) {
				$variants = array_map(
					static fn( array $variant ): array => array(
						'model'       => $variant['id'],
						'passed'      => sprintf( '%d/%d', $variant['passed'], $variant['total'] ),
						'score'       => number_format( (float) $variant['score'], 3 ),
						'task_tokens' => (int) $variant['diagnostics']['task_tokens']['total'],
						'duration_ms' => number_format( (float) $variant['duration_ms'], 1 ),
					),
					$report->getVariants()
				);
				\WP_CLI::log( 'Model comparison:' );
				\WP_CLI\Utils\format_items(
					'table',
					$variants,
					array( 'model', 'passed', 'score', 'task_tokens', 'duration_ms' )
				);
			}
		}

		$fail_under = isset( $assoc_args['fail-under'] ) ? (float) $assoc_args['fail-under'] : 0.0;
		if ( $report->getFailed() <= 0 && $report->getScore() >= $fail_under ) {
			return;
		}

		\WP_CLI::halt( 1 );
	}

	/** @param mixed $value @return list<string> */
	private function split( $value ): array {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
	}

	/** @param mixed $value */
	private function json( $value ): string {
		$json = wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		return false === $json ? '{}' : $json;
	}
}
