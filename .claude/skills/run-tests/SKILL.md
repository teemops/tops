---
name: run-tests
description: Runs the Laravel test suite for this repo, including the Docker fallback when host PHP lacks pdo_sqlite, plus scan rule validation and Playwright E2E. Use whenever tests need to be run, a test failure needs diagnosing, or the suite fails to start.
---

# Running the Test Suite

All commands run from the Laravel root — `app/` in a normal checkout, or the worktree's
`app/` when working under `.claude/worktrees/`.

## Standard run

```bash
php artisan test
```

Filter to one test while iterating:

```bash
php artisan test --filter=OrganizationsControllerTest
```

## When the suite will not start

`phpunit.xml` points at SQLite `:memory:`. Two failures are common and both look like
something else:

**No `pdo_sqlite` on the host.** Some dev machines run a PHP build without it, and
installing needs sudo. Run through the dev image instead:

```bash
docker run --rm -v "$PWD:/w" -w /w --entrypoint php saas-app artisan test
```

Use `docker run` with the bind mount, not `docker exec teemops-app` — that container
bakes its code into the image and does not mount the source, so `exec` silently tests
the image's copy rather than the working tree.

**Missing `APP_KEY`.** This surfaces as a cascade of "There is already an active
transaction" errors rather than a clear missing-key message. Fix with:

```bash
cp .env.example .env && php artisan key:generate
```

In a fresh worktree, `vendor/` is not shared between checkouts — run `composer install`
before anything else.

## Scan definitions

Services and rules are contributed as JSON and fail quietly when wrong: a rule aimed at
data no `tasks.json` collects never matches, which is indistinguishable from a compliant
account. This check is offline and makes no AWS calls:

```bash
php artisan scan:validate-rules
```

## End-to-end

Playwright covers critical flows only. It needs the app running — see
`app/PLAYWRIGHT_SETUP.md` for the environment it expects.

```bash
npx playwright test
```

## Install scripts

Not PHP, and run from the repo root rather than `app/`. Proves the database passwords are
generated at install time and never shipped in the repo. Starts no containers and touches
no working-tree file — everything happens in a throwaway directory:

```bash
tests/install-secrets.test.sh
```

The Compose section of it needs `docker compose` and skips itself when that is missing.

## Reporting results

Report what actually happened. If tests fail, show the failure output and say which
tests failed; if the suite could not run at all, say that rather than implying a pass.
Never describe a filtered run as a full suite run.
