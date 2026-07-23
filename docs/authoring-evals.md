# Authoring evaluation suites

## Vocabulary

- A **suite** is a namespaced collection owned by one plugin or feature.
- A **case** combines one input, task, expected value, evaluator set, and metadata record.
- A **task** invokes the system under test.
- An **evaluator** scores task output from 0 to 1 and returns pass/fail plus a reason.
- A **tag** is an orthogonal selector such as `smoke`, `safety`, `quality`, or `slow`.

The hierarchy is **suite → case**. Tags provide flexible subsets without forcing each case into one group.

## Organizing suites

Small plugins can register suites directly during `wp_ai_evals_init`. Larger plugins can load a directory:

```php
add_action(
    'wp_ai_evals_init',
    static fn(Registry $registry) => $registry->loadDirectory(__DIR__ . '/suites')
);
```

The loader accepts standalone PHP files that return a `Suite`, or modular directories:

```text
evals/suites/
├── content-quality.php        # Returns a Suite or iterable of suites.
└── agent-safety/
    ├── suite.php              # Returns the Suite manifest.
    └── cases/
        ├── refuses-lyrics.php # Returns an EvaluationCase.
        └── grounding/
            └── sources.php    # Case folders may be nested.
```

Case files may return one `EvaluationCase` or an iterable of cases. Files load in stable lexical order and retain normal PHP access to plugin callbacks, tasks, and custom evaluators.

Datasets can expand into stable cases with ordinary iterables and `Suite::addCases()`:

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

Use a callback to exercise the real public seam of a plugin:

```php
->task(static fn(array $input) => my_plugin_generate_answer($input))
```

For direct AI Client experiments, `PromptTask` captures rich result metadata:

```php
use Automattic\AiEvals\Task\PromptTask;

->task(
    PromptTask::text(
        static fn(array $input): string => 'Summarize: ' . $input['content'],
        static fn($builder) => $builder
            ->using_temperature(0.1)
            ->using_model_preference(
                'claude-sonnet-4-6',
                'gemini-3.1-pro-preview',
                'gpt-5.4'
            )
    )
)
```

To test a server-side WordPress Ability, including its schema and permissions:

```php
use Automattic\AiEvals\Task\AbilityTask;

->task(new AbilityTask('my-plugin/summarize-post'))
```

## Evaluators

| Type | Built-ins | Best for |
| --- | --- | --- |
| Deterministic | `ExactMatch`, `ContainsText`, `MatchesRegex`, `JsonSchema` | Contracts, structured output, invariants, golden values |
| Custom | `CallbackEvaluator` | Domain rules, similarity metrics, safety checks |
| Performance | `LatencyBelow` | Latency budgets |
| Model-graded | `LlmJudge` | Relevance, tone, groundedness, qualitative rubrics |

A case passes only when every evaluator passes. Its score is the mean evaluator score.

An LLM judge can use one criterion or a named, weighted rubric:

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

The harness asks for an independent score and reason for every item and computes the weighted aggregate itself. Items with a minimum also become independent pass/fail gates.

## Exact model comparisons

Production plugin behavior should normally use `using_model_preference()` and allow WordPress to select a compatible fallback. An evaluation comparison is stricter: every variant must use the exact requested `provider:model` pair or error.

`PromptTask` automatically honors a run-level target. A callback or multi-step agent that creates its own prompt builders should register a model-aware task:

```php
use Automattic\AiEvals\EvaluationContext;

EvaluationCase::make('agent-answer')
    ->input('Explain object caching.')
    ->modelTask(
        static function (
            string $input,
            EvaluationContext $context
        ): TaskResult {
            return my_plugin_run_agent($input, $context->getModelTarget());
        },
        'agent'
    );
```

Pass the optional target to every prompt step:

```php
$builder = wp_ai_client_prompt($prompt)
    ->using_model_preference('gpt-5.4', 'claude-sonnet-4-6');

if (null !== $modelTarget) {
    $builder = $modelTarget->apply($builder);
}
```

The runner verifies that model-aware tasks report the requested provider and model. Model-independent tasks execute once; model-aware tasks execute for every target and repetition. The judge uses its own optional target so candidate comparisons can keep grading fixed.

## Reported costs

The WordPress AI Client standardizes tokens but not monetary cost. This package does not maintain a price table or estimate billing.

When a provider or integration reports cost, tasks can return normalized metadata:

```php
TaskResult::fromOutput($answer, [
    'cost' => [
        'amount' => 0.00125,
        'currency' => 'USD',
        'source' => 'provider',
    ],
]);
```

Use `wp_ai_evals_ai_result_reported_cost` to normalize provider-specific result data. Return `null`, a `ReportedCost`, or the array shape above. Totals remain split between task and evaluator usage so candidate comparisons exclude judge cost.

## Selecting subsets

```bash
# One suite.
wp ai-evals run my-plugin

# One exact case.
wp ai-evals run --case=my-plugin/summarizes-post

# Any matching tag.
wp ai-evals run --tag=smoke,safety

# Repeated sampling.
wp ai-evals run --tag=quality --repeat=5

# Exact candidates with a fixed judge.
wp ai-evals run --tag=quality \
  --model=openai:gpt-5.4,anthropic:claude-sonnet-4-6 \
  --judge-model=google:gemini-3.1-pro-preview
```
