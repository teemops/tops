# Wireframe — Homepage (`/`)

**Job:** get a technical visitor from "what is this" to a copied install command in under
30 seconds. Everything else on the page is for the visitor who did not copy it.

**Primary CTA:** copyable install command (conversion event).
**Secondary CTA:** GitHub repo.
**There is no third CTA.** teemops.com sells nothing. The commercial side of the story
is one footer link to teem.cloud and that is the entire extent of it.

There is no "Start Free Scan", no "No credit card required", and no login link. There is
nothing to log into.

---

## Full-page structure

```
┌──────────────────────────────────────────────────────────────────────┐
│ 1. NAV                                                               │
│ 2. HERO                    ← install command + findings register     │
│ 3. PROOF STRIP             ← 74 checks · 11 services · CIS · Apache  │
│ 4. THE PROBLEM             ← scanners give you a report, not a queue │
│ 5. DURABLE FINDINGS        ← THE differentiator (milestone 2)        │
│ 6. HOW IT WORKS            ← 3 steps, install → role → scan          │
│ 7. SCOPE A SCAN            ← per-service + multi-region              │
│ 8. INSIGHTS                ← group by service / benchmark            │
│ 9. FINAL CTA                                                         │
│ 10. FOOTER                 ← carries the one Teem link               │
└──────────────────────────────────────────────────────────────────────┘
```

Sections 5, 7 and 8 are new and are all milestone 2.

**There is no "who runs it" or paid-options section.** An earlier draft had three cards
for managed hosting and support services; that is deleted. teemops.com is the project
site and the project is free — the company behind it gets one line in the footer, on the
Red Hat / Fedora split: the project site talks about the software, the company site
talks about the company.

The old "Social proof — logos, testimonials" section is also deleted, not deferred: we
have no customers to name, and a row of grey placeholder logos reads worse than no
section at all. Repo signals carry that weight instead, in §3.

---

## 1. Nav

```
┌──────────────────────────────────────────────────────────────────────┐
│  [logo] TOPS         Features    How it works    Docs                 │
│                                        [ ★ Star on GitHub  1.2k ]    │
└──────────────────────────────────────────────────────────────────────┘
```

- **No Pricing item.** There is no pricing page — see [pricing.md](pricing.md) for what
  happens at that route instead. A nav link named "Pricing" on a free project invites the
  "so what's the catch" reflex we have nothing to answer with.
- No Login / Sign up. Replaced by the GitHub button as the persistent right-hand action.
- Star count is live from the GitHub API, cached; render the button without a count if
  the call fails rather than blocking paint.
- Sticky on scroll. Below `md`, collapses to hamburger + GitHub button.

---

## 2. Hero

```
┌──────────────────────────────────────────────────────────────────────┐
│                                  │                                   │
│  Find what's wrong in your       │  ┌─────────────────────────────┐  │
│  AWS account. Then fix it.       │  │ ● ● ●            Findings   │  │
│                                  │  ├─────────────────────────────┤  │
│  Open-source AWS security        │  │ [ All ▾ ][ S3 ▾ ][ CIS ▾ ]  │  │
│  scanning you run yourself.      │  │                             │  │
│  74 checks, every finding        │  │ ▌CRIT  S3 bucket is public  │  │
│  carries the fix, and the        │  │       Block public access…  │  │
│  ones you've triaged stay        │  │       cis-2.1.5   ● Open    │  │
│  triaged.                        │  │ ─────────────────────────── │  │
│                                  │  │ ▌HIGH  Root has no MFA      │  │
│  ┌────────────────────────────┐  │  │       Enable MFA on root…   │  │
│  │ $ bash <(curl -fsSL …)  [⧉]│  │  │       cis-1.5   ⊘ Ignored   │  │
│  └────────────────────────────┘  │  │ ─────────────────────────── │  │
│   Docker is the whole list.      │  │ ▌HIGH  RDS is public        │  │
│                                  │  │       Set PubliclyAccess…   │  │
│  [ ★ Star on GitHub ]  Docs →    │  │       cis-2.3.1  ✓ Resolved │  │
│                                  │  └─────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────┘
```

**This panel replaces the `78/100` Security Score mockup.** Deliberately: the score was
deleted in `a246323`, and the replacement should show the thing we actually built
instead of it — a findings register where each row has a severity, a remediation, a
benchmark tag and a **status that persists**.

Notes:

- The three visible statuses (Open / Ignored / Resolved) are the whole pitch for #90 in
  one glance. Keep all three visible; do not show three Opens.
- `cis-2.1.5` style benchmark tags are #87 made visible. Use real rule IDs.
- The filter chips at the top are #85 / #87. They are decorative here, but they must
  match the real UI's chips — this panel becomes a real screenshot as soon as we have
  one, and it should not look like a downgrade when it does.
- Install command: one line, truncated with ellipsis, click-to-copy with a "Copied"
  toast. **Copy is the conversion event** — instrument it.
