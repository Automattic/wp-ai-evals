# Hello Dolly AI

Hello Dolly AI is a development-only sample plugin for WordPress 7.0. It provides a functioning dynamic chat block, a small connector-backed agent, three read-only WordPress Abilities, and two eval suites registered through `automattic/ai-evals` as a Composer development dependency.

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
- Exact, substring, regex, JSON Schema, callback, latency, and LLM-judge evaluators, including a weighted multi-item grounding rubric.

## Start it with wp-env

From the repository root, with Docker running:

```bash
composer install
npm install
npm run demo:setup
npm run env:start
```

Open <http://localhost:8888> and sign in at `/wp-admin` with `admin` / `password`. Activation creates and publishes `/hello-dolly-ai/` with the chat block already inserted.

The environment pins WordPress 7.0.2 and activates the official OpenAI provider plugin. Add an OpenAI API key in an ignored `.wp-env.override.json` at the repository root:

```json
{
    "config": {
        "OPENAI_API_KEY": "your-development-key"
    }
}
```

Restart wp-env after changing the override. You can swap the provider URL in `.wp-env.json` for another WordPress AI provider that supports text generation, function calls, and structured JSON output; the sample plugin itself is provider-agnostic.

The tool-using agent currently prefers `claude-sonnet-4-6` when it is available. Anthropic provider 1.0.3 does not preserve Claude Sonnet 5's signed adaptive-thinking blocks across a tool round trip. This is a preference rather than a requirement, so WordPress still falls back to another compatible configured model or provider.

`npm run demo:setup` stages a minimal copy of the library for the demo's local Composer path repository before installing it. This is necessary because the demo plugin is nested inside the library repository; the generated `.packages` directory and `vendor` install are both ignored by Git.

## Run the evals

The WordPress-native Admin app is at **Tools → AI Evals**. Select one or more suites, use the autocomplete tag field to add cross-cutting subsets as pills, watch each case arrive live, and expand its input, output, tokens, tools, metadata, and evaluator details. Completed runs remain selectable in Previous runs. WP-CLI uses the same registry and runner:

```bash
# Inventory both suites and their tags.
npx wp-env run cli wp --user=admin ai-evals list

# Fast deterministic contracts, including the registered Abilities.
npx wp-env run cli wp --user=admin ai-evals run hello-dolly-knowledge --tag=offline

# Connector-backed agent and model-quality checks.
npx wp-env run cli wp --user=admin ai-evals run hello-dolly-agent --tag=live

# Sample non-deterministic safety tests repeatedly and emit JUnit.
npx wp-env run cli wp --user=admin ai-evals run --tag=safety --repeat=3 --format=junit
```

Use `suite → case` for hierarchy and tags for cross-cutting subsets. For example, `smoke`, `safety`, `ability`, and `model-graded` can overlap without forcing a case into one group.

The `hello-dolly-knowledge` suite runs without an AI key. The `hello-dolly-agent` suite and its LLM judges require a configured connector. Ability cases need `--user=admin` in WP-CLI because the abilities correctly enforce the `read` capability.

## Development checks

```bash
npm run check --prefix examples/hello-dolly-ai
vendor/bin/phpunit -c examples/hello-dolly-ai/phpunit.xml.dist
```

`composer install --no-dev` does not install `automattic/ai-evals` or autoload `evals/register.php`. A production plugin build should use `--no-dev` and omit this sample entirely.

## Curated references

The knowledge functions link their output to Dolly Parton's official biography and Imagination Library history, the Country Music Hall of Fame, and Library of Congress material. The sample stores only short factual summaries and themes, never lyrics.

- [Dolly Parton — Life & Career](https://dollyparton.com/about-dolly-parton)
- [Country Music Hall of Fame — Dolly Parton](https://countrymusichalloffame.org/hall-of-fame/dolly-parton)
- [Library of Congress — “Coat of Many Colors” essay](https://www.loc.gov/static/programs/national-recording-preservation-board/documents/Coat-of-Many-Colors_Hubbs.pdf)
- [Library of Congress recording-registry announcement](https://www.loc.gov/news/2012/12-107.html)
