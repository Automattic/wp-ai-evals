# Releases

The **Release package** GitHub Actions workflow publishes the Composer library
after the **Tests** workflow succeeds for a push to `trunk`.

## Versioning

A release is triggered whenever `trunk` declares a version in the root
`package.json` that has not been tagged yet. Maintainers choose the version
explicitly by updating that file. The workflow accepts stable semantic versions
such as `0.2.0` and uses that exact value for the release tag; it never
calculates or increments a version.

The release tag is the source of truth for what has already shipped, not the
diff against the previous commit. That keeps the decision correct no matter how
the change reaches `trunk`: a merge commit, a squash, or a push whose version
bump is followed by further commits all release the declared version exactly
once. Pushes that declare an already-tagged version skip release packaging.
Re-running the workflow for the same release commit reuses its tag and repairs
or replaces its downloadable assets.

Because the tag decides, reverting `package.json` to a version that has already
been released does not republish it. Cut a new version instead.

## Built package

Development branches keep generated `build/` files ignored. During a release,
Actions checks out the exact commit that passed CI, validates it, builds the
Admin app, and creates a release-only commit containing:

- the compiled `build/admin` assets;
- a `VERSION` file;
- the release version in the root `package.json`.

The release tag points to that commit, so GitHub's tag archives include the
compiled Admin app even though `trunk` does not. The commit is reachable through
the tag only and is never pushed back to the development branch. Packagist uses
GitHub's generated archive for that tag, and the root `.gitattributes` limits
the archive to the same Composer-ready files assembled by the release packager.

Each GitHub Release also includes a minimal Composer-ready ZIP named
`automattic-wp-ai-evals-VERSION.zip` and its SHA-256 checksum. The ZIP contains the
Composer manifest, bootstrap, PHP library, license, README, version marker, and
compiled Admin assets. It is provided as a directly downloadable, checksummed
release asset; normal Packagist installs use the equivalent generated tag
archive. Development dependencies, tests, source assets, and the Hello Dolly
example are omitted from both.
