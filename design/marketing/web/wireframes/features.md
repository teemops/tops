# Wireframe — Features (`/features`)

**Job:** the evaluator has decided we are interesting and is now checking whether we do
the specific thing they need. This page is scanned, not read. Optimise for a reader
using ⌘F.

**Primary CTA:** install command, repeated at top and bottom.
**Structure:** one section per capability, each answering "what does it do" in a
sentence, then showing it.

The current page (`../pages/features.md`, 333 lines) is organised around a generic CSPM
feature list — scanning, compliance, multi-account, alerts, remediation, reporting,
integrations. Three of those sections describe things that either do not exist
(real-time alerts, integrations) or are now wrong (the three score tiles at
`../html/features.html:285,303,321`). This reorganises around what we actually ship.

---

## Structure

```
┌──────────────────────────────────────────────────────────────────────┐
│ 1. HERO + jump nav                                                   │
│ 2. DURABLE FINDINGS      ← #81, #90                                  │
│ 3. SCANNING              ← #84, #92, coverage table                  │
│ 4. FILTER AND SLICE      ← #85, #87, #83                             │
│ 5. REMEDIATION           ← #82                                       │
│ 6. INSIGHTS              ← #88, #89                                  │
│ 7. MULTI-TENANCY         ← orgs, roles, isolation                    │
│ 8. WHAT WE DON'T DO      ← honest boundaries                         │
│ 9. CTA                                                               │
└──────────────────────────────────────────────────────────────────────┘
```

---

## 1. Hero + jump nav

```
┌──────────────────────────────────────────────────────────────────────┐
│  Everything TOPS does                                                │
│  74 checks across 11 AWS services. Self-hosted. Apache-2.0.          │
│                                                                      │
│  Findings · Scanning · Filtering · Remediation · Insights · Teams    │
│  ──────────────────────────────────────────────────────────────────  │
└──────────────────────────────────────────────────────────────────────┘
```

Sticky jump nav under the main nav on `lg+`. This page is long; anchor links are the
main navigation aid. Drop the jump nav below `md` rather than stacking two sticky bars.

---

## 2. Durable findings

```
┌──────────────────────────────────────────────────────────────────────┐
│  Findings that persist                                               │
│  One record per resource and rule — not one row per scan.            │
│                                                                      │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │  ▌CRITICAL   S3 bucket allows public read                      │  │
│  │              arn:aws:s3:::acme-assets                          │  │
│  │              First seen 12 Jun · Last seen 2 Aug · 51 days open│  │
│  │              [ cis-2.1.5 ]  [ S3 ]           Status: ● Open    │  │
│  │              ──────────────────────────────────────────────    │  │
│  │              Fix: Enable Block Public Access on the bucket.    │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  ✓ Mark resolved or ignored — it stays that way through the next scan│
│  ✓ First-seen and last-seen on every finding                         │
│  ✓ Reappears automatically if the misconfiguration comes back        │
└──────────────────────────────────────────────────────────────────────┘
```

Ships #81, #90, and the anatomy of a finding in one figure — every element on this card
is a separate capability, labelled once, and the sections below refer back to it.

"51 days open" is the number that sells this. It is only computable because of #81.

---

## 3. Scanning

```
┌──────────────────────────────────────────────────────────────────────┐
│  Scan what you need, when you need it                                │
│                                                                      │
│  ┌──────────────────────┐  ┌──────────────────────┐                  │
│  │ Whole benchmark      │  │ One service          │                  │
│  │ CIS AWS Foundations, │  │ Changed one S3 policy│                  │
│  │ or the general basic │  │ ? Scan S3 and get an │                  │
│  │ profile              │  │ answer in a fraction │                  │
│  │                      │  │ of the time          │                  │
│  └──────────────────────┘  └──────────────────────┘                  │
│                                                                      │
│  Regions run in parallel. A ten-region account isn't ten times       │
│  the wait.                                                           │
│                                                                      │
│  ── Coverage ──────────────────────────────────────────────────────  │
│   S3        │ ██████ n checks  │ IAM     │ ██████ n checks           │
│   EC2       │ ████   n checks  │ RDS     │ ████   n checks           │
│   CloudTrail│ ███    n checks  │ KMS     │ ███    n checks           │
│   Lambda    │ ██     n checks  │ DynamoDB│ ██     n checks           │
│   ELBv2     │ ██     n checks  │ SNS     │ █      n checks           │
│   SQS       │ █      n checks  │         │                           │
│                                          [ See every check → docs ]  │
└──────────────────────────────────────────────────────────────────────┘
```

Ships #84 and #92.

The coverage table is the highest-value block on the page for an evaluator — it is the
⌘F target. Per-service check counts must be **generated**, not typed; `scan:validate-rules`
walks the rule JSON and is the source. A stale count here is the kind of small
inaccuracy that loses a technical buyer.

Link out to a full check listing in the docs rather than inlining 74 rows.

---

## 4. Filter and slice

