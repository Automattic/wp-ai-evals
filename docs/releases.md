# Releases

The **Release package** GitHub Actions workflow publishes the Composer library
after the **Tests** workflow succeeds for a push to `trunk`.

## Versioning

Normal `trunk` releases increment the latest stable tag's patch component. If
the repository has no stable tags yet, the first release uses the version in
the root `package.json`.

Maintainers can run the workflow manually and choose a patch, minor, or major
bump. Re-running it for a commit that already has a release tag reuses that tag
and repairs or replaces its downloadable assets instead of creating another
version.

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
