<?php
/**
 * Minimal WordPress test doubles for unit testing the library in isolation.
 *
 * These emulate just enough of WordPress for the filter, option, transient,
 * REST-schema, and AI-client seams the library touches. They are deliberately
 * simple stand-ins, not a faithful WordPress implementation, and exist only so
 * storage, JSON-schema, and LLM-judge logic can be exercised without a full
 * WordPress runtime. Every definition is guarded so a real WordPress bootstrap
 * (or repeated includes) never triggers a redeclaration error.
 *
 * @package Automattic\AiEvals\Tests
 */

declare(strict_types=1);

if ( ! function_exists( 'wp_ai_evals_test_reset_state' ) ) {
	/**
	 * Resets the in-memory options, transients, hooks, and canned AI response.
	 *
	 * Call this from each test's setUp() so state never leaks between tests.
	 */
	function wp_ai_evals_test_reset_state(): void {
		$GLOBALS['wp_ai_evals_test_options']        = array();
		$GLOBALS['wp_ai_evals_test_transients']     = array();
		$GLOBALS['wp_ai_evals_test_hooks']          = array();
		$GLOBALS['wp_ai_evals_test_abilities']      = array();
		$GLOBALS['wp_ai_evals_test_judge_response'] = '';
		$GLOBALS['wp_ai_evals_test_last_prompt']    = null;
		$GLOBALS['wp_ai_evals_test_builder_calls']  = array();
	}
}

// Initialize storage so the array accessors below never receive null.
wp_ai_evals_test_reset_state();

/*
-------------------------------------------------------------------------- */
/*
Hooks                                                                       */
/* -------------------------------------------------------------------------- */

