# Contributing

The package supports PHP 7.4 and WordPress 7.0 or newer. Keep the core provider-agnostic, use the WordPress AI Client rather than provider APIs, and add unit tests for behavior changes.

Run before submitting changes:

```bash
composer check
```

Public API changes should preserve the vocabulary and layering documented in `docs/architecture.md`.
