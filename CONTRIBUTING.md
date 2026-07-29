# Contributing to TOPS

TOPS is open source under [Apache License 2.0](LICENSE). Contributions are welcome.

> **Note:** this covers licensing and sign-off — the parts you need before your first
> patch. Fuller contributor documentation (development setup, architecture tour, good
> first issues) arrives with the public release; see [`docs/roadmap.md`](docs/roadmap.md).

## Licensing and the DCO

We use a **Developer Certificate of Origin** (DCO), not a Contributor Licence Agreement.

The practical difference matters, so it's worth stating plainly: **you keep the copyright
in what you write.** You are not assigning us rights, and you are not granting us
permission to relicense your work later. Your contribution is licensed under Apache-2.0
to everyone, on the same terms as the rest of the project.

That is a deliberate constraint on *us*, not on you. Because we do not collect
relicensing rights, we **cannot** later move TOPS to a proprietary or source-available
licence without the agreement of everyone who contributed. The promise that TOPS stays
open is structural rather than a matter of trust — the same reason GitLab moved from a
CLA to a DCO.

### Signing off

Add a `Signed-off-by` line to each commit:

```bash
git commit -s -m "Your commit message"
```

This certifies you wrote the patch or have the right to submit it under Apache-2.0 — the
full text is at [developercertificate.org](https://developercertificate.org/). It is a
statement about provenance, nothing more. Commits without a sign-off can't be merged.

## Before you open a pull request

The project's engineering practices live in [`docs/practices/`](docs/practices/) and the
development process in [`docs/processes/`](docs/processes/). The short version:

- **Tests ship with the change**, not after it
- **Multi-tenant isolation is non-negotiable** — every query touching tenant data is
  scoped by `organization_id`, and new endpoints get a test proving one organisation
  cannot read another's rows
- **Simplicity first** — the smallest working version, not the most general one
- Fill in the PR template honestly. An unticked box is information for the reviewer, not
  a failure

Run the suite before submitting:

```bash
php artisan test
```

If your host PHP lacks `pdo_sqlite`, run it through the dev image instead:

```bash
docker run --rm -v "$PWD:/w" -w /w --entrypoint php saas-app artisan test
```

If you touched anything under `app/rules/`, validate it — scan rules fail silently when
wrong, which is indistinguishable from a compliant account:

```bash
php artisan scan:validate-rules
```

## Adding a scan service or rule

Adding an AWS service is a JSON change with no PHP. Read `app/rules/README.md` first — it
documents the contract, the three item shapes, and the two conditions traps.

## Trademark

The code is Apache-2.0. The **name** "TOPS" is handled separately — see
[TRADEMARK.md](TRADEMARK.md). Short version: fork freely, just call your fork something
else.
