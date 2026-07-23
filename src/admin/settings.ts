import type { AdminSettings } from './types';

const defaults: AdminSettings = {
	suites: [],
	tags: [],
	history: [],
	platform: {
		connector_count: 0,
	},
	rest: {
		run: '',
		start: '',
		sessions: '',
		runs: '',
		models: '',
	},
	urls: {
		connectors: '',
	},
};

export const settings = window.wpAiEvalsSettings ?? defaults;
