# Architecture

## Vocabulary

- **Suite**: a namespaced collection owned by one plugin or feature.
- **Case**: one input, task, expected value, evaluator set, and metadata record.
- **Task**: the system under test. It may be a plugin callback, an AI Client prompt, or a WordPress Ability.
- **Evaluator**: a scorer that converts task output into a 0..1 score, pass/fail, and reason.
- **Tag**: an orthogonal selector such as `smoke`, `safety`, `quality`, or `slow`.
- **Run**: one selected experiment, optionally with repeated samples.

This deliberately avoids calling evaluators “test types.” A single case often needs several evaluation strategies at once—for example, JSON schema validity, latency, and an LLM rubric.

## Lifecycle

1. A plugin's development autoloader loads the package bootstrap.
2. The bootstrap exits in production unless explicitly enabled.
3. Plugins attach suite registration callbacks to `wp_ai_evals_init`.
4. Late on WordPress `init`, the shared registry fires that hook once.
5. WP Admin and WP-CLI create a `Selection`; CLI passes it directly to the shared `Runner`, while Admin expands it into a resumable run session.
6. The runner executes each selected case, captures duration and task metadata, then calls every evaluator. Admin advances one case per authenticated REST request so results are visible live on standard WordPress hosting.
7. A `RunReport` is retained for previous-run inspection and rendered in the Admin app, a CLI table, JSON, or JUnit XML.

The late registration hook allows several active plugins to contribute suites without coordinating load order.

## WordPress AI integration

`PromptTask` calls the WordPress 7.0 `wp_ai_client_prompt()` function and uses `generate_*_result()` methods. Rich result objects are normalized into:

- provider ID;
- model ID;
- request ID;
- input, output, total, and thinking tokens when supplied;
- generated output;
- harness-measured wall-clock latency.

Provider installation, discovery, authentication, and model capability matching remain owned by WordPress core and Settings → Connectors.

`AbilityTask` resolves an ability through `wp_get_ability()` and calls `WP_Ability::execute()`, preserving WordPress input validation, permission checks, output validation, and execution hooks.

## Extension points

- Implement `TaskInterface` for a new system-under-test adapter.
- Implement `EvaluatorInterface` for a new scoring strategy.
- Use `CallbackEvaluator` for small plugin-local rules.
- Listen to `wp_ai_evals_before_run`, `wp_ai_evals_after_run`, `wp_ai_evals_before_case`, and `wp_ai_evals_after_case` for instrumentation and fixtures.
- Filter `wp_ai_evals_capability` to change Admin authorization.
- Filter `wp_ai_evals_history_limit` to change retained run summaries.
- Filter `wp_ai_evals_run_session_ttl` to change how long interrupted live sessions can be resumed.
- Filter `wp_ai_evals_report_before_store` to redact persisted full reports.

## Production boundary

This library is executable development tooling, not a feature plugin. The primary boundary is Composer's `require-dev` plus `--no-dev` artifact creation. The runtime environment check is defense in depth for an accidentally included vendor directory. A process-wide bootstrap constant also prevents two plugins that vendor the package from registering the UI twice.
