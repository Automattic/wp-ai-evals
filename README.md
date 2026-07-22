# WordPress AI Evals

A development-only evaluation harness for plugins built on the WordPress AI Client, Connectors API, and Abilities API.

Plugin authors register one or more suites, then run the same cases from **Tools → AI Evals** or WP-CLI. The harness supports deterministic assertions, custom scorers, performance budgets, and LLM-as-a-judge grading while retaining provider, model, token, latency, score, and failure metadata.

> Status: early development. The Composer package name is `automattic/ai-evals`, but it is not published yet.

## Requirements

- WordPress 7.0 or newer
- PHP 7.4 or newer
- At least one AI provider configured under **Settings → Connectors** for prompt tasks or LLM judges
- A non-production WordPress environment, or the explicit opt-in described below

The harness uses the WordPress 7.0 `wp_ai_client_prompt()` API. It intentionally does not depend on the archived `wordpress/wp-ai-client` compatibility package or on a specific provider plugin.

## Install it as a development dependency

Until the package is published, add this checkout as a Composer path repository from the plugin under test:

```bash
composer config repositories.automattic-ai-evals path ../wp-eval-test-harness
composer require --dev automattic/ai-evals:@dev
```

Once published, installation becomes:

```bash
composer require --dev automattic/ai-evals
```

Keep eval registration code in the root plugin's `autoload-dev`, too:

```json
{
    "autoload-dev": {
        "files": [
            "evals/register.php"
        ]
    }
}
```

Then run `composer dump-autoload` during development.

## Register a suite

```php
<?php

use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Evaluator\ContainsText;
use Automattic\AiEvals\Evaluator\LlmJudge;
use Automattic\AiEvals\Registry;
use Automattic\AiEvals\Suite;
use Automattic\AiEvals\TaskResult;

add_action(
    'wp_ai_evals_init',
    static function (Registry $registry): void {
        $suite = Suite::make('my-plugin', 'My Plugin')
            ->describe('Quality and safety checks for the content assistant.')
            ->addCase(
                EvaluationCase::make('summarizes-post', 'Summarizes a post')
                    ->input([
                        'title' => 'Caching in WordPress',
                        'content' => 'A persistent object cache avoids repeated database work.',
                    ])
                    ->task(
                        static function (array $input): TaskResult {
                            $output = my_plugin_summarize($input['title'], $input['content']);

                            return TaskResult::fromOutput($output);
                        }
                    )
                    ->expected('cache')
                    ->evaluateWith(new ContainsText())
                    ->evaluateWith(
                        new LlmJudge(
                            'The answer is a faithful, concise summary with no unsupported claims.',
                            0.8
                        )
                    )
                    ->tag('smoke', 'quality')
            );

        $registry->register($suite);
    }
);
```

Multiple plugins can register independent suites. Duplicate suite and case IDs are rejected early.

For larger plugins, registration can load a directory instead of building every suite in one callback:

```php
add_action(
    'wp_ai_evals_init',
    static fn(Registry $registry) => $registry->loadDirectory(__DIR__ . '/suites')
);
```

The loader accepts standalone PHP files that return a `Suite`, or modular directories with this convention:

```text
evals/suites/
├── content-quality.php        # Returns a Suite (or iterable of suites).
└── agent-safety/
    ├── suite.php              # Returns the Suite manifest.
    └── cases/
        ├── refuses-lyrics.php # Returns an EvaluationCase.
        └── grounding/
            └── sources.php    # Case folders may be nested.
```

Case files may return one `EvaluationCase` or an iterable of cases. Files are loaded in stable lexical order and retain full PHP access to plugin callbacks, tasks, and custom evaluators.

Datasets can be parameterized into stable cases with ordinary PHP iterables and `Suite::addCases()`:

```php
$rows = [
    'caching' => ['input' => 'Explain object caching.', 'expected' => 'database'],
    'blocks' => ['input' => 'Explain a block theme.', 'expected' => 'Site Editor'],
];

$suite->addCases(
    (static function () use ($rows): iterable {
        foreach ($rows as $id => $row) {
            yield EvaluationCase::make($id)
                ->input($row['input'])
                ->task(static fn(string $input) => my_plugin_answer($input))
                ->expected($row['expected'])
                ->evaluateWith(new ContainsText())
                ->tag('dataset', 'quality');
        }
    })()
);
```

## Task types

Use a callback to evaluate the real public seam of your plugin. This is usually preferable to reproducing the plugin's prompt inside the eval:

```php
->task(static fn(array $input) => my_plugin_generate_answer($input))
```

For lower-level AI Client experiments, `PromptTask` calls the WordPress core client directly and captures its rich result metadata:

```php
use Automattic\AiEvals\Task\PromptTask;

->task(
    PromptTask::text(
        static fn(array $input): string => 'Summarize: ' . $input['content'],
        static fn($builder) => $builder
            ->using_temperature(0.1)
            ->using_model_preference('claude-sonnet-4-6', 'gemini-3.1-pro-preview', 'gpt-5.4')
    )
)
```

To test a server-side WordPress Ability, including its schema and permissions:

```php
use Automattic\AiEvals\Task\AbilityTask;

->task(new AbilityTask('my-plugin/summarize-post'))
```

## Evaluator types