if ( ! function_exists( 'add_filter' ) ) {
	/** @param callable $callback */
	function add_filter( string $hook, $callback, int $priority = 10, int $acceptedArgs = 1 ): bool {
		$GLOBALS['wp_ai_evals_test_hooks'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/** @param callable $callback */
	function add_action( string $hook, $callback, int $priority = 10, int $acceptedArgs = 1 ): bool {
		return add_filter( $hook, $callback, $priority, $acceptedArgs );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param mixed $value
	 * @param mixed ...$args
	 * @return mixed
	 */
	function apply_filters( string $hook, $value, ...$args ) {
		foreach ( $GLOBALS['wp_ai_evals_test_hooks'][ $hook ] ?? array() as $callback ) {
			$value = $callback( $value, ...$args );
		}

		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/** @param mixed ...$args */
	function do_action( string $hook, ...$args ): void {
		foreach ( $GLOBALS['wp_ai_evals_test_hooks'][ $hook ] ?? array() as $callback ) {
			$callback( ...$args );
		}
	}
}

if ( ! function_exists( 'remove_all_filters' ) ) {
	function remove_all_filters( string $hook ): bool {
		unset( $GLOBALS['wp_ai_evals_test_hooks'][ $hook ] );

		return true;
	}
}

/*
-------------------------------------------------------------------------- */
/*
Options                                                                     */
/* -------------------------------------------------------------------------- */

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param mixed $default
	 * @return mixed
	 */
	function get_option( string $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['wp_ai_evals_test_options'] )
			? $GLOBALS['wp_ai_evals_test_options'][ $name ]
			: $default;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/** @param mixed $value @param mixed $autoload */
	function add_option( string $name, $value = '', string $deprecated = '', $autoload = 'yes' ): bool {
		if ( array_key_exists( $name, $GLOBALS['wp_ai_evals_test_options'] ) ) {
			return false;
		}

		$GLOBALS['wp_ai_evals_test_options'][ $name ] = $value;

		return true;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/** @param mixed $value @param mixed $autoload */
	function update_option( string $name, $value, $autoload = null ): bool {
		$GLOBALS['wp_ai_evals_test_options'][ $name ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $name ): bool {
		if ( ! array_key_exists( $name, $GLOBALS['wp_ai_evals_test_options'] ) ) {
			return false;
		}

		unset( $GLOBALS['wp_ai_evals_test_options'][ $name ] );

		return true;
	}
}

/*
-------------------------------------------------------------------------- */
/*
Transients                                                                  */
/* -------------------------------------------------------------------------- */

if ( ! function_exists( 'get_transient' ) ) {
	/** @return mixed */
	function get_transient( string $key ) {
		return array_key_exists( $key, $GLOBALS['wp_ai_evals_test_transients'] )
			? $GLOBALS['wp_ai_evals_test_transients'][ $key ]
			: false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/** @param mixed $value */
	function set_transient( string $key, $value, int $expiration = 0 ): bool {
		$GLOBALS['wp_ai_evals_test_transients'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $key ): bool {
		if ( ! array_key_exists( $key, $GLOBALS['wp_ai_evals_test_transients'] ) ) {
			return false;
		}

		unset( $GLOBALS['wp_ai_evals_test_transients'][ $key ] );

		return true;
	}
}

/*
-------------------------------------------------------------------------- */
/*
JSON + errors                                                               */
/* -------------------------------------------------------------------------- */

if ( ! function_exists( 'wp_json_encode' ) ) {
	/** @param mixed $data @return string|false */
	function wp_json_encode( $data, int $options = 0, int $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {

		/** @var string */
		private $code;

		/** @var string */
		private $message;

		/** @param mixed $data */
		public function __construct( string $code = '', string $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/** @param mixed $thing */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

/*
-------------------------------------------------------------------------- */
/*
Abilities                                                                   */
/* -------------------------------------------------------------------------- */

if ( ! function_exists( 'wp_get_ability' ) ) {
	/** @return object|null */
	function wp_get_ability( string $name ) {
		$ability = $GLOBALS['wp_ai_evals_test_abilities'][ $name ] ?? null;

		return is_object( $ability ) ? $ability : null;
	}
}

/*
-------------------------------------------------------------------------- */
/*
REST schema validation (shallow)                                            */
/* -------------------------------------------------------------------------- */

if ( ! function_exists( 'wp_ai_evals_test_value_matches_type' ) ) {
	/**
	 * @param mixed $value
	 * @param mixed $type
	 */
	function wp_ai_evals_test_value_matches_type( $value, $type ): bool {
		switch ( $type ) {
			case 'object':
			case 'array':
				return is_array( $value );
			case 'string':
				return is_string( $value );
			case 'integer':
				return is_int( $value );
			case 'number':
				return is_int( $value ) || is_float( $value );
			case 'boolean':
				return is_bool( $value );
			case 'null':
				return null === $value;
			default:
				return true;
		}
	}
}

if ( ! function_exists( 'rest_validate_value_from_schema' ) ) {
	/**
	 * A shallow schema validator: enough to exercise the JsonSchema evaluator's
	 * own logic (type, required properties, and one level of property types).
	 *
	 * @param mixed                $value
	 * @param array<string, mixed> $schema
	 * @return true|\WP_Error
	 */
	function rest_validate_value_from_schema( $value, array $schema, string $param = 'value' ) {
		$type = $schema['type'] ?? null;
		if ( null !== $type && ! wp_ai_evals_test_value_matches_type( $value, $type ) ) {
			return new WP_Error(
				'rest_invalid_type',
				sprintf( '%s is not of type %s.', $param, is_string( $type ) ? $type : 'mixed' )
			);
		}

		if ( 'object' === $type && is_array( $value ) ) {
			foreach ( $schema['required'] ?? array() as $required ) {
				if ( ! array_key_exists( $required, $value ) ) {
					return new WP_Error(
						'rest_property_required',
						sprintf( '%s is missing the required property %s.', $param, $required )
					);
				}
			}

			foreach ( $schema['properties'] ?? array() as $key => $propertySchema ) {
				if ( ! is_array( $propertySchema ) || ! array_key_exists( $key, $value ) ) {
					continue;
				}

				$sub = rest_validate_value_from_schema( $value[ $key ], $propertySchema, $param . '[' . $key . ']' );
				if ( is_wp_error( $sub ) ) {
					return $sub;
				}
			}
		}

		return true;
	}
}

/*
-------------------------------------------------------------------------- */
/*
AI client double (for the LLM judge)                                        */
/* -------------------------------------------------------------------------- */

if ( ! class_exists( 'WpAiEvalsTestJudgeResult' ) ) {
	/**
	 * A canned AI text result. Exposes only toText() so AiResultAdapter reads
	 * the response and leaves metadata otherwise empty.
	 */
	class WpAiEvalsTestJudgeResult {

		/** @var string */
		private $text;

		public function __construct( string $text ) {
			$this->text = $text;
		}

		public function toText(): string {
			return $this->text;
		}
	}
}

if ( ! class_exists( 'WpAiEvalsTestJudgeBuilder' ) ) {
	/**
	 * A permissive prompt builder: every fluent call is a no-op that returns the
	 * builder, and generate_text_result() returns whatever a test staged in the
	 * $wp_ai_evals_test_judge_response global. The magic __call means
	 * method_exists() reports false for optional capability checks such as
	 * is_supported_for_text_generation(), so the judge skips them.
	 */
	class WpAiEvalsTestJudgeBuilder {

		/**
		 * @param array<int, mixed> $arguments
		 * @return self
		 */
		public function __call( string $name, array $arguments ): self {
			$GLOBALS['wp_ai_evals_test_builder_calls'][] = array(
				'name'      => $name,
				'arguments' => $arguments,
			);

			return $this;
		}

		public function generate_text_result(): WpAiEvalsTestJudgeResult {
			return new WpAiEvalsTestJudgeResult( (string) ( $GLOBALS['wp_ai_evals_test_judge_response'] ?? '' ) );
		}
	}
}

if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
	/** @param mixed $prompt */
	function wp_ai_client_prompt( $prompt = '' ): WpAiEvalsTestJudgeBuilder {
		$GLOBALS['wp_ai_evals_test_last_prompt'] = $prompt;

		return new WpAiEvalsTestJudgeBuilder();
	}
}
