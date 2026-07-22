// @jsxRuntime classic
// @jsx createElement

import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import type { TokenCounts } from '../types';
import { formatNumber, hasContent, outputText } from '../utils';

interface StatusPillProps {
	status?: string;
}

export function StatusPill( { status }: StatusPillProps ) {
	return (
		<span className={ `wp-ai-evals-status is-${ status || 'error' }` }>
			<span aria-hidden="true" />
			{ status || __( 'error', 'wp-ai-evals' ) }
		</span>
	);
}

interface SummaryStatProps {
	label: string;
	value: string;
	tone?: string;
}

export function SummaryStat( { label, value, tone = '' }: SummaryStatProps ) {
	return (
		<div className={ `wp-ai-evals-stat ${ tone }` }>
			<strong>{ value }</strong>
			<span>{ label }</span>
		</div>
	);
}

interface MetadataPillProps {
	label: string;
	value: unknown;
}

export function MetadataPill( { label, value }: MetadataPillProps ) {
	if ( ! hasContent( value ) ) {
		return null;
	}

	return (
		<span className="wp-ai-evals-metadata-pill">
			<span>{ label }</span>
			<strong>{ String( value ) }</strong>
		</span>
	);
}

interface DataPanelProps {
	title: string;
	value: unknown;
	className?: string;
}

export function DataPanel( { title, value, className = '' }: DataPanelProps ) {
	if ( ! hasContent( value ) ) {
		return null;
	}

	return (
		<section className={ `wp-ai-evals-data-panel ${ className }` }>
			<h4>{ title }</h4>
			<pre>{ outputText( value ) }</pre>
		</section>
	);
}

interface TokenUsageProps {
	tokens: TokenCounts;
}

export function TokenUsage( { tokens }: TokenUsageProps ) {
	return (
		<div className="wp-ai-evals-token-grid">
			{ [ 'input', 'output', 'thinking', 'total' ].map( ( key ) => (
				<div key={ key }>
					<span>{ key }</span>
					<strong>{ formatNumber( tokens[ key ] ) }</strong>
				</div>
			) ) }
		</div>
	);
}
