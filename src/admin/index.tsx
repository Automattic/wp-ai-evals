// @jsxRuntime classic
// @jsx createElement

import { createElement, createRoot } from '@wordpress/element';

import { App } from './app';
import './style.scss';

const rootElement = document.getElementById( 'wp-ai-evals-admin' );

if ( rootElement ) {
	createRoot( rootElement ).render( <App /> );
}
