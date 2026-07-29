---
name: practices-review
description: Reviews the working diff against the team's practices checklist before a PR — simplicity, security and multi-tenancy, code quality, database, testing, architecture. Use when the user asks to review changes, check practices, self-review, or prepare a pull request.
---

# Practices Review

Review the current diff against `docs/processes/practices-checklist.md`. This is a
practices review, not a bug hunt — for defect hunting use `/code-review`.

## Gather the diff

Compare against the base branch, which is `develop`:

```bash
git diff develop...HEAD --stat && git diff develop...HEAD
```

If the branch has no upstream, fall back to `git diff --stat` and `git diff` for
uncommitted work. Read the full diff before judging anything.

## Review

Open `docs/processes/practices-checklist.md` and work its sections against what
actually changed. Skip sections the diff does not touch — do not pad the report with
"N/A" lines.

Weight the review toward the things that cause real damage in this repo:

**Multi-tenancy.** Every new or modified query on tenant data scoped by
`organization_id`. Every new endpoint covered by a test proving cross-organization
access is denied. Background jobs carry the organization explicitly, since they have
no session.

**Security.** Server-side validation on all input. Authorization on every route, not
just authentication. No secrets in code, config, fixtures or test data. No user input
concatenated into SQL. Internal errors not leaked to clients.

**Simplicity.** This is the practice most often violated invisibly. Ask of each
addition: is there a hypothetical requirement being served here? An abstraction with
one implementation? An index or cache added without profiling? A config option where
a sensible default would do? Name these explicitly — nobody else will.

**Testing.** Tests present for the happy path and the error cases that matter. Tests
verify behaviour rather than implementation. No trivial or framework-testing tests
added for coverage's sake.

**Database.** Migrations reversible where possible, data migrations separate from
schema. No N+1 introduced. No premature denormalization.

**Code quality.** Names that explain themselves. Small, focused functions. Comments
explaining why rather than what. No commented-out code. Existing patterns followed.

## Verify before reporting

Check each finding against the actual file rather than reporting from memory of the
diff. A scope that looks missing is often applied in a parent scope or a global
Eloquent scope — confirm before flagging.

## Report

Group by practice area, most serious first. For each finding give the file and line,
what practice it violates, and the concrete fix. Separate genuine violations from
suggestions, and say plainly when the diff is clean rather than manufacturing findings.

Finish with the Quality Gate from `docs/processes/feature-development.md`, stating
which items are met and which are not.
