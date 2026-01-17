# Teemops Pricing Strategy

## Executive Summary

This document outlines the pricing strategy for Teemops' go-to-market launch, targeting SMBs (20-200 employees) in SaaS, Fintech, and Healthtech industries, with initial focus on the NZ market.

---

## Pricing Philosophy

### Core Principles

1. **Value-Based Pricing** — Price based on value delivered (security + compliance + time saved), not cost to serve
2. **Land and Expand** — Start customers on lower tiers, grow with their needs
3. **Transparent & Simple** — No hidden fees, clear feature boundaries
4. **Freemium for Acquisition** — Free tier to reduce friction and build trust

### Positioning

| Position | Description |
|----------|-------------|
| **Not the cheapest** | We're not competing on price with DIY/open source |
| **Not enterprise pricing** | Accessible to SMBs without procurement hoops |
| **Best value for SMBs** | Sweet spot between free tools and enterprise CSPM |

---

## Pricing Structure

### Recommended Tiers

| Tier | Price (NZD) | Price (USD) | Target Customer |
|------|-------------|-------------|-----------------|
| **Free** | $0 | $0 | Individual devs, evaluation |
| **Starter** | $149/mo | $99/mo | Small startups (10-30 staff) |
| **Pro** | $449/mo | $299/mo | Scale-ups (30-100 staff) |
| **Business** | Custom | Custom | Mid-market (100-500 staff) |

### Annual Discount
- **20% off** for annual commitment
- Starter: $119/mo billed annually ($1,428/yr)
- Pro: $359/mo billed annually ($4,308/yr)

---

## Tier Details

### Free Tier

**Purpose:** Acquisition, evaluation, word-of-mouth

| Feature | Limit |
|---------|-------|
| AWS Accounts | 1 |
| Scan Frequency | Weekly |
| Security Checks | 25 (basic) |
| History | 7 days |
| Users | 1 |
| Support | Community/docs |

**Conversion Goal:** 10-15% convert to paid within 60 days

**Why Free Works:**
- Reduces signup friction (no credit card)
- Lets users experience value before paying
- Creates word-of-mouth in developer communities
- Captures leads for nurturing

---

### Starter Tier ($149 NZD / $99 USD)

**Purpose:** First paid tier, small teams

| Feature | Limit |
|---------|-------|
| AWS Accounts | 3 |
| Scan Frequency | Daily |
| Security Checks | 100+ (full) |
| Compliance | CIS Benchmarks |
| History | 30 days |
| Users | 5 |
| Integrations | Email, Slack |
| Support | Email (48hr response) |

**Target Customer:**
- Early-stage startups
- Small dev teams
- Side projects with production AWS

**Value Justification:**
- 1 hour of security consultant = $200-400 NZD
- Starter saves 5+ hours/month = $1,000+ value
- Price is <15% of value delivered

---

### Pro Tier ($449 NZD / $299 USD)

**Purpose:** Core revenue driver, growing companies

| Feature | Limit |
|---------|-------|
| AWS Accounts | 10 |
| Scan Frequency | Hourly |
| Security Checks | 100+ (full) |
| Compliance | CIS, PCI-DSS, SOC 2 |
| History | 90 days |
| Users | 15 |
| Integrations | Email, Slack, Webhooks, API |
| Support | Priority email (24hr response) |
| Extras | Custom scan configs |

**Target Customer:**
- Scale-ups with compliance needs
- Fintech/Healthtech with audits
- Companies with multiple environments

**Value Justification:**
- SOC 2 audit prep consulting = $10,000-50,000 NZD
- PCI compliance assessment = $5,000-20,000 NZD
- Pro tier = $5,388/year = <10% of compliance costs

---

### Business Tier (Custom)

**Purpose:** Larger organizations, higher ACV

| Feature | Limit |
|---------|-------|
| AWS Accounts | Unlimited |
| Scan Frequency | Real-time/continuous |
| Security Checks | 100+ (full) |
| Compliance | All + Custom frameworks |
| History | Unlimited |
| Users | Unlimited |
| Integrations | All + SSO/SAML |
| Support | Dedicated CSM, phone |
| Extras | Custom SLAs, on-call |

