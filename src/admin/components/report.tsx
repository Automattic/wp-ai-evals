// @jsxRuntime classic
// @jsx createElement

import { Button, Notice, Spinner } from '@wordpress/components';
import { createElement, type RefObject } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import type {
	EvaluationResult,
	EvaluatorResult,
	RubricResult,
	RunReport,
	RunSession,
} from '../types';
import {
	formatDuration,
	formatNumber,
	formatPercent,
	outputText,
} from '../utils';
import {
	DataPanel,
	MetadataPill,
	StatusPill,
	SummaryStat,
	TokenUsage,
} from './ui';

interface RubricResultsProps {
	rubric?: RubricResult;
}

function RubricResults( { rubric }: RubricResultsProps ) {
	if ( ! rubric?.items.length ) {
		return null;
	}

	return (
		<div className="wp-ai-evals-rubric">
			<div className="wp-ai-evals-subheading">
				<h4>{ __( 'Rubric breakdown', 'wp-ai-evals' ) }</h4>
				<span>
					{ sprintf(
						/* translators: %s is the rubric aggregation method. */
						__( 'Aggregate: %s', 'wp-ai-evals' ),
						rubric.aggregation || 'weighted_mean'
					) }
				</span>
			</div>
			<div className="wp-ai-evals-rubric-grid">
				{ rubric.items.map( ( item ) => (
					<article key={ item.id }>
						<div>
							<strong>{ item.label || item.id }</strong>
							{ typeof item.passed === 'boolean' && (
								<StatusPill
									status={ item.passed ? 'passed' : 'failed' }
								/>
							) }
						</div>
						<p>{ item.criteria }</p>
						<div className="wp-ai-evals-rubric-score">
							<strong>{ formatPercent( item.score ) }</strong>
							<span>
								{ sprintf(
									/* translators: %s is a numeric rubric weight. */
									__( 'Weight %s', 'wp-ai-evals' ),
									String( item.weight )
								) }
								{ item.minimum_score !== null &&
									item.minimum_score !== undefined &&
									` · ${ sprintf(
										/* translators: %s is a minimum rubric score. */
										__( 'minimum %s', 'wp-ai-evals' ),
										formatPercent( item.minimum_score )
									) }` }
							</span>
						</div>
						<p className="wp-ai-evals-rubric-reason">
							{ item.reason }
						</p>
					</article>
				) ) }
			</div>
		</div>
	);
}

interface EvaluatorDetailsProps {
	evaluators?: EvaluatorResult[];
}

function EvaluatorDetails( { evaluators = [] }: EvaluatorDetailsProps ) {
	if ( evaluators.length === 0 ) {
		return null;
	}

	return (
		<section className="wp-ai-evals-evaluators">
			<h4>{ __( 'Evaluator results', 'wp-ai-evals' ) }</h4>
			{ evaluators.map( ( evaluator, index ) => {
				const metadata = evaluator.metadata ?? {};

				return (
					<article key={ `${ evaluator.name }-${ index }` }>
						<header>
							<div>
								<strong>{ evaluator.name }</strong>
								<code>{ evaluator.type }</code>
							</div>
							<div>
								<StatusPill
									status={
										evaluator.passed ? 'passed' : 'failed'
									}
								/>
								<strong>
									{ Number( evaluator.score ).toFixed( 3 ) }
								</strong>
							</div>
						</header>
						{ evaluator.reason && <p>{ evaluator.reason }</p> }
						<RubricResults rubric={ metadata.rubric } />
						{ ( metadata.tokens ||
							metadata.duration_ms !== undefined ) && (
							<div className="wp-ai-evals-inline-diagnostics">
								{ metadata.tokens && (
									<MetadataPill
										label={ __(
											'Judge provider',
											'wp-ai-evals'
										) }
										value={ metadata.provider }
									/>
								) }
								{ metadata.tokens && (
									<MetadataPill
										label={ __(
											'Judge model',
											'wp-ai-evals'
										) }
										value={ metadata.model }
									/>
								) }
								{ metadata.tokens && (
									<MetadataPill
										label={ __(
											'Judge tokens',
											'wp-ai-evals'
										) }
										value={ formatNumber(
											metadata.tokens.total
										) }
									/>
								) }
								<MetadataPill
									label={ __(
										'Evaluator time',
										'wp-ai-evals'
									) }
									value={ formatDuration(
										metadata.duration_ms
									) }
								/>
							</div>
						) }
					</article>
				);
			} ) }
		</section>
	);
}

