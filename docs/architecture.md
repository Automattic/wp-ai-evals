# Architecture

## Vocabulary

- **Suite**: a namespaced collection owned by one plugin or feature.
- **Case**: one input, task, expected value, evaluator set, and metadata record.
- **Task**: the system under test. It may be a plugin callback, an AI Client prompt, or a WordPress Ability.
- **Evaluator**: a scorer that converts task output into a 0..1 score, pass/fail, and reason.
- **Tag**: an orthogonal selector such as `smoke`, `safety`, `quality`, or `slow`.
- **Run**: one selected experiment, optionally with repeated samples.
- **Model target**: an exact `provider:model` pair requested for a model-aware task.
- **Variant**: the results and aggregate diagnostics for one target inside a comparison run.

This deliberately avoids calling evaluators “test types.” A single case often needs several evaluation strategies at once—for example, JSON schema validity, latency, and an LLM rubric.

## Lifecycle

1. A plugin's development autoloader loads the package bootstrap.
2. The bootstrap exits in production unless explicitly enabled.
3. Plugins attach suite registration callbacks to `wp_ai_evals_init`.
4. Late on WordPress `init`, the shared registry fires that hook once.
5. WP Admin and WP-CLI create a `Selection` and `RunConfiguration`. The configuration contains zero or more exact candidate model targets and an independent optional judge target.
6. The runner executes model-independent tasks once and expands model-aware tasks across every candidate target and repetition. It captures duration and task metadata, verifies exact target resolution, then calls every evaluator. Admin advances one case variant per authenticated REST request so results are visible live on standard WordPress hosting.
7. A `RunReport` is retained for previous-run inspection and rendered in the Admin app, a CLI table, JSON, or JUnit XML.

The late registration hook allows several active plugins to contribute suites without coordinating load order.

## WordPress AI integration

`PromptTask` calls the WordPress 7.0 `wp_ai_client_prompt()` function and uses `generate_*_result()` methods. Rich result objects are normalized into:

- provider ID;
- model ID;
- request ID;
- input, output, total, and thinking tokens when supplied;
- normalized provider-reported cost by currency when supplied, split between task and evaluator usage;
- generated output;
- harness-measured wall-clock latency.

Provider installation, discovery, authentication, and model capability matching remain owned by WordPress core and Settings → Connectors.

The Connectors API supplies provider connection and credential metadata, not a model catalog. `ModelCatalog` reads registered/configured providers from `AiClient::defaultRegistry()` and asks each provider's model metadata directory for its current catalog. Providers may discover catalogs dynamically, so Admin caches the normalized catalog briefly and offers an explicit refresh.

`ModelTarget` resolves an exact provider/model instance through that same registry and applies it with the WordPress prompt builder's `using_model()` method. There is no fallback in this path. `PromptTask` implements `ModelTargetAwareTaskInterface` and applies the target after case-level builder configuration, ensuring that the run override wins.

Custom agents opt in with `EvaluationCase::modelTask()`. Their callback reads `EvaluationContext::getModelTarget()` and passes it through every prompt step. The runner checks the returned task metadata against the requested target and turns an ignored target or fallback into a case error.

`EvaluationContext::getJudgeModelTarget()` is separate. `LlmJudge` applies it only to grading prompts, records the requested and resolved judge model, and fails the evaluator if they differ. Run diagnostics split task tokens and reported costs from evaluator usage so candidate comparisons are not distorted by judge usage. The harness never calculates cost from model pricing.

Judge selection has a cross-provider default policy: Anthropic Claude Sonnet 4.6, Google Gemini 3.1 Pro Preview, then OpenAI GPT-5.4. `JudgeModelPreferences` exposes the ordered model IDs to evaluators for normal AI Client fallback and selects the first available exact `provider:model` target for the Admin app. Projects can replace that order through `wp_ai_evals_judge_model_target_preferences`.

`AbilityTask` resolves an ability through `wp_get_ability()` and calls `WP_Ability::execute()`, preserving WordPress input validation, permission checks, output validation, and execution hooks.

## Extension points

- Implement `TaskInterface` for a new system-under-test adapter.
- Implement the marker `ModelTargetAwareTaskInterface`, or use `EvaluationCase::modelTask()`, when a task honors exact run targets.
- Implement `EvaluatorInterface` for a new scoring strategy.
- Use `CallbackEvaluator` for small plugin-local rules.
- Listen to `wp_ai_evals_before_run`, `wp_ai_evals_after_run`, `wp_ai_evals_before_case`, and `wp_ai_evals_after_case` for instrumentation and fixtures.
- Filter `wp_ai_evals_capability` to change Admin authorization.
- Filter `wp_ai_evals_history_limit` to change retained run summaries.
- Filter `wp_ai_evals_run_session_ttl` to change how long interrupted live sessions can be resumed.
- Filter `wp_ai_evals_report_before_store` to redact persisted full reports.
- Filter `wp_ai_evals_model_catalog` to add, remove, or annotate discovered model entries.
- Filter `wp_ai_evals_judge_model_target_preferences` to replace the ordered default judge targets.
- Filter `wp_ai_evals_ai_result_reported_cost` to normalize provider-specific cost metadata.

## Admin execution and persistence

The Admin app uses `@wordpress/element`, `@wordpress/components`, `@wordpress/api-fetch`, and `@wordpress/date`. It starts runs through short authenticated REST requests and advances one case variant at a time. This makes completed results visible immediately without requiring WebSockets, a queue worker, or a long-running HTTP request.

The run configuration records suite, case, and tag filters; repetitions; exact candidate targets; and the optional judge target. The report records inputs, expected values, outputs, task metadata, tool names, token usage, provider-reported cost, requested and resolved models, evaluator reasons, rubric items, and aggregate model variants.

Completed reports and recent summaries use non-autoloaded WordPress options. `wp_ai_evals_history_limit` bounds history, `wp_ai_evals_run_session_ttl` bounds interrupted live sessions, and `wp_ai_evals_report_before_store` allows sensitive fields to be removed before persistence.

The TypeScript source lives in `src/admin`. Generated assets live in `build/admin` and are excluded from source control.

## Production boundary

This library is executable development tooling, not a feature plugin. The primary boundary is Composer's `require-dev` plus `--no-dev` artifact creation. The runtime environment check is defense in depth for an accidentally included vendor directory. A process-wide bootstrap constant also prevents two plugins that vendor the package from registering the UI twice.
