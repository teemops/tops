---
name: feature-development
description: Drives a new feature through the team's six-phase process — Discovery, Definition, Design, Development, Review, Deployment. Use whenever the user asks to add, build, or implement a feature, capability, page, endpoint, or user-facing behaviour, and before writing any feature code. Not for bugfixes, refactors, or infrastructure work.
---

# Feature Development

You are running the process in `docs/processes/feature-development.md`. Your job is to
keep the work in phase order and refuse to skip ahead — most of the value here is
stopping implementation from starting before the problem and the acceptance criteria
are written down.

Work through the phases with the user. Do not dump all six on them at once; take the
current phase, do it properly, get agreement, move on.

## Before anything else

Confirm this is actually a feature. Bugfixes, refactors, dependency bumps and
infrastructure changes follow the practices docs but do not need this process — say so
and exit rather than imposing ceremony on a two-line fix.

## Phase 1 — Discovery

Establish the user problem, in the user's words, before any solution is discussed.

Ask what the user is trying to accomplish, what happens today, and what evidence
exists that this is worth solving. Then state the simplest solution you can see, even
if it is "configuration change" or "documentation" rather than code.

Do not proceed until the problem is stated independently of the proposed solution.

## Phase 2 — Definition

Write the user story using `docs/templates/user-story-template.md`. Read that template
before writing — do not invent a format.

The story needs:
- expected behaviour, stated so it can be tested
- acceptance criteria in Given-When-Then form, covering the happy path
- the critical error cases (authorization, invalid input, missing data)
- success metrics
- an explicit out-of-scope list

Save it under `docs/features/` and get the user's agreement on it. **This is the gate.**
Do not write feature code until an agreed story exists.

## Phase 3 — Design

Design before code, proportional to the change. Cover the user experience, the data
model if the schema moves, the Inertia pages and components, and the endpoints if an
external API is involved.

Apply the decision framework from `docs/practices/product.md` out loud:

1. Does this make it simpler for the end user?
2. Does this make it simpler to maintain?
3. Can we achieve the same outcome with less complexity?
4. What are we willing to remove to add this?

Consider edge cases, then deliberately decide which ones you are *not* handling in v1.
Present the design and get feedback before implementing.

## Phase 4 — Development

Build the smallest working version first, then add incrementally. Write tests as you
go, not at the end.

Follow the patterns already in the files you are editing. Read
`docs/practices/code-quality.md`, `docs/practices/security.md` and
`docs/practices/database.md` when the change touches their territory.

Hard requirements for this repo:
- every query on tenant data scoped by `organization_id`
- a feature test proving cross-organization access is denied
- server-side validation on all input
- no secrets committed
- `php artisan scan:validate-rules` after touching scan services or rules

## Phase 5 — Review

Self-review before asking anyone else. Run the tests, run the linter, then walk
`docs/processes/practices-checklist.md` against the diff — the `practices-review`
skill does this if you want it driven.

Verify each acceptance criterion from the story is actually met, by name. Update the
documentation the change invalidates.

## Phase 6 — Deployment

Confirm the feature works in staging, that monitoring will surface its failures, and
that there is a rollback path if the change is risky. Note how user feedback will be
collected.

## Quality gate

Before calling the feature done, check every line of the gate in
`docs/processes/feature-development.md`. Report honestly — if a box is unticked, say
which and why rather than quietly ticking it.

## Anti-patterns

Stop and name it if the work drifts into: building "just in case", over-engineering
v1, skipping tests for speed, optimizing before measuring, or adding a feature to
paper over a problem with an existing feature.