interface CaseResultDetailsProps {
	result: EvaluationResult;
}

function CaseResultDetails( { result }: CaseResultDetailsProps ) {
	const taskMetadata = result.task_result?.metadata ?? {};
	const tools = Array.isArray( taskMetadata.tools ) ? taskMetadata.tools : [];
	const tokens = taskMetadata.tokens;

	return (
		<details className={ `wp-ai-evals-case-result is-${ result.status }` }>
			<summary>
				<div className="wp-ai-evals-case-identity">
					<StatusPill status={ result.status } />
					<span>
						<strong>{ result.label }</strong>
						<code>{ result.qualified_id }</code>
					</span>
				</div>
				<div className="wp-ai-evals-case-summary">
					<span>{ Number( result.score ).toFixed( 3 ) }</span>
					<span>{ formatDuration( result.duration_ms ) }</span>
					{ tokens && (
						<span>
							{ sprintf(
								/* translators: %s is a token count. */
								__( '%s tokens', 'wp-ai-evals' ),
								formatNumber( tokens.total )
							) }
						</span>
					) }
					{ tools.length > 0 && (
						<span>
							{ sprintf(
								/* translators: %d is a tool call count. */
								__( '%d tools', 'wp-ai-evals' ),
								tools.length
							) }
						</span>
					) }
				</div>
			</summary>

			<div className="wp-ai-evals-case-body">
				<div className="wp-ai-evals-inline-diagnostics">
					<MetadataPill
						label={ __( 'Task', 'wp-ai-evals' ) }
						value={ result.task_type }
					/>
					<MetadataPill
						label={ __( 'Iteration', 'wp-ai-evals' ) }
						value={ result.iteration }
					/>
					<MetadataPill
						label={ __( 'Provider', 'wp-ai-evals' ) }
						value={ taskMetadata.provider }
					/>
					<MetadataPill
						label={ __( 'Model', 'wp-ai-evals' ) }
						value={ taskMetadata.model }
					/>
					<MetadataPill
						label={ __( 'Request', 'wp-ai-evals' ) }
						value={ taskMetadata.request_id }
					/>
					<MetadataPill
						label={ __( 'Task time', 'wp-ai-evals' ) }
						value={
							taskMetadata.duration_ms !== undefined
								? formatDuration( taskMetadata.duration_ms )
								: ''
						}
					/>
				</div>

				{ result.error && (
					<Notice status="error" isDismissible={ false }>
						{ result.error }
					</Notice>
				) }

				{ tokens && (
					<section className="wp-ai-evals-diagnostic-section">
						<h4>{ __( 'Token usage', 'wp-ai-evals' ) }</h4>
						<TokenUsage tokens={ tokens } />
					</section>
				) }

				{ tools.length > 0 && (
					<section className="wp-ai-evals-diagnostic-section">
						<h4>{ __( 'Tools used', 'wp-ai-evals' ) }</h4>
						<div className="wp-ai-evals-tool-list">
							{ tools.map( ( tool, index ) => (
								<code
									key={ `${ outputText( tool ) }-${ index }` }
								>
									{ outputText( tool ) }
								</code>
							) ) }
						</div>
					</section>
				) }

				<div className="wp-ai-evals-data-grid">
					<DataPanel
						title={ __( 'Input', 'wp-ai-evals' ) }
						value={ result.input }
					/>
					<DataPanel
						title={ __( 'Expected', 'wp-ai-evals' ) }
						value={ result.expected }
					/>
					<DataPanel
						title={ __( 'Output', 'wp-ai-evals' ) }
						value={ result.task_result?.output }
						className="is-wide"
					/>
					<DataPanel
						title={ __( 'Task metadata', 'wp-ai-evals' ) }
						value={ taskMetadata }
					/>
					<DataPanel
						title={ __( 'Case metadata', 'wp-ai-evals' ) }
						value={ result.metadata }
					/>
				</div>

				<EvaluatorDetails evaluators={ result.evaluators } />
			</div>
		</details>
	);
}

interface ReportProps {
	report: RunReport | null;
	reportRef: RefObject< HTMLElement >;
	liveSession: RunSession | null;
	isRunning: boolean;
	onResume: () => void;
	isLoading: boolean;
}