- Headline avoids "misconfigurations" in position one. The old subhead
  ("Find misconfigurations before attackers do") is fine as a meta description but
  buries what is different about us.

Below `lg`: panel drops below the copy, no truncation of the install command — it wraps
to two lines instead. Do not hide the install command on mobile; people screenshot it.

---

## 3. Proof strip

```
┌──────────────────────────────────────────────────────────────────────┐
│   74 checks   ·   11 AWS services   ·   CIS AWS Foundations   ·      │
│   Apache-2.0   ·   Docker Compose, nothing else                      │
└──────────────────────────────────────────────────────────────────────┘
```

Thin band, single row, no icons. All five are verifiable and all five are load-bearing
for a technical reader. Numbers come from the README and must be regenerated when the
rule set changes — flag in the content plan that `scan:validate-rules` output is the
source, not a hand-typed number.

---

## 4. The problem

```
┌──────────────────────────────────────────────────────────────────────┐
│              Most scanners hand you a PDF. Then another one.         │
│                                                                      │
│   ┌────────────────┐  ┌────────────────┐  ┌────────────────┐         │
│   │ Every scan     │  │ You triaged    │  │ 400 findings,  │         │
│   │ starts from    │  │ it last week.  │  │ no idea which  │         │
│   │ zero           │  │ It's back.     │  │ 3 matter       │         │
│   └────────────────┘  └────────────────┘  └────────────────┘         │
└──────────────────────────────────────────────────────────────────────┘
```

Three cards, no icons needed. Each one is answered by a section below — §5 answers the
first two, §8 answers the third. Keep the ordering aligned.

---

## 5. Durable findings — the differentiator

```
┌──────────────────────────────────────────────────────────────────────┐
│  A finding is a record, not a row in a report                        │
│                                                                      │
│  ┌────────────────────────────┐   Scan on Mon:  ▌HIGH  Root MFA      │
│  │                            │                  → you mark Ignored  │
│  │   [ before / after         │                    "root is break-   │
│  │     rescan diagram ]       │                     glass only"      │
│  │                            │                                      │
│  │   same finding, same       │   Scan on Fri:  ▌HIGH  Root MFA      │
│  │   record, status intact    │                  ⊘ still Ignored     │
│  │                            │                                      │
│  └────────────────────────────┘   Your triage survives. So does the  │
│                                   first-seen date.                   │
└──────────────────────────────────────────────────────────────────────┘
```

Ships #81 and #90. This is the section to get right — it is the only claim on the page
that a competitor's free tier does not also make.

- Left: simple two-state diagram, not a screenshot. A screenshot cannot show *time*.
- Right: concrete narrative with a real, defensible example (break-glass root account).
- Include the first-seen / last-seen line. "How long has this been open" is the question
  a durable record can answer and a per-scan row cannot.
- Copy must not say "we track findings over time" — every vendor says that. Say what
  breaks without it: *you re-triage the same thing every week*.

---

## 6. How it works

```
┌──────────────────────────────────────────────────────────────────────┐
│   ①  Install                ②  Connect an account    ③  Scan         │
│   ┌──────────────────┐      ┌──────────────────┐  ┌───────────────┐  │
│   │ $ bash <(curl …) │      │ CloudFormation   │  │ findings       │ │
│   │                  │      │ grants a         │  │ appear as they │ │
│   │ Docker Compose.  │      │ read-only        │  │ are produced   │ │
│   │ No PHP, no DB.   │      │ audit role       │  │                │ │
│   └──────────────────┘      └──────────────────┘  └───────────────┘  │
│                                                                      │
│   You don't need an AWS account to try it.                           │
└──────────────────────────────────────────────────────────────────────┘
```

- Step 2 must say **read-only** in the step itself, not in small print. It is the first
  objection a CTO has and answering it early is worth more than a trust badge.
- The "you don't need an AWS account to try it" line is from the README and is a genuine
  friction remover. Keep it directly under the steps.
- Do **not** claim a total time here. See open question 3 in the README.

---

## 7. Scope a scan

```
┌──────────────────────────────────────────────────────────────────────┐
│  Scan one service, not the whole benchmark                           │
│                                                                      │
│  ┌─────────────────────────────────────┐   Chasing one S3 change?    │
│  │ New scan                            │   Scan S3.                  │
│  │  Account  [ prod-1234 ▾ ]           │                             │
│  │  Profile  [ CIS Foundations ▾ ]     │   Regions run in parallel,  │
│  │  Services [✓ S3 ] [ IAM ] [ EC2 ]   │   so more regions doesn't   │
│  │           [ RDS ] [ +7 more ]       │   mean a longer wait.       │
│  │              [ Start scan ]         │                             │
│  └─────────────────────────────────────┘                             │
└──────────────────────────────────────────────────────────────────────┘
```

Ships #84 and #92. Becomes a screenshot of the real New Scan modal once captured.
The parallel-regions line is the only place multi-region gets airtime on the homepage —
it is an implementation win, not a buying reason, so one sentence is the right budget.