**Target Customer:**
- Companies with 10+ AWS accounts
- Agencies managing client accounts
- Organizations requiring SSO

**Pricing Approach:**
- Base: $999 NZD/month ($12,000/year)
- Per additional AWS account: $50-100/month
- Or negotiate based on total value

---

## Pricing Psychology

### Anchoring

- Show Business tier first (anchors high)
- Pro becomes "reasonable" by comparison
- Starter feels like a "deal"

### Decoy Effect

- Pro is the target tier
- Starter exists to make Pro look valuable
- Feature gap between Starter→Pro is significant

### Price Endings

- Use $X49 or $X99 (not round numbers)
- $149 feels cheaper than $150
- $449 feels cheaper than $450

### Social Proof on Pricing

- "Most Popular" badge on Pro tier
- Customer count or logos near pricing
- "Join 500+ companies" type messaging

---

## Launch Pricing Strategy

### Phase 1: Beta/Early Access (Month 1-2)

**Goal:** Get first 10-20 paying customers, gather feedback

| Offer | Details |
|-------|---------|
| **Founding Member Discount** | 50% off first year |
| **Extended Trial** | 30 days instead of 14 |
| **Locked-in Price** | Price guaranteed for 2 years |

**Messaging:**
> "Join as a founding member. Get 50% off your first year and lock in this price forever."

**Why This Works:**
- Creates urgency (limited spots)
- Rewards early adopters
- Builds case studies and testimonials

---

### Phase 2: Public Launch (Month 3-4)

**Goal:** Scale acquisition, establish market pricing

| Offer | Details |
|-------|---------|
| **Launch Discount** | 20% off first 3 months |
| **Annual Incentive** | Extra month free on annual |
| **Referral Program** | $50 credit per referral |

**Remove:**
- Founding member pricing (scarcity)
- Extended trials (back to 14 days)

---

### Phase 3: Growth (Month 5+)

**Goal:** Optimize pricing, increase ACV

| Action | Details |
|--------|---------|
| **Price Testing** | A/B test pricing page |
| **Tier Adjustments** | Based on usage data |
| **Add-ons** | Premium support, extra accounts |
| **Usage Pricing** | Consider per-account pricing |

---

## NZ Market Considerations

### Currency

| Approach | Pros | Cons |
|----------|------|------|
| **NZD only** | Simple, local feel | Limits international |
| **USD only** | Standard for SaaS | Feels foreign to NZ |
| **Both (recommended)** | Best of both | More complexity |

**Recommendation:** Show NZD by default for NZ visitors, USD for others. Use geo-detection.

### NZ Pricing Sensitivity

| Factor | Implication |
|--------|-------------|
| Smaller budgets | Price 20-30% below US competitors |
| Annual preference | Offer meaningful annual discount |
| Relationship-driven | Offer personal demos, not just self-serve |
| GST consideration | Prices should be +GST for NZ |

### NZ-Specific Offers

- **NZ Tech Startup Discount:** 50% off for NZ startups <$1M revenue
- **Kiwi Landing Pad Alumni:** Special pricing for KLP companies
- **NZTech Member Discount:** 10% off for NZTech members

---

## Competitive Positioning

### Price Comparison

| Competitor | Pricing | Our Position |
|------------|---------|--------------|
| **AWS Security Hub** | ~$0.001/check | We're more actionable |
| **Prowler (OSS)** | Free | We save time, provide remediation |
| **Wiz** | $50k+/year | We're 10x cheaper, SMB-focused |
| **Orca** | $30k+/year | We're accessible to SMBs |
| **Lacework** | $50k+/year | Enterprise, not our market |
| **CloudSploit** | $100-500/mo | Similar, we're more modern |

### Positioning Statement

> "Enterprise-grade AWS security scanning at SMB-friendly prices. 10x cheaper than Wiz, 10x faster than doing it yourself."

---

## Discounts & Promotions

### Standard Discounts

| Discount | Amount | Conditions |
|----------|--------|------------|
| Annual billing | 20% | Pay upfront |
| Startup program | 50% | <$1M revenue, <2 years old |
| Non-profit | 50% | Registered charity |
| Education | 50% | Educational institutions |

