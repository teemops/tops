# TOPS Roadmap

**Owner:** Ben Fellows · **Format:** Now / Next / Later · **Created:** 2026-07-29

This is the plan of record. [`PROGRESS.md`](./PROGRESS.md) records what *is*; this
records what's *next* and why. When they disagree, PROGRESS.md wins on facts and this
document wins on intent.

**How to use it:** pick the top item in **Now**. Run the `feature-development` skill for
anything user-facing; bugfixes and infrastructure work skip the user story but still ship
with tests. When an item lands, move it out and update `PROGRESS.md` in the same PR.
Re-read the Decisions Log before adding anything — several tempting items are
deliberately excluded.

---

## Direction

TOPS is an **open-source, self-hosted** AWS security scanner. There is **no revenue model
yet** — no billing, no plans, no licence gating, no paid tier. Every roadmap item is
judged on one question:

> Does this help someone run the scanner and act on what it finds?

Anything that only makes sense for a hosted commercial product is out of scope until that
changes.

## Milestone: Self-Hosted Design Partners

Get a small number of real operators running TOPS on their own infrastructure, scanning
their own AWS accounts, and telling us what's wrong with it.

**Done when:** a design partner can install TOPS from the documentation alone, connect an
AWS account, run a scan, and act on the findings — without a call.

Note this milestone was **not** "the repo goes public" — public release was meant to be a
later, higher-barred milestone (D-4). **The repo went public on 2026-07-30 anyway**, ahead
of that sequencing, which promoted several items. See D-4 and N-6.

### Who we're building for

**A solo engineer or small team** who owns AWS security at a small company, alongside
other responsibilities. They are not a dedicated security team.

What follows from that:
- **Time-to-first-scan is the metric that matters.** Setup friction loses them.
- **They need to be told what to fix, not just what's wrong.** A finding without a
  remediation step is homework, and they don't have time for homework.
- They will not tolerate a broken first command.
- Depth of compliance coverage matters *less* than actionability. CIS is a checkbox to
  them; "your S3 bucket is public, here's the fix" is the value.

### Constraint

**One person building, with Claude.** This is the single biggest input to the plan. The
**Now** bucket is sequenced, not parallel. Resist growing it — adding an item makes the
others later, it does not make more happen.

**Now was grown once, deliberately.** N-5 (release pipeline) was added on 2026-07-30 after
N-3's design showed that "installs from the documentation alone" is unreachable while
installation means compiling assets on the user's machine. It is not a fifth wish-list
item; it is a prerequisite that was missing from the original plan. The rule still holds —
N-3 and N-4 are now later than they were, and that is the price. See D-8.

---

## Decisions Log

Decisions already made, so we don't relitigate them. Each has a trigger for revisiting.

| # | Decision | Date | Rationale | Revisit when |
| --- | --- | --- | --- | --- |
| **D-1** | **Open source, self-hosted first.** Hosted multi-tenant SaaS is not the focus. | 2026-07-29 | Removes the entire commercial layer from the roadmap; every hard dependency on a hosted service becomes an adoption barrier instead of an assumption. | A commercial model is chosen. |
| **D-2** | **Firebase auth stays, opt-in and off by default.** Not removed. | 2026-07-29 | It's built and works; some self-hosters will want OAuth. The default path must never require a Firebase project. | Never — it stays optional. |
| **D-3** | **AWS onboarding stays as-is** (`install.sh` + CFN + SNS/SQS); we document it better rather than simplifying it. | 2026-07-29 | With a handful of hand-held design partners the friction is tolerable, and we'll learn whether the SNS automation is actually valued before investing in replacing it. | **Public release**, or the first design partner who gives up during setup. |
| ~~**D-4**~~ | ~~**Public repo release is a separate, later milestone.**~~ **Superseded 2026-07-30 — the repo is public.** | 2026-07-29 | Design partners were meant to come first, with public release held to a higher bar on security, docs and contributor experience. That sequencing did not happen. | **Done.** Its dependants are listed below. |
| **D-5** | **MFA ships as new-device email OTP, not TOTP.** No authenticator app, no external OTP service. | 2026-07-29 | Delivers most of the protection (stolen password alone is insufficient) for a fraction of the work, and works on the native auth path where TOTP currently doesn't. | Design partners ask for authenticator-app support, or a compliance requirement forces it. |
| **D-6** | **No billing, plans, or licence gating.** | 2026-07-29 | No revenue model yet (D-1). | A commercial model is chosen. |
| **D-7** | **Apache-2.0, with trademark held separately and a DCO for contributions.** | 2026-07-29 | See below — this one has enough reasoning behind it to warrant its own section. | Effectively never; the DCO is what makes it durable. |
| **D-8** | **Ship prebuilt images from Docker Hub, cut by a real release pipeline.** `install.sh` pulls tagged images; it never builds. | 2026-07-30 | See below — this is the biggest single change to how TOPS is delivered. | A registry other than Docker Hub is chosen, or images stop being the distribution unit. |
| **D-9** | **No application-level encryption at rest. `iam_role_arn` is stored plaintext; encryption is the host's job.** | 2026-07-30 | See below — deliberately reducing encryption on a security product needs its reasoning on the record. | A field is introduced that is genuinely a credential (an access key, a token, a password). Then encrypt *that* and revisit key rotation. |

