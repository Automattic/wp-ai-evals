// @jsxRuntime classic
// @jsx createElement

import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	FormTokenField,
	Notice,
	Spinner,
	TextControl,
} from '@wordpress/components';
import {
	createElement,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { Report } from './components/report';
import { RunHistory } from './components/run-history';
import { settings } from './settings';
import type {
	RunHistoryItem,
	RunReport,
	RunResponse,
	RunSession,
	SessionResponse,
} from './types';
import {
	errorMessage,
	normalizeTokens,
	reportFromSession,
	setRunUrl,
} from './utils';

export function App() {
	const allSuiteIds = useMemo(
		() => settings.suites.map( ( suite ) => suite.id ),
		[]
	);
	const availableTags = useMemo( () => settings.tags, [] );
	const [ selectedSuites, setSelectedSuites ] =
		useState< string[] >( allSuiteIds );
	const [ selectedTags, setSelectedTags ] = useState< string[] >( [] );
	const [ cases, setCases ] = useState( '' );
	const [ repetitions, setRepetitions ] = useState( '1' );
	const [ history, setHistory ] = useState< RunHistoryItem[] >(
		settings.history
	);
	const [ report, setReport ] = useState< RunReport | null >( null );
	const [ liveSession, setLiveSession ] = useState< RunSession | null >(
		null
	);
	const [ error, setError ] = useState( '' );
	const [ isRunning, setIsRunning ] = useState( false );
	const [ loadingRunId, setLoadingRunId ] = useState( '' );
	const reportRef = useRef< HTMLElement >( null );

	const scrollToReport = () => {
		window.setTimeout( () => {
			reportRef.current?.scrollIntoView( {
				behavior: 'smooth',
				block: 'start',
			} );
		}, 50 );
	};

	useEffect( () => {
		const runId = new URLSearchParams( window.location.search ).get(
			'run'
		);
		if ( ! runId ) {
			return;
		}

		let mounted = true;
		setLoadingRunId( runId );
		apiFetch< RunResponse >( {
			path: `${ settings.rest.runs }${ encodeURIComponent( runId ) }`,
		} )
			.then( ( response ) => {
				if ( mounted ) {
					setReport( response.report );
				}
			} )
			.catch( () =>
				apiFetch< SessionResponse >( {
					path: `${ settings.rest.sessions }${ encodeURIComponent(
						runId
					) }`,
				} ).then( ( response ) => {
					if ( mounted ) {
						setLiveSession( response.session );
						setReport( reportFromSession( response.session ) );
					}
				} )
			)
			.catch( ( requestError: unknown ) => {
				if ( mounted ) {
					setError(
						errorMessage(
							requestError,
							__(
								'The selected run details are unavailable.',
								'wp-ai-evals'
							)
						)
					);
				}
			} )
			.finally( () => {
				if ( mounted ) {
					setLoadingRunId( '' );
				}
			} );

		return () => {
			mounted = false;
		};
	}, [] );

	const toggleSuite = ( suiteId: string, checked: boolean ) => {
		setSelectedSuites( ( current ) =>
			checked
				? [ ...new Set( [ ...current, suiteId ] ) ]
				: current.filter( ( id ) => id !== suiteId )
		);
	};

	const continueSession = async ( initialSession: RunSession ) => {
		let current = initialSession;
		setIsRunning( true );
		setError( '' );

		try {
			while ( ! current.complete ) {
				const response = await apiFetch< SessionResponse >( {
					path: `${ settings.rest.sessions }${ encodeURIComponent(
						current.id
					) }/next`,
					method: 'POST',
				} );

				current = response.session;
				setLiveSession( current.complete ? null : current );
				setReport(
					response.report ?? reportFromSession( response.session )
				);

				if ( response.history ) {
					setHistory( response.history );
				}
			}
		} catch ( requestError ) {
			setError(
				errorMessage(
					requestError,
					__(
						'The live evaluation run stopped unexpectedly.',
						'wp-ai-evals'
					)
				)
			);
			setLiveSession( current );
		} finally {
			setIsRunning( false );
		}
	};

	const runEvaluations = async () => {
		setError( '' );
		setReport( null );
		setLiveSession( null );
		setIsRunning( true );

		try {
			const response = await apiFetch< SessionResponse >( {
				path: settings.rest.start,
				method: 'POST',
				data: {
					suites: selectedSuites,
					tags: selectedTags,
					cases: cases
						.split( ',' )
						.map( ( value ) => value.trim() )
						.filter( Boolean ),
					repetitions: Math.max(
						1,
						Math.min( 10, Number.parseInt( repetitions, 10 ) || 1 )
					),
				},
			} );

			setRunUrl( response.session.id );
			setLiveSession( response.session );
			setReport( reportFromSession( response.session ) );
			scrollToReport();
			await continueSession( response.session );
		} catch ( requestError ) {
			setError(
				errorMessage(
					requestError,
					__(
						'The evaluation run could not be started.',
						'wp-ai-evals'
					)
				)
			);
		} finally {
			setIsRunning( false );
		}
	};

	const inspectRun = async ( runId: string ) => {
		setError( '' );
		setLoadingRunId( runId );
		setLiveSession( null );
		setRunUrl( runId );

		try {
			const response = await apiFetch< RunResponse >( {
				path: `${ settings.rest.runs }${ encodeURIComponent( runId ) }`,
			} );
			setReport( response.report );
			scrollToReport();
		} catch ( requestError ) {
			setReport( null );
			setError(
				errorMessage(
					requestError,
					__(
						'The selected run details are unavailable.',
						'wp-ai-evals'
					)
				)
			);
		} finally {
			setLoadingRunId( '' );
		}
	};

	const allSelected = selectedSuites.length === allSuiteIds.length;
	const activeRunId = report?.id || liveSession?.id || '';

	return (
		<div className="wp-ai-evals-app">
			<header className="wp-ai-evals-hero">
				<div>
					<p className="wp-ai-evals-eyebrow">
						{ __( 'Developer tooling', 'wp-ai-evals' ) }
					</p>
					<h1>{ __( 'AI Evals', 'wp-ai-evals' ) }</h1>
					<p>
						{ __(
							'Run repeatable evaluation suites with live case results and inspect every recorded diagnostic.',
							'wp-ai-evals'
						) }
					</p>
				</div>
				<div className="wp-ai-evals-platform">
					<div>
						<span
							className={ `wp-ai-evals-ready ${
								settings.platform.text_supported
									? 'is-ready'
									: ''
							}` }
						>
							<span aria-hidden="true" />
							{ settings.platform.text_supported
								? __( 'Text generation ready', 'wp-ai-evals' )
								: __(
										'Text generation unavailable',
										'wp-ai-evals'
								  ) }
						</span>
						<small>
							{ sprintf(
								/* translators: 1: WordPress version, 2: number of AI connectors. */
								__(
									'WordPress %1$s · %2$d AI connectors',
									'wp-ai-evals'
								),
								settings.platform.wordpress_version,
								settings.platform.connector_count
							) }
						</small>
					</div>
					<Button
						variant="secondary"
						href={ settings.urls.connectors }
					>
						{ __( 'Manage connectors', 'wp-ai-evals' ) }
					</Button>
				</div>
			</header>

			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }

			<div className="wp-ai-evals-layout">
				<Card className="wp-ai-evals-card wp-ai-evals-run-card">
					<CardHeader>
						<div>
							<p className="wp-ai-evals-eyebrow">
								{ __( 'Configure', 'wp-ai-evals' ) }
							</p>
							<h2>
								{ __( 'New evaluation run', 'wp-ai-evals' ) }
							</h2>
						</div>
					</CardHeader>
					<CardBody>
						<div className="wp-ai-evals-field-heading">
							<div>
								<h3>{ __( 'Suites', 'wp-ai-evals' ) }</h3>
								<p>
									{ __(
										'Choose one or more registered suites.',
										'wp-ai-evals'
									) }
								</p>
							</div>
							<Button
								variant="link"
								onClick={ () =>
									setSelectedSuites(
										allSelected ? [] : allSuiteIds
									)
								}
							>
								{ allSelected
									? __( 'Clear all', 'wp-ai-evals' )
									: __( 'Select all', 'wp-ai-evals' ) }
							</Button>
						</div>
						<div className="wp-ai-evals-suite-options">
							{ settings.suites.map( ( suite ) => (
								<div
									className="wp-ai-evals-suite-option"
									key={ suite.id }
								>
									<CheckboxControl
										label={ suite.label }
										help={ sprintf(
											/* translators: %d is a number of evaluation cases. */
											__( '%d cases', 'wp-ai-evals' ),
											suite.case_count
										) }
										checked={ selectedSuites.includes(
											suite.id
										) }
										onChange={ ( checked ) =>
											toggleSuite( suite.id, checked )
										}
									/>
									<code>{ suite.id }</code>
								</div>
							) ) }
						</div>

						<div className="wp-ai-evals-fields">
							<FormTokenField
								label={ __( 'Tags', 'wp-ai-evals' ) }
								value={ selectedTags }
								suggestions={ availableTags.filter(
									( tag ) => ! selectedTags.includes( tag )
								) }
								onChange={ ( tokensValue ) =>
									setSelectedTags(
										normalizeTokens(
											tokensValue,
											availableTags
										)
									)
								}
								placeholder={ __(
									'Search registered tags…',
									'wp-ai-evals'
								) }
								__experimentalExpandOnFocus
								__experimentalAutoSelectFirstMatch
								__experimentalShowHowTo={ false }
								__experimentalValidateInput={ ( tag ) =>
									availableTags.includes( tag )
								}
								__next40pxDefaultSize
							/>
							<p className="wp-ai-evals-help">
								{ __(
									'Optional. Cases matching any selected tag are included.',
									'wp-ai-evals'
								) }
							</p>

							<div className="wp-ai-evals-field-row">
								<TextControl
									label={ __( 'Case IDs', 'wp-ai-evals' ) }
									help={ __(
										'Optional comma-separated case or suite/case IDs.',
										'wp-ai-evals'
									) }
									placeholder="suite/case, another-case"
									value={ cases }
									onChange={ setCases }
									__next40pxDefaultSize
								/>
								<TextControl
									className="wp-ai-evals-repetitions"
									label={ __( 'Repetitions', 'wp-ai-evals' ) }
									help={ __(
										'From 1 to 10.',
										'wp-ai-evals'
									) }
									type="number"
									min={ 1 }
									max={ 10 }
									value={ repetitions }
									onChange={ setRepetitions }
									__next40pxDefaultSize
								/>
							</div>
						</div>

						<div className="wp-ai-evals-run-actions">
							<Button
								variant="primary"
								size="compact"
								isBusy={ isRunning }
								disabled={
									isRunning || selectedSuites.length === 0
								}
								onClick={ runEvaluations }
							>
								{ isRunning
									? __(
											'Running evaluations…',
											'wp-ai-evals'
									  )
									: __( 'Run evaluations', 'wp-ai-evals' ) }
							</Button>
							{ isRunning && <Spinner /> }
							<span>
								{ __(
									'Results appear live after each case. AI-backed cases and judges may incur provider usage costs.',
									'wp-ai-evals'
								) }
							</span>
						</div>
					</CardBody>
				</Card>
			</div>

			<Report
				report={ report }
				reportRef={ reportRef }
				liveSession={ liveSession }
				isRunning={ isRunning }
				onResume={ () => {
					if ( liveSession ) {
						void continueSession( liveSession );
					}
				} }
				isLoading={ Boolean( loadingRunId ) && ! report }
			/>
			<RunHistory
				history={ history }
				onInspect={ ( runId ) => void inspectRun( runId ) }
				activeRunId={ activeRunId }
				loadingRunId={ loadingRunId }
			/>
		</div>
	);
}