| Type | Built-ins | Best for |
| --- | --- | --- |
| Deterministic | `ExactMatch`, `ContainsText`, `MatchesRegex`, `JsonSchema` | Contracts, structured output, invariants, golden values |
| Custom | `CallbackEvaluator` | Domain rules, similarity metrics, safety checks |
| Performance | `LatencyBelow` | Latency budgets |
| Model-graded | `LlmJudge` | Relevance, tone, groundedness, qualitative rubrics |

Each evaluator returns a normalized score from 0 to 1, pass/fail, a reason, and optional metadata. A case passes only when every evaluator passes; its score is the mean evaluator score. LLM judges also receive normalized task metadata, allowing cases to provide retrieved evidence, tool traces, and source context as part of the grading contract.

An LLM judge can use a single criterion or a named multi-item rubric. The harness requests an independent score and reason for every item, computes a weighted mean itself, and fails if the aggregate or any configured hard minimum is missed:

```php
use Automattic\AiEvals\Evaluator\LlmJudge;
use Automattic\AiEvals\Evaluator\Rubric;

->evaluateWith(
    new LlmJudge(
        Rubric::make()
            ->item('factuality', 'Facts match the reference answer.', 2.0, 0.8)
            ->item('grounding', 'Claims are supported by task metadata.', 2.0, 0.8)
            ->item('clarity', 'The answer is direct and easy to understand.'),
        0.8
    )
)
```

Items without an item-level minimum contribute to the weighted aggregate without becoming an independent pass/fail gate. Judge provider, model, token use, item scores, weights, thresholds, and reasons are all retained in the result.

## Admin app

**Tools → AI Evals** opens a WordPress-native app built with `@wordpress/element`, `@wordpress/components`, and `@wordpress/api-fetch`. It can select any combination of registered suites, filter cases with an autocomplete tag-token field, and repeat non-deterministic cases. Runs use short authenticated REST steps, so progress and each case result appear as soon as that case finishes without requiring WebSockets or a queue worker.

Completed reports are retained with their input, expected value, output, task metadata, tool list, token use, provider/model, evaluator reasons, and rubric breakdown. Select any previous run to reopen it, then expand a case to inspect its diagnostics. Reports are stored in non-autoloaded development options and bounded by `wp_ai_evals_history_limit`; use `wp_ai_evals_report_before_store` to redact or remove fields before persistence.

The TypeScript admin app lives in `src/admin`, with its components, API contracts, and utilities split into focused modules. Compiled assets are generated in `build/admin` and excluded from source control. Run `npm run build` after installing Node dependencies and whenever the admin source changes.

## Selecting subsets

The hierarchy is **suite → case**, and orthogonal subsets use **tags**. “Tags” are more flexible than “groups” because a case can independently be `smoke`, `quality`, `safety`, and `slow`.

```bash
# Discover everything registered by active plugins.
wp ai-evals list

# Run all cases.
wp ai-evals run

# Run suites, a specific case, or cases matching any tag.
wp ai-evals run my-plugin
wp ai-evals run --case=my-plugin/summarizes-post
wp ai-evals run --tag=smoke,safety

# Sample non-deterministic cases repeatedly.
wp ai-evals run --tag=quality --repeat=5

# Machine-readable CI output.
wp ai-evals run --format=json
wp ai-evals run --format=junit > ai-evals.xml
```

The command exits non-zero when any case fails or errors. `--fail-under=0.85` can enforce an additional aggregate score threshold.

## Keeping it out of production

The package uses three layers of protection:

1. It belongs in `require-dev`, never `require`.
2. Eval registration belongs in the root plugin's `autoload-dev`.
3. The runtime does not register Admin or WP-CLI surfaces when `wp_get_environment_type()` returns `production`.

Build distributed plugin artifacts with:

```bash
composer install --no-dev --classmap-authoritative
```

For CI environments that report themselves as production, opt in before Composer's autoloader is loaded:

```php
define('WP_AI_EVALS_ENABLED', true);
```

Defining `WP_AI_EVALS_ENABLED` as `false` always disables the harness.

## Design notes

- Connectors remain the source of provider discovery and credentials; the harness never stores API keys.
- Prompt tasks request a compatible model through the core AI Client and record the provider/model actually chosen.
- Admin and WP-CLI use the same registry, selection, runner, evaluator, and report objects.
- Recent summaries and bounded full reports are stored in non-autoloaded WordPress options for previous-run inspection.
- Composer's bootstrap has a process-wide guard so multiple vendored copies do not register duplicate UI or CLI surfaces.

See [Architecture](docs/architecture.md), [Roadmap](docs/roadmap.md), and the [complete registration example](examples/register-evals.php).

## Run the complete sample plugin

[`examples/hello-dolly-ai`](examples/hello-dolly-ai) is a working WordPress 7.0 chat block and agent named **Hello Dolly**. It uses three source-backed WordPress Abilities, an allowlisted function-calling loop, and two suites that exercise every task and evaluator style in this library.

```bash
composer install
npm install
npm run demo:setup
npm run env:start
```

The included `.wp-env.json` pins WordPress 7.0.2, mounts the sample, and installs the official OpenAI provider. See the [sample README](examples/hello-dolly-ai/README.md) for credential setup, Admin access, and eval commands.

## Development

```bash
composer install
npm install
npm run check
```

Licensed under GPL-2.0-or-later.
