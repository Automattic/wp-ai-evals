export type JsonValue =
	| boolean
	| number
	| string
	| null
	| JsonValue[]
	| { [ key: string ]: JsonValue };

export interface TokenCounts {
	input?: number;
	output?: number;
	thinking?: number;
	total?: number;
	[ key: string ]: number | undefined;
}

export interface RubricItemResult {
	id: string;
	label?: string;
	criteria: string;
	passed?: boolean;
	score: number;
	weight: number;
	minimum_score?: number | null;
	reason?: string;
}

export interface RubricResult {
	aggregation?: string;
	items: RubricItemResult[];
}

export interface EvaluatorMetadata {
	provider?: string;
	model?: string;
	duration_ms?: number;
	tokens?: TokenCounts;
	rubric?: RubricResult;
	[ key: string ]: unknown;
}

export interface EvaluatorResult {
	name: string;
	type: string;
	passed: boolean;
	score: number;
	reason?: string;
	metadata?: EvaluatorMetadata;
}

export interface TaskMetadata {
	provider?: string;
	model?: string;
	request_id?: string;
	duration_ms?: number;
	tokens?: TokenCounts;
	tools?: unknown[];
	[ key: string ]: unknown;
}

export interface TaskResult {
	output?: unknown;
	metadata?: TaskMetadata;
}

export interface EvaluationResult {
	suite: string;
	case: string;
	qualified_id: string;
	label: string;
	task_type: string;
	input?: unknown;
	expected?: unknown;
	tags: string[];
	metadata?: Record< string, unknown >;
	iteration: number;
	status: string;
	score: number;
	duration_ms: number;
	task_result?: TaskResult | null;
	evaluators?: EvaluatorResult[];
	error?: string;
}

export interface RunDiagnostics {
	tokens?: TokenCounts;
	tools?: string[];
	providers?: string[];
	models?: string[];
}

export interface RunSummary {
	total: number;
	passed: number;
	failed: number;
	score: number;
	diagnostics?: RunDiagnostics;
}

export interface RunReport {
	id: string;
	started_at: string;
	duration_ms: number;
	summary: RunSummary;
	results: EvaluationResult[];
}

export interface RunSession {
	id: string;
	started_at: string;
	total: number;
	completed: number;
	remaining: number;
	complete: boolean;
	duration_ms: number;
	summary: RunSummary;
	results: EvaluationResult[];
}

export interface RunHistoryItem {
	id: string;
	started_at: string;
	duration_ms: number;
	total: number;
	passed: number;
	failed: number;
	score: number;
	diagnostics?: RunDiagnostics;
}

export interface SuiteCase {
	id: string;
	qualified_id: string;
	label: string;
	type: string;
	tags: string[];
	evaluators: number;
}

export interface SuiteDefinition {
	id: string;
	label: string;
	description: string;
	case_count: number;
	cases: SuiteCase[];
}

export interface AdminSettings {
	suites: SuiteDefinition[];
	tags: string[];
	history: RunHistoryItem[];
	platform: {
		wordpress_version: string;
		connector_count: number;
		text_supported: boolean;
	};
	rest: {
		run: string;
		start: string;
		sessions: string;
		runs: string;
	};
	urls: {
		connectors: string;
	};
}

export interface RunResponse {
	report: RunReport;
	history: RunHistoryItem[];
}

export interface SessionResponse {
	session: RunSession;
	report?: RunReport | null;
	history?: RunHistoryItem[] | null;
}

export interface FormToken {
	value: string;
}

declare global {
	interface Window {
		wpAiEvalsSettings?: AdminSettings;
	}
}
