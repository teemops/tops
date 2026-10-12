# teemops.com

The marketing site and its demo-request form. One page, deployed to Cloudflare
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
│   └── index.ts          # POST /api/design-partner — demo requests, the only server-side code
├── test/
│   └── signup.test.ts    # 19 tests over that endpoint
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

Two CLI commands. There are no secrets to set. (Email Sending on `notify.teemops.com`
was enabled once with `npx wrangler email sending enable notify.teemops.com`.)

### 1. Create the leads table

The D1 database `tops-www-leads` already exists. Create its schema in the remote copy:

```bash
npm run db:init
```

### 2. Deploy

```bash
npm run deploy
```

That also attaches `teemops.com` and `www.teemops.com`: they are declared as custom
domains under `routes` in `wrangler.jsonc`, so Wrangler creates the DNS records and
certificates itself. Declaring routes turns the `workers.dev` URL off, so there is no
preview address — test locally with `npm run dev`.

## Reading the leads

```bash
npm run leads
```

That prints every signup, newest first. For the message someone left as well:

```bash
npx wrangler d1 execute tops-www-leads --remote \
  --command="SELECT created_at, name, email, company, aws_scale, notes FROM design_partner_leads ORDER BY created_at DESC LIMIT 20"
```

**Each new lead is also emailed to `help@teemops.com`**, from `leads@notify.teemops.com`
with Reply-To set to the lead, so you can answer from your inbox. The database is still the
record: the email is sent after the row is stored, and a failed send is logged as
`lead_notify_failed` without affecting the visitor. Check the Worker's logs if the emails
stop arriving.

`notify.teemops.com`, not the apex, is onboarded to Cloudflare Email Sending, because
`teemops.com`'s mail and DMARC record belong to Proton and a second DMARC record would
break both. The recipient is pinned by `destination_address` on the `EMAIL` binding in
`wrangler.jsonc`, and `NOTIFY_TO` in `src/index.ts` must match it.

## Spam protection, and what to do if it stops working

The form is protected by a **honeypot**: a hidden `website` field that people never see and
bots tend to fill. If it arrives non-empty the submission is dropped and the endpoint
answers `201` anyway, so a bot gets no signal about which field gave it away. The name is
defined once in `src/index.ts` as `HONEYPOT_FIELD` and must match the input in
`public/index.html`.

**There is deliberately no CAPTCHA.** Turnstile was built and then removed before launch,
for three reasons: at current lead volume a handful of junk rows is easier to skim
past than a widget is to maintain; the endpoint emails only our own pinned inbox and publishes nothing, so
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

19 tests covering `POST /api/design-partner`, run inside `workerd` against a real local D1
via `@cloudflare/vitest-pool-workers` — so the SQL is genuinely executed rather than
mocked. They cover the happy path, field trimming and length caps, every validation
rejection, the honeypot (including that a trapped request is byte-for-byte
indistinguishable from a genuine one), the lead notification (with the `EMAIL` binding
replaced by a recorder, so tests never send mail), and the method and path guards.

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

Overstating any of these to a lead costs more than it buys — they will install it
and find out.
