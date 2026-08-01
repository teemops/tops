# Marketing site wireframes — v2

Low-fidelity wireframes for the teemops.com marketing site, redrawn after
[milestone 2 "Findings & Scans v2"](https://github.com/teemops/tops/milestone/2) closed
and after the open-source / self-hosted pivot.

These are **structure and message**, not visual design. Once they are signed off, the
HTML mockups in `../html/` and the copy in `../pages/` get updated to match — in that
order. Do not update the HTML first; it is the expensive artefact.

**Two formats, same content.** The markdown files below are the source of truth. The
same wireframes are also in [html/](html/) as renderable pages — open
[html/index.html](html/index.html) in a browser, no server or build needed. Reviewer
notes are inline and there is a **Hide notes** toggle in the top bar to see the layout
clean.

`html/` is *not* `../html/`. These are greyscale, boxy and deliberately ugly, so nobody
mistakes a wireframe for a design decision; `../html/` holds the Tailwind mockups that
get built *after* sign-off. Keep the two apart — the wireframes are never deployed.

| Wireframe | Replaces | Status |
| --- | --- | --- |
| [homepage.md](homepage.md) | `../pages/homepage.md`, `../html/index.html` | For review |
| [features.md](features.md) | `../pages/features.md`, `../html/features.html` | For review |
| [pricing.md](pricing.md) | deletes `../pages/pricing.md`, `../html/pricing.html` | For review |

[pricing.md](pricing.md) is not a page wireframe — it is the case for **deleting** the
pricing page and what to do with the URL. teemops.com sells nothing.

Not redrawn yet, and deliberately: `security.md`, `about.md`, `use-cases.md`,
`compliance.md`. They are behind too, but they are behind on *positioning*, not on
*product truth* — nothing in milestone 2 makes them wrong. The homepage, the features
page and the pricing page are the three that currently describe a product we do not
ship. One of those three stops existing.

They will each need one small pass later for the "free tier" wording — that phrase
appears across `../pages/` and it implies a paid tier above it. The word for what we
have is just *free*.

---

## Why the site needs redrawing

Three things happened that the site has not caught up with.

**1. The security score is gone.** `a246323` removed it rather than recalibrating it
(issue #91 — it was pinned at 0 and could not improve). The homepage hero mockup is
built around a `78/100` score readout (`../html/index.html:104`), and the features page
has three more score tiles (`../html/features.html:285,303,321`). The single most
prominent visual on the site demos a feature that no longer exists.

**2. There is no SaaS to sign up for.** Every CTA on the site is "Start Free Scan" with
"No credit card required. Free tier available forever." underneath. There is no hosted
signup. The product installs with one Docker command, is Apache-2.0, and the README
leads with "Self-hosted, Apache-2.0, no limits and no paid tier". The word "open" does
not appear anywhere on the marketing site.

**3. Milestone 2 shipped the things worth selling.** A finding is now a durable record
whose ignore/resolve status survives a rescan; scans can target one service; findings
filter by service and by benchmark; Insights groups by both. That is the difference
between "a scanner that produces a report" and "a backlog you can actually work" — and
it is the strongest product story we have. None of it is on the site.

---

## Traceability — milestone 2 → website

| Issue | Shipped | Where it shows up on the site |
| --- | --- | --- |
| #81 D-1 · Durable findings | one record per resource + rule | Homepage §4 "It remembers", features §2 — the headline capability |
| #90 B-1 · Status survives rescan | ignore/resolve persists | Same section; this is the proof point for #81 |
| #91 B-2 · Security score removed | — | Removes hero panel, removes 3 feature tiles. Nothing replaces the number |
| #84 F-2 · Per-service scans | scan one service, not a whole benchmark | Homepage §5, features §3 |
| #85 F-3 · Filter by service | URL-carried | Features §4 (grouped as "slice the backlog") |
| #87 F-4 · Filter by benchmark | benchmark recorded per finding | Features §4 + compliance page later |
| #83 F-7 · Filters in URL | shareable filtered views | Features §4, one line — it is a detail, not a section |
| #82 F-1 · Remediation in Findings | one-line fix on every finding | Homepage §4, features §5 |
| #86 S-1 · Scan detail summarises | summary + dispatch, not a second list | Product tour screenshot only |
| #88 F-5 · Insights by service | severity breakdown | Features §6 |
| #89 F-6 · Insights by benchmark | — | Features §6 |
| #92 · Multi-region parallel scans | + batch settlement `26630ac` | Features §3, one line. Speed claim — see open questions |

---

## Positioning these wireframes assume

Confirmed with the product owner, 2026-08-02:

- **teemops.com stays completely free.** No tiers, no pricing page, no managed-hosting
  offer, no support packages, no contact-sales form. The software is Apache-2.0 with no
  feature gates and no seat limits, and the site says so plainly.
- **There is no SaaS version and we are not launching one yet.** Nothing on the site
  should imply a hosted multi-tenant signup exists.
- **One commercial mention, site-wide**: a footer link to
  [teem.cloud](https://teem.cloud) reading *"Teem is the company behind the open source
  software. For AWS security auditing, view their website."* Specified in
  [homepage.md](homepage.md) §10.
- **The Red Hat split.** Forever-free software here; a company behind it for customers
  who need stability or help with setup and management — and everything about *that*
  lives on teem.cloud, not here.

So the site's job is simply **drive installs**. The primary conversion event is a copied
install command; the secondary is a GitHub star. There is no revenue event on this site,
by design — the moment teemops.com starts describing support offerings, every free claim
on it reads as a funnel.

An earlier draft of this set had a three-card "who runs it" block on the homepage and a
full pricing page with managed and support tiers. Both are deleted. What survives of
that thinking is one footer sentence.

---

## Open questions for the product owner

These are flagged in the wireframes where they bite. None of them block drawing.

1. **Does the "Free. Forever." band earn homepage space?** [pricing.md](pricing.md)
   proposes a four-line band where `/pricing` redirects to. I recommend keeping it and
   keeping it small; the counter-argument is that the hero and the Apache-2.0 badge
   already say it and a whole band protests too much. Your call — it is the last
   structural decision left in this set.
2. **Speed claim.** `7494959` instrumented scan phases to baseline "where the 10 minutes
   goes", and multi-region now runs in parallel. The homepage wants a concrete number
   ("first findings in N minutes"). I have left it as `[N]` — do not ship a number that
   the instrumentation does not support.
3. **`../pricing-strategy.md` should move, not die.** 431 lines, now obsolete for this
   site, but it holds the only written-down SMB segment analysis and it is plausible
   input for teem.cloud. Move it out of `design/marketing/web/` rather than deleting it.
4. **`/contact` should probably go too.** It is still in the site structure in
   `../content-plan.md`. A contact form on the project site is a sales channel wearing a
   different hat; GitHub issues is the contact channel for a project, and teem.cloud can
   own the rest. Not actioned in these wireframes — flagging it.
5. **Social proof.** There are no customers, logos or testimonials, and the current
   homepage reserves a section for them. The wireframes replace that section with
   repo signals (stars, checks, services, license) — honest, and available today.
6. **Screenshots.** Every wireframe calls for real UI screenshots and there are none in
   `../` yet. Listed as the top asset gap; the app is running locally so these are cheap
   to capture.
