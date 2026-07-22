import type { FormToken, RunReport, RunSession } from './types';

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
		summary: session.summary,
		results: session.results,
	};
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
