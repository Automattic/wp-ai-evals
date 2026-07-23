// @jsxRuntime classic
// @jsx createElement

import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	ComboboxControl,
	Dropdown,
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
import { __, _n, sprintf } from '@wordpress/i18n';

import { Report } from './components/report';
import { RunHistory } from './components/run-history';
import { settings } from './settings';
import type {
	ModelCatalog,
	ModelCatalogEntry,
	ModelCatalogResponse,
	RunHistoryItem,
	RunReport,
	RunResponse,
	RunSession,
	SessionResponse,
} from './types';
import {
	errorMessage,
	normalizeModelTargets,
	reportFromSession,
	setRunUrl,
} from './utils';

type FilterKind = 'suite' | 'tag' | 'case';
type SettingKind = 'models' | 'judge' | 'repetitions';

export function App() {
	const registeredCases = useMemo(
		() =>
			settings.suites.flatMap( ( suite ) =>
				suite.cases.map( ( evalCase ) => ( {
					...evalCase,
					suiteId: suite.id,
				} ) )
			),
		[]
	);
	const availableTags = useMemo( () => settings.tags, [] );
	const [ selectedSuites, setSelectedSuites ] = useState< string[] >( [] );
	const [ selectedTags, setSelectedTags ] = useState< string[] >( [] );
	const [ caseIds, setCaseIds ] = useState< string[] >( [] );
	const [ repetitions, setRepetitions ] = useState( '1' );
	const [ modelTargets, setModelTargets ] = useState< string[] >( [] );
	const [ judgeModelTarget, setJudgeModelTarget ] = useState( '' );
	const [ filterKind, setFilterKind ] = useState< FilterKind | null >( null );
	const [ editingSetting, setEditingSetting ] =
		useState< SettingKind | null >( null );
	const [ modelCatalog, setModelCatalog ] = useState< ModelCatalog | null >(
		null
	);
	const [ isLoadingModels, setIsLoadingModels ] = useState( false );
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
	const defaultJudgeApplied = useRef( false );

	const modelSuggestion = ( model: ModelCatalogEntry ) =>
		`${ model.provider_name } · ${ model.name } (${ model.target })`;
	const caseSuggestion = ( evalCase: ( typeof registeredCases )[ number ] ) =>
		`${ evalCase.qualified_id } · ${ evalCase.label }`;

	const loadModels = async ( refresh = false ) => {
		setIsLoadingModels( true );
		try {
			const response = await apiFetch< ModelCatalogResponse >( {
				path: `${ settings.rest.models }${
					refresh ? '?refresh=true' : ''
				}`,
			} );
			setModelCatalog( response.catalog );
			if (
				! defaultJudgeApplied.current &&
				response.catalog.default_judge_target
			) {
				setJudgeModelTarget( response.catalog.default_judge_target );
				defaultJudgeApplied.current = true;
			}
		} catch ( requestError ) {
			setError(
				errorMessage(
					requestError,
					__(
						'Available models could not be loaded. You can still enter provider:model targets manually.',
						'wp-ai-evals'
					)
				)
			);
		} finally {
			setIsLoadingModels( false );
		}
	};

	useEffect( () => {
		void loadModels();
	}, [] );

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

	const addFilter = ( kind: FilterKind, value: string ) => {
		if ( kind === 'suite' ) {
			setSelectedSuites( ( current ) => [
				...new Set( [ ...current, value ] ),
			] );
		} else if ( kind === 'tag' ) {
			setSelectedTags( ( current ) => [
				...new Set( [ ...current, value ] ),
			] );
		} else {
			setCaseIds( ( current ) => [
				...new Set( [ ...current, value ] ),
			] );
		}
		setFilterKind( null );
	};

	const removeFilter = ( kind: FilterKind, value: string ) => {
		if ( kind === 'suite' ) {
			setSelectedSuites( ( current ) =>
				current.filter( ( id ) => id !== value )
			);
		} else if ( kind === 'tag' ) {
			setSelectedTags( ( current ) =>
				current.filter( ( tag ) => tag !== value )
			);
		} else {
			setCaseIds( ( current ) =>
				current.filter( ( id ) => id !== value )
			);
		}
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
					cases: caseIds,
					repetitions: Math.max(
						1,
						Math.min( 10, Number.parseInt( repetitions, 10 ) || 1 )
					),
					model_targets: modelTargets,
					judge_model_target: judgeModelTarget,
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

	const filteredCaseCount = useMemo(
		() =>
			registeredCases.filter( ( evalCase ) => {
				if (
					selectedSuites.length > 0 &&
					! selectedSuites.includes( evalCase.suiteId )
				) {
					return false;
				}
				if (
					caseIds.length > 0 &&
					! caseIds.includes( evalCase.id ) &&
					! caseIds.includes( evalCase.qualified_id )
				) {
					return false;
				}
				return (
					selectedTags.length === 0 ||
					evalCase.tags.some( ( tag ) =>
						selectedTags.includes( tag )
					)
				);
			} ).length,
		[ caseIds, registeredCases, selectedSuites, selectedTags ]
	);
	const filterCount =
		selectedSuites.length + selectedTags.length + caseIds.length;
	const modelTargetName = ( target: string ) => {
		const model = modelCatalog?.models.find(
			( item ) => item.target === target
		);
		return model ? `${ model.provider_name } · ${ model.name }` : target;
	};
	const candidateModelSummary =
		modelTargets.length === 0
			? __( "Each task's model preferences", 'wp-ai-evals' )
			: modelTargets.map( modelTargetName ).join( ', ' );
	const judgeModelSummary = judgeModelTarget
		? modelTargetName( judgeModelTarget )
		: __( "Each evaluator's model preferences", 'wp-ai-evals' );
	const repetitionCount = Math.max(
		1,
		Math.min( 10, Number.parseInt( repetitions, 10 ) || 1 )
	);
	const repetitionSummary =
		repetitionCount === 1
			? __( 'Once per case', 'wp-ai-evals' )
			: sprintf(
					/* translators: %d is the number of repetitions. */
					__( '%d times per case', 'wp-ai-evals' ),
					repetitionCount
			  );
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
					<strong>
						{ sprintf(
							/* translators: %d is the number of active AI connectors. */
							_n(
								'%d active connector',
								'%d active connectors',
								settings.platform.connector_count,
								'wp-ai-evals'
							),
							settings.platform.connector_count
						) }
					</strong>
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
						<section className="wp-ai-evals-config-section">
							<div className="wp-ai-evals-field-heading">
								<div>
									<h3>{ __( 'Filters', 'wp-ai-evals' ) }</h3>
									<p>
										{ filterCount === 0
											? sprintf(
													/* translators: %d is the number of cases. */
													__(
														'All %d registered cases',
														'wp-ai-evals'
													),
													registeredCases.length
											  )
											: sprintf(
													/* translators: 1: matching cases, 2: total cases. */
													__(
														'%1$d of %2$d cases match',
														'wp-ai-evals'
													),
													filteredCaseCount,
													registeredCases.length
											  ) }
									</p>
								</div>
								<Dropdown
									contentClassName="wp-ai-evals-dropdown"
									renderToggle={ ( { isOpen, onToggle } ) => (
										<Button
											variant="secondary"
											size="compact"
											aria-expanded={ isOpen }
											onClick={ () => {
												if ( isOpen ) {
													setFilterKind( null );
												}
												onToggle();
											} }
										>
											{ __(
												'+ Add filter',
												'wp-ai-evals'
											) }
										</Button>
									) }
									renderContent={ ( { onClose } ) => (
										<div className="wp-ai-evals-dropdown-content">
											{ ! filterKind && (
												<>
													<strong>
														{ __(
															'Filter cases by',
															'wp-ai-evals'
														) }
													</strong>
													<div className="wp-ai-evals-dropdown-options">
														<Button
															variant="tertiary"
															onClick={ () =>
																setFilterKind(
																	'tag'
																)
															}
														>
															{ __(
																'Tag',
																'wp-ai-evals'
															) }
														</Button>
														<Button
															variant="tertiary"
															onClick={ () =>
																setFilterKind(
																	'case'
																)
															}
														>
															{ __(
																'Case ID',
																'wp-ai-evals'
															) }
														</Button>
														<Button
															variant="tertiary"
															onClick={ () =>
																setFilterKind(
																	'suite'
																)
															}
														>
															{ __(
																'Suite',
																'wp-ai-evals'
															) }
														</Button>
													</div>
												</>
											) }
											{ filterKind === 'tag' && (
												<ComboboxControl
													label={ __(
														'Tag',
														'wp-ai-evals'
													) }
													value={ null }
													options={ availableTags
														.filter(
															( tag ) =>
																! selectedTags.includes(
																	tag
																)
														)
														.map( ( tag ) => ( {
															value: tag,
															label: tag,
														} ) ) }
													onChange={ ( value ) => {
														if ( value ) {
															addFilter(
																'tag',
																value
															);
															onClose();
														}
													} }
													__next40pxDefaultSize
												/>
											) }
											{ filterKind === 'case' && (
												<FormTokenField
													label={ __(
														'Case ID',
														'wp-ai-evals'
													) }
													value={ [] }
													suggestions={ registeredCases
														.filter(
															( evalCase ) =>
																! caseIds.includes(
																	evalCase.qualified_id
																)
														)
														.map( caseSuggestion ) }
													onChange={ (
														tokensValue
													) => {
														const token =
															tokensValue[ 0 ];
														if ( ! token ) {
															return;
														}
														const value =
															typeof token ===
															'string'
																? token
																: token.value;
														const evalCase =
															registeredCases.find(
																( item ) =>
																	caseSuggestion(
																		item
																	) ===
																		value ||
																	item.qualified_id ===
																		value
															);
														addFilter(
															'case',
															evalCase?.qualified_id ??
																value.trim()
														);
														onClose();
													} }
													placeholder={ __(
														'Search or enter a case ID…',
														'wp-ai-evals'
													) }
													__experimentalExpandOnFocus
													__experimentalAutoSelectFirstMatch
													__experimentalShowHowTo={
														false
													}
													__next40pxDefaultSize
												/>
											) }
											{ filterKind === 'suite' && (
												<ComboboxControl
													label={ __(
														'Suite',
														'wp-ai-evals'
													) }
													value={ null }
													options={ settings.suites
														.filter(
															( suite ) =>
																! selectedSuites.includes(
																	suite.id
																)
														)
														.map( ( suite ) => ( {
															value: suite.id,
															label: `${ suite.label } (${ suite.id })`,
														} ) ) }
													onChange={ ( value ) => {
														if ( value ) {
															addFilter(
																'suite',
																value
															);
															onClose();
														}
													} }
													__next40pxDefaultSize
												/>
											) }
											{ filterKind && (
												<Button
													variant="link"
													onClick={ () =>
														setFilterKind( null )
													}
												>
													{ __(
														'Back',
														'wp-ai-evals'
													) }
												</Button>
											) }
										</div>
									) }
								/>
							</div>
							<div className="wp-ai-evals-filter-pills">
								{ selectedSuites.map( ( suiteId ) => (
									<Button
										key={ `suite-${ suiteId }` }
										className="wp-ai-evals-filter-pill"
										variant="secondary"
										onClick={ () =>
											removeFilter( 'suite', suiteId )
										}
										aria-label={ sprintf(
											/* translators: %s is a suite ID. */
											__(
												'Remove suite filter %s',
												'wp-ai-evals'
											),
											suiteId
										) }
									>
										<span>
											{ __( 'Suite', 'wp-ai-evals' ) }
										</span>
										{ suiteId }
										<b aria-hidden="true">×</b>
									</Button>
								) ) }
								{ selectedTags.map( ( tag ) => (
									<Button
										key={ `tag-${ tag }` }
										className="wp-ai-evals-filter-pill"
										variant="secondary"
										onClick={ () =>
											removeFilter( 'tag', tag )
										}
										aria-label={ sprintf(
											/* translators: %s is a tag. */
											__(
												'Remove tag filter %s',
												'wp-ai-evals'
											),
											tag
										) }
									>
										<span>
											{ __( 'Tag', 'wp-ai-evals' ) }
										</span>
										{ tag }
										<b aria-hidden="true">×</b>
									</Button>
								) ) }
								{ caseIds.map( ( caseId ) => (
									<Button
										key={ `case-${ caseId }` }
										className="wp-ai-evals-filter-pill"
										variant="secondary"
										onClick={ () =>
											removeFilter( 'case', caseId )
										}
										aria-label={ sprintf(
											/* translators: %s is a case ID. */
											__(
												'Remove case filter %s',
												'wp-ai-evals'
											),
											caseId
										) }
									>
										<span>
											{ __( 'Case', 'wp-ai-evals' ) }
										</span>
										{ caseId }
										<b aria-hidden="true">×</b>
									</Button>
								) ) }
								{ filterCount === 0 && (
									<span className="wp-ai-evals-default-pill">
										{ __( 'All cases', 'wp-ai-evals' ) }
									</span>
								) }
							</div>
						</section>

						<section className="wp-ai-evals-config-section">
							<div className="wp-ai-evals-field-heading">
								<div>
									<h3>{ __( 'Settings', 'wp-ai-evals' ) }</h3>
									<p>
										{ __(
											'Current values for this run',
											'wp-ai-evals'
										) }
									</p>
								</div>
							</div>

							<div className="wp-ai-evals-settings">
								<div className="wp-ai-evals-setting">
									<div className="wp-ai-evals-setting-heading">
										<div>
											<strong>
												{ __(
													'Candidate models',
													'wp-ai-evals'
												) }
											</strong>
											<span>
												{ candidateModelSummary }
											</span>
										</div>
										<Button
											variant="link"
											aria-expanded={
												editingSetting === 'models'
											}
											onClick={ () =>
												setEditingSetting(
													editingSetting === 'models'
														? null
														: 'models'
												)
											}
										>
											{ editingSetting === 'models'
												? __( 'Done', 'wp-ai-evals' )
												: __(
														'Change',
														'wp-ai-evals'
												  ) }
										</Button>
									</div>
									{ editingSetting === 'models' && (
										<div className="wp-ai-evals-setting-control">
											<div>
												{ __(
													'Select exact targets to compare, or leave empty to use each task’s preferences.',
													'wp-ai-evals'
												) }
											</div>
											<FormTokenField
												label={ __(
													'Exact provider:model targets',
													'wp-ai-evals'
												) }
												value={ modelTargets }
												suggestions={ (
													modelCatalog?.models ?? []
												)
													.filter(
														( model ) =>
															! modelTargets.includes(
																model.target
															)
													)
													.map( modelSuggestion ) }
												onChange={ ( tokensValue ) => {
													const normalized =
														tokensValue.map(
															( token ) => {
																const value =
																	typeof token ===
																	'string'
																		? token
																		: token.value;
																const catalogModel =
																	modelCatalog?.models.find(
																		(
																			model
																		) =>
																			modelSuggestion(
																				model
																			) ===
																				value ||
																			model.target ===
																				value
																	);
																return (
																	catalogModel?.target ??
																	value
																);
															}
														);
													setModelTargets(
														normalizeModelTargets(
															normalized
														)
													);
												} }
												placeholder={ __(
													'Search models or enter provider:model…',
													'wp-ai-evals'
												) }
												__experimentalExpandOnFocus
												__experimentalAutoSelectFirstMatch
												__experimentalShowHowTo={
													false
												}
												__next40pxDefaultSize
											/>
										</div>
									) }
								</div>

								<div className="wp-ai-evals-setting">
									<div className="wp-ai-evals-setting-heading">
										<div>
											<strong>
												{ __(
													'Judge model',
													'wp-ai-evals'
												) }
											</strong>
											<span>{ judgeModelSummary }</span>
										</div>
										<Button
											variant="link"
											aria-expanded={
												editingSetting === 'judge'
											}
											onClick={ () =>
												setEditingSetting(
													editingSetting === 'judge'
														? null
														: 'judge'
												)
											}
										>
											{ editingSetting === 'judge'
												? __( 'Done', 'wp-ai-evals' )
												: __(
														'Change',
														'wp-ai-evals'
												  ) }
										</Button>
									</div>
									{ editingSetting === 'judge' && (
										<div className="wp-ai-evals-setting-control">
											<div>
												{ __(
													'Keep this model fixed while comparing candidates.',
													'wp-ai-evals'
												) }
											</div>
											<ComboboxControl
												label={ __(
													'Exact judge target',
													'wp-ai-evals'
												) }
												value={
													judgeModelTarget || null
												}
												options={ (
													modelCatalog?.models ?? []
												).map( ( model ) => ( {
													value: model.target,
													label: modelSuggestion(
														model
													),
												} ) ) }
												onChange={ ( value ) =>
													setJudgeModelTarget(
														value ?? ''
													)
												}
												__next40pxDefaultSize
											/>
											{ judgeModelTarget && (
												<Button
													variant="link"
													onClick={ () =>
														setJudgeModelTarget(
															''
														)
													}
												>
													{ __(
														'Use evaluator defaults',
														'wp-ai-evals'
													) }
												</Button>
											) }
										</div>
									) }
								</div>

								<div className="wp-ai-evals-setting">
									<div className="wp-ai-evals-setting-heading">
										<div>
											<strong>
												{ __(
													'Repetitions',
													'wp-ai-evals'
												) }
											</strong>
											<span>{ repetitionSummary }</span>
										</div>
										<Button
											variant="link"
											aria-expanded={
												editingSetting === 'repetitions'
											}
											onClick={ () =>
												setEditingSetting(
													editingSetting ===
														'repetitions'
														? null
														: 'repetitions'
												)
											}
										>
											{ editingSetting === 'repetitions'
												? __( 'Done', 'wp-ai-evals' )
												: __(
														'Change',
														'wp-ai-evals'
												  ) }
										</Button>
									</div>
									{ editingSetting === 'repetitions' && (
										<div className="wp-ai-evals-setting-control">
											<div>
												{ __(
													'Repeat every matching case from 1 to 10 times.',
													'wp-ai-evals'
												) }
											</div>
											<TextControl
												className="wp-ai-evals-repetitions"
												label={ __(
													'Repetitions',
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
									) }
								</div>
							</div>

							<div className="wp-ai-evals-model-status">
								<span>
									{ modelCatalog
										? sprintf(
												/* translators: %d is a number of discovered models. */
												__(
													'%d models available',
													'wp-ai-evals'
												),
												modelCatalog.models.length
										  )
										: __(
												'Loading available models…',
												'wp-ai-evals'
										  ) }
								</span>
								<Button
									variant="link"
									isBusy={ isLoadingModels }
									onClick={ () => void loadModels( true ) }
								>
									{ __( 'Refresh', 'wp-ai-evals' ) }
								</Button>
							</div>

							{ ( modelCatalog?.errors.length ?? 0 ) > 0 && (
								<Notice
									status="warning"
									isDismissible={ false }
									className="wp-ai-evals-model-warning"
								>
									{ __(
										'Some providers could not return their model catalog:',
										'wp-ai-evals'
									) }{ ' ' }
									{ modelCatalog?.errors.join( ' · ' ) }
								</Notice>
							) }
						</section>

						<div className="wp-ai-evals-run-actions">
							<Button
								variant="primary"
								size="compact"
								isBusy={ isRunning }
								disabled={
									isRunning || filteredCaseCount === 0
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