export function Report( {
	report,
	reportRef,
	liveSession,
	isRunning,
	onResume,
	isLoading,
}: ReportProps ) {
	if ( isLoading ) {
		return (
			<section
				ref={ reportRef }
				className="wp-ai-evals-report is-loading"
			>
				<Spinner />
				<p>{ __( 'Loading run details…', 'wp-ai-evals' ) }</p>
			</section>
		);
	}

	if ( ! report?.summary ) {
		return null;
	}

	const { summary } = report;
	const diagnostics = summary.diagnostics ?? {};
	const tokens = diagnostics.tokens ?? {};
	const tools = diagnostics.tools ?? [];
	const completed = liveSession?.completed ?? summary.total;
	const total = liveSession?.total ?? summary.total;
	const progress = total > 0 ? ( completed / total ) * 100 : 0;
	let status = summary.failed === 0 ? 'passed' : 'failed';
	if ( liveSession ) {
		status = isRunning ? 'running' : 'paused';
	}

	let passTone = '';
	if ( ! liveSession && summary.failed === 0 ) {
		passTone = 'is-positive';
	} else if ( summary.failed > 0 ) {
		passTone = 'is-negative';
	}

	return (
		<section ref={ reportRef } className="wp-ai-evals-report">
			<div className="wp-ai-evals-section-heading">
				<div>
					<p className="wp-ai-evals-eyebrow">
						{ liveSession
							? __( 'Live run', 'wp-ai-evals' )
							: __( 'Run details', 'wp-ai-evals' ) }
					</p>
					<h2>
						{ sprintf(
							/* translators: %s is an evaluation run ID. */
							__( 'Run %s', 'wp-ai-evals' ),
							report.id
						) }
					</h2>
					<small>{ report.started_at }</small>
				</div>
				<div className="wp-ai-evals-report-status">
					<StatusPill status={ status } />
					{ liveSession && ! isRunning && (
						<Button variant="secondary" onClick={ onResume }>
							{ __( 'Resume run', 'wp-ai-evals' ) }
						</Button>
					) }
				</div>
			</div>

			{ liveSession && (
				<div className="wp-ai-evals-progress-wrap">
					<div className="wp-ai-evals-progress-label">
						<strong>
							{ sprintf(
								/* translators: 1: completed case count, 2: total case count. */
								__(
									'%1$d of %2$d cases complete',
									'wp-ai-evals'
								),
								completed,
								total
							) }
						</strong>
						<span>{ Math.round( progress ) }%</span>
					</div>
					<div
						className="wp-ai-evals-progress"
						role="progressbar"
						aria-valuemin={ 0 }
						aria-valuemax={ total }
						aria-valuenow={ completed }
					>
						<span style={ { width: `${ progress }%` } } />
					</div>
				</div>
			) }

			<div className="wp-ai-evals-summary-grid">
				<SummaryStat
					label={ __( 'Cases passed', 'wp-ai-evals' ) }
					value={ `${ summary.passed }/${ total }` }
					tone={ passTone }
				/>
				<SummaryStat
					label={ __( 'Aggregate score', 'wp-ai-evals' ) }
					value={ formatPercent( summary.score ) }
				/>
				<SummaryStat
					label={ __( 'Duration', 'wp-ai-evals' ) }
					value={ formatDuration( report.duration_ms ) }
				/>
				<SummaryStat
					label={ __( 'Total tokens', 'wp-ai-evals' ) }
					value={ formatNumber( tokens.total ) }
				/>
				<SummaryStat
					label={ __( 'Tools used', 'wp-ai-evals' ) }
					value={ formatNumber( tools.length ) }
				/>
			</div>

			{ report.results.length === 0 ? (
				<div className="wp-ai-evals-live-empty">
					{ isRunning && <Spinner /> }
					<p>
						{ isRunning
							? __(
									'The first case is running. Results will appear here as each case completes.',
									'wp-ai-evals'
							  )
							: __(
									'This run has no case results.',
									'wp-ai-evals'
							  ) }
					</p>
				</div>
			) : (
				<div className="wp-ai-evals-case-results">
					{ report.results.map( ( result ) => (
						<CaseResultDetails
							key={ `${ result.qualified_id }-${ result.iteration }` }
							result={ result }
						/>
					) ) }
					{ liveSession && isRunning && (
						<div className="wp-ai-evals-next-case">
							<Spinner />
							<span>
								{ __( 'Running next case…', 'wp-ai-evals' ) }
							</span>
						</div>
					) }
				</div>
			) }
		</section>
	);
}