### D-7 in full: licensing

**The model is Red Hat's; the licensing tactics are deliberately not.**

Red Hat sells subscriptions, not software — support, certified builds, backports,
indemnification, long-term updates, wrapped around code that is 100% open. That is the
model TOPS is aiming at: enterprise support, hand-holding through setup, uptime and
incident response, training and remediation help, and integration tooling on top.

What we are **not** copying is how Red Hat implements it. The GPL obliges them to give
source to whoever receives binaries, not to the public; they satisfy that for subscribers,
then use the *subscription contract* to terminate customers who exercise their
redistribution rights. The Software Freedom Conservancy's summary — ["if you exercise your
rights under the GPL, your money is no good here"](https://sfconservancy.org/blog/2023/jun/23/rhel-gpl-analysis/)
— and their verdict that it is "not in the spirit of the GPL". Restricting RHEL sources to
CentOS Stream in 2023 compounded it. That is precisely the "one day this turns on me"
dynamic TOPS exists to avoid. Canonical's Ubuntu Pro has a milder version of the same
problem: free for 5 machines, then a bill.

**Why Apache-2.0**
- **No limits, ever.** Nothing in it permits usage caps, seat counts or tiers, so the "you
  hit a threshold, now pay" scenario is structurally impossible rather than merely
  promised.
- **Explicit patent grant**, which MIT lacks — worth having on a security product.
- **Enterprise-friendly.** Our target user is a solo engineer installing a scanner inside
  a company. AGPL is blanket-banned at many companies' procurement layer, so its cost
  would be immediate and certain while its benefit — deterring a competitor's managed
  offering — is speculative and years away.
- **Integration-friendly**, which matters because "integration CLI and tools on top" is
  one of the intended service lines.
