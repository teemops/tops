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

Note this milestone is **not** "the repo goes public". Public release is a later
milestone with a higher bar (see Decisions Log, D-4).

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
**Now** bucket held four items at creation and is sequenced, not parallel — two are done,
two remain. Resist growing it — adding an item makes the others later, it does not make
more happen.

---

## Decisions Log

Decisions already made, so we don't relitigate them. Each has a trigger for revisiting.

| # | Decision | Date | Rationale | Revisit when |
| --- | --- | --- | --- | --- |
| **D-1** | **Open source, self-hosted first.** Hosted multi-tenant SaaS is not the focus. | 2026-07-29 | Removes the entire commercial layer from the roadmap; every hard dependency on a hosted service becomes an adoption barrier instead of an assumption. | A commercial model is chosen. |
| **D-2** | **Firebase auth stays, opt-in and off by default.** Not removed. | 2026-07-29 | It's built and works; some self-hosters will want OAuth. The default path must never require a Firebase project. | Never — it stays optional. |
| **D-3** | **AWS onboarding stays as-is** (`install.sh` + CFN + SNS/SQS); we document it better rather than simplifying it. | 2026-07-29 | With a handful of hand-held design partners the friction is tolerable, and we'll learn whether the SNS automation is actually valued before investing in replacing it. | **Public release**, or the first design partner who gives up during setup. |
| **D-4** | **Public repo release is a separate, later milestone.** | 2026-07-29 | Design partners are a smaller, safer audience. Going public raises the bar on security, docs, and contributor experience. | Design partners are running successfully. |
| **D-5** | **MFA ships as new-device email OTP, not TOTP.** No authenticator app, no external OTP service. | 2026-07-29 | Delivers most of the protection (stolen password alone is insufficient) for a fraction of the work, and works on the native auth path where TOTP currently doesn't. | Design partners ask for authenticator-app support, or a compliance requirement forces it. |
| **D-6** | **No billing, plans, or licence gating.** | 2026-07-29 | No revenue model yet (D-1). | A commercial model is chosen. |
| **D-7** | **Apache-2.0, with trademark held separately and a DCO for contributions.** | 2026-07-29 | See below — this one has enough reasoning behind it to warrant its own section. | Effectively never; the DCO is what makes it durable. |

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

## At a Glance

Sizes are rough and relative, for one person: **XS** under a day · **S** a day or two ·
**M** about a week · **L** more than a week.

### Now — the path to a design partner

| # | Feature | Why it's here | Size |
| --- | --- | --- | :---: |
| ~~**N-1**~~ | ~~Fix the clean-checkout build~~ | ✅ **Done** 2026-07-29 — `@vitejs/plugin-vue` on `^6`, `npm ci` clean, frontend CI job added. | — |
| ~~**N-2**~~ | ~~Choose and add a licence~~ | ✅ **Done** 2026-07-29 — Apache-2.0, trademark held separately, DCO for contributions. See D-7. | — |
| **N-3** | Setup docs a stranger can follow | The milestone is "installs without a call". This is that, plus removing vendor-baked defaults from `.env.example`. | L |
| **N-4** | Remediation for every finding | 39 of 74 rules have no fix text — including all 22 CIS rules. A finding without a fix is homework. | M |

### Next — before the repo goes public

| # | Feature | Why it's here | Size |
| --- | --- | --- | :---: |
| **X-1** | New-device email OTP | Wanted soon. Code prompt only on an unrecognised browser. Generator already exists — mostly extraction. | M |
| **X-2** | SNS signature verification | Currently a stub with a `TODO`. Exposure is per-deployment today; **that reasoning expires at public release.** | M |
| **X-3** | Prove the self-hosted path in CI | Nothing asserts the app boots with `FIREBASE_USER_AUTH=false` — the default config. | S |
| **X-4** | Delete or route the dead OAuth controller | 99 lines, zero routes, unused dependency. Could give native-auth users OAuth without Firebase. | S |
| **X-6** | Enforce DCO sign-off in CI | Sign-off is required in writing but unchecked. D-7's no-rug-pull guarantee depends on provenance being recorded. | XS |
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
| Simplify AWS onboarding | Public release, or the first partner who gives up during setup (D-3). | M |
| Contributor experience | The public-release milestone (D-4). | M |
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

### N-4 · Give every finding a remediation

**User story**
> As a solo engineer reviewing my scan results, I want every finding to tell me how to
> fix it, so that I can act on the report immediately instead of researching each issue.

**Expected behaviour**
Every finding surfaced in the UI carries, at minimum, a one-line remediation. The
highest-severity and most common findings additionally carry step-by-step guidance with
links. No finding is a dead end.

**Current state — the gap is bigger than it looks**

| Layer | Coverage | Worst gap |
| --- | --- | --- |
| One-line `remediation` on the rule | 35 of 74 | 17 basic rules |
| Rich guidance — steps, links, impact (`tips.json`) | 10 of 74 | — |
| **CIS ruleset specifically** | **0 of 22** | **Every CIS rule is a dead end** |

**Acceptance criteria**
- [ ] Given any rule in `basic.json` or `cis.json`, when it produces a finding, then that finding displays a remediation
- [ ] Given a critical or high severity finding, when I expand it, then I see step-by-step guidance with links to AWS documentation
- [ ] Given the recommendations file, when it is validated, then every rule it references exists (today `tops-route53-001` does not)
- [ ] Given a new rule is added without remediation text, when `scan:validate-rules` runs, then it fails

**Technical notes**
- Two layers, and the cheap one goes first: fill the 39 missing one-line `remediation`
  fields in the rulesets before extending `tips.json`. The CIS ruleset's 22 rules are the
  single highest-value batch.
- Extending `scan:validate-rules` to enforce remediation is what stops this regressing —
  do that in the same change, or the gap reopens.
- `tips.json` groups rules by recommendation for the remediation workflow; keep that
  grouping meaningful rather than one recommendation per rule.

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

### X-2 · Implement SNS signature verification

*Security. Currently a stub.*

`SnsSignatureVerifier.php:62-66` is a literal `TODO`. It checks a header and three JSON
field names — nothing cryptographic. `verifyWithAwsSdk()` is a comment-only shell.
`SubscriptionConfirmation` messages are auto-accepted. The consuming route is
unauthenticated by design and registers IAM role ARNs.

**Placed in Next, not Now, deliberately:** each design partner runs their own instance
and their own SNS topic, so the exposure is to their own deployment rather than a shared
tenant. That reasoning **expires at public release** — this must be closed before D-4.

`aws/aws-sdk-php` is already a dependency and ships `Aws\Sns\MessageValidator`. Reject
`SigningCertURL` values not on an `amazonaws.com` host over HTTPS — that's the classic
bypass.

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

**Finish this before public release**, alongside X-2.

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
| **Contributor experience** | `CONTRIBUTING.md`, code of conduct, issue templates, good-first-issues, published container images. Belongs with the public-release milestone (D-4). |
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
4. **Does the SNS gap (X-2) need closing before you hand the code to an external party?**
   The current placement assumes partners are trusted and self-hosting. If any partner is
   at arm's length, this moves to Now.
5. **What does a design partner have to tell us for this milestone to count as a
   success?** Worth deciding before we start, not after.

---

## Changelog

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
