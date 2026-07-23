<?php

declare(strict_types=1);

namespace HelloDollyAI;

final class Block {

	public static function register(): void {
		$path = HELLO_DOLLY_AI_DIR . '/build';
		if ( ! is_readable( $path . '/block.json' ) ) {
			return;
		}

		register_block_type( $path, array( 'render_callback' => array( self::class, 'render' ) ) );
	}

	/** @param array<string, mixed> $attributes */
	public static function render( array $attributes ): string {
		$greeting     = isset( $attributes['greeting'] )
			? (string) $attributes['greeting']
			: __( 'Hi! I’m Hello Dolly. Ask me about Dolly Parton’s life, music, or philanthropy.', 'hello-dolly-ai' );
		$is_logged_in = is_user_logged_in();
		$is_ready     = self::is_agent_ready();
		$input_id     = wp_unique_id( 'hello-dolly-ai-message-' );

		$wrapper = get_block_wrapper_attributes(
			array(
				'class'          => 'hello-dolly-ai-chat',
				'data-endpoint'  => esc_url_raw( rest_url( 'hello-dolly/v1/chat' ) ),
				'data-nonce'     => wp_create_nonce( 'wp_rest' ),
				'data-ready'     => $is_ready ? 'true' : 'false',
				'data-logged-in' => $is_logged_in ? 'true' : 'false',
			)
		);

		ob_start();
		?>
		<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="hello-dolly-ai-chat__header">
				<div class="hello-dolly-ai-chat__portrait" aria-hidden="true">✦</div>
				<div>
					<h2><?php echo esc_html__( 'Hello Dolly', 'hello-dolly-ai' ); ?></h2>
					<p><?php echo esc_html__( 'A grounded guide to Dolly Parton', 'hello-dolly-ai' ); ?></p>
				</div>
				<span class="hello-dolly-ai-chat__badge"><?php echo esc_html__( 'Connector powered', 'hello-dolly-ai' ); ?></span>
			</div>

			<?php if ( ! $is_logged_in ) : ?>
				<div class="hello-dolly-ai-chat__notice">
					<?php echo esc_html__( 'Sign in to use this development chat demo.', 'hello-dolly-ai' ); ?>
				</div>
			<?php elseif ( ! $is_ready ) : ?>
				<div class="hello-dolly-ai-chat__notice">
					<?php echo esc_html__( 'Configure an AI provider with text generation and function calling before chatting.', 'hello-dolly-ai' ); ?>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>"><?php echo esc_html__( 'Open Connectors', 'hello-dolly-ai' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="hello-dolly-ai-chat__transcript" role="log" aria-live="polite" aria-label="<?php echo esc_attr__( 'Chat transcript', 'hello-dolly-ai' ); ?>">
				<div class="hello-dolly-ai-chat__message is-assistant">
					<span class="screen-reader-text"><?php echo esc_html__( 'Hello Dolly:', 'hello-dolly-ai' ); ?></span>
					<p><?php echo esc_html( $greeting ); ?></p>
				</div>
			</div>

			<div class="hello-dolly-ai-chat__suggestions" aria-label="<?php echo esc_attr__( 'Suggested questions', 'hello-dolly-ai' ); ?>">
				<button type="button" data-question="Where did Dolly grow up?" <?php disabled( ! $is_logged_in || ! $is_ready ); ?>><?php echo esc_html__( 'Where did Dolly grow up?', 'hello-dolly-ai' ); ?></button>
				<button type="button" data-question="Tell me about Coat of Many Colors." <?php disabled( ! $is_logged_in || ! $is_ready ); ?>><?php echo esc_html__( 'Coat of Many Colors', 'hello-dolly-ai' ); ?></button>
				<button type="button" data-question="What is the Imagination Library?" <?php disabled( ! $is_logged_in || ! $is_ready ); ?>><?php echo esc_html__( 'Imagination Library', 'hello-dolly-ai' ); ?></button>
			</div>

			<form class="hello-dolly-ai-chat__form">
				<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html__( 'Ask about Dolly Parton', 'hello-dolly-ai' ); ?></label>
				<textarea id="<?php echo esc_attr( $input_id ); ?>" rows="2" maxlength="1000" placeholder="<?php echo esc_attr__( 'Ask about Dolly’s story…', 'hello-dolly-ai' ); ?>" <?php disabled( ! $is_logged_in || ! $is_ready ); ?>></textarea>
				<button class="hello-dolly-ai-chat__submit" type="submit" <?php disabled( ! $is_logged_in || ! $is_ready ); ?>><?php echo esc_html__( 'Ask', 'hello-dolly-ai' ); ?></button>
			</form>
			<div class="hello-dolly-ai-chat__status" aria-live="polite"></div>
			<div class="hello-dolly-ai-chat__meta" hidden></div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	private static function is_agent_ready(): bool {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		$builder = wp_ai_client_prompt( 'Hello Dolly readiness check' )
			->using_abilities( ...Abilities::names() );

		return $builder->is_supported_for_text_generation();
	}
}
