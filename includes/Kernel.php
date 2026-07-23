<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

final class Kernel {

	private static ?self $instance = null;
	private Registry $registry;
	private bool $initialized = false;

	private function __construct() {
		$this->registry = new Registry();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function initialize(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->initialized = true;
		do_action( 'wp_ai_evals_init', $this->registry );
	}

	public function get_registry(): Registry {
		return $this->registry;
	}
}
