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

**Target: 5 design partners by 2026-08-31.** Set 2026-07-31. Four weeks from today. This
is what sizes the **Now** bucket below — everything in it is on the critical path to that
date, and nothing else is.

**Status 2026-08-01: the code side of that sentence is demonstrated, and Now is empty.**
N-7 closed with a real account, on v0.3.1: install → add AWS account → scan → Insights and
Findings. What is *not* demonstrated is the subject of the sentence — a **design partner**,
doing it **without a call**. Nobody outside the project has run this path. **From here the
milestone is limited by finding and watching five operators, not by shipping features**, and
the plan should be read that way: pull from Next sparingly, and keep capacity free to react
to what the first partner actually hits.

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

**And once more, on 2026-07-31.** N-10 (one installer, in colour) is the same shape of
addition, and passes the same test: it is not a new capability, it is the install path N-3
documented and N-5 rebuilt, finally presented as one thing. It went in *ahead* of N-7 —
verifying the documented onboarding path is wasted effort a day before that path changes.
The price is that N-7 moves by a day or two, knowingly.

---

## Decisions Log

Decisions already made, so we don't relitigate them. Each has a trigger for revisiting.

| # | Decision | Date | Rationale | Revisit when |
| --- | --- | --- | --- | --- |
| **D-1** | **Open source, self-hosted first.** Hosted multi-tenant SaaS is not the focus. | 2026-07-29 | Removes the entire commercial layer from the roadmap; every hard dependency on a hosted service becomes an adoption barrier instead of an assumption. | A commercial model is chosen. |
| **D-2** | **Firebase auth stays, opt-in and off by default.** Not removed. | 2026-07-29 | It's built and works; some self-hosters will want OAuth. The default path must never require a Firebase project. | Never — it stays optional. |
| **D-3** | **AWS onboarding stays as-is** (`install.sh` + CFN + SNS/SQS); we document it better rather than simplifying it. **Still true of the AWS architecture** — N-10 simplified the *script*, not what it deploys. | 2026-07-29 | With a handful of hand-held design partners the friction is tolerable, and we'll learn whether the SNS automation is actually valued before investing in replacing it. | **Public release**, or the first design partner who gives up during setup. |
| ~~**D-4**~~ | ~~**Public repo release is a separate, later milestone.**~~ **Superseded 2026-07-30 — the repo is public.** | 2026-07-29 | Design partners were meant to come first, with public release held to a higher bar on security, docs and contributor experience. That sequencing did not happen. | **Done.** Its dependants are listed below. |
| **D-5** | **MFA ships as new-device email OTP, not TOTP.** No authenticator app, no external OTP service. | 2026-07-29 | Delivers most of the protection (stolen password alone is insufficient) for a fraction of the work, and works on the native auth path where TOTP currently doesn't. | Design partners ask for authenticator-app support, or a compliance requirement forces it. |
| **D-6** | **No billing, plans, or licence gating.** | 2026-07-29 | No revenue model yet (D-1). | A commercial model is chosen. |
| **D-7** | **Apache-2.0, with trademark held separately and a DCO for contributions.** | 2026-07-29 | See below — this one has enough reasoning behind it to warrant its own section. | Effectively never; the DCO is what makes it durable. |
| **D-8** | **Ship prebuilt images from Docker Hub, cut by a real release pipeline.** `install.sh` pulls tagged images; it never builds. | 2026-07-30 | See below — this is the biggest single change to how TOPS is delivered. | A registry other than Docker Hub is chosen, or images stop being the distribution unit. |
| **D-9** | **No application-level encryption at rest. `iam_role_arn` is stored plaintext; encryption is the host's job.** | 2026-07-30 | See below — deliberately reducing encryption on a security product needs its reasoning on the record. | A field is introduced that is genuinely a credential (an access key, a token, a password). Then encrypt *that* and revisit key rotation. |
| **D-10** | **User documentation lives in a top-level `user-docs/` directory, not under `docs/`, and deploys to `docs.teemops.com`.** One site for technical and non-technical readers — no separate tracks. | 2026-07-31 | See below. | The content outgrows plain Markdown, or a contributor proposes a better home. |
| **D-12** | **No security score.** Removed rather than recalibrated. Severity counts and the breakdown are what we show; a score can come back later if a design partner asks for one and we know what it should mean. | 2026-08-02 | See below. Closes [#91](https://github.com/teemops/tops/issues/91). | A design partner asks for a single headline number — and then design it so it can *move*. |
| **D-11** | **A finding is a durable record keyed by AWS account + resource + rule, and findings are current state. No per-scan history, no observations table.** The most recent scan that examined a resource is authoritative for its content; status belongs to the user. | 2026-08-01 | See below. Shipped as [#81](https://github.com/teemops/tops/issues/81). | A design partner asks for trends over time — and then treat it as a new data model, not an addition to this one. |
| **D-14** | **`teemops.com` is a single page whose only job is recruiting design partners, not a product marketing site.** Built fresh in `www/`; the pre-pivot `design/marketing/web/` is deleted. | 2026-08-06 | See below. | Design partners exist and the constraint moves from recruitment to something else. |
| **D-13** | **Docs renderer: MkDocs + Material theme**, deployed to `docs.teemops.com` via Cloudflare Pages (`mkdocs build`, output `site/`). Fills in the choice D-10 deliberately deferred. | 2026-08-03 | Clears the "MkDocs- or Docsify-class" bar D-10 set, now that section count and search actually bite (four pages against an eight-section, 34-page IA). Material gives sidebar nav, on-page TOC and client-side search for free — nothing here needed building by hand. Chosen over Docsify because it renders real static HTML per page rather than client-side, which matters for the evaluator audience D-10's own design work identified as arriving via search or a vendor-review link rather than already inside the app. Adds no new language to the repo: `python3` already backs the link-checker and SVG-validator scripts next to it in `user-docs/README.md`. | Content needs something outside MkDocs' plugin ecosystem, or the Python build step becomes a maintenance burden of its own. |

> **Naming collision, flagged 2026-08-01.** [`durable-findings.md`](./features/durable-findings.md)
> and the wireframes label the durable-findings decision **D-1**, which in *this* document is
> "open source, self-hosted first". They are different namespaces and the clash is only in
> prose. The Decisions Log entry above is **D-11**; the story keeps its own ID until someone
> renames it. **Worth renaming the story's ID to `DF-1`** — deferred rather than done
> unilaterally, because it touches three docs and a settled wireframe.

### D-14 in full: what teemops.com is for

**The milestone is limited by recruitment, not engineering** — this document has said so
since 2026-08-01 — and until 2026-08-06 there was no public page to send anyone to.
`teemops.com` served nothing.

**What was there was worse than nothing.** `design/marketing/web/` held a complete,
well-built three-page site for a **hosted commercial SaaS**: Free/Starter/Pro/Business
pricing tiers, "Start Free" CTAs pointing at `app.teem.nz/register` — a domain that now
redirects to an unrelated property — and no mention anywhere that TOPS is open source. It
contradicted D-1 and D-6 on its face. It is deleted rather than kept as reference, because
a second, contradictory description of the product in the repo is a trap for whoever opens
it next. Git history has it.

**The reframe that shaped the build.** A brand-new page with no traffic, no backlinks and no
search history will not generate inbound leads inside the milestone window. The realistic
sequence is outbound — a post, a message, an email — followed by the recipient looking us
up. So the page is a **conversion asset for outbound**, not a lead engine. That is why it is
one page and not eight: what it needs is a clear statement of what TOPS is, proof it is real
(the install one-liner, the GitHub repo, live docs, a version number), an honest account of
what it does *not* do, and one specific ask. Use-case pages, compliance landing pages and
SEO keyword targeting — all specified in the old content plan — are premature at zero
traffic and were dropped.

**The "what it isn't" section is the load-bearing one.** It says AWS-only, not runtime
protection, not a compliance guarantee, no scheduled scans, no report export, PCI empty —
and that nobody outside the project is running it. For an audience of sceptical engineers,
volunteering the limits is what makes the rest of the page credible. It is also lifted
directly from `user-docs/start-here/what-tops-is.md`, so the two cannot drift into telling
different stories.

**Every number on the page is checked against the code**, not against intent: 74 rules
(52 basic + 22 CIS), 11 services, all 74 carrying a remediation, all 28 critical/high
carrying step-by-step guidance. `www/README.md` records where each is verified, because
overstating any of them to a design partner costs more than it buys.

**Lead capture is a Cloudflare Worker writing to D1**, protected by a honeypot field.
**Turnstile was built, tested and then removed before launch** — at five-partner volume a
few junk rows are cheaper to skim than a widget is to run, the endpoint sends no email and
publishes nothing so spam has no amplification path, and a third-party challenge script
that fails to load makes the form unsubmittable and loses a real lead silently. That last
risk is the one that decided it. Bot Fight Mode and a rate-limiting rule are the first two
responses if spam arrives; Turnstile is the third, and `www/README.md` records how.

Deliberately no email notification either: reading leads is one command, and building
notification before a single lead exists is work with no evidence behind it. Deployment
reuses D-13's pattern — Workers static assets — so this adds no new category of
infrastructure, only a second project in the same account. **The result is that deploying
needs one CLI command and one dashboard step, with no secrets to manage at all.**

### D-12 in full: why the security score is gone rather than fixed

The score was `100 − (critical×10 + high×5 + medium×2 + low×1)`, floored at zero.

The reference install's 67 findings — 0 critical, 27 high, 37 medium, 3 low — compute to
**−212**. It read **0** from the first scan and could not have read anything else until the
findings dropped by roughly two thirds. **Every real account sits on the floor.**

That is worse than showing nothing, for a specific reason: a number that cannot move still
looks like information. Someone fixes ten things, rescans, sees 0 again, and concludes the
product is broken or the work was pointless. The one job a score has — showing improvement —
was the one thing it could not do.

**Recalibrating was the obvious alternative and was rejected.** Any curve we picked would be
invented: we have one real account's data, no partner has asked for a score, and nobody has
told us what "good" looks like for a small AWS estate. Choosing a formula now means guessing
at the answer and then defending the guess. That is building ahead of evidence, which the
practices call out by name.

So the score is **removed**, not replaced. The Findings summary keeps its severity counts,
Dashboard shows open findings instead, and Scan detail and Insights already show a severity
breakdown that says strictly more than one number could.

**If it comes back**, the requirement is fixed in advance: it must visibly move when someone
fixes ten things. That is the acceptance criterion any future scoring design has to meet.

Note the weighting itself survives as `ScanResult::SEVERITY_WEIGHTS`, because "fix these
first" still needs to rank a group of criticals above a larger group of mediums. What is gone
is the pretence that the weighting rolls up into a meaningful headline figure.

### D-11 in full: durable findings

The rule that governs everything else: **the most recent scan that actually examined a
resource is authoritative for that finding's content. Status belongs to the user.** A scan
may change a finding's severity, title or remediation; only a person marks something
ignored.

**Not looking is not the same as looking and seeing nothing** — three outcomes, not two:

| What the scan did | Outcome |
| --- | --- |
| Examined the resource, rule now passes | **Resolved — fixed** |
| Enumerated that service in that region, resource absent | **Resolved — resource gone** |
| Did not enumerate that service in that region at all | **Untouched.** Not resolved, not fixed |

The third row is the safety rule, and it is what makes per-service scans (F-2) and partial
region coverage safe to ship at all. Without it, scanning only S3 would silently "resolve"
every EC2 finding.

**`resource_gone` is deferred** and did not ship with #81. Its safety guard rests on
`is_partial`, and [#92](https://github.com/teemops/tops/pull/92) showed `is_partial` reads
`false` when regions silently failed to report. Fixing that is PERF-2
([#65](https://github.com/teemops/tops/issues/65)); `resource_gone` waits behind it.

**The price, accepted knowingly:** "what did scan X find?" is not answerable for any scan
but the latest. Older scans become a thin audit record — what ran, when, how it went.

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

### D-10 in full: where user documentation lives

**Not `docs/`.** That directory is a contributor and maintainer knowledge base —
practices, decisions, postmortems, roadmap, session notes — written in an internal
engineering voice, and it should stay that way. Product documentation for the people
*running* TOPS is a different audience with a different tone, and mixing the two either
dilutes `docs/` or forces user docs to read like an engineering journal. A new top-level
`user-docs/` directory keeps the split obvious: `docs/` is about building TOPS,
`user-docs/` is about running it.

**One site, not two.** The ask was explicit: technical and non-technical readers share the
same docs, not a "developer docs" track and a "business user" track. In practice this means
structuring by task (install, connect an account, run a scan, read a finding, manage your
team) rather than by audience, with depth increasing as a page goes on — a non-technical
reader gets what they need from the top of a page, a technical reader keeps scrolling.

**`docs.teemops.com`, not `.teem.nz`.** `teemops.com` is the domain already wired into the
live app — `MAIL_FROM_ADDRESS` defaults to `help@teemops.com`
(`app/config/mail.php:114`), and `docs/architecture.md:117` names `app.teemops.com`.
`teem.nz` only appeared in `design/marketing/web/` — an unbuilt, pre-pivot marketing mockup
that still had a paid "Start Free" pricing page, which contradicts D-6. **That directory was
deleted on 2026-08-06** when `www/` replaced it; see D-14. The note is kept so the domain
choice isn't relitigated by whoever next goes looking for it in git history.

**Deployment: Cloudflare Pages**, connected to this repo, publishing `user-docs/`. This is
not a new category of infrastructure — the deleted marketing mockup's `DEPLOY.md` already
proposed Cloudflare Pages; this applies the same plan to a second directory. (In the event
D-13 landed on Cloudflare **Workers** static assets rather than Pages, and `www/` follows
it.)

**Format: plain Markdown, tooling decided later.** Per the product practices — ship the
smallest working version, don't reach for a framework before there's content to render —
the first pages should just be Markdown files. Pick the lightest static-site renderer
(MkDocs- or Docsify-class, not a full framework like Docusaurus) once there's enough
content to need navigation and search, not before. Locking in a generator today, before a
single page is written, is exactly the kind of premature decision the practices docs warn
against.

**Relationship to N-3 and `README.md`.** N-3 already covers the minimum: install, connect
an AWS account, run a first scan — that content stays in `README.md` because it's a
GitHub-landing-page concern, and duplicating it into `user-docs/` would just give it a
second place to drift out of sync. `user-docs/` picks up from "you're logged in" — findings
and remediation, team management, scan profiles, insights — and once it exists,
`README.md`'s setup section should trim to a summary that links out rather than being the
canonical copy in two places.

---

## At a Glance

Sizes are rough and relative, for one person: **XS** under a day · **S** a day or two ·
**M** about a week · **L** more than a week.

### Now — the path to a design partner

**The original Now emptied on 2026-08-01.** Every item on the sequenced path to a design
partner has closed, four weeks ahead of the **2026-08-31** deadline. N-7 was the last of them:
install → add AWS account → scan, on a real account, on v0.3.1, reaching Insights and Findings.

**It did not stay empty.** Running the product for real is what exposed the next tranche of
work, and that work is now the active one — see
[Workstream: Scans, Findings & Insights](#workstream-scans-findings--insights) below.

**And it refilled again on 2026-08-02, from a different direction.** **N-11** came out of
writing an architecture diagram for CISOs rather than out of running the product: explaining
the AWS integration to a sceptical reader is its own review technique, and it found a
`Principal: "*"` on the one inbound path. Sequenced ahead of the workstream items on the
grounds that it is cheap in phase 1 and the repo is public.

**What that means, stated carefully.** The milestone's "done when" has two halves, and only
one is closed. The *code* half — a documented path that takes someone from nothing to
findings — is demonstrated. The other half is "**a design partner** … **without a call**",
and the maintainer running his own installer proves nothing about that. **The remaining work
on this milestone is finding and watching five real operators, not writing more code.** The
most valuable thing the plan can do now is stay small enough to react to what they say.

**What to pull, and in what order.** Now emptied and was immediately refilled — not from
**Next**, but from a workstream that did not exist when this section was written. See
[Workstream: Scans, Findings & Insights](#workstream-scans-findings--insights) below, which
is where the work actually went on 2026-08-01 and where it continues.

The **Next** items are unchanged and still queued behind it.
[X-6 (DCO in CI)](https://github.com/teemops/tops/issues/32) keeps the strongest claim among
them: the repo is public, an external PR can arrive any day, and D-7's no-rug-pull guarantee
rests on provenance that nothing currently checks. Then
[X-3](https://github.com/teemops/tops/issues/30), which protects the default self-hosted
path that every one of those five operators will run.

*The history that got here.* N-5 closed the same day it was found incomplete — v0.1.2 went through
the fixed pipeline cleanly (see below), so the release process TOPS never had now exists
and is proven. N-9 was found and closed the same day: the published default database
passwords are gone, generated at install time instead. N-8 took the OS layer out of the
release path. **N-10** was added on 2026-07-31 as critical rather than queued — the install
experience is the first thing a design partner touches, and shipping it with two scripts
and no warning about what lands in their AWS account undoes the work N-3 and N-5 did — and
shipped the same day as v0.3.0, deliberately *ahead* of N-7, because there is no sense
verifying an onboarding path that is about to change. That call paid for itself within a
day: [#56](https://github.com/teemops/tops/issues/56) found the unified installer's AWS
step failing on a real machine, fixed in v0.3.1 before anyone verified anything against it.

**N-7** (was X-9, promoted rather than left in Next when the milestone target made it
explicitly critical-path) was the last thing between the code and a design partner, and it
closed on 2026-08-01 — [#60](https://github.com/teemops/tops/issues/60). v0.3.1's installer
fixes are what made that run succeed, which is the sequencing argument justifying itself.

| # | Feature | Why it's here | Size |
| --- | --- | --- | :---: |
| **N-12** | **`teemops.com` — the design-partner recruitment page** | The milestone has been recruitment-limited since 2026-08-01 and there was nowhere to send anyone: `teemops.com` served nothing, and what was in the repo sold a hosted product with a pricing page. Built 2026-08-06 as one page in `www/`. See **D-14**. **Not yet deployed** — needs `npm run db:init` and the custom domain attached in the dashboard; no secrets. Steps in `www/README.md`. | S |
| ~~**N-11**~~ | ~~Lock down the account-linking SNS topic~~ | ✅ **Done** 2026-08-03 — consumer-side validation ([#100](https://github.com/teemops/tops/issues/100)) and an install-scoped filter secret ([#101](https://github.com/teemops/tops/issues/101)), verified end to end on a real account: a correct install id links as before, a wrong one is filtered to quarantine and never reaches `teemops_main`. The live run also caught `aws:link-rejections` reporting "nothing rejected" while a message sat in quarantine — the silent failure moved one layer out, and is now fixed. [Feature doc](features/sns-topic-publish-authorization.md). | S + M |
| ~~**N-1**~~ | ~~Fix the clean-checkout build~~ | ✅ **Done** 2026-07-29 — `@vitejs/plugin-vue` on `^6`, `npm ci` clean, frontend CI job added. | — |
| ~~**N-2**~~ | ~~Choose and add a licence~~ | ✅ **Done** 2026-07-29 — Apache-2.0, trademark held separately, DCO for contributions. See D-7. | — |
| ~~**N-6**~~ | ~~SNS signature verification~~ | ✅ **Done** 2026-07-30 — real signature verification via AWS's validator package, plus a topic allowlist that fails closed. Promoted from X-2 that morning when going public expired its deferral. | — |
| ~~**N-5**~~ | ~~Release pipeline + Docker Hub images~~ | ✅ **Done** 2026-07-31 — v0.1.2 went `prepare → merge → tag → publish` cleanly: tag content matches, images published on Docker Hub for both architectures, `latest` digest matches `v0.1.2`. See D-8 and below. | — |
| ~~**N-7**~~ | ~~Verify AWS onboarding end to end~~ | ✅ **Done** 2026-08-01 — install → add AWS account → scan on a real account, on v0.3.1, reaching Insights and Findings. `generated/teemops.env` carries all eight CFN outputs including the DLQ pair PR #57 fixed; the install log is clean. Only the cost-vs-documented check is outstanding, and AWS has not billed yet. [#60](https://github.com/teemops/tops/issues/60). | — |
| ~~**N-10**~~ | ~~One installer, in colour~~ | ✅ **Done** 2026-07-31 — `install.sh` absorbed `install-messaging.sh`, which is deleted. One script, an account-and-caller-ARN confirmation before anything deploys, a warn-and-skip failure mode, colour and a banner. Shipped as v0.3.0; [#56](https://github.com/teemops/tops/issues/56) found the AWS step still failing on a real machine and v0.3.1 fixed it. | — |
| ~~**N-9**~~ | ~~Generate real database passwords at install time~~ | ✅ **Done** 2026-07-31 — the published defaults are gone; `install.sh` and `prepare-build.sh` generate all three with `openssl rand -base64 32`, and `docker-compose.yml` requires them rather than falling back. | — |
| ~~**N-8**~~ | ~~Base image for the app container~~ | ✅ **Done** 2026-07-31 — `teem/tops-base:php8.3-1` published for both architectures and verified on Docker Hub; the app build pulls it instead of compiling. 48.5s → 13.8s on a cold clean build. Merged in PR #49; the CI number still gets recorded after the next release. | — |
| ~~**N-3**~~ | ~~Setup docs a stranger can follow~~ | ✅ **Done** 2026-07-30 — README rebuilt around the one-liner, vendor defaults purged from `.env.example`, nine-symptom troubleshooting section, installer verified end to end. | — |
| ~~**N-4**~~ | ~~Remediation for every finding~~ | ✅ **Done** 2026-07-30 — all 74 rules carry a remediation, all 28 critical/high rules carry step-by-step guidance, and `scan:validate-rules` now fails rather than warns. | — |

### Workstream: Scans, Findings & Insights

**Added to this document 2026-08-01, retrospectively.** The work below was designed, agreed
and partly shipped on 2026-08-01 without ever appearing here — three feature docs, a signed-off
wireframe set and 30-odd issues. This section closes that gap. **It is the reason Now stopped
being empty**, and reading the section above without it gives a false picture of what is
happening.

**Why it exists.** N-7 put a real account through the product and the product was the weak
part, not the installer. Three things were wrong at once: every scan re-created every finding
from scratch, a scan could only cover a whole benchmark, and a full scan took ~10 minutes.

**Sources.** [`durable-findings.md`](./features/durable-findings.md) ·
[`scan-individual-services.md`](./features/scan-individual-services.md) ·
[`parallel-region-scans.md`](./features/parallel-region-scans.md) · wireframes rev 3
("Scans, Findings & Insights", agreed 2026-08-01).

**The organising idea, from the wireframes:** *a finding is read in exactly one place.* Scan
detail summarises and dispatches; Findings is where a finding is read. Every number on Scan
detail becomes a link into Findings, filtered. The per-finding list comes **off** Scan detail
— that deletion is what pays for the feature.

#### Shipped

| # | Item | What landed | Size |
| --- | --- | --- | :---: |
| ~~**#81**~~ | ~~D-1 · Durable findings~~ | ✅ **Done** 2026-08-01 — a finding is one lasting record per account + resource + rule, not one row per scan. Recorded as **D-11** above. `resource_gone` deferred behind PERF-2. PR #95. | M |
| ~~**#84**~~ | ~~F-2 · Scan individual services~~ | ✅ **Done** 2026-08-01 — profiles and `scan_types` are now combinable, so "only S3, against CIS" is expressible; narrowing is validated, not silently intersected. Modal gained an expandable tree, indeterminate parent state and a live "2 of 11 services · 12 rules" summary. 34 `ScansControllerTest` tests green. | S–M |
| ~~**#90**~~ | ~~B-1 · Status lost on rescan~~ | ✅ Closed by #81 — ignored stays ignored across a rescan. | — |
| ~~**#64**~~ | ~~PERF-1 · Instrument scan phases~~ | ✅ **Done** 2026-08-01 — phase timing instrumented so the ~10 minutes is attributed rather than guessed. Baseline captured. PR #96. | S |
| ~~**#85**~~ | ~~F-3 · Filter Findings by service~~ | ✅ **Done** 2026-08-02, verified on the reference install — service pills with server-computed facet counts, ordered by size, long tail behind `+ N more`. The facet deliberately does not constrain itself, so selecting one pill leaves the rest navigable, and counts follow list semantics so a pill reading N returns N rows. | S |
| ~~**#83**~~ | ~~F-7 · Findings does not read its filters from the URL~~ | ✅ **Done** 2026-08-02 — shipped inside F-3, which could not meet its "and the URL reflects it" criterion without it. Filters now read from and write back to the URL, so a filtered view can be bookmarked or sent to a colleague. | XS |
| ~~**#87**~~ | ~~F-4 · Filter Findings by compliance benchmark~~ | ✅ **Done** 2026-08-02 — **the only genuine schema change in the workstream.** `scan_results.rulesets` is a JSON *list*, because one rule may belong to several rulesets and a finding that is both a Basic and a CIS gap should say so; folding the ruleset into a finding's identity was rejected as it would recreate the duplication D-1 removed. No backfill: existing findings show blank rather than a guess. `evaluateScan` now dedupes by rule id, so a shared rule is evaluated once carrying both rulesets. | M |
| ~~**#91**~~ | ~~B-2 · Security score pinned at 0~~ | ✅ **Done** 2026-08-02 — **removed rather than recalibrated**, recorded as **D-12**. It read 0 for every real account and could not move, and a number that cannot move still looks like information. Severity counts and the breakdown say more. Dashboard shows open findings instead. | XS |
| ~~**#88**~~ | ~~F-5 · Insights by service with severity~~ | ✅ **Done** 2026-08-02, verified on the reference install — "Top affected services" became the same severity-stacked, drillable breakdown Scan detail shows, keyed on the organization. **The shared component and service were consumed byte-for-byte unchanged**, so the whole feature is ~20 lines of production code. Deliberately *not* period-scoped: it is current state, so a service reads the same number here as on Scan detail and Findings — pinned by a test. | S |
| ~~**#86**~~ | ~~S-1 · Scan detail summarises and dispatches~~ | ✅ **Done** 2026-08-02, verified on the reference install — Scan detail now summarises and dispatches: run facts, severity totals, "fix these first" ranked by severity, and a drillable breakdown whose every row links into Findings filtered. The per-finding list, its remediation and its severity filter are **deleted**, which is what pays for the feature. Built as a shared service and component so Insights is a re-key, not a rewrite. | M |
| ~~**#82**~~ | ~~F-1 · Findings drops the remediation it already has~~ | ✅ **Done** 2026-08-02 — the one-line remediation now renders on its own rather than being gated behind `tips.json` step-by-step guidance, which only critical/high rules carry. Fixed the empty state for **46 of 74 rules**. Landed *before* S-1 deliberately: S-1 makes Findings the only page showing remediation at all. | XS |

#### Open — UI, in dependency order

**Nothing is left in this table. The UI workstream is closed** — verified against GitHub on
2026-08-06: #81, #82, #83, #85, #86, #87, #88, #89 and #91 are all closed. Every row that
used to sit here is in the Shipped table above.

*The table that was here listed #87, #88, #89 and #91 as open for four days after they
landed, which is the failure mode this document is most prone to: the Shipped table gets
updated and the Open table does not. If you close something here, delete its row in the same
commit.*

**One architectural instruction, worth repeating from the wireframes:** S-1's grouped,
severity-stacked, drillable breakdown must be built as a **reusable component**. F-5 and F-6
are the same block keyed differently. If it does not come out shared, the Insights work
doubles in cost.

#### Open — performance ([#63](https://github.com/teemops/tops/issues/63) tracks)

**The fan-out is already parallel; the deployment runs one worker.** Region jobs are
independent and already dispatched one per (service, region) — roughly 153 of them on a full
scan — and then executed strictly one at a time. That single fact reframes the whole series:
most of it is deployment and bookkeeping, not rearchitecting.

Two of these were **correctness bugs wearing a performance label**, and both are now fixed —
they gated everything else:

- ~~**PERF-2 ([#65](https://github.com/teemops/tops/issues/65))**~~ — ✅ **Done 2026-08-02.**
  Region dispatch became a single `Bus::batch()`, so the batch size is fixed atomically and the
  window in which a finishing job could complete a whole scan cannot exist. Completion moved to
  the batch's `finally()` callback. **`checkAndMarkRegionBasedScanComplete()` was deleted rather
  than deprecated** — it retained the branch that marked a barely-started scan `completed,
  is_partial=false`, and a "reported clean when nobody looked" path has no business surviving on
  a security scanner. Verified with multiple workers locally.
- ~~**PERF-3 ([#66](https://github.com/teemops/tops/issues/66))**~~ — ✅ **Done 2026-08-02.**
  `settleRegionScan()` claims a scan with a conditional `UPDATE`, so exactly one of the batch
  callback and the stale sweep wins. The sweep now marks everything it settles **partial**,
  unconditionally: it only runs when the normal path did not, so a fallback can never say "all
  clear".

~~**PERF-6 (#69, run more than one worker)**~~ — ✅ **Done 2026-08-02.** The worker container
now runs two supervisord pools instead of one process: the orchestrator (`default`,
`teemops_audit`) and region jobs (`teemops_audit_region`), the latter sized by
`TOPS_WORKER_PROCESSES`, **default 5**. Split deliberately, so a long orchestrator job cannot
block the ~153 region jobs queued behind it. The no-SQS path gets the same concurrency —
it previously ran a single bare `queue:work`. Safe because the database driver uses
`SELECT … FOR UPDATE SKIP LOCKED` on MySQL 8 and completion is settled by the batch from
PERF-2, not by workers racing a counter. Costs ~0.6–0.8 GB at the default, which is now a
documented minimum-spec note. Then the other cheap wins:
PERF-11 (#74, index `scan_details`), PERF-12 (#75, bulk-insert), PERF-13 (#76, memoize AWS
clients), PERF-14 (#77), PERF-15 (#78), PERF-18 (#94). Remaining: PERF-4 (#67),
PERF-5 (#68), PERF-7 (#70), PERF-8 (#71), PERF-9 (#72), PERF-10 (#73), PERF-16 (#79),
PERF-17 (#80).

**Region pruning stays off by default** (`SCAN_PRUNE_REGIONS_WITH_TAGGING`) for the
documented and correct reason that it can silently mark an untagged region clean. PERF-16
revisits the default; it is not a free win.

#### How this squares with the milestone

It does not, entirely, and that is worth stating rather than smoothing over. The milestone's
remaining constraint is **recruitment, not engineering** — and this workstream is engineering.
The defence is that N-7 surfaced it by running the product for real, and that a partner who
scans twice hits durable findings immediately. **The honest risk is that this workstream is
large enough to absorb all remaining capacity before 2026-08-31 while nobody is recruited.**
Keep it to the P0/P1 items until a partner is actually watching.

### Next — queued behind the design-partner milestone

| # | Feature | Why it's here | Size |
| --- | --- | --- | :---: |
| **X-10** | Trim the child IAM role to what TOPS actually uses | Fell out of N-11 and was never written down here. [#111](https://github.com/teemops/tops/issues/111) | S |
| **X-11** | `teemops_main` queue policy grants `SQS:ReceiveMessage` to `Principal: "*"` | Dead permission — the `aws:SourceArn` condition can never match a direct call — but it is a `Principal: "*"` in a public repo. Also from N-11. [#109](https://github.com/teemops/tops/issues/109) | XS |
| **X-1** | New-device email OTP | **Parked 2026-08-06, not dropped.** No design partner has asked, and the milestone is recruitment-limited; building the largest open item ahead of evidence is the anti-pattern this document names. Two findings from the discovery are worth keeping: the generator is welded to a Firebase ID token and keyed on `firebase_uid`, so it is a rewrite rather than the extraction this row claimed; and the default install cannot send email to a real inbox at all (see below). | M |
| **X-3** | Prove the self-hosted path in CI | Nothing asserts the app boots with `FIREBASE_USER_AUTH=false` — the default config. | S |
| **X-4** | Delete or route the dead OAuth controller | 99 lines, zero routes, unused dependency. Could give native-auth users OAuth without Firebase. | S |
| **X-6** | Enforce DCO sign-off in CI | Sign-off is required in writing but unchecked. D-7's guarantee depends on provenance. **The repo is public, so an external PR can now arrive at any time.** | XS |
| **X-5** | Reconcile member permissions | Code, comments and the plan doc disagree on who can manage members. | XS |
| **X-12** | A self-hoster cannot point TOPS at their own SMTP | `docker-compose.yml` hardcodes `MAIL_*` in `environment:`, which overrides `env_file:`. Verification and invitation email goes to a catcher on an unauthenticated port. Affects shipped features, not just X-1. | XS |
| **X-8** | Publish a SHA256 for `install.sh` | N-3 documents the download-and-read form but cannot document a checksum, because the release workflow does not emit one. Small addition to `release.yml`. | XS |
| **X-7** | Clear the remaining npm audit backlog | 17 → **5**, criticals at 0 and gated in CI. What's left needs a `firebase` major bump; nothing reaches a running instance. | XS |

~~**X-9** · Verify AWS onboarding end to end~~ — **promoted to N-7 in Now** on 2026-07-31.
The design partner deadline makes it critical-path rather than queued.

Tracked on GitHub: [X-1 #29](https://github.com/teemops/tops/issues/29) ·
[X-3 #30](https://github.com/teemops/tops/issues/30) ·
[X-4 #31](https://github.com/teemops/tops/issues/31) ·
[X-5 #33](https://github.com/teemops/tops/issues/33) ·
[X-6 #32](https://github.com/teemops/tops/issues/32) ·
[X-7 #34](https://github.com/teemops/tops/issues/34) ·
[X-8 #61](https://github.com/teemops/tops/issues/61).

### Later — real, but waiting on a trigger

| Feature | Waiting on | Size |
| --- | --- | :---: |
| Scheduled / recurring scans | First design partner to ask. Likely the first request you get. | M |
| Report export (PDF/CSV/JSON) | A partner asking. Solo engineers may be happy with the UI. | M |
| Expand scanner coverage | Nothing — it's available now. Cheapest: rules for the four pilot services (DynamoDB, ELBv2, SNS, SQS) where plumbing exists. | Ongoing |
| Scan Amazon Bedrock | **A design partner saying they run Bedrock.** One is enough — the research is done and the marginal cost is low. See below. | S–M |
| User documentation site | Nothing — it's available now. Built in parallel with feature work, not blocking it. See D-10. | Ongoing |
| PCI ruleset | A decision: author it or delete it. Empty for six months. | M |
| Sandbox rule conditions (`eval()`) | **Any feature accepting a ruleset we didn't write.** Community rules turn a condition into RCE. | M |
| Simplify AWS onboarding | **Trigger fired** — D-3 named public release, which has happened. | M |
| Contributor experience | **Trigger fired** — repo is public. | M |
| Multi-cloud (Azure, GCP) | AWS being genuinely good first. | L |

Tracked on GitHub: [#35](https://github.com/teemops/tops/issues/35) ·
[#36](https://github.com/teemops/tops/issues/36) ·
[#37](https://github.com/teemops/tops/issues/37) ·
[#38](https://github.com/teemops/tops/issues/38) ·
[#39](https://github.com/teemops/tops/issues/39) ·
[#40](https://github.com/teemops/tops/issues/40) ·
[#41](https://github.com/teemops/tops/issues/41) ·
[#42](https://github.com/teemops/tops/issues/42), and the documentation site below as
[#62](https://github.com/teemops/tops/issues/62).

### Scan Amazon Bedrock

*Researched 2026-08-11. Not committed — this is a Later item with a named trigger. Full
research, the control list, the comparator analysis and the sizing:*
**[docs/features/bedrock-scanning.md](features/bedrock-scanning.md)**.

**Why it's cheap.** The scan engine is already generic enough: the installed AWS SDK
(3.369.9) ships every Bedrock client, all the calls needed resolve through
`GenericAwsScanner` with **no PHP**, and `ReadOnlyAccess` on the onboarding role already
grants every `bedrock:` read action needed — so this would work against **every account
already onboarded, with no CloudFormation change**. It is two `tasks.json` files (`bedrock` and
`bedrock-agent` are separate SDK clients) plus rules.

**Why it's not free.** The highest-value check — model invocation logging, off by default —
is *account-level* on a *regional* service, and that combination is new. It surfaces a
latent defect in D-11: `ScanResult::identityHash()` does not include region, so the same
check run in ~17 regions collapses to one finding and the last region job to finish wins.
Worse, once separated per region, an account using Bedrock in one region gets sixteen
findings telling it to enable logging in regions it does not use — the exact
unactionable-coverage failure this product is meant to be the opposite of. Suppressing that
needs a cross-task condition the evaluator cannot express today.

**The shape of a first ship, if the trigger fires:** resource-scoped rules only — guardrail
prompt-attack and sensitive-information filters, agents without a guardrail, and CMK
encryption on custom models, agents, guardrails and prompts. Those fire only where a
resource exists, so they cannot produce findings in unused regions, and they need no engine
change at all. **Do not ship the logging check without the region gating**; A+B without C in
the research doc's table is the one combination that makes the product worse.

**Scope note:** a configuration scanner checks that the controls making runtime attacks
*detectable and containable* are switched on. It does not detect prompt injection or
jailbreaks, and nothing we ship or say should imply it does.

**Worth doing regardless:** the identity-hash collision above is a real property of a
shipped model, not a Bedrock issue. Record it as a known limitation of D-11 whether or not
Bedrock is ever scanned, so the next regional account-level check does not rediscover it in
production.

### User documentation site

*Ongoing, in parallel with feature work — not a Now/Next gate. Full reasoning on location,
domain and format in D-10.*

Unlike the scanner-coverage backlog this doesn't have a natural per-item unit, so track it
as a first slice plus continuous growth rather than a checklist.

**First slice**
- [x] `user-docs/` exists — 10 of the IA's 34 pages written, closing the entire
      priority-1 set: what TOPS is, how TOPS connects to AWS, install, connect your first
      AWS account, run your first scan, what the IAM role can do, reading a finding,
      resolving a finding, the security model, reporting a vulnerability. Scan profiles
      and member management are priority-2, still ahead, per the IA's writing order
- [x] `docs.teemops.com` resolves and serves the built site — confirmed live 2026-08-06.
      **D-13** picked the renderer (MkDocs + Material); it deploys as Cloudflare **Workers**
      static assets (`wrangler.jsonc` at the repo root) rather than Pages as originally
      written
- [ ] `README.md`'s setup section trims to a summary linking into `user-docs/`, so
      installation instructions have exactly one canonical copy

**After that:** a page per feature as it ships, written by whoever ships the feature —
the same "tests ship with the change" discipline applied to docs. No dedicated
documentation sprint; no page written for a feature that doesn't exist yet.

---

## NOW

The path to putting TOPS in a design partner's hands. Sequenced — do them in order.

### N-11 · Lock down the account-linking SNS topic

*Security. Added 2026-08-02, found while producing the AWS integration architecture diagram
for CISO/CTO audiences — the diagram describes SNS as "the one inbound path into TOPS",
which invites exactly this question.*

The parent account's `teemops-sns` topic carries an `allow-all-aws-users` statement granting
`sns:Publish` to `Principal: AWS: "*"`. The narrowing condition beside it is commented out,
and could never have worked: `sns:Publish` supports no message-content condition keys, and
CloudFormation's custom-resource publish carries no message attributes to match on.

**Why it is not a five-alarm fire, stated honestly.** The exposure is publish-only — the
sibling statement is scoped by `AWS:SourceOwner`, so nobody can subscribe, delete the topic
or rewrite its policy. Damage still requires guessing `external_id`, a CSPRNG v4 UUID. The
credible risks are denial-of-onboarding, data pollution, and unauthenticated cost — not
exfiltration. **Why it is still N-11.** It is a `Principal: "*"` in a public repo on the one
inbound path, and the number of reviewers who will read that statement and stop reading is
not zero.

**Two phases.** Phase 1 is consumer-side validation with no AWS changes — the message already
carries the child account twice, in `StackId` and `TopsRoleArn`, and neither is checked
against the other. Phase 2 is an install-scoped filter secret: a GUID minted once at install,
threaded through the parent stack, both child templates and the quick-create URL, and matched
in the subscription's `MessageBody` filter policy. It is a speed bump rather than an
authentication boundary — the value is shared with every account admin onboarded — but it
removes internet-wide unauthenticated access without putting AWS credentials back into the
web tier or capping the product at SNS's 200-principal quota.

**Both shipped on 2026-08-02**, ahead of the original sequencing, because phase 1 turned out
to be the load-bearing half: the topic policy is unchanged and cannot be narrowed, so what
actually validates a link request is the consumer. Two checks were added beyond the written
acceptance criteria — the `StackId` cross-check on `Update` as well as `Create`, and the
removal of the unsigned "direct message" fallback — because closing the gaps exactly as
specified would have left the same hole one step to the side.

**Verified on a real account 2026-08-03.** SNS payload filtering does match a live
CloudFormation custom-resource message: a correct install id linked as before, and a
deliberately wrong one was filtered to the quarantine queue without reaching `teemops_main`.

**The live run earned its keep.** It found `aws:link-rejections` answering "No account-linking
messages have been rejected" while a rejected message sat in quarantine — both numbers
individually correct, since the cache counters only see what reached the poller, but the
command an operator is told to run was reassuring them about the exact failure the quarantine
queue exists to expose. The silent failure had moved one layer out rather than being closed.
Fixed by reporting quarantine depth alongside the counters, and giving the all-clear only when
both are positively known to be empty — a queue that cannot be read now reports as unknown,
not as zero.

Full research, the four options considered and why three were rejected, the open issues on
the proposed design, and acceptance criteria for both phases:
**[docs/features/sns-topic-publish-authorization.md](features/sns-topic-publish-authorization.md)**.

- [x] Phase 1 — consumer-side validation ships with tests — [#100](https://github.com/teemops/tops/issues/100)
- [x] Phase 2 — install-scoped filter secret, verified end to end against a real AWS account — [#101](https://github.com/teemops/tops/issues/101) — **shipped and verified; the issue is still open on GitHub and wants closing**
- [x] Payload filtering confirmed to work against a real CloudFormation message before it is relied on — 2026-08-03
- [x] The architecture diagram's "unlimited child accounts" claim still holds after the change

---

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

### ~~N-6 · SNS signature verification~~ ✅ Done 2026-07-30

*Security. Promoted from X-2 on 2026-07-30 because the repo went public, then closed the same day.*

`SnsSignatureVerifier::verify()` checks that three JSON fields are present and then
`return true`. The `TODO` listing the four real steps — fetch `SigningCertURL`, download the
certificate, verify the chain, verify the signature — is untouched, and `verifyWithAwsSdk()`
is a comment-only shell. `SubscriptionConfirmation` messages are auto-accepted.

**What changed.** Nothing in the code. What changed is who can read it. The consuming route
is unauthenticated by design and is the one that registers IAM role ARNs during account
linking. While the repo was private, an attacker had to guess the shape of an acceptable
payload; now the accepted fields and the absence of any cryptographic check are public.
X-2's own text said this reasoning "expires at public release" — it has.

**Acceptance criteria** — all met 2026-07-30
- [x] Given a message with a valid AWS signature, when it arrives, then it is accepted
- [x] Given a message with a missing, malformed or incorrect signature, when it arrives, then it is rejected and logged
- [x] Given a `SigningCertURL` not on an `amazonaws.com` host over HTTPS, when it arrives, then it is rejected **without** fetching the URL — that is the classic bypass
- [x] Given a `SubscriptionConfirmation`, when it arrives, then it is verified before the subscription is confirmed
- [x] Given verification fails, when the request completes, then no account state changed
- [x] **Added during implementation:** given a validly-signed message from a topic that is not ours, then it is rejected

**Technical notes — two corrections to what this item originally assumed**

1. **`aws/aws-sdk-php` does *not* ship `Aws\Sns\MessageValidator`.** The installed
   `src/Sns/` holds only `SnsClient` and exceptions. AWS publishes the validator as a
   separate package, `aws/aws-php-sns-message-validator`, now added as a dependency. Its
   host pattern is stricter than the criterion above asked for — it requires
   `sns.<region>.amazonaws.com`, not merely any `amazonaws.com` host.

2. **A valid signature is not sufficient**, and this item did not originally say so.
   Anyone can create their own SNS topic and have AWS sign messages for it, so signature
   verification alone would accept a validly-signed message from a stranger's topic. The
   `TopicArn` is therefore checked against `services.aws.sns_arn`, and **fails closed**
   when no topic is configured. This is the check that is usually missed.

**Also fixed, and it mattered:** SNS posts JSON with `Content-Type: text/plain`, so Laravel
never parsed the body into the input bag. `$request->input('Message')` was always empty,
which means the callback returned 400 for every real notification. The endpoint could not
have worked as written — the SQS path is what has been carrying account linking.

The dead `verifyWithAwsSdk()` shell is gone.

---

### ~~N-5 · Release pipeline and published images~~ ✅ Done 2026-07-31

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
- [ ] Given a machine with only Docker, when I run `install.sh`, then the stack starts from pulled images with no PHP, Composer, Node or npm present — images now exist to test this against; not yet run on a clean machine (folds into **N-7**)
- [x] Given a push to `develop` with passing tests, when the workflow runs, then a new tag, GitHub release and set of pushed images exist — **confirmed 2026-07-31 by v0.1.2**
- [ ] Given a push to `develop` with failing tests, when the workflow runs, then nothing is tagged or published — implemented (the Tests-gate step in `tag-release.yml`), not yet exercised against an actual red build
- [ ] Given the tag already exists, when the workflow re-runs, then it is a no-op rather than an error — implemented, not yet re-run to confirm
- [ ] Given I set `TOPS_IMAGE_TAG` to a previous version, when I restart, then I am running that version — not yet exercised
- [x] Given I am a contributor, when I run `install-build.sh`, then the stack builds from my working tree exactly as it does today — verified locally 2026-07-30
- [ ] Given an upgrade, when containers restart, then migrations apply automatically (the entrypoint already does this) — not yet exercised across a real version bump

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

**Revised 2026-07-31** — the release is no longer cut straight off a push to `develop`.
It is a two-phase, pull-request-gated process: **Prepare release**
(`.github/workflows/release.yml`, run manually) bumps `VERSION`, writes the
`CHANGELOG.md` entry, and opens a `release/vX.Y.Z` pull request against `develop`;
merging that PR triggers **Tag and release** (`.github/workflows/tag-release.yml`),
which publishes the images, cuts the tag and creates the GitHub release. The
`workflow_run` gate on Tests is gone — the release PR carries its own Tests run, and
merging it is the gate. Documented in `docs/processes/release.md`.

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

**Resolved 2026-07-31** — `DOCKERHUB_USERNAME` and `DOCKERHUB_TOKEN` are configured, and
`teem/tops` images are live on Docker Hub, multi-arch (amd64+arm64), so the pull-and-run
path can now be exercised for real.

**Found 2026-07-31, same day, and resolved the same day:** the `v0.1.1` tag, GitHub release
and Docker Hub images from earlier that day turned out not to be a clean product of the
pipeline — the tag pointed at the `develop` commit from just before PR #46's fix, with
`VERSION` still reading `0.1.0`. Ben cut a real release, **v0.1.2**, through the fixed
pipeline by hand rather than scripting a fix for the v0.1.1 mess. Verified against the
running system, not just the workflow's own "success" status:

- PR #47 (`chore(release): v0.1.2`, branch `release/v0.1.2`) merged into `develop` at
  2026-07-31T07:04.
- `develop`'s `VERSION` is `0.1.2` and `CHANGELOG.md` exists with a `v0.1.2` entry —
  the drift that broke `v0.1.1` is gone.
- The `Tag and release` workflow ran to completion (21m34s) for the first time ever under
  its current, post-split form.
- Tag `v0.1.2` points at the PR #47 merge commit, and that commit's `VERSION` file reads
  `0.1.2` — unlike `v0.1.1`, tag and content agree.
- All three images (`teem/tops`, `teem/tops-backup`, `teem/tops-installer`) are on Docker
  Hub at `v0.1.2`, multi-arch (amd64 + arm64).
- `teem/tops:latest`'s digest is byte-identical to `teem/tops:v0.1.2` — `latest` now points
  at a real, tagged, released build, not the orphaned `v0.1.1`.

**N-5 is done.** `v0.1.1` is left as-is — a known-messy artifact superseded by `v0.1.2`,
not worth spending more effort unwinding. What's genuinely still unverified is narrower
than N-5's original scope and doesn't block the milestone: the failing-tests gate, the
tag-already-exists no-op, and the `TOPS_IMAGE_TAG` rollback path have none of them been
exercised for real yet (see the acceptance criteria above). Revisit opportunistically —
none of the three is likely to bite before a design partner is in the door.

---

### ~~N-10 · One installer, in colour~~ ✅ Done 2026-07-31

*Added 2026-07-31 as critical, at Ben's call, and sequenced ahead of N-7. This is a
user-experience defect, not a feature — the four questions in `docs/practices/product.md`
all answer the same way, because this removes a script rather than adding one.*

**Problem, from the user's side.** The repo root has `install.sh` and
`install-messaging.sh`. A first-time user has to work out that there are two steps, which
order they go in, and that the second one is the one that touches their AWS account. The
names do not help: "messaging" is our word for it, not theirs — they are trying to *scan an
AWS account*, and nothing on the tin says so.

Worse, the second script asks for a region and then deploys CloudFormation, SQS, SNS and an
S3 bucket into whatever account the ambient credentials happen to point at, without ever
saying which account that is or what it is about to create. On a machine with several
profiles that is a genuinely easy mistake to make, and an annoying one to unpick. It also
fails late and unhelpfully — the credential check happens inside the installer container,
after a build, rather than in the first second.

And it is monochrome. That sounds cosmetic and partly is, but the old TeemOps installer set
an expectation here, and a wall of undifferentiated text is measurably harder to scan for
the one line that says what went wrong.

**Shape of the work**

| Piece | Detail |
| --- | --- |
| One script | `install.sh` does both phases. `install-messaging.sh` is deleted, not deprecated. |
| Flags | `--aws` (no prompt), `--no-aws`, `--aws-only` (just phase 2, for a re-run or the contributor path), `--help`. |
| The prompt | Before anything is created: the exact resource list, the cost shape, and that it is skippable. |
| Credential check | `aws sts get-caller-identity` on the host, up front. Show the account **and the caller ARN**, and confirm before deploying. |
| Failure mode | A missing or misconfigured AWS CLI **warns and skips**, leaving a working TOPS. It does not abort an install that has already succeeded. |
| Colour | The original TeemOps palette, plus its ASCII banner. Off when stdout is not a terminal, and when `NO_COLOR` is set. |
| Installer image | `docker-compose.install.yml` gains `image: teem/tops-installer`, so the AWS step pulls rather than builds — D-8's rule applied to the half of the install that was still exempt from it. |

**Acceptance criteria**
- [x] Given the repo root, when I look at it, then there is exactly one install script a user is asked to run
- [x] Given I run `./install.sh`, when the app is up, then I am asked — once, in plain language — whether to connect an AWS account, and told what that creates
- [x] Given my AWS CLI is not configured, when the AWS step runs, then I get the red "check your AWS CLI configuration" warning and a working TOPS, not a failed install
- [x] Given my AWS CLI *is* configured, when the AWS step runs, then I see the account ID and caller ARN and must confirm before anything is deployed
- [x] Given I pipe the script into `bash` with no terminal, when it reaches a prompt, then it declines and carries on rather than hanging
- [x] Given I already installed TOPS, when I want to connect an account later, then one documented command does it (`./install.sh --aws-only`)
- [x] Given a non-UTF-8 terminal, when the banner prints, then it is plain text rather than mojibake
- [ ] Given a real AWS account, when I accept the prompt, then the stacks deploy and the app picks up `generated/teemops.env` — **folds into N-7**; nothing here has been run against a real account

**What shipped.** `install.sh` absorbed `install-messaging.sh`, which is deleted. The AWS
step is `setup_aws()`: intro, `check_aws_identity()`, region resolution, confirmation,
`docker compose run --rm installer`, then `docker compose up -d` so the new
`generated/teemops.env` is actually loaded — a step users previously had to know to do
themselves. README, `docker-compose.README.md`, `DEBUG.md`, `.env.docker.example`,
`install-build.sh` and `SnsSignatureVerifier`'s error message all now name one script.

`tests/install-flow.test.sh` covers it — 22 checks, `aws` stubbed, no containers, no AWS
calls, running in CI beside `install-secrets.test.sh`. It asserts the paths that only a
stranger hits: no AWS CLI, a CLI that fails, no region configured anywhere, no terminal to
prompt on, and a non-UTF-8 locale.

**Two things worth recording**
1. **The credential check must not be fatal.** The obvious reading of the reference script
   is `exit 1` when `get-caller-identity` fails. Here that would kill a run whose app half
   had already succeeded, leaving the user with a working TOPS and an error message. It
   warns and skips instead, and tells them the one command to come back with. `--aws`
   asked for it explicitly, so that mode does still fail hard.
2. **`resolve_aws_region` sets a variable rather than echoing one.** `read -p` writes its
   prompt to stderr, so `region=$(resolve_aws_region)` *looks* safe — but it is one
   redirect away from capturing the prompt into the region name and deploying to
   `AWS region to deploy into [us-east-1]:`. Not worth the cleverness.

**Deliberately not done.** The internal `docker/installer/scripts/install-messaging.sh`
keeps its name — it lives inside the image, no user ever types it, and renaming it would
touch the Dockerfile and entrypoint for no user-visible gain. `install-build.sh` also stays:
it is the contributor path, it needs PHP and Node, and merging it into `install.sh` would
put four host prerequisites back in front of the people D-8 removed them for.

**What happened next, and why sequencing this ahead of N-7 was right.** N-10 shipped as
**v0.3.0** on 2026-07-31. Within hours,
[#56](https://github.com/teemops/tops/issues/56) came in from a real install: the AWS step
died with `/workspace/.env: line 80: 17: command not found` and the UI then reported
`AWS messaging not configured (missing AWS_PARENT_ACCOUNT_ID)`. Four compounding bugs, none
of which the 22 stubbed checks could have caught, fixed in
[#57](https://github.com/teemops/tops/pull/57):

1. The installer **sourced** `/workspace/.env` as a shell script. Compose env files are not
   shell — `TOPS_BACKUP_FULL_CRON=0 17 * * *` assigned `0` and then *executed* `17`. Under
   `set -e` that killed the install before a single stack deployed. `load_dotenv` now parses
   the file the way Compose reads it, executing nothing.
2. `main` called `setup_aws` as an `if` condition, and bash disables `errexit` for the whole
   body of a function invoked that way — so a failed `docker compose run installer` fell
   through to printing "AWS messaging is deployed". **That is why #56 arrived as a UI
   complaint rather than an install failure.** The exit code is checked explicitly now, as
   is the existence of `generated/teemops.env`.
3. Every failing `sts get-caller-identity` was reported as "credentials not configured,
   mount `~/.aws`", with AWS's real error going only to the log. An opt-in region the account
   has not enabled returns `InvalidClientTokenId` for perfectly good credentials, which sent
   the reporter looking at the wrong thing.
4. `core-docker/template.yaml` re-exported six of `sqs.cfn.yaml`'s eight outputs, dropping
   `TopsMainDlqName` and `TopsMainDlqArn` — required by `write_env_file`, which runs last, so
   the install spent a full two-stack deploy before failing.

`tests/install-messaging.test.sh` is new and derives the required CloudFormation outputs from
`install-messaging.sh` itself, so template drift cannot pass unnoticed. 93 checks across the
three installer suites. **v0.3.1** then dropped the second confirmation
([#58](https://github.com/teemops/tops/pull/58)) — the step asked three times about one
decision, which only trains people to hit `y` without reading.

**The lesson for N-7:** every one of these needed a real machine and a real account. Verify
against **v0.3.1 or later**, and treat the stubbed installer suites as a regression net, not
as evidence the path works.

---

### ~~N-7 · Verify AWS onboarding end to end~~ ✅ Done 2026-08-01

*Promoted from X-9 on 2026-07-31, when the 5-partner-by-2026-08-31 target made it
critical-path rather than something to get to eventually. Closed the next day.*

**Problem:** nobody had connected a real AWS account and run a scan through the documented
path (`install.sh`'s AWS step, CloudFormation quick-create, SNS/SQS account linking).
N-3 documented that path and D-3 chose to keep it as-is rather than simplify it, but neither
closed the loop with a real account and real spend. This was the one item in the milestone's
own "done when" that had not been demonstrated at all, on any account.

**It has now been demonstrated.** Ben ran install → add AWS account → scan on a clean copy
at `~/my/sandbox/testops/tops`, and reached Insights and Findings. **The documented path
works end to end on a real account.**

**Acceptance criteria**
- [x] Given a real AWS account, when I follow the documented onboarding path, then the
      CloudFormation stack deploys and SNS confirms the subscription
- [x] Given the account is linked, when I run a scan, then it completes and returns findings
      from real resources — Insights and Findings both populated
- [ ] Given the setup guide's cost description, when I check the actual AWS bill, then it
      matches what was documented — **not yet checkable**; AWS billing lags by a day or more.
      See below.
- [x] Given something goes wrong, when I hit it, then either the troubleshooting section
      already covers it, or it gets added — **nothing went wrong**, which satisfies this
      vacuously rather than by exercise. The troubleshooting section remains untested by a
      failure on this run.

**What was verified, against the install itself rather than a recollection**
- `VERSION` and `TOPS_IMAGE_TAG` both read **`v0.3.1`** — the fixed installer, not v0.3.0.
- `generated/teemops.env` written `2026-08-01T02:44:15Z`, carrying all eight CloudFormation
  outputs — **including `TOPS_SQS_DLQ_NAME` and `TOPS_SQS_DLQ_ARN`**, the two that
  `core-docker/template.yaml` was dropping before PR #57. That is the bug that made the
  install spend a full two-stack deploy before failing, and it is gone.
- `AWS_PARENT_ACCOUNT_ID` and `TOPS_CFN_TEMPLATE_URL` are both present — the two variables
  [#56](https://github.com/teemops/tops/issues/56) reported missing from the UI.
- `generated/install.log` has no warning or error, and ends on
  `[installer] Messaging install complete.` `sts-error.log` is zero bytes.

**What this closes, and what it does not.** The *technical* half of the milestone's "done
when" is now demonstrated: the path from a clean copy to findings works, on a real account,
on a released build. The rest of that sentence — "**a design partner** can install TOPS from
the documentation alone … **without a call**" — is not demonstrated by the maintainer doing
it. Ben knows where the bodies are buried; a stranger does not. The remaining risk in the
milestone is now a recruitment and observation problem, not a code problem.

**Still open, deliberately not blocking:** the cost criterion. Billing data does not exist
yet for a stack deployed this morning, and the honest way to close it is to look at the bill
in a few days and compare it against what the setup guide claims. Small, dateable, and worth
doing before a design partner is surprised by a charge — but not worth holding N-7 open for.

**Depended on:** N-5 (a build confirmed clean) and N-10 (an installer that was about to
change). Both closed first, which was the right sequencing — N-10's follow-up fixes in
v0.3.1 are precisely what made this run succeed.

---

### ~~N-9 · Generate real database passwords at install time~~ ✅ Done 2026-07-31

*Added 2026-07-31 and closed the same day. Security, not optional — treated with the same
weight as N-6 (SNS verification).*

**Problem:** `docker-compose.yml` has three password variables with hardcoded, insecure
fallbacks — `MYSQL_ROOT_PASSWORD:-mysql` (`docker-compose.yml:7,19,139,179`),
`MYSQL_PASSWORD`/`DB_PASSWORD:-teemops_dev_password` (`:10,62,109`), and
`TOPS_BACKUP_PASSWORD:-tops_backup_dev_password` (`:141,181`). `.env.docker.example` ships
the exact same values as the *example*, so anyone who follows the setup docs without
manually overriding three specific variables gets a database with a publicly known root
password — publicly, because this repo is Apache-2.0 and these values are readable by
anyone. MySQL's port (`3306`) is bound to the host by default (`:11-12`), so "publicly
known password" and "reachable outside the container network" are both true at once,
which is the actual exposure. `install.sh` already solves exactly this shape of problem for
`APP_KEY` — generate once, at install time, never ship a real value in the repo — this is
the same pattern applied to three more variables.

**Shape of the work**
| Piece | Detail |
| --- | --- |
| `.env.docker.example` | Blank `MYSQL_ROOT_PASSWORD`, `DB_PASSWORD`/`MYSQL_PASSWORD`, and `TOPS_BACKUP_PASSWORD` — same treatment `APP_KEY=` already gets — with a comment explaining they're generated at install time. |
| `install.sh` (`write_env()`) | Generate all three, only if unset, the same way `APP_KEY` is generated (`openssl rand -base64 …`) — mirrors lines 165-170 exactly. |
| `docker/scripts/prepare-build.sh` | The contributor/build-from-source path needs the identical block — it already generates `APP_KEY` (lines 11-25), so this is additive to existing machinery, not new. |
| `docker-compose.yml` | Remove the `:-mysql` / `:-teemops_dev_password` / `:-tops_backup_dev_password` fallbacks. A missing password should fail loudly, not silently start with a known one. |
| Usernames | Unchanged (`teem`, `tops_backup`) — usernames aren't secrets, only the passwords need generating. |

**Acceptance criteria** — all met 2026-07-31
- [x] Given a fresh `install.sh` run, when `.env` is created, then `MYSQL_ROOT_PASSWORD`, `DB_PASSWORD`/`MYSQL_PASSWORD` and `TOPS_BACKUP_PASSWORD` are each freshly generated, not the values in `.env.docker.example`
- [x] Given `install.sh` is re-run against an existing `.env`, when it completes, then none of the three passwords changed — they must stay stable forever, for the same reason `APP_KEY` does (see technical notes)
- [x] Given `install-build.sh` / `prepare-build.sh`, when it runs, then the same three passwords are generated the same way
- [x] Given any of the three password variables is unset, when `docker compose up` runs, then the affected container fails to start rather than falling back to a known default
- [x] Given `.env.docker.example`, when anyone reads it, then no real password value appears anywhere in the repo

**What shipped.** `.env.docker.example` blanks all four secrets (the three passwords plus
`DB_PASSWORD`); `install.sh`'s `write_env()` and `docker/scripts/prepare-build.sh` each
generate them with `openssl rand -base64 32`, only when unset, mirroring the existing
`APP_KEY` block in both files; `docker-compose.yml` uses `${VAR:?…}` in all nine places, so
a missing password stops the container rather than substituting a known one.

`tests/install-secrets.test.sh` covers it, and runs in CI as the `Install secrets` job — 30
checks over a throwaway directory, starting no containers. It exercises both installers,
asserts the passwords survive a re-run, and uses `docker compose config` to prove each
variable is genuinely required. Writing it caught a real bug before it shipped: `grep` exits
1 when a key is absent from `.env` entirely, and under `set -o pipefail` that aborted the
installer instead of generating the missing password.

**Verified against a real MySQL, not just the scripts.** A base64 password contains `+`, `/`
and `=`, which is exactly the sort of thing that survives the shell and then breaks
somewhere downstream. Confirmed on `mysql:8.0` with generated values: Compose's `.env`
parsing, MySQL's first-init, the `mysqladmin -p…` healthcheck, the app-user login, and the
quoted `password="…"` my.cnf that `docker/backup/entrypoint.sh` writes.

**Two things found during implementation**
1. `install.sh` gained a `[[ "${BASH_SOURCE[0]}" == "${0}" ]]` guard around `main` so the
   test can source `write_env` without running an install. `bash <(curl …)` still runs —
   both are the same `/dev/fd` entry there.
2. The manual-rotation instructions now in `docker-compose.README.md` have to alter
   **both** root accounts. `mysql:8.0` creates `root@localhost` and `root@%`, and the
   healthcheck connects over the socket as `root@localhost`, so rotating only `root@%`
   leaves the container reporting unhealthy. Checked against a live container.
3. `install-build.sh` creates `.env` with a plain `cp`, so its mode came from the umask —
   0644 on a typical host. Harmless while the file held only example values, not once it
   holds real passwords. `prepare-build.sh` now chmods it to 0600, matching what
   `install.sh` already produced by writing through `mktemp`.

**Not done, deliberately.** An install from v0.1.2 or earlier keeps its known password
silently — `install.sh` sees a non-empty value and leaves it alone, by design, since that
value is what MySQL's datadir holds. The rotation instructions cover it. A warning when a
known-default value is detected would reach people who won't re-read the docs; that wasn't
in this item's scope, and it's a small addition if it's ever wanted.

**Technical notes**
- `MYSQL_ROOT_PASSWORD` and `MYSQL_PASSWORD` are consumed exactly once, by the official
  `mysql:8.0` image's own init, only against an empty datadir. Generate before the first
  `docker compose up`, and never regenerate against a datadir that already has data — the
  stored credential doesn't move with `.env`, so drifting the two apart locks you out
  exactly like rotating `APP_KEY` would.
- `TOPS_BACKUP_PASSWORD` is the one exception: `docker/backup/entrypoint.sh:98-99` runs
  `CREATE USER IF NOT EXISTS` *and* `ALTER USER ... IDENTIFIED BY` on every container start,
  so that one actually is safe to rotate later. It doesn't need to be today — generate it
  once alongside the other two for consistency.
- **Scope, decided 2026-07-31:** passwords only. MySQL's port stays published to the host by
  default — some self-hosters want direct DB access — so this closes the "known credential"
  half of the exposure, not the "reachable at all" half. Firewalling or un-publishing the
  port is a separate, later hardening item if it's ever raised.
- **Existing installs aren't retroactively fixed.** Every install through v0.1.2 already has
  the known default written into its `.env`, and `install.sh` never overwrites an existing
  `.env`. Low blast radius today — these are Ben's own test installs, not a design partner's
  — but worth a line in `docker-compose.README.md` about rotating manually if anyone kept
  one of the early installs running.

---

### ~~N-8 · Base image for the app container~~ ✅ Done 2026-07-31

*Added 2026-07-31, unblocked and built the same day. Started ahead of its sequencing behind
N-7 and N-9, at Ben's call — those two remain the critical path to the design-partner target.*

**Problem:** `docker/app/Dockerfile:8-23` installs `nginx`, `supervisor` and four `-dev`
libraries via `apt-get`, then compiles `bcmath`, `opcache`, `pdo_mysql` and `zip` via
`docker-php-ext-install` — on **every** build of the app image, for **both** `linux/amd64`
and `linux/arm64`. None of that changes release to release; only the app code and configs
below it (lines 25-52) do. The arm64 leg runs under QEMU emulation in CI, where compiling
PHP extensions is markedly slower than native — `tag-release.yml` already budgets 45
minutes for the multi-arch build specifically because of this. It costs the same thing
twice: slower CI on every release, and a slower first build for every contributor and
self-hoster who builds from source (`install-build.sh`).

**Shape of the work**
- New image, same Docker Hub org: **`teem/tops-base`**. Contains `docker/app/Dockerfile`'s
  current lines 6-23 (the `FROM php:8.3-fpm-bookworm`, the `apt-get install`, the
  `docker-php-ext-install`) and nothing else.
- `docker/app/Dockerfile` changes its `FROM` to a pinned `teem/tops-base` tag and drops the
  `apt-get`/`docker-php-ext-install` block entirely. Everything from `COPY docker/app/nginx.conf`
  onward (configs, `entrypoint.sh`, the app code, the vendor/build-artifact checks) is
  unchanged.
- `teem/tops-base` still needs to be multi-arch — the app depends on both — but it only
  needs building when its own inputs change (a PHP version bump, a new system package, a
  new extension), which is rare. That's the entire point.
- Version pinning, not `latest`: the app Dockerfile references an explicit
  `teem/tops-base:<tag>`, so a base rebuild never silently changes what the next app build
  produces. Bumping it is a one-line, reviewable diff.
- Given how rarely this changes, a manual `workflow_dispatch` (or even a documented
  `docker buildx build --push` run by hand, per D-3's reasoning for `install.sh`) is
  probably the right amount of automation — resist building change-detection for
  something that moves a few times a year.

**Acceptance criteria**
- [x] Given `docker/app/Dockerfile`, when it builds, then it starts `FROM teem/tops-base:<tag>`
      and contains no `apt-get` or `docker-php-ext-install` step — pinned to
      `teem/tops-base:php8.3-1`
- [x] Given `teem/tops-base`, when it's built, then it's published for `linux/amd64` and
      `linux/arm64` — pushed manually 2026-07-31 and verified against the registry with
      `docker buildx imagetools inspect`: index digest `sha256:71c4a842…`, one manifest per
      architecture. `.github/workflows/publish-base.yml` is there for the next one.
- [x] Given a base-image change is needed, when someone makes it, then a documented process
      publishes a new `teem/tops-base` tag and the app Dockerfile's pin is bumped in the
      same PR — `docs/processes/release.md`, "Base image for the app container". Tags are
      immutable and the workflow refuses to overwrite one, which is what makes the pin mean
      anything.
- [x] Given a release with an unchanged base tag, when `tag-release.yml` builds the app
      image, then it no longer compiles PHP extensions — measured locally on native amd64,
      `--no-cache` both times: **48.5s → 7.1s** warm, **13.8s** cold including the pull. That
      is the cheap architecture; the arm64 leg in CI compiles the same extensions under QEMU,
      so the release-path saving should be larger. **Still to record: the real CI before/after,
      after the first release through this.** The 45-minute budget in `tag-release.yml` can
      probably come down then — leave it until there is a number behind it.
- [x] Given a contributor running `install-build.sh` for the first time, when the app image
      builds, then it pulls `teem/tops-base` rather than compiling extensions locally —
      verified by deleting the local base image and rebuilding: the build pulled
      `teem/tops-base:php8.3-1` from Docker Hub and compiled nothing, in 13.8s total

**Verified equivalent, not just faster:** app images built from the old and new Dockerfiles
have an identical `php -m` and an identical `dpkg -l`. The base image is the old lines 6-23
moved, not rewritten.

**Technical notes**
- `docker/backup/Dockerfile` and `docker/installer/Dockerfile` are out of scope — neither
  compiles anything (`percona/percona-xtrabackup` and AWS's SAM build image are already
  prebuilt), so there's no equivalent win there.
- `docker-compose.build.yml` needs no change: it still builds `docker/app/Dockerfile` from
  the working tree, which will just pull `teem/tops-base` as its first layer instead of
  `php:8.3-fpm-bookworm`. Confirmed — no change was needed.
- The base image has no `latest` tag, deliberately. The app must always name the exact base
  it was built on; a floating tag would put the thing the pin exists to prevent back within
  reach.
- `publish-base.yml` sets no buildx cache. It runs a few times a year, so the cache would be
  cold every time anyway — and caching the layer whose rebuild this change removes from the
  release path is the wrong thing to spend complexity on.

---

### ~~N-3 · Setup documentation a stranger can follow~~ ✅ Done 2026-07-30

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

### X-1 · New-device email OTP — **parked 2026-08-06**

**Why it is parked.** Picked up on 2026-08-06, taken through Discovery, and stopped before a
user story was written. Nobody has asked for it, the milestone is limited by recruiting
design partners rather than by shipping features, and it is the largest open item on the
board. The counter-argument — the repo is public, this is a security product, and
password-only access to a map of someone's AWS weaknesses is a poor look — is real, and is
why this is parked rather than moved to *Not Doing*. **D-5 still stands**: when it is built,
it is email OTP, not TOTP.

**Two findings from the discovery, both worth keeping:**

1. **This is a rewrite, not the extraction the summary below claims.**
   `FirebaseAuthController::requestEmailOtp()` identifies the user by verifying a Firebase
   **ID token** and caches under `mfa_email_otp:{firebase_uid}` — a column that is null for
   every native-auth user. Both the input and the cache key have to be replaced. What is
   genuinely reusable is about six lines plus `maskEmail()`; the valuable inheritance is the
   *shape* (cache-backed, 6-digit, 10-minute expiry), not the code. It also uses `Mail::raw`
   where the repo's pattern is a queued `Notification`.

2. **The default install cannot send email to a real inbox, and `.env` cannot fix it.** See
   **X-12** below. This is a prerequisite, and shipping OTP without it would deliver the
   second factor to an unauthenticated mail catcher on the same host — the appearance of MFA
   without the substance, which is worse than not shipping it.

---

**User story** *(retained as written; not yet agreed)*
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

### X-12 · A self-hoster cannot point TOPS at their own SMTP

*Bug. Found 2026-08-06 during X-1's discovery, but it is not an MFA problem — it affects
mail that ships today.*

`docker-compose.yml` sets `MAIL_MAILER`, `MAIL_HOST` and `MAIL_PORT` in the `environment:`
block, hardcoded to the bundled `maildev` catcher. The comment directly above them, added
for the queue settings, explains exactly why this is a bug: **`environment:` overrides
`env_file:`**, so a value in the operator's `.env` is ignored. Mail got the treatment the
queue settings were deliberately spared.

The consequences exist now, without MFA:

- **Email verification** (`MustVerifyEmail` is live) and **organisation invitations** both
  send to a catcher, so an invited colleague never receives anything. Today the operator
  has to know to open maildev's web UI on port `8090`.
- That UI is published on **all interfaces with no authentication** — fine for a local mail
  catcher, not fine as the place account email lands.
- Changing it means editing `docker-compose.yml`, a file `install.sh` owns and an upgrade
  may replace.

**The fix is XS**: move the three `MAIL_*` keys out of `environment:` so `.env` layering
wins, keep `maildev` as the default for anyone who has configured nothing, and document the
real-SMTP variables in `.env.example`. **Blocks X-1**, and should be done regardless of
whether X-1 is ever built.

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
3. ~~**How many design partners, and by when?**~~ ✅ Resolved 2026-07-31 — **5 by
   2026-08-31.** See the milestone target above.
4. ~~**Does the SNS gap need closing before you hand the code to an external party?**~~
   ✅ **Answered by events 2026-07-30** — the repo is public, so the code is already in
   arm's-length hands. Promoted to **N-6**.
5. **What does a design partner have to tell us for this milestone to count as a
   success?** Worth deciding before we start, not after.

---

## Changelog

- **2026-08-06** — **The plan turned from shipping to recruiting, and this document caught
  up with itself.** X-1 (email OTP) was picked up, taken through Discovery and **parked**
  before a user story existed: nobody has asked for it, and it is the largest open item on a
  board whose stated constraint is finding five operators. Its discovery is kept, because it
  found two things worth more than the feature would have been — the "generator already
  exists, mostly extraction" claim is wrong (it is keyed on a Firebase ID token and
  `firebase_uid`, so it is a rewrite), and **the default install cannot send email to a real
  inbox at all**, now filed as **X-12**. That second one is a live bug affecting email
  verification and organisation invitations today, not a hypothetical MFA prerequisite.
  In its place, **N-12**: `teemops.com` built as a single design-partner recruitment page
  (**D-14**), with the pre-pivot `design/marketing/web/` mockup deleted — it sold a hosted
  product with Free/Starter/Pro/Business pricing and CTAs pointing at a domain that now
  redirects elsewhere, contradicting D-1 and D-6 on its face. **A review of this document
  against GitHub also found it stale in four places**, all now corrected: N-11's checkboxes
  were unticked though it shipped on 2026-08-03; the workstream's "Open — UI" table still
  listed #87, #88, #89 and #91 four days after they closed; `docs.teemops.com` was recorded
  as not yet connected when it has been live and serving; and [#109](https://github.com/teemops/tops/issues/109)
  and [#111](https://github.com/teemops/tops/issues/111), both filed on 2026-08-03 out of
  N-11, appeared nowhere at all — now **X-11** and **X-10**.
- **2026-08-02** — **S-1 landed, and the P0/P1 run of this workstream is complete.** Scan
  detail summarises and dispatches: run facts, severity totals, "fix these first" ranked by
  severity weight rather than raw count, and a drillable breakdown whose every row links into
  Findings already filtered. **The per-finding list, its remediation and its severity filter
  are deleted** — that duplication was the whole problem, and removing it is what pays for the
  feature. An older scan now renders deliberately blank apart from its run facts and a link to
  the latest, which follows directly from D-11: a current-state number under a July date would
  be wrong *and* plausible. The cost, accepted knowingly, is that "what did scan X find?" is no
  longer answerable for anything but the latest scan. Built as `FindingsBreakdownService` plus a
  shared `FindingsBreakdown` component, both keyed on an organization with an *optional*
  account, so F-5 is a re-key rather than a rewrite — there is a test covering that path
  already. The severity weighting moved to `ScanResult::SEVERITY_WEIGHTS` so the security score
  and the ranking cannot drift apart. **One bug found in review by Ben, not by the tests:** the
  scan group rendered `[object Object]`, because `rulesetLabels()` returns a label/description
  pair per ruleset and the whole map was shipped to the client typed as flat strings. Labels are
  now resolved server-side. That is the second time the missing frontend test runner has cost
  something — **worth deciding on Vitest rather than continuing to rely on manual checks.**
- **2026-08-02** — **F-3, F-7 and F-1 landed; the Findings page is now the destination S-1
  needs.** F-3 ([#85](https://github.com/teemops/tops/issues/85)) gained server-computed service
  facets — one grouped query against the indexed `scan_results.service`, with the rule that
  makes faceting usable: **the service filter does not constrain its own facet**, so selecting a
  pill leaves the rest navigable instead of zeroing them. Counts follow the list's semantics
  rather than the summary's, so a pill reading N returns N rows — a count the user can watch
  disagree with the list is worse than no count. F-7
  ([#83](https://github.com/teemops/tops/issues/83)) shipped inside it, because F-3's "and the
  URL reflects it" criterion *is* F-7 and `Findings/Index.vue` read no query parameters at all.
  F-1 ([#82](https://github.com/teemops/tops/issues/82)) was cleared first rather than after:
  it fixes the empty remediation state for **46 of 74 rules**, and S-1 makes Findings the only
  page that renders remediation, so shipping S-1 over a broken F-1 would have hidden it
  product-wide. Verified by Ben on the reference install. Two gaps recorded honestly: the URL
  parsing has **no automated test** (the repo has no JS unit runner, only Playwright), and the
  `$except` faceting parameter is a generalization slightly ahead of need, kept because F-4
  needs the same mechanism and a test covers the failure it invites.
- **2026-08-01** — **The Scans/Findings/Insights workstream added to this document,
  retrospectively.** Three feature docs
  ([durable findings](./features/durable-findings.md),
  [scan individual services](./features/scan-individual-services.md),
  [parallel region scans](./features/parallel-region-scans.md)), a signed-off wireframe set
  and roughly thirty issues (#63–#94) had been designed, agreed and partly shipped without
  ever reaching the roadmap — which still said "Now is empty" while four items had landed and
  twenty-odd were open. **This document was the stale half, again**, and in the same way the
  2026-08-01 issue reconciliation found: the plan of record silently stopped recording.
  Shipped and now marked as such: **#81** durable findings (recorded as **D-11**), **#84**
  per-service scans, **#90** (closed by #81), **#64** scan-phase instrumentation. Two
  observations that came out of writing it down. First, **#82 (F-1, remediation dropped) is a
  P0 bug that gets strictly worse when S-1 ships**, because Findings becomes the only page
  rendering remediation — it should go first and it is XS. Second, **PERF-2 (#65) is a
  correctness bug wearing a performance label**: it is why `is_partial` reads `false` after
  regions silently fail, which is why `resource_gone` was cut from #81, and it must land
  before more queue workers are run rather than after. Also flagged, not fixed: the feature
  docs call durable findings **D-1**, which collides with this document's D-1 ("open source,
  self-hosted first") — a rename to `DF-1` is proposed but touches three docs and a settled
  wireframe, so it is left as a decision rather than taken unilaterally.
- **2026-08-01** — **N-7 closed, and with it the whole of Now.** Ben ran the documented path
  on a real AWS account against v0.3.1 — install, add AWS account, scan — and reached
  Insights and Findings. Verified against the install rather than a recollection:
  `generated/teemops.env` written `02:44:15Z` with all eight CloudFormation outputs, the
  `TOPS_SQS_DLQ_*` pair that PR #57 restored among them, `AWS_PARENT_ACCOUNT_ID` and
  `TOPS_CFN_TEMPLATE_URL` both present (the two [#56](https://github.com/teemops/tops/issues/56)
  reported missing), and an install log with no warnings. **The milestone's code half is
  demonstrated; its "a design partner, without a call" half is not, and cannot be by the
  maintainer.** The constraint on 5-by-2026-08-31 is now recruitment, not engineering. One
  criterion stays open — the AWS bill against the documented cost — because billing data for
  a stack deployed this morning does not exist yet; it was not worth holding N-7 for. With
  Now empty, the next pull is X-6 (DCO in CI), on the grounds that the repo is public and
  D-7's guarantee rests on provenance nothing currently checks.
- **2026-08-01** — **Roadmap and GitHub issues reconciled.** The issue tracker had drifted:
  N-7 — the only open item in Now — had no issue at all, and neither did X-8 or the
  documentation site. Created [#60](https://github.com/teemops/tops/issues/60) (N-7, with the
  N-5 and N-10 criteria that fold into it), [#61](https://github.com/teemops/tops/issues/61)
  (X-8) and [#62](https://github.com/teemops/tops/issues/62) (user documentation site, per
  D-10). Updated [#30](https://github.com/teemops/tops/issues/30) — X-3's claim that
  `EnsureFirebaseAuthEnabledTest` is the only flag coverage is stale, `FirebaseFeatureFlagTest`
  and a `false` default in `.env.example` narrowed the gap — plus
  [#40](https://github.com/teemops/tops/issues/40) and
  [#41](https://github.com/teemops/tops/issues/41) for what N-5, N-3 and N-10 closed under
  them. This document was the stale half too: N-9, N-10 and N-5 were done in their own
  sections while At a Glance still listed them as open work.
- **2026-08-01** — **N-10 closed 2026-07-31, and immediately earned its keep.** One `install.sh`, one
  confirmation, colour, and a banner; `install-messaging.sh` deleted. Shipped as v0.3.0, and
  [#56](https://github.com/teemops/tops/issues/56) arrived from a real install within hours —
  four compounding bugs, the worst of which printed "AWS messaging is deployed" after the
  installer had failed, because `main` calls `setup_aws` inside an `if` and bash drops
  `errexit` there. Fixed in v0.3.1. Every one of the four needed a real machine to find, which
  is the case for **N-7** restated: the installer test suites are a regression net, not
  evidence the path works. Sequencing N-10 ahead of N-7 was the right call — N-7 would have
  verified an installer that was about to change, on a build that did not work.
- **2026-07-31** — **N-9 closed, same day it was found.** `.env.docker.example` ships no
  password values; `install.sh` and `docker/scripts/prepare-build.sh` generate all three
  with `openssl rand -base64 32` on first run and leave them alone afterwards;
  `docker-compose.yml` requires them rather than defaulting, so a missing password stops the
  container instead of quietly starting one anyone can log into. Verified against a real
  `mysql:8.0` — first-init, healthcheck, app login and the backup container's my.cnf all
  handle a base64 password. `tests/install-secrets.test.sh` runs in CI. Two things the item
  didn't anticipate: `install.sh` needed a source guard to be testable, and manual rotation
  has to cover `root@localhost` as well as `root@%` or the healthcheck goes red. **N-7 is
  now the only item in Now.**
- **2026-07-31** — **N-9 added, critical-path.** `docker-compose.yml` defaults
  `MYSQL_ROOT_PASSWORD` to `mysql` and `DB_PASSWORD` to `teemops_dev_password`, both
  published in `.env.docker.example` in what is now a public repo, with MySQL's port bound
  to the host by default — a database reachable outside the container network with a
  publicly known root password unless someone manually overrides three specific variables.
  Fix mirrors the `APP_KEY` pattern `install.sh` already has: generate once, at install
  time, never ship a real value. Scoped to passwords only, not un-publishing the port
  (Ben's call) — that stays a separate future hardening item.
- **2026-07-31** — **N-8 done.** `docker/base/Dockerfile` holds the old app Dockerfile's lines
  6-23 verbatim, `docker/app/Dockerfile` is pinned to `teem/tops-base:php8.3-1`, and
  `.github/workflows/publish-base.yml` publishes future revisions for both architectures with
  a hard refusal to overwrite an existing tag. Ben pushed the first base manually; verified
  against the registry, not just the workflow — both `linux/amd64` and `linux/arm64` under
  index `sha256:71c4a842…`. Deleting the local copy and rebuilding proved the contributor
  path: the app image pulls the base and compiles nothing, 48.5s → 13.8s cold on native
  amd64, with identical `php -m` and `dpkg -l` to the old image. The arm64 leg under QEMU is
  where the real saving lands, so the CI number and any cut to `tag-release.yml`'s 45-minute
  budget wait for the next release. Started ahead of its sequencing, at Ben's call — **N-7 and
  N-9 remain the critical path** to 5 design partners by 2026-08-31.
- **2026-07-31** — **N-5 closed.** v0.1.2 went through the fixed release pipeline cleanly:
  PR #47 merged, `develop`'s `VERSION` and `CHANGELOG.md` landed correctly, `Tag and
  release` ran to completion for the first time, and all three images are on Docker Hub for
  both architectures with `latest` matching `v0.1.2` exactly. Verified against the running
  system (git, GitHub releases, Docker Hub API), not just workflow status. **N-7 is now the
  only item in Now** — the last thing between the code and a design partner. N-8 is
  unblocked and queued behind it.
- **2026-07-31** — Product owner review against current state (git, GitHub Actions, Docker
  Hub — not just this document). Set the milestone target: **5 design partners by
  2026-08-31**, resolving the open question. N-5's two "still needed" items turned out to
  be done — Docker Hub secrets are configured and images are live — but the review also
  found the `v0.1.1` tag/release/images are leftovers from a pre-fix run: `develop`'s
  `VERSION` and `CHANGELOG.md` were never actually updated, so N-5 stays open until a
  release goes through the fixed pipeline cleanly. Ben is handling that directly. X-9
  promoted to **N-7** — with a four-week deadline, "verify AWS onboarding end to end" is
  critical-path, not queued. **N-8** added the same session: `docker/app/Dockerfile`
  recompiles nginx, supervisor and four PHP extensions from source on every build, for both
  architectures — a rarely-changing `teem/tops-base` image gets that out of the release
  path. Ships after the first clean N-5 release, not blocking it. **D-10** recorded: user
  documentation lives in a new top-level `user-docs/` directory (not `docs/`, which stays
  contributor-facing), one site for technical and non-technical readers, deployed to
  `docs.teemops.com` via Cloudflare Pages — matching the domain actually wired into the
  live app rather than the stale `teem.nz` in the unbuilt marketing mockups. Tracked as an
  ongoing Later item, built in parallel with feature work.
- **2026-07-30** — `APP_KEY` leaked in a tracked infra env file. Response recorded as D-9:
  rather than build the `APP_KEY` rotation command that the leak seemed to demand, the
  encryption that made rotation dangerous was removed. `iam_role_arn` is now plaintext,
  rotation is `php artisan key:generate`, and the rotation design is kept as a documented
  rejection.
- **2026-07-30** — N-3 landed, and **Now is empty**. The README opens on what TOPS is and
  one install command instead of a directory tree and a prerequisites list naming Firebase
  and AWS. `app/.env.example` no longer ships Teemops' account id, SQS ARNs, CFN template
  URL or Firebase project — there were more of those than the audit had recorded — and
  `FIREBASE_USER_AUTH` now defaults to `false` so the documented path never asks for a
  Firebase project. The published one-liner was run from an empty directory and reached a
  healthy instance, which also caught a `set -u` bug that made v0.1.0's installer print an
  error and exit non-zero *after* succeeding. Two criteria did not land and are now X-8 and
  X-9: no published checksum, and no real AWS account has been connected through the
  documented path.
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
