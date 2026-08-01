# Wireframe — the pricing page, and why there isn't one

**Recommendation: delete the pricing page. Keep the URL.**

teemops.com sells nothing. There are no tiers, no managed hosting cards, no service
offers, no "talk to us". The software is free under Apache-2.0 and stays that way, and
the company behind it gets exactly one sentence in the footer of every page — specified
in [homepage.md](homepage.md) §10.

This file exists because `/pricing` is currently in the nav and in the sitemap, and
"deleted the page" is not a complete answer for a URL that people will still type,
search for, and link to.

---

## What replaces it

```
teemops.com/pricing   →  301  →  teemops.com/#free
```

A permanent redirect to a short **"Free. Forever."** band on the homepage, sitting
between the proof strip (§3) and the problem section (§4).

```
┌──────────────────────────────────────────────────────────────────────┐
│                                                                      │
│                        Free. Forever.                                │
│                                                                      │
│   Apache-2.0. Every check, unlimited AWS accounts, unlimited users.   │
│   No trial, no seats, no edition above this one, and no feature       │
│   we've held back to sell you later.                                  │
│                                                                      │
│                        [ Read the licence ]                          │
└──────────────────────────────────────────────────────────────────────┘
```

Four lines and a link. That is the whole answer, and giving it a dedicated page would
make it look like a longer answer than it is.

Why keep the route at all: "is X free" and "X pricing" are the highest-intent sceptical
queries an open-source project gets, and they are the ones a commercial competitor's
comparison page will try to own. Landing them on a two-sentence "yes, actually free"
costs one redirect rule.

**Add this band to the homepage wireframe when this file is signed off.** It is not
drawn into [homepage.md](homepage.md) yet, because whether it earns above-the-fold-
adjacent space is the one open judgement call here — see below.

---

## The Red Hat framing, and where it lives

Product owner, 2026-08-02:

> We provide open source, forever free software, but we have a company behind this so if
> a customer needs stability and a different level of support during setup or management
> of the software, Teem will support.

That is the right model and the wireframes adopt it. The part worth being disciplined
about is **where each half of it is said**:

| Claim | Said on |
| --- | --- |
| The software is free, forever, no held-back features | teemops.com — loudly, repeatedly |
| A company stands behind it | teemops.com — one footer sentence, once |
| What that company will do for you, and what it costs | **teem.cloud only** |

Red Hat works because fedoraproject.org does not sell RHEL. The moment teemops.com
starts describing support tiers, response times or engagement models, every free claim
on the site reads as a funnel. The footer link is load-bearing precisely *because* it is
the only one — it is a door, not a pitch.

So: no support page, no enterprise page, no contact-sales form, no "need help?" callout,
no comparison table with a paid column. If a visitor needs a vendor, one footer link is
enough — people looking to spend money are good at finding where to spend it.

---

## Consequences for the rest of the site

- **Nav loses "Pricing & support"** → `Features · How it works · Docs` + GitHub button.
  Reflected in [homepage.md](homepage.md) §1.
- **Homepage loses the three-card paid block** from the first draft. Reflected in
  [homepage.md](homepage.md).
- **`../pages/pricing.md`** (230 lines of Free/Starter/Pro/Business) is deleted, not
  rewritten.
- **`../html/pricing.html`** (588 lines) is deleted.
- **`../pricing-strategy.md`** (431 lines) is obsolete on this site. It may still be
  useful input to teem.cloud, so **move it rather than delete it** — it is the only place
  the SMB segment analysis is written down, and it should not be lost to a `git rm`.
- **`../content-plan.md`** site structure loses `/pricing`; `/contact` should go too, on
  the same logic — a contact form on the project site is a sales channel by another name.
  GitHub issues is the contact channel for a project.
- **Anything in `../pages/*.md` promising a "free tier"** needs rewording. "Free tier"
  implies a paid tier; the word for what we have is just *free*.

---

## The one open judgement call

Does the "Free. Forever." band belong on the homepage at all, or is it redundant?

The hero already says open-source and self-hosted, and §3 already shows the Apache-2.0
badge. An argument exists that a whole band restating it protests too much.

**My recommendation: keep it, and keep it small.** Every visitor arriving from a
commercial CSPM is carrying the assumption that there is a catch, and one unambiguous
paragraph is cheaper than losing them to that suspicion. But it should read as a
statement of fact, not a sales counter-argument — no comparison table, no "unlike other
vendors", no exclamation marks.

If it is cut, the `/pricing` redirect should point at the FAQ answer on the features
page instead of `#free`, so the URL still lands somewhere that answers the question.

---

## Deleted from the current pricing page

- Free / Starter ($149) / Pro ($449) / Business tiers — priced a SaaS we are not shipping
- "20% off annual" — no subscription product to discount
- Per-tier limits on AWS accounts, scan frequency, check count, history, users — none of
  these limits exist in the software, and inventing them contradicts the licence
- "Not competing on price with DIY/open source" positioning — **we are the open source**
- The managed-hosting and support-services cards drafted earlier in this wireframe set —
  those belong on teem.cloud, which is out of scope for `design/marketing/web/`
