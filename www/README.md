# teemops.com

The marketing site for the teemops SaaS. One static page, deployed to Cloudflare Workers
as static assets. Every sign-up button links to `https://app.teemops.com/`, where the
app's welcome page handles sign-up and sign-in through auth.teem.cloud. This site has no
server-side code.

**This is a separate Workers project from the docs site.** The repo root's
`wrangler.jsonc` is `docs.teemops.com` (built by `mkdocs.yml`); this directory's is
`teemops.com`. Run every command below from inside `www/`, or you will deploy the wrong
one.

## What's here

```
www/
├── wrangler.jsonc        # Worker config: static assets, custom domains, observability
├── build-assets.py       # regenerates public/og.png and public/favicon.ico from teem.cloud's logo
└── public/               # everything is static
    ├── index.html        # the page, with its CSS and JS inline
    ├── 404.html
    ├── fonts/            # IBM Plex, copied from the app's UI kit (OFL)
    ├── og.png            # generated, do not edit by hand
    ├── favicon.ico       # generated, do not edit by hand
    ├── robots.txt
    └── sitemap.xml
```

The Teem logo is the only logo used for teemops. "teemops" appears as plain text beside
it. The page loads the logo straight from teem.cloud rather than keeping a copy, so a
change there shows up here with no deploy:

- `https://teem.cloud/logo-dark.png`, the white wordmark teem.cloud uses on dark
  backgrounds (`logo.png` there, also `design/brand/TeemLogo1ps-small.png` here, is the
  black one for light backgrounds and would vanish on this page)
- `https://teem.cloud/favicon.svg`, the mark alone

`/favicon.ico` and `og.png` can't point elsewhere, so `build-assets.py` downloads those two
files and rebuilds them. Run it again if the Teem logo changes. The colours in
`index.html` are the dark theme of the app's UI kit (`packages/ui/src/css/tokens.css` in
`teemops/teemops`), so the site and the app look like one product.

## Deploy

```bash
npm install
npm run deploy
```

That also attaches `teemops.com` and `www.teemops.com`: they are declared as custom
domains under `routes` in `wrangler.jsonc`, so Wrangler creates the DNS records and
certificates itself. Declaring routes turns the `workers.dev` URL off, so there is no
preview address. Test locally with `npm run dev`.

Deploy only while `app.teemops.com` is live, since every call to action points there.

## Local development

```bash
npm install
npm run dev
```

Then open the printed URL.

## Changing the page

The CSS and JS are inline in `public/index.html` on purpose: the page is one file, has no
build step, and loads nothing from anywhere but this site and teem.cloud (the logo). Keep it that way unless there is a
reason not to.

After changing the headline or the Teem logo, regenerate the derived assets so the
link-preview card and the page agree. It needs network access and `rsvg-convert`
(librsvg):

```bash
python3 build-assets.py
```

## Legal pages

Terms, privacy and cookies are Teem's company-wide policies, published on teem.cloud and
linked from the footer and the pricing section. They are not copied here, so there is one
version to keep up to date:

- https://teem.cloud/legal/terms/ (section 7 covers teemops, as "Teem Ops")
- https://teem.cloud/legal/privacy/
- https://teem.cloud/legal/cookies/

This page sets no cookies and runs no analytics. If that changes, check the privacy and
cookie policies still describe it.

## Claims made on this page

The page states specific numbers and behaviour. They were true of `teemops/teemops` on
2026-10-27 and they are checkable. If the product changes, check them again:

| Claim | Source in `teemops/teemops` |
| --- | --- |
| 77 checks: 55 baseline + 22 CIS | `packages/scan-core/rules/aws/basic.json`, `cis.json` |
| 11 AWS services | `packages/scan-core/collectors/aws/` |
| Remediation on every check | the `remediation` field on every rule |
| Daily scans | N-8 in `docs/roadmap.md`, `docs/features/n8-scheduled-scans.md` |
| Health score 0–100 | `docs/features/insights-health-score.md` |
| Email and Slack on new critical or high | `docs/features/x4-notifications.md` |
| Agent prompt on each finding | `apps/web/src/pages/FindingPage.vue` |
| `--fail-on` exits non-zero | `apps/cli/src/cli.ts` |
| Read-only role with a per-account ExternalId, no stored keys | N-4 in `docs/roadmap.md`, `docs/features/n4-connect-aws.md` |
| Sign up with Google, Microsoft, GitHub or email | `apps/web/src/welcome.ts` |
| Plans, prices, $1 for 30 days, limits, downgrade behaviour | "Pricing" in `docs/roadmap.md`, `docs/features/billing.md` |
| AWS only today; GCP, Azure, Cloudflare next | X-1 to X-3 in `docs/roadmap.md` |

Team and Business sell 5 and 10 users, so team invites (X-9) must ship before those
plans are sold to anyone who needs a second user.

Overstating any of these costs more than it buys: a customer will sign up and find out.

## The old demo-request form

Until October 2026 this site was the page for the self-hosted scanner, with a
demo-request form at `POST /api/design-partner`. The form and its Worker were removed when
the site moved to sign-up. The leads it collected are still in the D1 database
`tops-www-leads` (id `88cc0eec-9c77-4714-8b1a-62c7db52e0e5`). Nothing writes to it now,
and it has not been deleted.
