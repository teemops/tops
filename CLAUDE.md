# TOPS SaaS — Agent Guide

Read this first. It is loaded into every session. It is deliberately short: the
authoritative rules live in `docs/practices/` and `docs/processes/`, and this file
tells you when to open them.

## Stack

Laravel 11 + Inertia + Vue monolith in `app/`. MySQL in production, SQLite `:memory:`
for tests. AWS SDK for scanning; queue workers via SQS. Docker Compose for local and
self-hosted deployment. Infrastructure in `infra/`, CloudFormation in `templates/`.

## Non-negotiables

These are the rules that cause real damage when broken. Everything else is judgement.

- **Multi-tenant isolation.** Every query touching tenant data is scoped by
  `organization_id`. Every new endpoint gets a test proving one org cannot read
  another's rows. See `docs/practices/security.md`.
- **No raw SQL with user input.** Eloquent or the query builder, always parameterized.
- **No secrets in the repo.** Not in code, config, fixtures, or test data.
- **Authorization on every route.** Authenticate *and* authorize; never trust the client.
- **Tests ship with the change**, not after it. A feature without a test is not done.
- **Migrations are reversible** where possible, and separate from data migrations.

## The decision framework

Before adding anything — a feature, a dependency, an abstraction, a config option —
answer these four (`docs/practices/product.md`):

1. Does this make it simpler for the end user?
2. Does this make it simpler to maintain?
3. Can we achieve the same outcome with less complexity?
4. What are we willing to remove to add this?

If any answer is "no", reconsider the approach before writing code.

The two standing principles behind every practices doc are **simplicity first** and
**startup agility**: ship the smallest working version, learn from usage, and optimize
only after measuring. Do not build for hypothetical scale, do not add robustness "just
in case", and do not reach for enterprise patterns without enterprise problems.

## Starting new work

**Any new feature must follow the process in `docs/processes/feature-development.md`.**
Invoke the `feature-development` skill and it will walk the six phases with you —
Discovery, Definition, Design, Development, Review, Deployment — and it will stop you
from writing code before a user story with acceptance criteria exists.

Do not skip to Development because the change looks small. Small changes still need a
clear problem statement and a test; they just move through the early phases quickly.

Bugfixes, refactors and infrastructure work do not need a user story, but still follow
the practices docs and still ship with tests.

## Before opening a PR

Run the `practices-review` skill, or walk `docs/processes/practices-checklist.md`
manually against your diff. The PR template repeats the Quality Gate — fill it in
honestly; unticked boxes are a signal to the reviewer, not a formality.

## Commands

Run from `app/` unless noted.

```bash
composer install && cp .env.example .env && php artisan key:generate
```

```bash
php artisan test
```

Host PHP on some dev machines lacks `pdo_sqlite`, which the in-memory test database
needs. If `php artisan test` fails to connect, run it through the dev image instead —
`docker exec` will not work here, because the container bakes its own copy of the code:

```bash
docker run --rm -v "$PWD:/w" -w /w --entrypoint php saas-app artisan test
```

Scan services and rules are contributed as JSON and fail silently when wrong — a rule
targeting data no `tasks.json` collects matches nothing, which is indistinguishable
from a compliant account. Always validate after touching them:

```bash
php artisan scan:validate-rules
```

## Where the rules live

| Topic | Doc |
| --- | --- |
| Product, simplicity, MVP scope | `docs/practices/product.md` |
| Architecture and boundaries | `docs/practices/architecture.md` |
| Security and multi-tenancy | `docs/practices/security.md` |
| Code quality and review | `docs/practices/code-quality.md` |
| Testing strategy and layers | `docs/practices/testing.md` |
| Schema, queries, migrations | `docs/practices/database.md` |
| Feature development | `docs/practices/feature-development.md` |
| Design and UX | `docs/practices/design.md` |
| Infrastructure | `docs/practices/infrastructure.md` |
| Integrations | `docs/practices/integration.md` |
| Six-phase process | `docs/processes/feature-development.md` |
| Release process | `docs/processes/release.md` |
| Full checklist | `docs/processes/practices-checklist.md` |
| User story template | `docs/templates/user-story-template.md` |

## Anti-patterns

Call these out when you see them, including in your own suggestions:
building "just in case", optimizing before measuring, over-engineering the first
version, complex solutions where simple ones work, enterprise patterns without
enterprise needs, skipping tests to move faster, waiting for perfection before shipping.
