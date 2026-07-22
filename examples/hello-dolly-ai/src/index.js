import { registerBlockType } from '@wordpress/blocks';
import { PanelBody, TextareaControl } from '@wordpress/components';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import './style.scss';
import './editor.scss';

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( {
		className: 'hello-dolly-ai-chat is-editor',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Hello Dolly settings', 'hello-dolly-ai' ) }
				>
					<TextareaControl
						label={ __( 'Opening greeting', 'hello-dolly-ai' ) }
						value={ attributes.greeting }
						onChange={ ( greeting ) =>
							setAttributes( { greeting } )
						}
						help={ __(
							'Shown before a visitor starts the conversation.',
							'hello-dolly-ai'
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="hello-dolly-ai-chat__header">
					<div
						className="hello-dolly-ai-chat__portrait"
						aria-hidden="true"
					>
						✦
					</div>
					<div>
						<h2>{ __( 'Hello Dolly', 'hello-dolly-ai' ) }</h2>
						<p>
							{ __(
								'A grounded guide to Dolly Parton',
								'hello-dolly-ai'
							) }
						</p>
					</div>
					<span className="hello-dolly-ai-chat__badge">
						{ __( 'Connector powered', 'hello-dolly-ai' ) }
					</span>
				</div>
				<div className="hello-dolly-ai-chat__transcript">
					<div className="hello-dolly-ai-chat__message is-assistant">
						<p>{ attributes.greeting }</p>
					</div>
				</div>
				<div className="hello-dolly-ai-chat__editor-note">
					{ __(
						'The live chat, connector status, and source links appear on the published page.',
						'hello-dolly-ai'
					) }
				</div>
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