---

## 8. Insights

```
┌──────────────────────────────────────────────────────────────────────┐
│  Know which three things matter                                      │
│                                                                      │
│   ┌───────────────────────────┐  ┌───────────────────────────┐       │
│   │ Findings by service       │  │ Findings by benchmark     │       │
│   │  S3    ███████ 42  ▌12 ▌8 │  │  CIS 1.x  ████ 18         │       │
│   │  IAM   █████   31  ▌ 9 ▌4 │  │  CIS 2.x  ███████ 40      │       │
│   │  EC2   ███     19  ▌ 2 ▌7 │  │  CIS 3.x  ██ 11           │       │
│   └───────────────────────────┘  └───────────────────────────┘       │
│                                                                      │
│              Click through to a filtered, shareable list →           │
└──────────────────────────────────────────────────────────────────────┘
```

Ships #88, #89, and — via the caption — #83 and #85/#87. "Shareable" is the user-facing
value of filters living in the URL; say that, not "URL state".

**Note the section has no total number and no score.** Resist adding a headline metric
here; that instinct is what produced the score in the first place.

---

## 9. Final CTA

```
┌──────────────────────────────────────────────────────────────────────┐
│                 One command. Docker is the only prerequisite.        │
│                                                                      │
│              ┌──────────────────────────────────────────┐            │
│              │ $ bash <(curl -fsSL …/install.sh)    [⧉] │            │
│              └──────────────────────────────────────────┘            │
│                                                                      │
│          Read the script first →      ★ Star on GitHub               │
└──────────────────────────────────────────────────────────────────────┘
```

"Read the script first" links to the `curl -o install.sh` variant in the docs. Offering
it is a trust signal that costs nothing and lands with exactly the audience we want.

---

## 10. Footer — and the one Teem link

```
┌──────────────────────────────────────────────────────────────────────┐
│  Product         Docs            Project          Legal              │
│  Features        Install         GitHub           Licence            │
│  How it works    Scanning AWS    Issues           Trademark          │
│  Insights        Configuration   Releases         Privacy            │
│                                                                      │
│  ──────────────────────────────────────────────────────────────────  │
│                                                                      │
│   Teem is the company behind the open source software.               │
│   For AWS security auditing, view their website → teem.cloud         │
│                                                                      │
│  ──────────────────────────────────────────────────────────────────  │
│   TOPS is free and open source under Apache-2.0.  [Apache-2.0]       │
└──────────────────────────────────────────────────────────────────────┘
```

**This band is the only commercial mention on the entire site.** Not a card, not a
banner, not a CTA — a sentence and a link, below the fold of every page, in body text
weight. Anyone who needs a vendor will find it; nobody who just wants the software has
to step around it.

The model is Red Hat: the project is genuinely free forever and stays that way, and a
company exists behind it for organisations that need stability, help with setup, or
someone accountable for running it. That whole proposition lives on teem.cloud. It does
**not** get explained here — the moment teemops.com starts describing support offerings,
it stops being a project site.

Rules for this band, which should be treated as constraints and not preferences:

- **One placement, site-wide.** Footer only. It does not also appear in the nav, the
  hero, a mid-page section, or a banner.
- **No commercial verbs.** No "get support", "talk to sales", "enterprise", "pricing",
  "book a demo". The link text is the company name and what it is.
- **`rel="noopener"`, opens in a new tab.** A visitor leaving for teem.cloud should not
  lose the install command they were about to copy.
- **Wording is fixed**, product-owner supplied: *"Teem is the company behind the open
  source software. For AWS security auditing, view their website."* Copywriting may fix
  punctuation and nothing else.
- The Apache-2.0 line sits *below* the Teem line, so the last thing on every page is the
  free-software statement rather than the company.

The rest of the footer is standard. The "Company" column from the old design is renamed
**Project** and carries GitHub / Issues / Releases — because that is what the community
around this software actually is. Legal keeps the licence link and the trademark notice
(`TRADEMARK.md` exists, and a marketing site is the place it actually matters).

---

## Assets needed

| Asset | Blocking? | Note |
| --- | --- | --- |
| Findings list screenshot | Hero — yes | Needs realistic data with all 3 statuses visible |
| New Scan modal screenshot | §7 — no | Wireframe box is adequate for review |
| Insights screenshot | §8 — no | Same |
| Rescan diagram | §5 — yes | Custom illustration; nothing existing works |
| Live GitHub star count | Nav — no | Degrade to plain button |

## Deleted from the current homepage

- Security Score panel (`../html/index.html:104`) — feature no longer exists
- "Start Free Scan" CTAs — nothing to sign up for
- "No credit card required. Free tier available forever." — implies a paid SaaS tier above it
- Social proof / customer logo section — no customers to name
- Pricing teaser with $/mo — there is no pricing
- "Pricing" nav item — see [pricing.md](pricing.md)
- The three-card "who runs it" block from the first draft of this wireframe — replaced by
  the single footer line in §10