- **Validated in this exact market:** [Prowler](https://github.com/prowler-cloud/prowler),
  the closest comparator, is Apache-2.0 and runs a commercial hosted offering on top of it.

**Rejected:** AGPL-3.0 (corporate friction outweighs speculative protection at this
stage), GPL-3.0 (AGPL's friction without its protection — the SaaS loophole stays open),
MIT (no patent grant), and anything source-available such as BUSL or SSPL (they are the
rug-pull we are promising not to perform).

**Trademark does the protecting, not the licence.** Red Hat's real moat is that anyone may
rebuild RHEL but nobody may call it Red Hat. [`TRADEMARK.md`](../TRADEMARK.md) gets the
same protection honestly: the code is free to fork, the name is not free to reuse.

**DCO, not CLA — and this is the load-bearing part.** Contributors keep their copyright
and grant us no relicensing rights. We therefore *cannot* move TOPS to a proprietary or
source-available licence later without every contributor agreeing. A CLA is exactly the
mechanism that made the Elastic and HashiCorp relicensings possible. Giving up that
optionality is the point: it converts "we won't rug-pull you" from a promise into a
structural fact. The cost, stated plainly, is that dual-licensing is now off the table.

**Same software, managed or not.** No open core, no enterprise build, no feature flags
that unlock with payment. If a managed offering ever exists, it runs this code.

---

### D-8 in full: distribution and releases

**`install.sh` runs prebuilt images. It does not build anything.**

The honest reason this decision is here at all: **TOPS has never had a release process.**
There is no version number, no tag, no changelog, no published artifact. Every install to
date has been a `git clone` plus a local build. That was survivable while the only operator
was the author, and it stops being survivable the moment a design partner is involved —
"which version are you running?" currently has no answer.

**What a first-time user does**

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/teemops/tops/develop/install.sh)
```

That script pulls tagged images from Docker Hub and starts the stack. No PHP, no Composer,
no Node, no npm, no compile step. The only host dependency is Docker.

**What it costs.** This is a **more intense effort** than writing setup documentation, and
it is worth being blunt about that: it needs a release workflow, a versioning scheme, a
Docker Hub organisation, image publishing for three images, a second Compose file, and a
split of the current install path in two. Two or three times the work of the documentation
task it replaces.

**Why it wins anyway.** It is not extra work bolted onto N-3 — it is the release process
that should have existed from the first commit, arriving late. It buys, in one change:

- **A supportable answer to "what are you running?"** — a version, a tag, and an image
  digest instead of a branch name and a hope.
- **Time-to-first-scan measured in minutes**, because the slowest part of setup today is
  compiling assets on the user's machine.
- **A rollback story.** Pin `TOPS_IMAGE_TAG` to the previous version and restart.
- **A downloadable artifact** — the tagged source zip GitHub cuts automatically.
- **The removal of four host prerequisites** that had nothing to do with running a scanner.

Anything less than this leaves the milestone's "installs from the documentation alone"
resting on the user having a correct PHP and Node toolchain, which is the single most
likely thing to go wrong on a machine we cannot see.

**What stays for contributors.** `docker/scripts/prepare-build.sh` is retained unchanged,
and the build-from-source path keeps working — it moves to `install-build.sh` plus
`docker-compose.build.yml`. Contributors are explicitly not asked to pull images to work
on the code.

### D-9 in full: encryption at rest, and why we removed some

**Context.** `APP_KEY` leaked on 2026-07-30 in `infra/scripts/test/app.env`. Rotating it was
not safe, because `aws_accounts.iam_role_arn` was encrypted with `Crypt` and a new key would
have made every stored ARN unreadable — silently, because the accessor caught decryption
failures and returned the ciphertext. The first plan was a rotation command. Instead we
removed the reason to need one.

**The ARN is not a credential.** `arn:aws:iam::123456789012:role/X` is an identifier.
Assuming that role additionally requires `sts:AssumeRole` permission, a trust policy naming
the caller, and the ExternalId. On its own it grants nothing; the realistic exposure from
plaintext is reconnaissance — account IDs and role names.

**The protection was already inconsistent.** `external_id` — the ExternalId that actually
gates `AssumeRole` — is stored plaintext in the very next column, and is part of a composite
index, so it cannot be encrypted without breaking lookups. Encrypting the identifier while
the shared secret sits in the clear beside it is not a security boundary.

**And it protected little.** In the single-host Docker deployment, `APP_KEY` is in `.env` on
the same machine as MySQL. Anyone who can read the database can almost certainly read the
key. Encryption at rest is real when the key lives somewhere the data does not — a KMS, a
separate host — which is exactly what a self-hoster's disk encryption and database
permissions provide, and what we now delegate to them.

**What it bought.** Rotating `APP_KEY` is now `php artisan key:generate` — native Laravel,
no data migration, no bespoke command, no registry of encrypted fields, no guard test to
keep that registry honest. The only consequences are that users are logged out and
outstanding email-verification links stop working, because both derive from `APP_KEY`.

**`APP_KEY` itself is not optional and was never a candidate for removal.** Two independent
uses make it mandatory: `bootstrap/app.php:26` encrypts cookies, so the encrypter is
resolved on every web request; and `MustVerifyEmail` is live, so verification links are
signed URLs HMAC'd with the key. An app without it throws `MissingAppKeyException` on boot.
Password hashes are unaffected — bcrypt does not use `APP_KEY`.

**Rejected:** the `APP_KEY` rotation command, specified in full at
[`docs/features/app-key-rotation.md`](./features/app-key-rotation.md) and kept as a record.
If a genuine credential is ever stored, that design is the starting point — including the
`APP_PREVIOUS_KEYS` sequencing, which is the part worth not rediscovering.

## At a Glance

Sizes are rough and relative, for one person: **XS** under a day · **S** a day or two ·
**M** about a week · **L** more than a week.

### Now — the path to a design partner

| # | Feature | Why it's here | Size |
| --- | --- | --- | :---: |
| ~~**N-1**~~ | ~~Fix the clean-checkout build~~ | ✅ **Done** 2026-07-29 — `@vitejs/plugin-vue` on `^6`, `npm ci` clean, frontend CI job added. | — |
| ~~**N-2**~~ | ~~Choose and add a licence~~ | ✅ **Done** 2026-07-29 — Apache-2.0, trademark held separately, DCO for contributions. See D-7. | — |
| **N-6** | SNS signature verification | **Promoted from Next on 2026-07-30**, the day the repo went public — this is X-2, whose own deferral reasoning said it expired at exactly that point. The verifier is a stub that returns `true`, on an unauthenticated route that registers IAM role ARNs, and the code is now readable by anyone. | M |
| **N-5** | Release pipeline + Docker Hub images | Built 2026-07-30; blocked on Docker Hub secrets. TOPS had no version, tag, changelog or published artifact. See D-8. | L |
| **N-3** | Setup docs a stranger can follow | The milestone is "installs without a call". Now sits on top of N-5: one `install.sh`, plus removing vendor-baked defaults from `.env.example`. | M |
| ~~**N-4**~~ | ~~Remediation for every finding~~ | ✅ **Done** 2026-07-30 — all 74 rules carry a remediation, all 28 critical/high rules carry step-by-step guidance, and `scan:validate-rules` now fails rather than warns. | — |

### Next — before the repo goes public

| # | Feature | Why it's here | Size |
| --- | --- | --- | :---: |
| **X-1** | New-device email OTP | Wanted soon. Code prompt only on an unrecognised browser. Generator already exists — mostly extraction. | M |
| **X-3** | Prove the self-hosted path in CI | Nothing asserts the app boots with `FIREBASE_USER_AUTH=false` — the default config. | S |
| **X-4** | Delete or route the dead OAuth controller | 99 lines, zero routes, unused dependency. Could give native-auth users OAuth without Firebase. | S |
| **X-6** | Enforce DCO sign-off in CI | Sign-off is required in writing but unchecked. D-7's guarantee depends on provenance. **The repo is public, so an external PR can now arrive at any time.** | XS |
| **X-5** | Reconcile member permissions | Code, comments and the plan doc disagree on who can manage members. | XS |
| **X-7** | Clear the remaining npm audit backlog | 17 → **5**, criticals at 0 and gated in CI. What's left needs a `firebase` major bump; nothing reaches a running instance. | XS |

### Later — real, but waiting on a trigger

| Feature | Waiting on | Size |
| --- | --- | :---: |
| Scheduled / recurring scans | First design partner to ask. Likely the first request you get. | M |
| Report export (PDF/CSV/JSON) | A partner asking. Solo engineers may be happy with the UI. | M |
| Expand scanner coverage | Nothing — it's available now. Cheapest: rules for the four pilot services (DynamoDB, ELBv2, SNS, SQS) where plumbing exists. | Ongoing |
| PCI ruleset | A decision: author it or delete it. Empty for six months. | M |
| Sandbox rule conditions (`eval()`) | **Any feature accepting a ruleset we didn't write.** Community rules turn a condition into RCE. | M |
| Simplify AWS onboarding | **Trigger fired** — D-3 named public release, which has happened. | M |
| Contributor experience | **Trigger fired** — repo is public. | M |
| Multi-cloud (Azure, GCP) | AWS being genuinely good first. | L |

---

## NOW

The path to putting TOPS in a design partner's hands. Sequenced — do them in order.

### ~~N-1 · Fix the clean-checkout build~~ ✅ Done 2026-07-29

`@vitejs/plugin-vue` moved from `^5` to `^6`, whose peer range is
vite `^5 || ^6 || ^7`. No other change was needed — the plugin's own API is unchanged for
this project's usage, and the build output is identical.

- [x] `npm ci` succeeds on a clean checkout with no `--force` or `--legacy-peer-deps`
- [x] `npm run build` produces a working bundle
- [x] CI gains a frontend build job that would catch a regression
- [x] The workaround comment in `tests.yml` is removed, not amended
- [x] `app/install-deps.sh` no longer passes `--legacy-peer-deps`, and no longer
      swallows a failed build behind a warning

**Note for N-3:** the frontend build needs `composer install` to have run first — Ziggy's
route helper is published by the Composer package and imported by the type-check step.
The CI job installs both. The setup docs must state that order.

---

### ~~N-2 · Choose and add a licence~~ ✅ Done 2026-07-29

**Apache-2.0**, with trademark held separately and a DCO for contributions. Full
reasoning in **D-7** above.

- [x] `LICENSE` at the repository root (canonical Apache-2.0 text)
- [x] `NOTICE` with the copyright line
- [x] `TRADEMARK.md` — code is free, the name is not
- [x] `CONTRIBUTING.md` — DCO sign-off, and why it's a DCO rather than a CLA
- [x] `README.md`, `composer.json` and `package.json` state the licence consistently
      (`composer.json` had been declaring the project MIT under the Laravel skeleton's
      name — a direct conflict, now fixed)
- [x] Recorded in the Decisions Log as D-7

---

### N-6 · SNS signature verification

*Security. Promoted from X-2 on 2026-07-30 because the repo went public.*

`SnsSignatureVerifier::verify()` checks that three JSON fields are present and then
`return true`. The `TODO` listing the four real steps — fetch `SigningCertURL`, download the
certificate, verify the chain, verify the signature — is untouched, and `verifyWithAwsSdk()`
is a comment-only shell. `SubscriptionConfirmation` messages are auto-accepted.

**What changed.** Nothing in the code. What changed is who can read it. The consuming route
is unauthenticated by design and is the one that registers IAM role ARNs during account
linking. While the repo was private, an attacker had to guess the shape of an acceptable
payload; now the accepted fields and the absence of any cryptographic check are public.
X-2's own text said this reasoning "expires at public release" — it has.

**Acceptance criteria**
- [ ] Given a message with a valid AWS signature, when it arrives, then it is accepted
- [ ] Given a message with a missing, malformed or incorrect signature, when it arrives, then it is rejected and logged
- [ ] Given a `SigningCertURL` not on an `amazonaws.com` host over HTTPS, when it arrives, then it is rejected **without** fetching the URL — that is the classic bypass
- [ ] Given a `SubscriptionConfirmation`, when it arrives, then it is verified before the subscription is confirmed
- [ ] Given verification fails, when the request completes, then no account state changed

**Technical notes**
- `aws/aws-sdk-php` is already a dependency and ships `Aws\Sns\MessageValidator`. Use it
  rather than hand-rolling certificate handling.
- The dead `verifyWithAwsSdk()` shell should go in the same change, not linger beside a
  working implementation.

---

### N-5 · Release pipeline and published images

*Infrastructure with a user-facing outcome. Sequenced before N-3, which depends on it.*

**Problem:** there is no release process. No version, no tag, no changelog, no published
artifact. A design partner cannot say which build they are running, and `install.sh` cannot
pull an image that nobody publishes. Full reasoning in **D-8**.

**Shape of the work**

| Piece | Detail |
| --- | --- |
| Version source of truth | A root `VERSION` file. The repo is polyglot — neither `composer.json` nor `app/package.json` is the natural home. |
| Release workflow | On push to `develop`, gated on the test suite passing: bump, tag `vX.Y.Z`, cut the GitHub release (which gives the downloadable zip for free), then build and push images. |
| Images | **Three**, not one: `tops-app` (shared by the app and worker services), `tops-backup`, `tops-installer`. Missing any one leaves users building locally. |
| Tags per image | `vX.Y.Z`, `X.Y`, `sha-<short>`, and `latest`. |
| Architectures | `linux/amd64` **and** `linux/arm64` via buildx. Apple Silicon is common among the target users; without arm64 they get QEMU emulation or a failure. |
| Compose | `docker-compose.yml` pulls `${TOPS_IMAGE_TAG:-latest}` and carries no `build:` stanza. A new `docker-compose.build.yml` keeps the build-from-source path. |
| Script split | `install.sh` (pull and run, new) · `install-build.sh` (contributor build path, new) · `install-messaging.sh` (today's root `install.sh`, renamed). |

**Acceptance criteria**
- [ ] Given a machine with only Docker, when I run `install.sh`, then the stack starts from pulled images with no PHP, Composer, Node or npm present
- [ ] Given a push to `develop` with passing tests, when the workflow runs, then a new tag, GitHub release and set of pushed images exist
- [ ] Given a push to `develop` with failing tests, when the workflow runs, then nothing is tagged or published
- [ ] Given the tag already exists, when the workflow re-runs, then it is a no-op rather than an error
- [ ] Given I set `TOPS_IMAGE_TAG` to a previous version, when I restart, then I am running that version
- [ ] Given I am a contributor, when I run `install-build.sh`, then the stack builds from my working tree exactly as it does today
- [ ] Given an upgrade, when containers restart, then migrations apply automatically (the entrypoint already does this)

**Technical notes**
- CI builds images by running `docker/scripts/prepare-build.sh` on the runner and then
  `docker build`, reusing today's Dockerfile. **No multi-stage rewrite is needed** — the
  runner has PHP and Node, and this keeps the contributor path byte-identical.
- Gate on tests via `workflow_run` against the existing Tests workflow, checking
  `conclusion == success`.
- Docker Hub credentials are a scoped access token in repository secrets, never a password.
- Testing does not require the full Actions cycle — images can be pushed manually to
  Docker Hub to validate `install.sh` end to end first.

**Built 2026-07-30** — `VERSION`, `.github/workflows/release.yml`, `install.sh`,
`install-build.sh`, `docker-compose.build.yml`, and `docker-compose.yml` switched to
`teem/tops` + `teem/tops-backup`. Today's root `install.sh` is now `install-messaging.sh`.

**Verified locally:** the contributor path builds and tags both images; `install.sh`
creates `.env`, generates `APP_KEY`, pins `TOPS_IMAGE_TAG`, and is idempotent on re-run.

**Not yet verified:** the pull-and-run path, which needs images on Docker Hub, and the
workflow itself, which only runs on `develop`.

**Found while testing:** the Compose services use fixed `container_name` values, so a
leftover container from an earlier install blocks a new one with an opaque Docker error.
`install.sh` now detects this and explains it. Removing the hardcoded names would be the
real fix — deferred, since it changes the names in every existing runbook.

**`latest` tracks `develop`** (decided 2026-07-30). There is no separate stable channel;
`develop` is the integration branch and every release moves `latest`. The risk that a user
silently floats onto an untested build is handled at the other end: `install.sh` pins
`TOPS_IMAGE_TAG` to the version it installed, so moving forward is always an explicit
`docker compose pull`. Revisit if a design partner ever needs to stay on a known-good
release for longer than a single merge.

**Repo renamed 2026-07-30** — `teemops/saas` is now `teemops/tops`, so the install URL is
correct. It points at `develop` rather than `master`: `develop` is the default branch, and
it is consistent with `latest` tracking `develop`. The consequence, accepted knowingly, is
that the script users pipe into `bash` moves when `develop` moves.

**Untested until public release:** `raw.githubusercontent.com` returns 404 for private
repos, so the one-liner cannot be verified while D-4 is open — and GitHub only documents
rename redirects for web and git operations, not for the raw host. Re-check the URL by hand
the day the repo goes public.

**Open — still needed**
- `DOCKERHUB_USERNAME` and `DOCKERHUB_TOKEN` repository secrets.
- Images pushed to Docker Hub, so the pull-and-run path can be tested end to end.

---

### N-3 · Setup documentation a stranger can follow

**User story**
> As a solo engineer evaluating TOPS, I want to install it and complete my first scan by
> following the documentation alone, so that I can decide whether it's worth my time
> without needing help from the maintainer.

**Expected behaviour**
A new operator lands on the README, follows one clear path for self-hosted Docker
installation, completes the AWS messaging setup step, connects an AWS account, and sees
findings from a real scan. At no point do they have to read source code, guess an
environment variable, or ask a question to get unstuck.

**Acceptance criteria**
- [ ] Given a clean machine with Docker, when I follow the setup guide start to finish, then I reach a running instance and can log in
- [ ] Given I am logged in, when I follow the AWS account connection guide, then I connect an account and run a scan that returns findings
- [ ] Given the `install.sh` AWS messaging step, when I read the guide, then I understand what it deploys into my AWS account, what it costs, and how to remove it
- [ ] Given I do not want Firebase, when I follow the default path, then I am never asked for a Firebase project
- [ ] Given a step fails, when I read the guide, then a troubleshooting section covers the common causes

**Technical notes**
- `README.md:31-36` currently lists a Firebase project and an AWS account as hard
  prerequisites and routes readers to `app/README.md`. It reads as hosted-SaaS docs.
- Remove vendor-owned defaults from `app/.env.example` as part of this: the CFN template
  URL points at `storage.teemops.com` (`:88`) and a Teemops AWS account ID plus SQS ARNs
  are hard-coded (`:89-93`). A self-hoster copying these silently points at our
  infrastructure.
- Per D-3, document `install.sh` honestly rather than replacing it. Be explicit that it
  deploys real AWS resources.
- **Validate by doing.** The only meaningful test is a genuine clean-machine run-through.

**Success metrics**
- A design partner reaches first scan without contacting us
- Time from clone to first findings under 30 minutes

---

### ~~N-4 · Give every finding a remediation~~ ✅ Done 2026-07-30

**User story**
> As a solo engineer reviewing my scan results, I want every finding to tell me how to
> fix it, so that I can act on the report immediately instead of researching each issue.

**Expected behaviour**
Every finding surfaced in the UI carries, at minimum, a one-line remediation. The
highest-severity and most common findings additionally carry step-by-step guidance with
links. No finding is a dead end.

**Before and after**

| Layer | Was | Now |
| --- | --- | --- |
| One-line `remediation` on the rule | 35 of 74 | **74 of 74** |
| Step-by-step guidance for critical + high (`tips.json`) | 3 of 28 | **28 of 28** |
| **CIS ruleset specifically** | **0 of 22 — every rule a dead end** | **22 of 22** |
| Recommendations referencing a rule that exists | 10 of 11 | **all of them** |
| `scan:validate-rules` on a rule with no remediation | warned | **fails** |

**Acceptance criteria** — all met 2026-07-30
- [x] Given any rule in `basic.json` or `cis.json`, when it produces a finding, then that finding displays a remediation
- [x] Given a critical or high severity finding, when I expand it, then I see step-by-step guidance with links to AWS documentation
- [x] Given the recommendations file, when it is validated, then every rule it references exists (`tops-route53-001` no longer does)
- [x] Given a new rule is added without remediation text, when `scan:validate-rules` runs, then it fails

**How it was done**
- Both layers, in the planned order: the 39 one-line `remediation` fields first, then
  `tips.json`. Field order in the rulesets matches rules that already had the key, so the
  diff is 39 added lines and nothing reformatted.
- **Medium and low severity deliberately have no step-by-step guidance** — 38 rules. The
  one-line remediation is enough for them, and writing 38 more recommendations nobody
  asked for is the kind of completeness that costs weeks and helps no one. Promote
  individual ones when a partner asks.
- `tips.json` grew from 8 recommendations to 17, grouped by theme (root account, IAM least
  privilege, S3 public access, RDS encryption, RDS exposure, admin ports, CloudTrail,
  IMDSv2, Lambda URLs, KMS deletion) rather than one per rule. A test fails if that
  grouping degenerates into a recommendation per rule.
- The dead `tops-rec-008` Route53 recommendation is removed rather than left dangling. It
  returns with Route53 scanner coverage.
- `scan:validate-rules` now also validates `tips.json`, which it never did — which is why
  the dangling reference survived for months.

**What stops it regressing.** `RemediationCoverageTest` asserts over the *shipped*
rulesets, not fixtures: every rule has a remediation, every remediation is at least 40
characters, every critical/high rule has guidance, no reference dangles, every
recommendation has steps and HTTPS links. The original gap was not a broken code path —
the code worked fine — so only a test that reads what actually ships would have caught it.

**Success metrics**
- 100% of findings carry a remediation; 0 dead ends
- Design partners report resolving findings without external research

---

## NEXT

Queued behind the design-partner milestone. Not started, not forgotten.

### X-1 · New-device email OTP

**User story**
> As a self-hosting user, I want to be asked for an emailed code only when I sign in from
> a browser I haven't used before, so that a stolen password alone isn't enough to reach
> my account — without adding friction to everyday logins.

**Acceptance criteria**
- [ ] Given I sign in from a browser previously verified for my account, when I authenticate correctly, then I reach the dashboard with **no** code prompt
- [ ] Given I sign in from an unrecognised browser, when I authenticate correctly, then a 6-digit code is emailed and required before I proceed
- [ ] Given I enter a valid code, when verification succeeds, then that browser is remembered and does not prompt again
- [ ] Given a code older than its expiry, when I submit it, then it is rejected and I can request another
- [ ] Given I request codes repeatedly, when I exceed the rate limit, then further requests are refused
- [ ] Given `FIREBASE_USER_AUTH=false` (the self-hosted default), when I sign in, then this flow works

**Technical notes**
- Email OTP generation **already exists** — `FirebaseAuthController::requestEmailOtp()`,
  cache-backed, 6-digit, 10-minute expiry. But it is welded to the Firebase controller and
  gated by `EnsureFirebaseAuthEnabled`, so it is **unreachable on the self-hosted default
  path**. The work is extracting it into a service the native Laravel auth path can use.
- Device recognition: start with a signed long-lived cookie carrying a device token, plus
  a record per verified device so it can be revoked. Do **not** build a device-management
  UI until someone asks.
- This is deliberately **not** full MFA (D-5). No TOTP, no authenticator app. The external
  Teem OTP API dependency stays unused and out of the open-source stack.
- Rate limit code requests — 3 per 15 minutes per user is a reasonable starting point.
- `MfaSection.vue` and `MfaAlertBanner.vue` are Firebase-path UI — decide whether to reuse
  or leave them alone.

---

### X-3 · Prove the self-hosted path in CI

No test asserts the app boots and authenticates with `FIREBASE_USER_AUTH=false` — which
is the default self-hosted configuration. `EnsureFirebaseAuthEnabledTest` (2 methods) is
the only flag coverage. The most important path is the least tested.

### X-4 · Delete or route the dead OAuth controller

`Auth/OAuthController.php` is 99 lines referenced by zero routes, with an unused
`laravel/socialite` dependency. Either wire it up to give native-auth users OAuth without
Firebase (genuinely useful for D-2), or delete both. Leaving it is the worst option.

### X-6 · Enforce DCO sign-off in CI

`CONTRIBUTING.md` requires `Signed-off-by` on every commit, but nothing checks it. D-7's
guarantee — that TOPS cannot be relicensed without every contributor agreeing — rests on
provenance being recorded, so an unsigned commit that slips through weakens it. A
sign-off check is a standard GitHub Action and belongs in place before the first external
PR, not after.

### X-7 · Clear the remaining npm audit backlog

Surfaced by N-1: once `npm ci` completed, `npm audit` ran for the first time and reported
**17 advisories — 4 critical, 10 high, 3 moderate**.

**On 2026-07-29 this went 17 → 5, with criticals at 0** and CI now failing on a new one
(`npm audit --audit-level=critical`).

Only two packages needed a manifest change; everything else was a lockfile refresh into
ranges the manifest already allowed.

| Package | Was | Now | Route in | Needed |
| --- | --- | --- | --- | --- |
| `axios` | 1.13.2 | 1.18.1 | direct, **bundled** | manifest floor `^1.11.0` → `^1.18.1` |
| `concurrently` | 9.2.1 | 10.0.4 | direct | major bump — it pins `shell-quote` exactly |
| `shell-quote` | 1.8.3 | 1.9.0 | `concurrently` | came with the above |
| `protobufjs` | 7.5.4 | 7.6.5 | `firebase` → `@grpc/proto-loader` | in-range |
| `websocket-driver` | 0.7.4 | 0.7.5 | `firebase` → `faye-websocket` | in-range |
| `vite` | 7.3.1 | 7.3.6 | direct | in-range |
| `form-data`, `qs`, `lodash-es`, `minimatch`, `picomatch`, `postcss`, `rollup` | — | — | transitive | in-range |

`axios` was the important one: it is bundled into the app at `resources/js/bootstrap.ts:1`,
so it is the only entry here that reached a running instance. It was on a range covering
nine advisories including a `validateStatus` prototype-pollution auth bypass. The manifest
range `^1.11.0` already permitted the fix — the lockfile had simply never been refreshed —
but the floor was raised so it cannot drift back below it.

`concurrently` raises the Node floor to 22, now recorded in `package.json` `engines`
alongside the 22.12 that `@vitejs/plugin-vue@6` already required. Its CLI flags are
unchanged; `composer run dev` is the only caller.

**What remains — 5 high, in two packages, neither reaching a running instance:**

- **`@grpc/grpc-js`** via `firebase` → `@firebase/firestore`. Pinned by Firebase, so it
  needs a `firebase` major bump. Per D-2 Firebase is opt-in and off by default, so this
  affects only operators who enable it.
- **`brace-expansion`** via `vue-tsc` → `minimatch`. Build-time only. The 2.x line has no
  fix, so it clears when `vue-tsc` updates its `minimatch`.

**The public-release deadline for this passed on 2026-07-30.** Finish it alongside N-6.

### X-5 · Reconcile member-management permissions

Code permits administrators to manage members; the comments directly above it and
`organization-team-management-plan.md` both say owner-only. Decide which is right, then
align all three.

---

## LATER

Real, but not now. Most need a user to ask before they're worth building.

| Item | Note |
| --- | --- |
| **Report export (PDF/CSV/JSON)** | Not started. Wait for a design partner to ask — solo engineers may just want the UI. |
| **Scheduled / recurring scans** | Not started. Likely the first thing a real operator asks for; promote on first request. |
| **PCI ruleset** | `pci.json` has been empty for six months and the profile is hidden. **Author it or delete it** — an empty file is a promise we're not keeping. |
| **Simplify AWS onboarding** | Promote the existing manual role-ARN path to primary, drop the SNS/SQS requirement for basic use. Triggered by D-3's revisit condition. |
| **Contributor experience** | `CONTRIBUTING.md`, code of conduct, issue templates, good-first-issues, published container images. **Trigger fired 2026-07-30** — the repo is public, so this is live rather than waiting. |
| **Expand scanner coverage** | 11 services today. [`planning.md`](./planning.md) holds the staged plan — Secrets Manager, CloudWatch, GuardDuty, EBS, ACM and more. Now a JSON-only change, so it's contributor-friendly work — good candidates for first issues once public. Cheapest wins: rules for the four pilot services (DynamoDB, ELBv2, SNS, SQS) where the plumbing already exists. |
| **Sandbox rule conditions** | `ConditionEvaluator.php:40` evaluates rule conditions with PHP `eval()`. Safe while we author every ruleset — **not safe once rulesets are shared**, which open source invites. **Trigger: any feature that accepts a ruleset we didn't write** (community rules, rule upload, downloadable rulesets). Options and rationale in [`planning.md`](./planning.md#known-constraint-rule-conditions-use-eval). |
| **Multi-cloud (Azure, GCP)** | Untouched. Not before AWS is genuinely good. |

---

## Not Doing

Explicitly out of scope, so they don't creep back in:

- **Billing, subscription plans, licence gating, paid tiers** — no revenue model (D-6)
- **Hosted multi-tenant SaaS operations** — autoscaling, RDS Proxy, ALB tuning and the
  rest of the EC2 deployment story. These were specified in `planning.md` and were pruned
  from it on 2026-07-29; recover them from git history if the direction ever reverses.
- **TOTP / authenticator-app MFA** — superseded by D-5
- **Removing Firebase** — it stays, opt-in (D-2)

---

## Open Questions

Blocking nothing today, but each one shapes the plan:

1. ~~**Which licence?**~~ ✅ Resolved 2026-07-29 — Apache-2.0. See D-7.
2. **Is "TeemOps" a legal entity?** The copyright line in `NOTICE` currently reads
   `Copyright 2026 TeemOps`. If there is no incorporated company, copyright vests
   personally and the line should name the individual instead. One-line fix either way,
   but worth getting right before the repo is public.
3. **How many design partners, and by when?** Sizes the Now bucket and sets a real
   deadline.
4. ~~**Does the SNS gap need closing before you hand the code to an external party?**~~
   ✅ **Answered by events 2026-07-30** — the repo is public, so the code is already in
   arm's-length hands. Promoted to **N-6**.
5. **What does a design partner have to tell us for this milestone to count as a
   success?** Worth deciding before we start, not after.

---

## Changelog

- **2026-07-30** — `APP_KEY` leaked in a tracked infra env file. Response recorded as D-9:
  rather than build the `APP_KEY` rotation command that the leak seemed to demand, the
  encryption that made rotation dangerous was removed. `iam_role_arn` is now plaintext,
  rotation is `php artisan key:generate`, and the rotation design is kept as a documented
  rejection.
- **2026-07-30** — N-4 landed. Every rule now carries a remediation and every critical or
  high rule carries step-by-step guidance, so no finding is a dead end. `scan:validate-rules`
  fails rather than warns on a missing remediation, and validates `tips.json` for the first
  time — which is how a recommendation pointing at a nonexistent rule survived for months.
  **Now** is down to N-3 alone.
- **2026-07-30** — **The repo went public**, ahead of D-4's intended sequencing. Verified
  the install one-liner resolves (`raw.githubusercontent.com` does follow the rename
  redirect, so both old and new URLs serve). Four deferrals were keyed to this moment and
  all fired at once: SNS signature verification promoted to **N-6** in Now, the npm
  backlog's deadline passed, DCO enforcement is now urgent because an external PR can
  arrive any day, and contributor experience plus AWS-onboarding simplification are live.
  D-4 is marked superseded rather than done — the bar it described was not met first.
- **2026-07-30** — N-5 added to **Now**, ahead of N-3, and recorded as D-8. Designing N-3
  showed that installation currently means compiling assets on the user's machine, which
  the milestone cannot survive. `install.sh` becomes a thin runner over published Docker
  Hub images, and TOPS gets the release process it never had. N-3 drops from L to M as a
  result; `setup-env.sh` was deleted the same day.
- **2026-07-29** — npm advisories cut 17 → 5, criticals to 0, and CI fails on a new
  critical. `axios` — the only bundled dependency among them — is on 1.18.1. X-7 now
  covers only `firebase`'s `@grpc/grpc-js` and a build-time `brace-expansion`.
- **2026-07-29** — N-1 landed. `npm ci` is clean on a fresh clone and CI now builds the
  frontend. **Now** is down to N-3 (setup docs) and N-4 (remediation coverage); N-3 is
  next.
- **2026-07-29** — N-2 landed. Apache-2.0, trademark held separately, DCO. Recorded as
  D-7.
- **2026-07-29** — Created. Follows a full code audit that found `PROGRESS.md` six months
  and ~8 merged PRs stale. Direction set to open-source self-hosted; milestone set to
  design partners; decisions D-1 to D-6 recorded.
