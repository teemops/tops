# docs.teemops.com — information architecture and content map

Design work, not built. Scope: the structure and content plan for the user documentation
site, plus the page design for **How TOPS connects to AWS**, which is the page that prompted
it.

**Out of scope for this worktree:** `teemops.com` marketing site wireframes and design. That
continues elsewhere. Where this document touches the marketing site it is only to draw the
boundary between the two.

## What D-10 already decided

[D-10](../../docs/roadmap.md#d-10-in-full-where-user-documentation-lives) settled most of the
structural questions. Nothing here relitigates them:

| Decision | Consequence for this design |
| --- | --- |
| A new top-level `user-docs/`, not `docs/` | `docs/` stays a contributor knowledge base in an engineering voice. Nothing in this plan moves it |
| One site, structured **by task**, not by audience | No "developer track" / "business track" split. Depth increases *down the page* instead |
| `docs.teemops.com`, Cloudflare Pages | Already the plan for the marketing site; this is the same pattern on a second directory |
| **Plain Markdown; renderer decided later** | This design deliberately does **not** pick a static-site generator. See [Tooling](#tooling-stays-deferred-on-purpose) |
| `README.md` stays canonical for install | The site links to it rather than copying it. Duplication is the thing D-10 was most concerned about |

### One place this design extends D-10

D-10 scoped `user-docs/` to pick up **from "you're logged in"** — findings, remediation, team
management, scan profiles — with `README.md` owning everything before that.

That holds for *operators*. It does not hold for the audience that prompted this work.
**A CISO deciding whether to permit TOPS in their AWS organisation reads before anything is
installed, and will not read a GitHub README to do it.** They want trust boundaries, IAM
permissions, and what crosses the wire. That is a third audience D-10 did not model.

**Proposed amendment, for the decisions log:** `user-docs/` owns **evaluate** and **use**.
`README.md` stays canonical for the *commands* — the one-liner, the flags, the upgrade steps
— and the site links out to it rather than restating them. This keeps D-10's
anti-duplication rule intact while admitting the evaluator. Suggest recording as **D-13**
before the first page is written, so it isn't rediscovered.

## Audiences

Three, in the order they arrive. The task-based structure serves all three without splitting
the site, because the tasks themselves are ordered this way.

| | Reads | Arrives from | Leaves when |
| --- | --- | --- | --- |
| **Evaluator** — CISO, CTO, security reviewer | Architecture, trust boundaries, IAM permissions, licence | A vendor-review request, or the marketing site | They can answer "is this safe to allow in our org?" |
| **Operator** — DevOps, platform engineer | Install, connect accounts, upgrade, back up, troubleshoot | GitHub, or the evaluator handing it over | TOPS is running and scanning |
| **User** — whoever reads the findings | Findings, remediation, insights, scan profiles, team | The app itself, usually mid-task | They know what a finding means and what to do about it |

The evaluator is the smallest audience and the highest-stakes: they are a gate, not a user.
One unanswered question on their list stops the other two ever arriving.

## Site map

```
docs.teemops.com
│
├── Start here
│   ├── What TOPS is — and what it is not
│   ├── How TOPS connects to AWS          ← evaluator entry point
│   ├── What it costs to run
│   ├── Install                            → links README, does not copy it
│   ├── Connect your first AWS account
│   └── Run your first scan
│
├── Using TOPS
│   ├── The dashboard
│   ├── Running a scan
│   ├── Scan profiles and scan types
│   ├── Reading a finding
│   ├── Resolving a finding
│   └── Insights
│
├── AWS accounts
│   ├── Adding an account
│   ├── What the IAM role can do          ← the honest permissions page
│   ├── Removing an account
│   └── Many accounts at once
│
├── Your organization
│   ├── Organizations and data isolation
│   ├── Inviting and managing members
│   ├── Roles and permissions
│   └── Your profile
│
├── Running the service
│   ├── Configuration reference
│   ├── Upgrading and rolling back
│   ├── Backups and restore
│   ├── Workers, queues and scan speed
│   ├── Troubleshooting
│   └── Uninstalling
│
├── Security
│   ├── The security model
│   ├── Where your data lives
│   ├── Reporting a vulnerability
│   └── Licence and trademark
│
└── Reference
    ├── Glossary
    ├── What TOPS scans — services and rules
    ├── Command reference
    └── Release notes
```

Eight sections is at the top of what a sidebar carries before it needs search. If it grows,
**Security** folds into **Start here** before anything else does — its pages are read once,
by one audience, in one sitting.

## Content inventory

Status is about the *content*, not the file. "Exists" means the substance is written
somewhere and needs porting and a change of voice, not that a `user-docs/` page is ready.

| Page | For | Status | Source | Priority |
| --- | --- | --- | --- | :---: |
| **Start here** |
| What TOPS is — and what it is not | All | ✍️ Write | `README.md` intro, roadmap Direction | 1 |
| How TOPS connects to AWS | Evaluator | ✅ **Built this session** | `design/architecture/aws-integration.html` | 1 |
| What it costs to run | Evaluator, Operator | ⚠️ Partial | `install.sh` `aws_intro()`, `docker-compose.README.md` | 2 |
| Install | Operator | ✅ Exists | `README.md` § Install — link, don't copy | 1 |
| Connect your first AWS account | Operator | ✅ Exists | `README.md` § Scanning a real AWS account | 1 |
| Run your first scan | Operator, User | ⚠️ Thin | `README.md`, `docs/features/scan-*.md` | 1 |
| **Using TOPS** |
| The dashboard | User | ✍️ Write | `Pages/Dashboard.vue` | 3 |
| Running a scan | User | ✍️ Write | `Pages/Scans/*`, `NewScanModal.vue` | 2 |
| Scan profiles and scan types | User | ✍️ Write | `ScanProfilesService`, `ScanTypesService`, `config/scan.php` | 2 |
| Reading a finding | User | ✍️ Write | `Pages/Findings/*`, `docs/features/filter-findings-by-*.md` | **1** |
| Resolving a finding | User | ⚠️ Partial | Rule remediation text (N-4 gave all 74 rules one) | **1** |
| Insights | User | ✍️ Write | `Pages/Insights/`, `docs/features/insights-by-service.md` | 3 |
| **AWS accounts** |
| Adding an account | Operator | ✅ Exists | `docs/features/onboarding-flow.md` (rewrite — it is a spec, not a guide) | 2 |
| What the IAM role can do | Evaluator | ✅ **Written** | `templates/iam.role.child.account.cfn.yaml` | **1** |
| Removing an account | Operator | ✍️ Write | Stack deletion → `Delete` custom resource | 3 |
| Many accounts at once | Operator, Evaluator | ✍️ Write | This session's research | 3 |
| **Your organization** |
| Organizations and data isolation | Evaluator, User | ✍️ Write | `OrganizationPermission`, multi-tenancy practice | 2 |
| Inviting and managing members | User | ✍️ Write | `Pages/Organizations/*`, `organization-team-management-plan.md` | 2 |
| Roles and permissions | User | ⚠️ Unstable | `OrganizationPermission` — see [#—/X-5](../../docs/roadmap.md), reconciliation open | 3 |
| Your profile | User | ✍️ Write | `Pages/Profile/` | 4 |
| **Running the service** |
| Configuration reference | Operator | ⚠️ Scattered | `.env.docker.example`, `docker-compose.yml` comments | 2 |
| Upgrading and rolling back | Operator | ✅ Exists | `README.md` § Upgrading | 2 |
| Backups and restore | Operator | ✅ Exists, strong | `docs/backups.md` | 2 |
| Workers, queues and scan speed | Operator | ✅ Exists | `worker-supervisord.conf` comments, `parallel-region-scans.md` | 3 |
| Troubleshooting | Operator | ✅ Exists | `DEBUG.md`, `README.md` § If something goes wrong | 2 |
| Uninstalling | Operator, Evaluator | ⚠️ Partial | `docker-compose.README.md`; the two CFN stacks | 3 |
| **Security** |
| The security model | Evaluator | ✅ **Built this session** | The architecture page's boundary table | **1** |
| Where your data lives | Evaluator | ✍️ Write | Nothing leaves the install — needs stating plainly | 2 |
| Reporting a vulnerability | Evaluator | ✍️ Write | No `SECURITY.md` exists — **gap** | **1** |
| Licence and trademark | Evaluator | ✅ Exists | `LICENSE`, `TRADEMARK.md`, roadmap D-7 | 3 |
| **Reference** |
| Glossary | All | ✅ Exists | `docs/GLOSSARY.md` | 3 |
| What TOPS scans — services and rules | Evaluator, User | ✅ Exists | `docs/planning.md`, the rule JSON | 2 |
| Command reference | Operator | ✍️ Write | `artisan` commands, `backup.sh`, `install.sh` flags | 4 |
| Release notes | All | ✅ Exists | `CHANGELOG.md` | 4 |

**Totals:** 34 pages — 12 exist in substance, 5 partial or scattered, 17 to write from
scratch. Roughly a third of the site is a porting-and-rewriting job rather than an authoring
job, which is the argument for doing the IA before the writing.

### Two gaps this inventory exposed

1. ~~**There is no `SECURITY.md`.**~~ **Written 2026-08-02.** A public repo with a `security`
   label, three open security issues and a CISO-facing architecture page had nowhere to
   report a vulnerability, and `README.md` said "open an issue" — precisely the wrong advice.
   Now at the repository root, with the README pointing to it. **Two follow-ups it depends
   on:** GitHub private vulnerability reporting must be enabled (it is currently off), and
   `security@teemops.com` must be created and routed. Until both are done the policy names
   channels that do not answer.
2. **`docs/quick-start.md` is dead.** It describes a Nuxt 3 + Serverless + Prisma monorepo —
   the pre-pivot stack. It is linked from `docs/README.md` and would mislead anyone who found
   it. Delete it rather than port it.

## What not to document yet

Per `docs/practices/product.md` — no page for a feature that does not exist. Verified against
`docs/PROGRESS.md`, which is checked against the code:

| | Why not |
| --- | --- |
| **Firebase / OAuth sign-in** | Built but **off by default** (`FIREBASE_USER_AUTH=false`). Documenting it as a path implies the default install needs a Firebase project. It does not. One line under Configuration reference, no page |
| **MFA** | Partially built, deliberately gated, and not reachable without Firebase yet |
| **Report export, scheduled scans, multi-cloud** | Not started. No controller, no route, no UI |

## Page archetypes

Three, which is enough. Every page in the map is one of them.

**1. Task page** — the default. Title, one-sentence outcome, prerequisites as a short list,
numbered steps with real command output, then a "when it goes wrong" section. Depth increases
down the page: the first screen is enough for a non-technical reader, the last third is for
someone debugging.

**2. Explainer** — architecture, security model, permissions. Diagram first, prose second,
a table of specifics last. This is the archetype **How TOPS connects to AWS** already
implements: zoned diagrams, numbered captions, a boundary table, and an honest
read/write permissions split.

**3. Reference** — glossary, commands, rules, release notes. Scannable, tabular, no
narrative. Assumed to be arrived at from search or a link, never read top to bottom.

Copyable skeletons, with the guidance inline as comments that do not render:
[`archetypes/task.md`](archetypes/task.md) ·
[`archetypes/explainer.md`](archetypes/explainer.md) ·
[`archetypes/reference.md`](archetypes/reference.md).

## House voice

D-10's decision to structure by task rather than by audience only works if every page is
written the same way. Pages will be written by whoever ships the feature, months apart, so
the rules have to be few enough to remember and specific enough to check.

**Depth increases down the page.** The first screen serves someone non-technical; the last
third serves someone debugging. This is the mechanism that lets one site serve three
audiences without splitting into tracks — if a page cannot be arranged this way, it is
probably two pages.

**Write from the reader's side of the screen.** They manage *accounts* and *findings*, not
`aws_accounts` rows and rule evaluations. Name things the way the UI names them. Internal
vocabulary belongs in `docs/`, not here.

**Symptom before cause.** Troubleshooting entries are reached by searching an error message,
so lead with the literal text the reader has in front of them — then the cause, then the fix.
A section organised by subsystem is unfindable by the person who needs it.

**Show real output.** Readers compare their screen to the page character by character.
Paraphrased output silently breaks that.

**Never claim more than the code does.** The child IAM role is not read-only, so no page says
it is. A reader who catches one overstatement stops trusting the rest of the site, and
reviewers are exactly the audience that checks. Every explainer carries a *What we do not
claim* section for this reason.

**Link, do not restate.** Install commands live in `README.md`; the site links to them. Two
canonical copies drift, and the stale one is the one someone follows.

**No page for a feature that does not exist.** Verified against `docs/PROGRESS.md`, not
against the roadmap — the roadmap describes intent, `PROGRESS.md` describes the code.

## Writing order

Priority in the inventory says what matters; this says what unblocks what. Roughly dependency
order, not importance order.

1. **`SECURITY.md`** — repo hygiene, blocked by nothing, and the one gap with an active
   reason to exist today.
2. **The two evaluator pages** — already written. *How TOPS connects to AWS* is done; *What
   the IAM role can do* is a straight extraction from it and the child template.
3. **Reading a finding**, then **Resolving a finding.** In that order: resolution is
   meaningless to someone who cannot yet interpret severity and evidence. These two are what
   a design partner hits within minutes of a first scan, and nothing currently describes them.
4. **Deploy the site.** Cloudflare Pages, `docs.teemops.com`. Do it once there are four real
   pages rather than before — a live site with one page invites filler.
5. **Trim `README.md`'s setup section** to link inward. Only after an install page exists to
   link *to*; doing it earlier points at nothing.
6. **Delete `docs/quick-start.md`.**
7. **Everything else, as the feature that needs it ships.** No documentation sprint.

The one ordering trap: **Configuration reference** looks cheap because the values already
exist in `.env.docker.example` and the compose file. It is not — it is a second copy of
something the code states, and it goes stale silently. Write it late, and only after deciding
whether it can be generated.

## Sample test: the architecture page as a real Markdown page

Built and measured, not theorised. `user-docs/start-here/how-tops-connects-to-aws.md` plus
three standalone SVGs in `user-docs/assets/diagrams/`, rendered through a deliberately
minimal shell (python-markdown + ~60 lines of CSS — no framework, which was the point).

**Verdict: Markdown-first survives. The diagrams need one theme rule and some rework.**

### What worked

Plain CommonMark carried the whole page — headings, tables, lists, blockquote callout,
linked images. Nothing needed MDX, a component, or a build step. The three diagrams are
external `.svg` files referenced with `![alt](path.svg)`, which keeps the Markdown readable
and the diagrams independently editable. Each SVG carries its own `prefers-color-scheme`
block, so dark mode works without inheriting page tokens.

### Two bugs that only appear once the SVG is standalone

Both would have shipped invisibly if the diagrams had stayed inline in HTML.

1. **HTML named entities are undefined in XML.** `&mdash;`, `&times;`, `&rarr;` are fine in
   inline SVG inside an HTML document, because the HTML parser resolves them. A standalone
   `.svg` served as `image/svg+xml` is parsed as XML, where only five entities exist — the
   file fails to render **entirely**, not partially. All must be numeric references
   (`&#8212;`). Worth a lint rule if more diagrams follow.
2. **`<img>` inside a CSS comment breaks the parse.** An XML parser reads it as a tag.
   Angle brackets cannot appear anywhere in a standalone SVG's CSS, even in comments.

Validate every diagram with an XML parser before committing. A malformed SVG shows as a
broken image, which reads as a missing file rather than a syntax error.

### The real finding: diagram width versus prose measure

The diagrams were first authored at a 1160px viewBox, carried over from a full-bleed HTML
page. That does not survive a docs column. Measured effective body-text size after scaling:

| Diagram viewBox | Layout | Diagram width | Scale | Body text | Readable |
| --- | --- | --- | --- | --- | --- |
| 1160px | Prose column (68ch), default | 686px | 0.59 | 6.8px | No |
| 1160px | Full content column | 757px | 0.65 | 7.5px | No |
| 1160px | Wide variant, 1680px viewport | 1202px | 1.04 | 11.9px | Yes, but only above ~1600px |
| **900px** | **Standard column, 1440px viewport** | **892px** | **0.99** | **12.4px** | **Yes** |
| **900px** | **Standard column, 1100px viewport** | **767px** | **0.85** | **10.7px** | **Yes** |
| **900px** | **Standard column, 820px viewport** | **765px** | **0.85** | **10.6px** | **Yes** |

**The diagrams were re-authored at a 900px viewBox** with proportionally larger type (body
12.5px, mono 13px, titles 15.5px) and zones stacked vertically rather than placed side by
side, which is what pays for the larger text. They now read at every desktop width in the
*standard* layout.

Two rules follow, and they are the whole theme requirement:

- **Images must not inherit the prose measure.** Markdown wraps an image in a `<p>`, so
  `p { max-width: 68ch }` silently caps every diagram at 686px. One rule fixes it
  (`main p:has(> img) { max-width: none }`) and it is the single most important line of CSS
  on the site.
- **The theme must collapse its side rails at the usual breakpoints** — on-page TOC below
  ~1200px, sidebar below ~900px. Every candidate renderer already does this by default.

**The earlier recommendation of a wide layout variant for explainer pages is withdrawn.** It
was compensating for over-wide diagrams. With the diagrams sized correctly, the standard
layout is enough, and the site keeps one layout instead of two.

Below ~800px — phones — no diagram of this density is readable inline, and no amount of
layout work changes that. Every diagram is therefore wrapped in a link to itself,
`[![alt](x.svg)](x.svg)`, plain CommonMark, so one tap opens the standalone SVG and it scales
to the viewport. One line, and it is the right answer on small screens regardless.

### Guardrails for the next diagram

Hand-placed SVG at this density does not survive being eyeballed. Both defects found in the
re-authored set were geometric — a 4px card overlap and a text run escaping its card — and
both were caught by loading the SVGs in a browser and comparing every `getBBox()` against its
containing rectangle. Worth keeping as a check when diagrams are added:

- no text extends past the card that contains it, or past its zone
- no two non-decorative cards overlap
- nothing sits outside the viewBox

Numbered chips that sit on a boundary-crossing arrow will trip a naive version of that check;
exclude them by class rather than loosening the tolerance.

### What this settles for tooling

The page needs: Markdown with tables, static assets served alongside, per-page layout
selection, and a theme whose image CSS is one rule away from correct. That is the whole
requirement list, and every MkDocs- or Docsify-class renderer meets it. **Nothing found here
argues for a framework.**

## Tooling stays deferred, on purpose

D-10 said plain Markdown, renderer chosen once there is content. **This design does not
change that**, and the wireframes should not be read as a vote for a framework.

What the wireframes commit to is only what any renderer in the MkDocs/Docsify class gives
you free: a left sidebar of sections, a content column at a readable measure, an on-page
table of contents, and search. Nothing in the IA needs MDX, React components, or a build
step. The one page with real layout demands — the architecture explainer — is already a
self-contained HTML page with inline SVG, which every candidate renderer can embed.

**Revisit when:** the first slice is written and the section count or search need actually
bites. Not before.

## First slice

D-10's own first slice, sharpened by the inventory above. Ordered by what unblocks the most.

1. `SECURITY.md` in the repo root — not a site page, and not blocked by any of this.
2. `user-docs/` with the four priority-1 evaluator/user pages: **How TOPS connects to AWS**,
   **What the IAM role can do**, **Reading a finding**, **Resolving a finding**.
3. Cloudflare Pages connected, `docs.teemops.com` resolving.
4. `README.md`'s setup section trimmed to a summary that links in, so install has one
   canonical copy.
5. Delete `docs/quick-start.md`.

After that, D-10's rule holds: a page per feature as it ships, written by whoever ships it.
No documentation sprint.
