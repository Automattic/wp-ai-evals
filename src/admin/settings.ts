import type { AdminSettings } from './types';

const defaults: AdminSettings = {
	suites: [],
	tags: [],
	history: [],
	platform: {
		wordpress_version: '',
		connector_count: 0,
		text_supported: false,
	},
	rest: {
		run: '',
		start: '',
		sessions: '',
		runs: '',
	},
	urls: {
		connectors: '',
	},
};

export const settings = window.wpAiEvalsSettings ?? defaults;
