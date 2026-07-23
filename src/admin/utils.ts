import { dateI18n, getSettings as getDateSettings } from '@wordpress/date';

import type {
	FormToken,
	ReportedCost,
	ReportedCosts,
	RunReport,
	RunSession,
} from './types';

export function formatDateTime( value: string ): string {
	return dateI18n( getDateSettings().formats.datetime, value );
}

export function formatDuration( milliseconds?: number ): string {
	return `${ new Intl.NumberFormat( undefined, {
		maximumFractionDigits: 1,
	} ).format( milliseconds ?? 0 ) } ms`;
}

export function formatNumber( value?: number ): string {
	return new Intl.NumberFormat().format( Number( value ) || 0 );
}

export function formatPercent( score?: number ): string {
	return `${ new Intl.NumberFormat( undefined, {
		minimumFractionDigits: 1,
		maximumFractionDigits: 1,
	} ).format( ( score ?? 0 ) * 100 ) }%`;
}

export function formatCost( cost?: ReportedCost ): string {
	if (
		! cost ||
		! Number.isFinite( cost.amount ) ||
		! cost.currency.trim()
	) {
		return '—';
	}

	const currency = cost.currency.toUpperCase();
	const minimumFractionDigits = cost.amount > 0 && cost.amount < 0.01 ? 4 : 2;

	try {
		return new Intl.NumberFormat( undefined, {
			style: 'currency',
			currency,
			currencyDisplay: 'narrowSymbol',
			minimumFractionDigits,
			maximumFractionDigits: 6,
		} ).format( cost.amount );
	} catch {
		return `${ new Intl.NumberFormat( undefined, {
			minimumFractionDigits,
			maximumFractionDigits: 6,
		} ).format( cost.amount ) } ${ currency }`;
	}
}

export function formatCosts( costs?: ReportedCosts ): string {
	if ( ! costs ) {
		return '—';
	}

	const entries = Object.entries( costs ).filter( ( [ , amount ] ) =>
		Number.isFinite( amount )
	);
	if ( entries.length === 0 ) {
		return '—';
	}

	return entries
		.sort( ( [ first ], [ second ] ) => first.localeCompare( second ) )
		.map( ( [ currency, amount ] ) => formatCost( { amount, currency } ) )
		.join( ' · ' );
}

export function normalizeTokens(
	tokens: Array< string | FormToken >,
	allowed: string[]
): string[] {
	return [
		...new Set(
			tokens
				.map( ( token ) =>
					typeof token === 'string' ? token : token.value
				)
				.filter( ( token ) => allowed.includes( token ) )
		),
	];
}

export function outputText( output: unknown ): string {
	if ( typeof output === 'string' ) {
		return output;
	}

	try {
		return JSON.stringify( output, null, 2 );
	} catch {
		return String( output ?? '' );
	}
}

export function hasContent( value: unknown ): boolean {
	if ( value === null || value === undefined || value === '' ) {
		return false;
	}

	return ! Array.isArray( value ) || value.length > 0;
}

export function setRunUrl( runId: string ): void {
	const url = new URL( window.location.href );
	if ( runId ) {
		url.searchParams.set( 'run', runId );
	} else {
		url.searchParams.delete( 'run' );
	}
	window.history.replaceState( {}, '', url );
}

export function reportFromSession( session: RunSession ): RunReport {
	return {
		id: session.id,
		started_at: session.started_at,
		duration_ms: session.duration_ms,
		configuration: session.configuration,
		summary: session.summary,
		variants: session.variants,
		results: session.results,
	};
}

export function normalizeModelTargets(
	tokens: Array< string | FormToken >
): string[] {
	return [
		...new Set(
			tokens
				.map( ( token ) =>
					typeof token === 'string' ? token : token.value
				)
				.map( ( token ) => token.trim() )
				.filter( ( token ) =>
					/^[a-z0-9][a-z0-9_-]*:.+$/i.test( token )
				)
		),
	];
}

export function errorMessage( error: unknown, fallback: string ): string {
	if (
		typeof error === 'object' &&
		error !== null &&
		'message' in error &&
		typeof error.message === 'string'
	) {
		return error.message;
	}

	return fallback;
}
