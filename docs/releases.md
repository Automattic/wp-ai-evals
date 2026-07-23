# Releases

The **Release package** GitHub Actions workflow publishes the Composer library
after the **Tests** workflow succeeds for a push to `trunk`.

## Versioning

A release is triggered only when the successful commit merged into `trunk`
changes the version in the root `package.json` relative to its first parent.
Maintainers choose the version explicitly by updating that file in the change
being merged. The workflow accepts stable semantic versions such as `0.2.0` and
uses that exact value for the release tag; it never calculates or increments a
version.

Merges that leave the version unchanged skip release packaging. If the declared
version's tag already belongs to another commit, the workflow fails rather than
publishing over it. Re-running the workflow for the same release commit reuses
its tag and repairs or replaces its downloadable assets.

## Built package

Development branches keep generated `build/` files ignored. During a release,
Actions checks out the exact commit that passed CI, validates it, builds the
Admin app, and creates a release-only commit containing:

- the compiled `build/admin` assets;
- a `VERSION` file;
- the release version in the root `package.json`.

The release tag points to that commit, so GitHub's tag archives include the
compiled Admin app even though `trunk` does not. The commit is reachable through
the tag only and is never pushed back to the development branch.

Each GitHub Release also includes a minimal Composer-ready ZIP named
`automattic-ai-evals-VERSION.zip` and its SHA-256 checksum. The ZIP contains the
Composer manifest, bootstrap, PHP library, license, README, version marker, and
compiled Admin assets; development dependencies, tests, source assets, and the
Hello Dolly example are omitted.
