// @jsxRuntime classic
// @jsx createElement

import { Button, Notice, Spinner } from '@wordpress/components';
import { createElement, type RefObject } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import type {
	EvaluationResult,
	EvaluatorResult,
	ReportedCost,
	RubricResult,
	RunReport,
	RunSession,
	RunVariant,
} from '../types';
import {
	formatCost,
	formatCosts,
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
							metadata.cost ||
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
								{ metadata.cost && (
									<MetadataPill
										label={ __(
											'Reported cost',
											'wp-ai-evals'
										) }
										value={ formatCost( metadata.cost ) }
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
	grouped?: boolean;
	comparisonBenchmarks?: CaseComparisonBenchmarks;
	showCost?: boolean;
}

interface CaseComparisonBenchmarks {
	hasComparison: boolean;
	score: number;
	duration?: number;
	tokens?: number;
	cost?: ReportedCost;
}

interface ComparisonMetricProps {
	value: string;
	comparisonLabel?: string;
	isBest?: boolean;
}

function ComparisonMetric( {
	value,
	comparisonLabel,
	isBest = false,
}: ComparisonMetricProps ) {
	return (
		<span
			className={ `wp-ai-evals-comparison-metric ${
				isBest ? 'is-best' : ''
			}` }
		>
			<span>{ value }</span>
			{ comparisonLabel && <small>{ comparisonLabel }</small> }
		</span>
	);
}

function CaseResultDetails( {
	result,
	grouped = false,
	comparisonBenchmarks,
	showCost = false,
}: CaseResultDetailsProps ) {
	const taskMetadata = result.task_result?.metadata ?? {};
	const tools = Array.isArray( taskMetadata.tools ) ? taskMetadata.tools : [];
	const tokens = taskMetadata.tokens;
	const tokenTotal =
		typeof tokens?.total === 'number' ? tokens.total : undefined;
	const cost = taskMetadata.cost;
	const showComparison = comparisonBenchmarks?.hasComparison ?? false;
	const isSuccessful = result.status === 'passed';
	const isBestScore =
		showComparison && result.score === comparisonBenchmarks?.score;
	const isBestDuration =
		showComparison &&
		isSuccessful &&
		result.duration_ms === comparisonBenchmarks?.duration;
	const isBestTokens =
		showComparison &&
		isSuccessful &&
		tokenTotal !== undefined &&
		tokenTotal === comparisonBenchmarks?.tokens;
	const isBestCost =
		showComparison &&
		isSuccessful &&
		cost !== undefined &&
		cost.currency === comparisonBenchmarks?.cost?.currency &&
		cost.amount === comparisonBenchmarks.cost.amount;
	let scoreComparisonLabel: string | undefined;
	let durationComparisonLabel: string | undefined;
	let tokenComparisonLabel: string | undefined;
	let costComparisonLabel: string | undefined;

	if (
		showComparison &&
		isSuccessful &&
		comparisonBenchmarks?.duration !== undefined
	) {
		durationComparisonLabel = isBestDuration
			? __( 'Best', 'wp-ai-evals' )
			: `+${ formatDuration(
					result.duration_ms - comparisonBenchmarks.duration
			  ) }`;
	}

	if (
		showComparison &&
		isSuccessful &&
		tokenTotal !== undefined &&
		comparisonBenchmarks?.tokens !== undefined
	) {
		tokenComparisonLabel = isBestTokens
			? __( 'Best', 'wp-ai-evals' )
			: `+${ formatNumber( tokenTotal - comparisonBenchmarks.tokens ) }`;
	}

	if (
		showComparison &&
		isSuccessful &&
		cost !== undefined &&
		cost.currency === comparisonBenchmarks?.cost?.currency
	) {
		costComparisonLabel = isBestCost
			? __( 'Best', 'wp-ai-evals' )
			: `+${ formatCost( {
					amount: cost.amount - comparisonBenchmarks.cost.amount,
					currency: cost.currency,
			  } ) }`;
	}

	if ( showComparison ) {
		scoreComparisonLabel = isBestScore
			? __( 'Best', 'wp-ai-evals' )
			: `−${ (
					( comparisonBenchmarks?.score ?? 0 ) - result.score
			  ).toFixed( 3 ) }`;
	}

	return (
		<details
			className={ `wp-ai-evals-case-result is-${ result.status } ${
				grouped ? 'is-grouped' : ''
			} ${ showCost ? 'has-cost' : '' }` }
		>
			<summary>
				{ grouped ? (
					<>
						<StatusPill status={ result.status } />
						<strong className="wp-ai-evals-comparison-model">
							{ result.model_target?.id ??
								__( 'Default task', 'wp-ai-evals' ) }
						</strong>
						<ComparisonMetric
							value={ Number( result.score ).toFixed( 3 ) }
							comparisonLabel={ scoreComparisonLabel }
							isBest={ isBestScore }
						/>
						<ComparisonMetric
							value={ formatDuration( result.duration_ms ) }
							comparisonLabel={ durationComparisonLabel }
							isBest={ isBestDuration }
						/>
						<ComparisonMetric
							value={
								tokenTotal !== undefined
									? formatNumber( tokenTotal )
									: '—'
							}
							comparisonLabel={ tokenComparisonLabel }
							isBest={ isBestTokens }
						/>
						{ showCost && (
							<ComparisonMetric
								value={ formatCost( cost ) }
								comparisonLabel={ costComparisonLabel }
								isBest={ isBestCost }
							/>
						) }
						<ComparisonMetric
							value={ formatNumber( tools.length ) }
						/>
					</>
				) : (
					<>
						<StatusPill status={ result.status } />
						<div className="wp-ai-evals-case-identity">
							<strong>{ result.label }</strong>
							<code>{ result.qualified_id }</code>
							{ result.model_target && (
								<code>{ result.model_target.id }</code>
							) }
						</div>
						<ComparisonMetric
							value={ Number( result.score ).toFixed( 3 ) }
						/>
						<ComparisonMetric
							value={ formatDuration( result.duration_ms ) }
						/>
						<ComparisonMetric
							value={
								tokenTotal !== undefined
									? formatNumber( tokenTotal )
									: '—'
							}
						/>
						{ showCost && (
							<ComparisonMetric value={ formatCost( cost ) } />
						) }
						<ComparisonMetric
							value={ formatNumber( tools.length ) }
						/>
					</>
				) }
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
						label={ __( 'Requested target', 'wp-ai-evals' ) }
						value={ result.model_target?.id }
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
					<MetadataPill
						label={ __( 'Reported cost', 'wp-ai-evals' ) }
						value={ cost ? formatCost( cost ) : '' }
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

interface CaseResultGroup {
	id: string;
	label: string;
	qualifiedId: string;
	iteration: number;
	results: EvaluationResult[];
}

function groupComparisonResults(
	results: EvaluationResult[],
	modelTargets: string[]
): CaseResultGroup[] {
	const groups = new Map< string, CaseResultGroup >();
	const targetOrder = new Map(
		modelTargets.map( ( target, index ) => [ target, index ] )
	);

	results.forEach( ( result ) => {
		const id = `${ result.qualified_id }-${ result.iteration }`;
		const existing = groups.get( id );
		if ( existing ) {
			existing.results.push( result );
			return;
		}

		groups.set( id, {
			id,
			label: result.label,
			qualifiedId: result.qualified_id,
			iteration: result.iteration,
			results: [ result ],
		} );
	} );

	return Array.from( groups.values() ).map( ( group ) => ( {
		...group,
		results: [ ...group.results ].sort( ( first, second ) => {
			const firstOrder = first.model_target
				? targetOrder.get( first.model_target.id ) ??
				  Number.MAX_SAFE_INTEGER
				: -1;
			const secondOrder = second.model_target
				? targetOrder.get( second.model_target.id ) ??
				  Number.MAX_SAFE_INTEGER
				: -1;
			return firstOrder - secondOrder;
		} ),
	} ) );
}

function getComparisonBenchmarks(
	results: EvaluationResult[]
): CaseComparisonBenchmarks {
	const successfulResults = results.filter(
		( result ) => result.status === 'passed'
	);
	const tokenTotals = successfulResults
		.map( ( result ) => result.task_result?.metadata?.tokens?.total )
		.filter( ( total ): total is number => typeof total === 'number' );
	const reportedCosts = successfulResults
		.map( ( result ) => result.task_result?.metadata?.cost )
		.filter( ( cost ): cost is ReportedCost => cost !== undefined );
	const costCurrencies = new Set(
		reportedCosts.map( ( cost ) => cost.currency )
	);
	const comparableCost =
		reportedCosts.length > 0 &&
		reportedCosts.length === successfulResults.length &&
		costCurrencies.size === 1
			? {
					amount: Math.min(
						...reportedCosts.map( ( cost ) => cost.amount )
					),
					currency: reportedCosts[ 0 ].currency,
			  }
			: undefined;

	return {
		hasComparison: results.length > 1,
		score: Math.max( ...results.map( ( result ) => result.score ) ),
		duration:
			successfulResults.length > 0
				? Math.min(
						...successfulResults.map(
							( result ) => result.duration_ms
						)
				  )
				: undefined,
		tokens: tokenTotals.length > 0 ? Math.min( ...tokenTotals ) : undefined,
		cost: comparableCost,
	};
}

interface CaseComparisonGroupProps {
	group: CaseResultGroup;
	showIteration: boolean;
	showCost: boolean;
}

function CaseComparisonGroup( {
	group,
	showIteration,
	showCost,
}: CaseComparisonGroupProps ) {
	const benchmarks = getComparisonBenchmarks( group.results );

	return (
		<article className="wp-ai-evals-case-comparison-group">
			<header>
				<div>
					<strong>{ group.label }</strong>
					<code>{ group.qualifiedId }</code>
				</div>
				{ showIteration && (
					<span>
						{ sprintf(
							/* translators: %d is the repetition number. */
							__( 'Iteration %d', 'wp-ai-evals' ),
							group.iteration
						) }
					</span>
				) }
			</header>
			<div className="wp-ai-evals-case-comparison-results">
				<div
					className={ `wp-ai-evals-case-comparison-columns ${
						showCost ? 'has-cost' : ''
					}` }
				>
					<span>{ __( 'Result', 'wp-ai-evals' ) }</span>
					<span>{ __( 'Model', 'wp-ai-evals' ) }</span>
					<span>{ __( 'Score', 'wp-ai-evals' ) }</span>
					<span>{ __( 'Latency', 'wp-ai-evals' ) }</span>
					<span>{ __( 'Tokens', 'wp-ai-evals' ) }</span>
					{ showCost && (
						<span>{ __( 'Reported cost', 'wp-ai-evals' ) }</span>
					) }
					<span>{ __( 'Tools', 'wp-ai-evals' ) }</span>
					<span aria-hidden="true" />
				</div>
				{ group.results.map( ( result ) => (
					<CaseResultDetails
						key={ `${ result.model_target?.id ?? 'default' }-${
							result.iteration
						}` }
						result={ result }
						grouped
						comparisonBenchmarks={ benchmarks }
						showCost={ showCost }
					/>
				) ) }
			</div>
		</article>
	);
}

interface VariantComparisonProps {
	variants?: RunVariant[];
}

function VariantComparison( { variants = [] }: VariantComparisonProps ) {
	if ( variants.length < 2 ) {
		return null;
	}
	const showReportedCost = variants.some(
		( variant ) =>
			Object.keys( variant.diagnostics?.task_costs ?? {} ).length > 0
	);

	return (
		<section className="wp-ai-evals-comparison">
			<div className="wp-ai-evals-subheading">
				<h3>{ __( 'Model comparison', 'wp-ai-evals' ) }</h3>
				<span>
					{ showReportedCost
						? __(
								'Candidate tokens and reported cost exclude evaluator usage.',
								'wp-ai-evals'
						  )
						: __(
								'Candidate task tokens exclude evaluator usage.',
								'wp-ai-evals'
						  ) }
				</span>
			</div>
			<div className="wp-ai-evals-table-wrap">
				<table className="wp-ai-evals-table">
					<thead>
						<tr>
							<th>{ __( 'Model target', 'wp-ai-evals' ) }</th>
							<th>{ __( 'Passed', 'wp-ai-evals' ) }</th>
							<th>{ __( 'Score', 'wp-ai-evals' ) }</th>
							<th>{ __( 'Candidate tokens', 'wp-ai-evals' ) }</th>
							{ showReportedCost && (
								<th>
									{ __( 'Candidate cost', 'wp-ai-evals' ) }
								</th>
							) }
							<th>{ __( 'Duration', 'wp-ai-evals' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ variants.map( ( variant ) => (
							<tr key={ variant.id }>
								<td>
									<code>{ variant.id }</code>
								</td>
								<td>{ `${ variant.passed }/${ variant.total }` }</td>
								<td>{ formatPercent( variant.score ) }</td>
								<td>
									{ formatNumber(
										variant.diagnostics?.task_tokens?.total
									) }
								</td>
								{ showReportedCost && (
									<td>
										{ formatCosts(
											variant.diagnostics?.task_costs
										) }
									</td>
								) }
								<td>
									{ formatDuration( variant.duration_ms ) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
		</section>
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
	const showReportedCost = Object.keys( diagnostics.costs ?? {} ).length > 0;
	const showCaseReportedCost = report.results.some(
		( result ) => result.task_result?.metadata?.cost !== undefined
	);
	const completed = liveSession?.completed ?? summary.total;
	const total = liveSession?.total ?? summary.total;
	const progress = total > 0 ? ( completed / total ) * 100 : 0;
	const configuration =
		report.configuration ?? liveSession?.configuration ?? null;
	const variants = report.variants ?? liveSession?.variants ?? [];
	const comparisonGroups = configuration?.is_comparison
		? groupComparisonResults(
				report.results,
				configuration.model_targets.map( ( target ) => target.id )
		  )
		: [];
	const showComparisonIterations = comparisonGroups.some(
		( group ) => group.iteration > 1
	);
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
	let reportEyebrow: string = __( 'Run details', 'wp-ai-evals' );
	if ( configuration?.is_comparison ) {
		reportEyebrow = __( 'Model comparison', 'wp-ai-evals' );
	} else if ( liveSession ) {
		reportEyebrow = __( 'Live run', 'wp-ai-evals' );
	}

	return (
		<section ref={ reportRef } className="wp-ai-evals-report">
			<div className="wp-ai-evals-section-heading">
				<div>
					<p className="wp-ai-evals-eyebrow">{ reportEyebrow }</p>
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

			{ configuration &&
				( configuration.model_targets.length > 0 ||
					configuration.judge_model_target ) && (
					<div className="wp-ai-evals-run-targets">
						{ configuration.model_targets.map( ( target ) => (
							<MetadataPill
								key={ target.id }
								label={ __( 'Candidate', 'wp-ai-evals' ) }
								value={ target.id }
							/>
						) ) }
						{ configuration.judge_model_target && (
							<MetadataPill
								label={ __( 'Judge', 'wp-ai-evals' ) }
								value={ configuration.judge_model_target.id }
							/>
						) }
					</div>
				) }

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

			<div
				className={ `wp-ai-evals-summary-grid ${
					showReportedCost ? 'has-reported-cost' : ''
				}` }
			>
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
				{ showReportedCost && (
					<SummaryStat
						label={ __( 'Reported cost', 'wp-ai-evals' ) }
						value={ formatCosts( diagnostics.costs ) }
					/>
				) }
				<SummaryStat
					label={ __( 'Tools used', 'wp-ai-evals' ) }
					value={ formatNumber( tools.length ) }
				/>
			</div>

			<VariantComparison variants={ variants } />

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
				<>
					{ configuration?.is_comparison && (
						<div className="wp-ai-evals-subheading wp-ai-evals-case-comparison-heading">
							<h3>{ __( 'Test comparisons', 'wp-ai-evals' ) }</h3>
							<span>
								{ __(
									'Each test groups its model results together. Expand a model for full diagnostics.',
									'wp-ai-evals'
								) }
							</span>
						</div>
					) }
					<div
						className={
							configuration?.is_comparison
								? 'wp-ai-evals-case-comparison-groups'
								: 'wp-ai-evals-case-results'
						}
					>
						{ ! configuration?.is_comparison && (
							<div
								className={ `wp-ai-evals-case-result-columns ${
									showCaseReportedCost ? 'has-cost' : ''
								}` }
							>
								<span>{ __( 'Result', 'wp-ai-evals' ) }</span>
								<span>{ __( 'Test', 'wp-ai-evals' ) }</span>
								<span>{ __( 'Score', 'wp-ai-evals' ) }</span>
								<span>{ __( 'Latency', 'wp-ai-evals' ) }</span>
								<span>{ __( 'Tokens', 'wp-ai-evals' ) }</span>
								{ showCaseReportedCost && (
									<span>
										{ __( 'Reported cost', 'wp-ai-evals' ) }
									</span>
								) }
								<span>{ __( 'Tools', 'wp-ai-evals' ) }</span>
								<span aria-hidden="true" />
							</div>
						) }
						{ configuration?.is_comparison
							? comparisonGroups.map( ( group ) => (
									<CaseComparisonGroup
										key={ group.id }
										group={ group }
										showIteration={
											showComparisonIterations
										}
										showCost={ showCaseReportedCost }
									/>
							  ) )
							: report.results.map( ( result ) => (
									<CaseResultDetails
										key={ `${ result.qualified_id }-${
											result.model_target?.id ?? 'default'
										}-${ result.iteration }` }
										result={ result }
										showCost={ showCaseReportedCost }
									/>
							  ) ) }
						{ liveSession && isRunning && (
							<div className="wp-ai-evals-next-case">
								<Spinner />
								<span>
									{ __(
										'Running next case…',
										'wp-ai-evals'
									) }
								</span>
							</div>
						) }
					</div>
				</>
			) }
		</section>
	);
}
