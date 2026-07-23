# Project guidance

## Purpose

This repository contains `automattic/ai-evals`, a development-only Composer
library for evaluating WordPress plugins built with the WordPress AI Client,
Connectors API, and Abilities API. It also contains the Hello Dolly AI example
plugin used for integration and end-to-end testing.

Keep the harness:

- Provider-agnostic. Use WordPress AI APIs rather than provider SDKs or APIs.
- Safe to install as a Composer `require-dev` dependency and easy to omit from
  production builds.
- Compatible with PHP 7.4 or newer and WordPress 7.0 or newer.
- Able to support multiple independently registered suites without assuming
  that one plugin owns the process.

## Repository layout

- `bootstrap.php`: Composer bootstrap and environment guard.
- `includes/`: PHP library, Admin REST API, WP-CLI command, storage, tasks,
  evaluators, and report formatters under the `Automattic\AiEvals` namespace.
- `src/admin/`: TypeScript and SCSS source for the WordPress Admin application.
- `build/`: Generated Admin assets. Do not commit this directory.
- `tests/`: PHPUnit unit tests for the library.
- `examples/hello-dolly-ai/`: Functional sample plugin and its test harness.
- `docs/`: Architecture, authoring guidance, roadmap, and documentation assets.

## Implementation conventions

- Preserve the suite → case hierarchy and use tags as orthogonal selectors.
- Exercise plugin public seams through tasks. Keep task execution separate from
  evaluator scoring and report persistence.
- Model comparisons must honor exact `provider:model` targets. Keep candidate
  model selection independent from judge model selection.
- Do not hardcode provider pricing. Record monetary cost only when a provider or
  integration reports it.
- Treat run inputs, outputs, metadata, and reports as potentially sensitive.
  Preserve the existing filtering hooks and avoid exposing secrets in Admin,
  CLI, REST, fixtures, screenshots, or committed history.
- Keep Admin code in `src/admin` and use WordPress packages such as
  `@wordpress/element`, `@wordpress/components`, `@wordpress/api-fetch`,
  `@wordpress/i18n`, and `@wordpress/date`; do not import React directly.
- Follow WordPress capability, nonce, sanitization, escaping, and localization
  conventions for PHP, REST, and Admin changes.
- Maintain PHP 7.4 syntax compatibility. Do not introduce PHP 8-only language
  features.
- Add or update tests for behavior changes. Public API changes should preserve
  the vocabulary and layering in `docs/architecture.md`.
- Update authoring or architecture documentation when changing registration,
  tasks, evaluators, reports, model selection, storage, REST, CLI, or Admin
  behavior.

## Commands

Install dependencies:

```bash
composer install
npm install
```

Run the full library validation:

```bash
npm run check
```

Useful focused checks:

```bash
composer check
npm run lint:js
npm run lint:css
npm run typecheck
npm run build
```

Prepare and validate the sample plugin:

```bash
npm run demo:setup
npm run demo:check
```

Run the local WordPress environment:

```bash
npm run env:start
npm run env:status
npm run env:stop
```

## Before finishing a change

- Run the smallest relevant check while iterating and `npm run check` before
  handing off a completed library or Admin change.
- Run `npm run demo:check` when behavior shared with the example plugin changes.
- Exercise the affected workflow in `wp-env` for Admin, REST, connector, model
  comparison, live-run, persistence, or block changes.
- Confirm generated `build/`, dependency directories, local credentials,
  temporary reports, and WordPress runtime data are not staged.
- Keep commits focused and include only files belonging to the requested change.
