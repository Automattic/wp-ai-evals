# Roadmap

The initial scaffold establishes the stable seams: suite and directory registration, tagged cases, task adapters, composable evaluators, multi-item judge rubrics, normalized reports, live Admin runs, previous-run inspection, WP-CLI, repeated sampling, and CI formats.

Exact model experiments are also available now. The harness discovers dynamic model metadata from configured WordPress AI Client providers, accepts explicit `provider:model` targets, applies no-fallback overrides to model-aware tasks, keeps judge selection independent, verifies requested versus resolved models, and compares score, pass rate, latency, candidate-only token usage, and optional provider-reported cost across live and stored run variants.

Likely next increments:

1. **Dataset loaders and parameterization** — JSON, JSONL, and CSV rows that expand into cases without losing stable IDs. PHP iterable and directory loading are available now.
2. **Baselines and regression comparison** — compare scores, latency, tokens, model targets, and resolved models against an approved run.
3. **Experiment provenance** — record WordPress, PHP, plugin, prompt, dataset, and optional source-revision metadata so historical comparisons remain reproducible.
4. **Statistical reports** — mean, median, percentiles, variance, pass-at-k, and confidence intervals for repeated samples.
5. **Traces and conversations** — task/evaluator metadata, tools, sources, providers, models, and tokens are inspectable now; first-class nested spans, expected tool trajectories, and multi-turn conversation evaluation remain future work.
6. **Human review and annotations** — structured manual scores and notes that can become regression cases or reference answers.
7. **Cost budgets** — optional provider price catalogs and per-case/run ceilings, kept separate from core result capture.
8. **Fixtures and isolation** — per-suite setup/teardown, database transactions where possible, and destructive-ability safeguards.
9. **Async Admin runs** — live resumable case-at-a-time sessions are available now; background workers, cancellation during an active case, and parallel execution remain future work.
10. **Redaction and export policy** — configurable filtering before outputs or inputs are persisted or exported.
11. **Pluggable storage** — option-backed local history by default, with file, database-table, and remote experiment-store adapters.
12. **Quality gates** — evaluator weighting, informational evaluators, per-suite thresholds, and controlled flaky-case policies.

The public vocabulary should remain **suite**, **case**, **task**, **evaluator**, **tag**, and **run** as these features are added.
