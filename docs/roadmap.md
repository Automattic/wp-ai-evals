# Roadmap

The initial scaffold establishes the stable seams: suite and directory registration, tagged cases, task adapters, composable evaluators, multi-item judge rubrics, normalized reports, live Admin runs, previous-run inspection, WP-CLI, repeated sampling, and CI formats.

Likely next increments:

1. **Dataset loaders and parameterization** — JSON, JSONL, and CSV rows that expand into cases without losing stable IDs. PHP iterable and directory loading are available now.
2. **Baselines and regression comparison** — compare scores, latency, tokens, and selected model against an approved run.
3. **Statistical reports** — mean, median, percentiles, variance, pass-at-k, and confidence intervals for repeated samples.
4. **Cost budgets** — optional provider price catalogs and per-case/run ceilings, kept separate from core result capture.
5. **Traces and trajectories** — task/evaluator metadata, tools, sources, providers, models, and tokens are inspectable now; first-class nested spans and multi-turn event timelines remain future work.
6. **Fixtures and isolation** — per-suite setup/teardown, database transactions where possible, and destructive-ability safeguards.
7. **Async Admin runs** — live resumable case-at-a-time sessions are available now; background workers, cancellation during an active case, and parallel execution remain future work.
8. **Redaction and export policy** — configurable filtering before outputs or inputs are persisted or exported.
9. **Pluggable storage** — option-backed local history by default, with file, database-table, and remote experiment-store adapters.
10. **Quality gates** — evaluator weighting, informational evaluators, per-suite thresholds, and controlled flaky-case policies.

The public vocabulary should remain **suite**, **case**, **task**, **evaluator**, **tag**, and **run** as these features are added.