```
┌──────────────────────────────────────────────────────────────────────┐
│  Cut 400 findings down to the 8 you're working on                    │
│                                                                      │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │ [ Account: prod ▾ ] [ Service: S3 ▾ ] [ Benchmark: CIS 2.x ▾ ] │  │
│  │ [ Severity: Critical ▾ ] [ Status: Open ▾ ]         12 results │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
│   /findings?service=s3&benchmark=cis&status=open                     │
│   ↑ every filter is in the URL — bookmark it, paste it in Slack,     │
│     send it to the person who owns S3                                │
└──────────────────────────────────────────────────────────────────────┘
```

Ships #85, #87, #83. The URL under the filter bar is the whole point of the section —
render it as visible monospace text, not a caption.

Benchmark filtering only works because findings record which benchmark raised them
(`a74a6c3`). Worth one sentence: a finding knows which control it came from, so a
compliance question has a direct answer.

---

## 5. Remediation

```
┌──────────────────────────────────────────────────────────────────────┐
│  Every finding tells you how to fix it                               │
│                                                                      │
│  ┌───────────────────────────┐  ┌───────────────────────────────┐    │
│  │ In the list               │  │ On the finding                │    │
│  │                           │  │                               │    │
│  │ ▌HIGH  RDS is public      │  │  1. Open the RDS console      │    │
│  │  Set PubliclyAccessible   │  │  2. Modify → Connectivity     │    │
│  │  to false            ← 1  │  │  3. Public access → No        │    │
│  │  line, no click needed    │  │  4. Apply immediately         │    │
│  │                           │  │     [ AWS docs → ]            │    │
│  └───────────────────────────┘  └───────────────────────────────┘    │
│                                                                      │
│  Step-by-step guidance on every critical and high finding.           │
└──────────────────────────────────────────────────────────────────────┘
```

Ships #82. The left/right split makes the distinction the product actually draws: a
one-liner everywhere, full steps on critical and high. Do not imply full step-by-step
exists on all 74 checks — the README is precise about this and the site should match.

---

## 6. Insights

```
┌──────────────────────────────────────────────────────────────────────┐
│  Where the problems actually are                                     │
│                                                                      │
│  ┌─────────────────────────────┐ ┌─────────────────────────────┐     │
│  │ By service                  │ │ By benchmark                │     │
│  │  with a severity breakdown  │ │  which controls are failing │     │
│  │  per service, so "42 in S3" │ │  and how badly              │     │
│  │  becomes "12 critical in S3"│ │                             │     │
│  └─────────────────────────────┘ └─────────────────────────────┘     │
│                                                                      │
│  Also: findings trend over a period, severity distribution,          │
│  remediation rate.                                                   │
└──────────────────────────────────────────────────────────────────────┘
```

Ships #88, #89. The trend / distribution / remediation-rate line covers what Insights
already had, briefly — it is table stakes, not differentiation, and should not get
equal billing with the two groupings.

**No score, no grade, no single health number anywhere on this page.**

---

## 7. Teams and isolation

```
┌──────────────────────────────────────────────────────────────────────┐
│  Built multi-tenant from the schema up                               │
│                                                                      │
│  Organisations · team roles · every query scoped by organisation     │
│  Run one instance for several clients, or several teams, without     │
│  one seeing another's findings.                                      │
└──────────────────────────────────────────────────────────────────────┘
```

Short. Relevant to agencies and MSPs — a real segment for a self-hosted scanner — and
it is genuinely enforced rather than aspirational, so we can state it flatly.

---

## 8. What we don't do

```
┌──────────────────────────────────────────────────────────────────────┐
│  What TOPS doesn't do (yet)                                          │
│                                                                      │
│   ✗ Azure or GCP — AWS only                                          │
│   ✗ Auto-remediation — we tell you the fix, you apply it             │
│   ✗ Real-time alerting / webhooks — scans are on demand              │
│   ✗ Agent-based runtime monitoring — this is config scanning         │
│                                                                      │
│   Roadmap → github.com/teemops/tops                                  │
└──────────────────────────────────────────────────────────────────────┘
```

**Recommend keeping this section.** It disqualifies bad-fit visitors before they install
and bounce, it is unusual enough to be memorable, and for an open-source project it
reads as confidence. It also lets us delete the current page's "Real-time Alerts" and
"Integrations (future)" sections honestly rather than leaving them as vapour.

Verify each line against the roadmap before shipping — this section is only an asset
while it is accurate.

---

## 9. CTA

Same block as [homepage.md](homepage.md) §9. Install command, "read the script first",
GitHub star. No paid alternative offered here or anywhere else on the page — the only
commercial mention on the site is the footer line in [homepage.md](homepage.md) §10.

---

## Deleted from the current features page

- Three "Score" tiles (`../html/features.html:285,303,321`) — feature removed in `a246323`
- "Real-time Alerts" section — does not exist; now a line in §8
- "Integrations (future)" section — does not exist; now a line in §8
- "Reporting & Exports" as a headline section — demote to a line unless we can screenshot it
- All "Start Free Scan" CTAs
