// @jsxRuntime classic
// @jsx createElement

import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { dateI18n, getSettings as getDateSettings } from '@wordpress/date';
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import type { RunHistoryItem } from '../types';
import {
	formatCosts,
	formatDuration,
	formatNumber,
	formatPercent,
} from '../utils';

interface RunHistoryProps {
	history: RunHistoryItem[];
	onInspect: ( runId: string ) => void;
	activeRunId: string;
	loadingRunId: string;
}

export function RunHistory( {
	history,
	onInspect,
	activeRunId,
	loadingRunId,
}: RunHistoryProps ) {
	const showReportedCost = history.some(
		( run ) => Object.keys( run.diagnostics?.costs ?? {} ).length > 0
	);
	const dateTimeFormat = getDateSettings().formats.datetime;

	return (
		<Card className="wp-ai-evals-card wp-ai-evals-history">
			<CardHeader>
				<div>
					<p className="wp-ai-evals-eyebrow">
						{ __( 'History', 'wp-ai-evals' ) }
					</p>
					<h2>{ __( 'Previous runs', 'wp-ai-evals' ) }</h2>
				</div>
			</CardHeader>
			<CardBody>
				{ history.length === 0 ? (
					<p>{ __( 'No evaluation runs yet.', 'wp-ai-evals' ) }</p>
				) : (
					<div className="wp-ai-evals-table-wrap">
						<table className="wp-ai-evals-table is-compact">
							<thead>
								<tr>
									<th>{ __( 'Run', 'wp-ai-evals' ) }</th>
									<th>{ __( 'Passed', 'wp-ai-evals' ) }</th>
									<th>{ __( 'Score', 'wp-ai-evals' ) }</th>
									<th>{ __( 'Models', 'wp-ai-evals' ) }</th>
									<th>{ __( 'Tokens', 'wp-ai-evals' ) }</th>
									{ showReportedCost && (
										<th>
											{ __(
												'Reported cost',
												'wp-ai-evals'
											) }
										</th>
									) }
									<th>{ __( 'Duration', 'wp-ai-evals' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ history.map( ( run ) => (
									<tr
										key={ run.id }
										className={
											run.id === activeRunId
												? 'is-active'
												: ''
										}
									>
										<td>
											<Button
												className="wp-ai-evals-history-run"
												variant="link"
												isBusy={
													loadingRunId === run.id
												}
												onClick={ () =>
													onInspect( run.id )
												}
											>
												<time
													dateTime={ run.started_at }
													title={ run.started_at }
												>
													{ dateI18n(
														dateTimeFormat,
														run.started_at
													) }
												</time>
											</Button>
											<code className="wp-ai-evals-history-run-id">
												{ run.id }
											</code>
										</td>
										<td>{ `${ run.passed }/${ run.total }` }</td>
										<td>{ formatPercent( run.score ) }</td>
										<td>
											{ run.configuration?.model_targets
												?.length
												? run.configuration.model_targets.map(
														( target ) => (
															<code
																key={
																	target.id
																}
															>
																{ target.id }
															</code>
														)
												  )
												: __(
														'Default',
														'wp-ai-evals'
												  ) }
										</td>
										<td>
											{ run.diagnostics?.tokens
												? formatNumber(
														run.diagnostics.tokens
															.total
												  )
												: '—' }
										</td>
										{ showReportedCost && (
											<td>
												{ formatCosts(
													run.diagnostics?.costs
												) }
											</td>
										) }
										<td>
											{ formatDuration(
												run.duration_ms
											) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
				) }
			</CardBody>
		</Card>
	);
}