### Promotional Discounts

| Promotion | Use Case | Duration |
|-----------|----------|----------|
| Launch discount | Public launch | 3 months |
| Event discount | Conference/meetup | 1 month |
| Partner discount | Agency partners | Ongoing |
| Referral credit | Word of mouth | Ongoing |

### Discount Rules

1. **Max one discount** — Discounts don't stack
2. **First year only** — Startup/nonprofit discounts apply to year 1
3. **Verify eligibility** — Require proof for startup/nonprofit
4. **No public coupons** — Avoid discount culture

---

## Monetization Roadmap

### Current (MVP)

| Revenue Stream | Description |
|----------------|-------------|
| Subscription tiers | Free/Starter/Pro/Business |
| Annual upsell | 20% discount incentive |

### Near-term (6-12 months)

| Revenue Stream | Description |
|----------------|-------------|
| Additional AWS accounts | $50-100/account/month |
| Premium support | $200/month add-on |
| Compliance add-ons | $100/month per framework |

### Long-term (12+ months)

| Revenue Stream | Description |
|----------------|-------------|
| Multi-cloud (Azure, GCP) | Separate pricing tier |
| Professional services | Implementation, training |
| API access tiers | Usage-based API pricing |
| White-label/reseller | For MSPs and agencies |

---

## Key Metrics to Track

### Pricing Metrics

| Metric | Target | Why It Matters |
|--------|--------|----------------|
| **Free→Paid Conversion** | 10-15% | Funnel health |
| **Trial→Paid Conversion** | 25-40% | Value demonstration |
| **Monthly Churn** | <5% | Pricing/value alignment |
| **Average Revenue Per User (ARPU)** | $200+ NZD | Revenue efficiency |
| **Annual Contract Value (ACV)** | $2,500+ NZD | Revenue predictability |
| **Expansion Revenue** | 20%+ of new ARR | Land and expand working |

### Pricing Page Metrics

| Metric | Target |
|--------|--------|
| Pricing page visits | Track volume |
| Time on pricing page | 30-60 seconds |
| CTA click rate | 10-20% |
| Plan selection distribution | Pro = 50%+ |

---

## Pricing Communication

### How to Talk About Pricing

**Do:**
- Lead with value, not price
- Compare to alternatives (time saved, consultant costs)
- Offer to discuss needs before quoting
- Be transparent about what's included

**Don't:**
- Apologize for pricing
- Offer discounts unprompted
- Compete on price alone
- Hide pricing (creates distrust)

### Objection Handling

| Objection | Response |
|-----------|----------|
| "Too expensive" | "What's your current spend on security tools/consultants?" |
| "Can I get a discount?" | "We offer annual billing at 20% off. What's your timeline?" |
| "Competitor is cheaper" | "What features are most important to you? Let's compare." |
| "Need to think about it" | "What information would help you decide?" |
| "Free tier is enough" | "As you grow, you'll need [specific Pro features]. We're here when ready." |

---

## Implementation Checklist

### Before Launch

- [ ] Implement pricing tiers in billing system
- [ ] Set up Stripe/payment processor
- [ ] Create pricing page with tier comparison
- [ ] Build upgrade/downgrade flows
- [ ] Set up trial expiration emails
- [ ] Create discount code system
- [ ] Document pricing for sales team

### At Launch

- [ ] Announce founding member pricing
- [ ] Set up conversion tracking
- [ ] Monitor trial→paid funnel
- [ ] Collect pricing feedback

### Post-Launch (Monthly)

- [ ] Review conversion rates
- [ ] Analyze churn by tier
- [ ] Survey churned customers on pricing
- [ ] A/B test pricing page elements

---

## Summary

| Element | Recommendation |
|---------|----------------|
| **Tiers** | Free / Starter ($149) / Pro ($449) / Business (custom) |
| **Annual discount** | 20% off |
| **Launch offer** | 50% founding member discount |
| **Target tier** | Pro (60% of revenue) |
| **NZ pricing** | Show NZD, 20-30% below US market |
| **Key metric** | 10%+ free→paid conversion |

---

*Last updated: January 2026*
