# teemops.com

The marketing site and its design-partner signup form. One page, deployed to Cloudflare
Workers as static assets plus a single API route.

**This is a separate Workers project from the docs site.** The repo root's
`wrangler.jsonc` is `docs.teemops.com` (built by `mkdocs.yml`); this directory's is
`teemops.com`. Run every command below from inside `www/`, or you will deploy the wrong
one.

## What's here

```
www/
├── wrangler.jsonc        # Worker config — assets, D1 binding, observability
├── schema.sql            # the design_partner_leads table
├── vitest.config.ts      # runs the tests inside workerd, against a real local D1
├── build-assets.py       # regenerates public/og.png and public/favicon.ico
├── src/
│   └── index.ts          # POST /api/design-partner — the only server-side code
├── test/
│   └── signup.test.ts    # 16 tests over that endpoint
└── public/               # everything else is static
    ├── index.html        # the page, with its CSS and JS inline
    ├── 404.html
    ├── og.png            # generated — do not edit by hand
    ├── favicon.ico       # generated — do not edit by hand
    ├── logo.png          # the source both are derived from
    ├── robots.txt
    └── sitemap.xml
```

## First-time setup

Two CLI commands and one dashboard step. There are no secrets to set.

### 1. Create the leads table

The D1 database `tops-www-leads` already exists. Create its schema in the remote copy:

```bash
npm run db:init
```

### 2. Deploy

```bash
npm run deploy
```

### 3. Point teemops.com at it

In **Workers & Pages → tops-www → Settings → Domains & Routes**, add `teemops.com` and
`www.teemops.com` as custom domains. This is dashboard-only — a repo commit cannot do it.

## Reading the leads

```bash
npm run leads
```

That prints every signup, newest first. For the message someone left as well:

```bash
npx wrangler d1 execute tops-www-leads --remote \
  --command="SELECT created_at, name, email, company, aws_scale, notes FROM design_partner_leads ORDER BY created_at DESC LIMIT 20"
```

**Nothing emails you when a lead arrives.** You have to run this. That is a deliberate
starting point rather than an oversight — it is one command, and adding notification
before there is a single lead to notify about is work with no evidence behind it. When
checking manually gets annoying, that is the signal to add it, and the account already has
`email_sending` available for exactly that.

## Spam protection, and what to do if it stops working

The form is protected by a **honeypot**: a hidden `website` field that people never see and
bots tend to fill. If it arrives non-empty the submission is dropped and the endpoint
answers `201` anyway, so a bot gets no signal about which field gave it away. The name is
defined once in `src/index.ts` as `HONEYPOT_FIELD` and must match the input in
`public/index.html`.

**There is deliberately no CAPTCHA.** Turnstile was built and then removed before launch,
for three reasons: at five-design-partner volume a handful of junk rows is easier to skim
past than a widget is to maintain; the endpoint sends no email and publishes nothing, so
spam has no amplification path; and a third-party challenge script that fails to load —
corporate network, privacy extension — makes the form unsubmittable and loses a real lead
with no signal that it happened.

**If you start getting spam**, in increasing order of effort:

1. Turn on **Bot Fight Mode** for the zone. No code, no deploy.
2. Add a **rate-limiting rule** on `/api/design-partner`. Still no code.
3. Add [Turnstile](https://developers.cloudflare.com/turnstile/) — a widget in the
   dashboard, its site key in the page, `wrangler secret put TURNSTILE_SECRET_KEY`, and a
   `siteverify` call in `handleSignup`. Note the stored OAuth token lacks
   `challenge-widgets.write`, so creating the widget from the CLI needs `wrangler login`
   first. If you do this, never ship Cloudflare's test key (`1x00000000000000000000AA`) —
   it always passes, so the form would look protected and be wide open.

Reach for 3 only if 1 and 2 have not held.

## Tests

```bash
npm test
```

16 tests covering `POST /api/design-partner`, run inside `workerd` against a real local D1
via `@cloudflare/vitest-pool-workers` — so the SQL is genuinely executed rather than
mocked. They cover the happy path, field trimming and length caps, every validation
rejection, the honeypot (including that a trapped request is byte-for-byte
indistinguishable from a genuine one), and the method and path guards.

Each assertion checks the database as well as the status code, because the failure that
matters is a submission that answers `201` and stores nothing.

Note `@cloudflare/vitest-pool-workers` 0.20 removed the `/config` subpath and the
`defineWorkersConfig` helper that most examples online still use. On vitest 4 the pool
options go to a `cloudflareTest()` **plugin** — see the comment in `vitest.config.ts`.

## Local development

```bash
npm install
npm run db:init:local
npm run dev
```

Then open the printed URL. There are no secrets and no `.dev.vars` to set up.

Inspect what local submissions wrote:

```bash
npx wrangler d1 execute tops-www-leads --local --command="SELECT * FROM design_partner_leads"
```

## Changing the page

The CSS and JS are inline in `public/index.html` on purpose — the page is one file, has no
build step, and loads no third-party assets at all. Keep it that way
unless there is a reason not to.

After changing the headline, regenerate the derived assets so the link-preview card and
the page agree:

```bash
python3 build-assets.py
```

## Claims made on this page

The page states specific numbers. They were true at v0.5.0 and they are checkable — if you
change the rules, check them again:

| Claim | Source |
| --- | --- |
| 74 checks | `app/rules/rulesets/basic.json` (52) + `cis.json` (22) |
| 11 AWS services | directories under `app/rules/tasks/` |
| 28 with step-by-step guidance | `app/rules/recommendations/tips.json` |
| Install needs only Docker | `install.sh`, and D-8 in `docs/roadmap.md` |

Overstating any of these to a design partner costs more than it buys — they will install it
and find out.
