# Release Process

Releases are cut in two phases, both automated, with a pull request between them.
Nothing is tagged or published until a human has merged that PR.

`develop` is the integration branch and the release branch — there is no separate
stable channel.

## Phase 1 — Prepare release

`.github/workflows/release.yml`, run manually from the Actions tab.

Inputs:

| Input | Meaning |
| --- | --- |
| `release_type` | `patch` (default), `minor`, or `major`. Bumps `VERSION`. |
| `version` | Explicit `x.y.z`. Overrides `release_type`. |
| `dry_run` | Compute the version and changelog, open no pull request. |

What it does:

1. Reads the root `VERSION` file, computes the next version, and fails if that tag
   already exists.
2. Writes the new `VERSION`.
3. Builds the `CHANGELOG.md` entry from the pull requests merged into `develop`
   since the previous tag, and prepends it.
4. Commits to `release/vX.Y.Z` and opens a PR titled `chore(release): vX.Y.Z`
   against `develop`.

It never pushes to `develop`, tags, or publishes. That keeps `develop` PR-only.

Re-running for the same version updates the existing release PR rather than
opening a second one.

## Review the release PR

The release PR is an ordinary pull request, so the Tests workflow runs against it.
Read the changelog entry, confirm the tests are green, then merge. **That merge is
the release gate** — nothing else checks the build before publishing.

## Phase 2 — Tag and release

`.github/workflows/tag-release.yml`, triggered by a push to `develop` that touches
`VERSION` — i.e. the release PR merging.

1. Builds and pushes `teem/tops`, `teem/tops-backup` and `teem/tops-installer` to
   Docker Hub for `linux/amd64` and `linux/arm64`, tagged `vX.Y.Z` and `latest`
   (the app image also gets `sha-<commit>`).
2. Creates the annotated tag `vX.Y.Z`. Tags are not subject to branch protection,
   so this works with `develop` locked down.
3. Cuts the GitHub release — which provides the downloadable source zip that
   `install.sh` pulls — using the `CHANGELOG.md` entry as the release notes.

Images are pushed **before** the tag exists, deliberately: tagging first would mean
a failed image build leaves a dangling tag, the next release would skip past that
version, and it would be burned with nothing published under it.

If `VERSION` already has a matching tag, the workflow is a no-op. `workflow_dispatch`
is available for re-running a failed publish; it reads `VERSION` the same way, so it
cannot cut a release no PR prepared.

## Secrets

| Secret | Used by | Required |
| --- | --- | --- |
| `DOCKERHUB_USERNAME` / `DOCKERHUB_TOKEN` | Phase 2 | Yes. A scoped Docker Hub access token, never an account password. |
| `RELEASE_TOKEN` | Phase 1 | Optional PAT with `repo` scope. Pull requests opened with the default `GITHUB_TOKEN` do not trigger other workflows, so without it the release PR arrives with no Tests run attached. |

## Version pinning

`install.sh` writes `TOPS_IMAGE_TAG=vX.Y.Z` into `.env` at install time, so a later
`docker compose up` cannot silently jump onto a newer `latest`. Moving forward is an
explicit `docker compose pull` after editing that value.
