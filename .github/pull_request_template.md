## What and why

<!-- The user problem this solves, not the implementation. One or two sentences. -->

## User story

<!-- Link the story in docs/features/. New features need one; bugfixes, refactors and
     infrastructure changes do not — delete this section and say which it is. -->

## How it was tested

<!-- Which tests, and anything verified manually. -->

---

## Quality gate

From `docs/processes/feature-development.md`. Leave a box unticked if it does not hold —
an honest gap is more useful to the reviewer than a full set of ticks.

- [ ] Acceptance criteria are met
- [ ] Solves the user problem simply — the decision framework was applied
- [ ] Follows the practices in `docs/practices/`
- [ ] Tests written and passing
- [ ] Documentation updated
- [ ] No obvious performance issues
- [ ] Ready for production

## Practices check

Only tick what the change actually touches.

- [ ] **Multi-tenancy** — queries on tenant data scoped by `organization_id`, and a
      test proves cross-organization access is denied
- [ ] **Security** — input validated server-side, every route authorized, no secrets
      committed, no user input concatenated into SQL
- [ ] **Simplicity** — no speculative features, no abstraction with one implementation,
      no optimization without profiling
- [ ] **Database** — migrations reversible, data migrations separate from schema,
      no N+1 introduced
- [ ] **Scan definitions** — `php artisan scan:validate-rules` passes

## Out of scope

<!-- What was deliberately left out, so the reviewer does not flag it as missing. -->
