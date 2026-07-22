import { __, _n, sprintf } from '@wordpress/i18n';

function appendMessage( transcript, role, content ) {
	const message = document.createElement( 'div' );
	message.className = `hello-dolly-ai-chat__message is-${ role }`;

	const label = document.createElement( 'span' );
	label.className = 'screen-reader-text';
	label.textContent =
		role === 'user'
			? __( 'You:', 'hello-dolly-ai' )
			: __( 'Hello Dolly:', 'hello-dolly-ai' );
	message.appendChild( label );

	content.split( /\n{2,}/ ).forEach( ( paragraph ) => {
		const element = document.createElement( 'p' );
		element.textContent = paragraph;
		message.appendChild( element );
	} );

	transcript.appendChild( message );
	message.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
}

function renderMeta( container, response ) {
	container.replaceChildren();

	const summary = document.createElement( 'span' );
	const model = [ response.provider, response.model ]
		.filter( Boolean )
		.join( ' / ' );
	const tokens = response.tokens?.total
		? sprintf(
				/* translators: %d is a token count. */
				__( '%d tokens', 'hello-dolly-ai' ),
				response.tokens.total
		  )
		: '';
	const tools =
		Array.isArray( response.tools ) && response.tools.length
			? sprintf(
					/* translators: %d is a number of WordPress knowledge abilities used. */
					_n(
						'%d knowledge tool',
						'%d knowledge tools',
						response.tools.length,
						'hello-dolly-ai'
					),
					response.tools.length
			  )
			: '';
	summary.textContent = [ model, tokens, tools ]
		.filter( Boolean )
		.join( ' · ' );
	container.appendChild( summary );

	if ( Array.isArray( response.sources ) && response.sources.length ) {
		const sources = document.createElement( 'span' );
		sources.className = 'hello-dolly-ai-chat__sources';
		sources.append( __( 'Sources:', 'hello-dolly-ai' ), ' ' );

		response.sources.forEach( ( source, index ) => {
			if ( index > 0 ) {
				sources.append( ', ' );
			}
			const link = document.createElement( 'a' );
			link.href = source.url;
			link.target = '_blank';
			link.rel = 'noreferrer noopener';
			link.textContent = source.label || source.url;
			sources.appendChild( link );
		} );

		container.appendChild( sources );
	}

	container.hidden = ! container.textContent;
}

function initializeChat( root ) {
	if ( root.dataset.initialized === 'true' ) {
		return;
	}
	root.dataset.initialized = 'true';

	const form = root.querySelector( '.hello-dolly-ai-chat__form' );
	const input = form?.querySelector( 'textarea' );
	const submit = form?.querySelector( 'button[type="submit"]' );
	const transcript = root.querySelector( '.hello-dolly-ai-chat__transcript' );
	const status = root.querySelector( '.hello-dolly-ai-chat__status' );
	const meta = root.querySelector( '.hello-dolly-ai-chat__meta' );
	const history = [];

	if ( ! form || ! input || ! submit || ! transcript || ! status || ! meta ) {
		return;
	}

	root.querySelectorAll( '[data-question]' ).forEach( ( suggestion ) => {
		suggestion.addEventListener( 'click', () => {
			input.value = suggestion.dataset.question || '';
			input.focus();
			if (
				root.dataset.ready === 'true' &&
				root.dataset.loggedIn === 'true'
			) {
				form.requestSubmit();
			}
		} );
	} );

	input.addEventListener( 'keydown', ( event ) => {
		if ( event.key !== 'Enter' || event.shiftKey || event.isComposing ) {
			return;
		}

		event.preventDefault();
		form.requestSubmit( submit );
	} );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		const question = input.value.trim();
		if ( ! question ) {
			return;
		}

		appendMessage( transcript, 'user', question );
		input.value = '';
		input.disabled = true;
		submit.disabled = true;
		root.setAttribute( 'aria-busy', 'true' );
		status.textContent = __(
			'Checking the curated Dolly knowledge…',
			'hello-dolly-ai'
		);

		try {
			const request = await fetch( root.dataset.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': root.dataset.nonce,
				},
				body: JSON.stringify( {
					message: question,
					history: history.slice( -10 ),
				} ),
			} );
			const response = await request.json();
			if ( ! request.ok ) {
				throw new Error(
					response.message ||
						__(
							'The connector could not answer right now.',
							'hello-dolly-ai'
						)
				);
			}

			appendMessage( transcript, 'assistant', response.answer );
			history.push(
				{ role: 'user', content: question },
				{ role: 'assistant', content: response.answer }
			);
			if ( history.length > 10 ) {
				history.splice( 0, history.length - 10 );
			}
			renderMeta( meta, response );
			status.textContent = '';
		} catch ( error ) {
			appendMessage(
				transcript,
				'assistant',
				error.message || __( 'Something went wrong.', 'hello-dolly-ai' )
			);
			status.textContent = __(
				'The request did not complete.',
				'hello-dolly-ai'
			);
		} finally {
			input.disabled = false;
			submit.disabled = false;
			root.removeAttribute( 'aria-busy' );
			input.focus();
		}
	} );
}

function initializeAll() {
	document
		.querySelectorAll( '.hello-dolly-ai-chat[data-endpoint]' )
		.forEach( initializeChat );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initializeAll );
} else {
	initializeAll();
}
