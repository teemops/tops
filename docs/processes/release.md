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

1. Computes the next version and fails if that tag already exists. The bump is
   applied to whichever is higher, the root `VERSION` file or the newest `vX.Y.Z`
   tag. They are normally the same; they diverge when a publish failed part-way
   and spent a version without committing it, and bumping from `VERSION` alone
   would then reissue a number that already has images on Docker Hub.
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
Read the changelog entry, confirm the tests are green, then merge.

Phase 2 independently re-checks Tests against the merge commit and refuses to
publish a red one, so this review is about the changelog and the timing — not
about being the last thing standing between a broken build and Docker Hub.

## Phase 2 — Tag and release

`.github/workflows/tag-release.yml`, triggered by a push to `develop` that touches
`VERSION` — i.e. the release PR merging.

1. Waits for the Tests workflow on the merge commit and fails if it is not green.
   Tests runs on the same push, so this normally waits a minute or two.
2. Builds and pushes `teem/tops`, `teem/tops-backup` and `teem/tops-installer` to
   Docker Hub for `linux/amd64` and `linux/arm64`, tagged `vX.Y.Z` (the app image
   also gets `sha-<commit>`).
3. Creates the annotated tag `vX.Y.Z`. Tags are not subject to branch protection,
   so this works with `develop` locked down.
4. Cuts the GitHub release — which provides the downloadable source zip that
   `install.sh` pulls — using the `CHANGELOG.md` entry as the release notes.
5. Moves `latest` onto `vX.Y.Z`, by re-pointing the manifest that step 2 already
   pushed. No rebuild, and the digest matches the version tag exactly.

Images are pushed **before** the tag exists, deliberately: tagging first would mean
a failed image build leaves a dangling tag, the next release would skip past that
version, and it would be burned with nothing published under it.

`latest` is the exception, and moves **last**. It is the tag users can land on
without asking for it, so it must never point at a build with no tag and no
release behind it — which is what a run that died before tagging used to leave.

If `VERSION` already has a matching tag, the workflow is a no-op. `workflow_dispatch`
is available for re-running a failed publish; it reads `VERSION` the same way, so it
cannot cut a release no PR prepared.

## When a publish fails part-way

Phase 2 can die after pushing images but before tagging. Docker Hub then holds a
`vX.Y.Z` that git and GitHub know nothing about.

Re-run Phase 2 with `workflow_dispatch` — it reads `VERSION`, sees no matching tag,
and finishes the job. That is the normal fix, and it republishes the same version.

If the commit is no longer the one you want to ship, do not just bump past the
spent version and leave the orphan behind. Either tag the commit those images were
built from (the app image carries `sha-<commit>`, so it is always identifiable) or
delete the orphaned Docker Hub tags. Phase 1 will refuse to reissue the number once
a tag exists for it.

## Secrets

| Secret | Used by | Required |
| --- | --- | --- |
| `DOCKERHUB_USERNAME` / `DOCKERHUB_TOKEN` | Phase 2 | Yes. A scoped Docker Hub access token, never an account password. |
| `RELEASE_TOKEN` | Phase 1 | Optional PAT with `repo` scope. Pull requests opened with the default `GITHUB_TOKEN` do not trigger other workflows, so without it the release PR arrives with no Tests run attached. Convenience only — Phase 2 re-checks Tests on the merge commit either way. |

## Version pinning

`install.sh` writes `TOPS_IMAGE_TAG=vX.Y.Z` into `.env` at install time, so a later
`docker compose up` cannot silently jump onto a newer `latest`. Moving forward is an
explicit `docker compose pull` after editing that value.
