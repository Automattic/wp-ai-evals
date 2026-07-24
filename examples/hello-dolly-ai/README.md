# Hello Dolly AI

Hello Dolly AI is a development-only sample plugin for WordPress 7.0. It provides a functioning dynamic chat block, a small connector-backed agent, three read-only WordPress Abilities, and two eval suites registered through `automattic/wp-ai-evals` as a Composer development dependency.

The sample is intentionally narrow: it helps signed-in users learn about Dolly Parton's life, career, signature songs, and philanthropy. Its source-backed knowledge functions keep the model grounded, and its system instruction prohibits reproducing song lyrics.

## What it demonstrates

- A block built from `block.json`, with a server-rendered front end and editable greeting.
- A logged-in REST chat route protected by a REST nonce, capability check, input limits, and a per-user rate limit.
- An agent loop built with the WordPress AI Client and an explicit `WP_AI_Client_Ability_Function_Resolver` allowlist.
- Provider-neutral model selection through the Connectors API; no key or model ID is stored by this plugin.
- Portable model preferences for tool use, with automatic fallback when the preferred model is unavailable.
- Fact, timeline, and song-note Abilities with JSON schemas, permission callbacks, and read-only annotations.
- Multiple registered eval suites loaded from suite manifests and individual case files, plus parameterized datasets, metadata, repetitions, and tag-based subsets.
- Callable, Ability, and direct Prompt tasks.
- Exact cross-provider model targets, model-aware agent callbacks, comparison summaries, and an independently pinned judge model.
- Exact, substring, regex, JSON Schema, callback, latency, and LLM-judge evaluators, including a weighted multi-item grounding rubric.

## Coverage map

The sample doubles as an integration fixture. Its two suites contain nine cases, and its PHPUnit tests verify the surrounding WordPress integration.

| Area | Covered features |
| --- | --- |
| Tasks | Callable, Ability, direct text Prompt, and model-aware agent callbacks |
| Evaluators | Exact match, contains text, regex, JSON Schema, callback, latency, and LLM judge |
| Organization | Multiple suites, directory manifests, individual case files, iterable datasets, tags, metadata, and repetitions |
| Model experiments | Exact provider/model targets, candidate comparisons, and an independently pinned judge |
| Diagnostics | Tool calls, token usage, latency, score details, errors, and provider-reported cost when available |
| WordPress integration | Ability registration and permissions, activation, plugin action links, REST route security and rate limiting, and dynamic block states |

## Start it with wp-env

From the repository root, with Docker running:

```bash
composer install
corepack enable pnpm
pnpm install
pnpm demo:setup
pnpm env:start
```

Open <http://localhost:8888> and sign in at `/wp-admin` with `admin` / `password`. Activation creates and publishes `/hello-dolly-ai/` with the chat block already inserted.

The environment pins WordPress 7.0.2 and activates the official OpenAI and Anthropic provider plugins. Add API keys in an ignored `.wp-env.override.json` at the repository root:

```json
{
    "config": {
        "OPENAI_API_KEY": "your-development-key",
        "ANTHROPIC_API_KEY": "your-development-key"
    }
}
```

Configure only the providers you intend to use, then restart wp-env after changing the override. You can add another WordPress AI provider that supports text generation, function calls, and structured JSON output; the sample plugin itself is provider-agnostic.

The tool-using agent currently prefers `claude-sonnet-4-6` when it is available. Anthropic provider 1.0.3 does not preserve Claude Sonnet 5's signed adaptive-thinking blocks across a tool round trip. This is a preference rather than a requirement, so WordPress still falls back to another compatible configured model or provider.

`pnpm demo:setup` stages a minimal copy of the library for the demo's local Composer path repository before installing it. This is necessary because the demo plugin is nested inside the library repository; the generated `.packages` directory and `vendor` install are both ignored by Git.

## Run the evals

The WordPress-native Admin app is at **Tools → AI Evals**. It starts with all cases selected; use **+ Add filter** to narrow by suite, tag, or case ID. The compact **Settings** list shows the current candidate models, judge, and repetitions; choose **Change** beside one to edit it. Filters appear as removable pills, and the first available preferred judge is selected automatically. Watch each case arrive live; comparison runs group every model beneath the shared test in aligned result, score, latency, token, optional provider-reported cost, and tool columns, with best values and deltas visible before expanding the full diagnostics. Completed runs remain selectable in Previous runs. WP-CLI uses the same registry and runner:

```bash
# Inventory both suites and their tags.
pnpm exec wp-env run cli wp --user=admin ai-evals list

# Inspect exact provider:model targets currently exposed by configured providers.
pnpm exec wp-env run cli wp --user=admin ai-evals models

# Fast deterministic contracts, including the registered Abilities.
pnpm exec wp-env run cli wp --user=admin ai-evals run hello-dolly-knowledge --tag=offline

# Connector-backed agent and model-quality checks.
pnpm exec wp-env run cli wp --user=admin ai-evals run hello-dolly-agent --tag=live

# Sample non-deterministic safety tests repeatedly and emit JUnit.
pnpm exec wp-env run cli wp --user=admin ai-evals run --tag=safety --repeat=3 --format=junit

# Compare the same live agent cases with an independent fixed judge.
pnpm exec wp-env run cli wp --user=admin ai-evals run hello-dolly-agent \
  --model=openai:gpt-5.4,anthropic:claude-sonnet-4-6 \
  --judge-model=openai:gpt-5.4
```

Use `suite → case` for hierarchy and tags for cross-cutting subsets. For example, `smoke`, `safety`, `ability`, and `model-graded` can overlap without forcing a case into one group.

The `hello-dolly-knowledge` suite runs without an AI key. The `hello-dolly-agent` suite and its LLM judges require a configured connector. Ability cases need `--user=admin` in WP-CLI because the abilities correctly enforce the `read` capability.

## Development checks

```bash
pnpm --filter hello-dolly-ai-example check
pnpm --filter hello-dolly-ai-example test:php
```

`composer install --no-dev` does not install `automattic/wp-ai-evals` or autoload `evals/register.php`. A production plugin build should use `--no-dev` and omit this sample entirely.

## Curated references

The knowledge functions link their output to Dolly Parton's official biography and Imagination Library history, the Country Music Hall of Fame, and Library of Congress material. The sample stores only short factual summaries and themes, never lyrics.

- [Dolly Parton — Life & Career](https://dollyparton.com/about-dolly-parton)
- [Country Music Hall of Fame — Dolly Parton](https://countrymusichalloffame.org/hall-of-fame/dolly-parton)
- [Library of Congress — “Coat of Many Colors” essay](https://www.loc.gov/static/programs/national-recording-preservation-board/documents/Coat-of-Many-Colors_Hubbs.pdf)
- [Library of Congress recording-registry announcement](https://www.loc.gov/news/2012/12-107.html)
