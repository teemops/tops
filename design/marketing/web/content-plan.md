# Teemops Marketing Website - Content Plan

## Overview

This document outlines all content required for the Teemops marketing website, targeting CTOs and DevOps engineers at SMBs (20-200 employees) in SaaS, Fintech, and Healthtech industries.

---

## Site Structure

```
teemops.com/
├── / (Homepage)
├── /features
├── /pricing
├── /security
├── /docs (links to documentation)
├── /blog (future)
├── /about
├── /contact
├── /login (→ app)
├── /signup (→ app)
│
├── /use-cases/
│   ├── /saas-companies
│   ├── /fintech
│   ├── /healthtech
│   └── /agencies
│
├── /compliance/
│   ├── /cis-benchmarks
│   ├── /pci-dss
│   └── /soc2
│
└── /resources/ (future)
    ├── /case-studies
    ├── /whitepapers
    └── /webinars
```

---

## Page Requirements

### 1. Homepage (/)

**Purpose:** Convert visitors to signups, communicate core value proposition

**Sections:**
1. Hero - Main value prop + CTA
2. Problem/Pain - Why cloud security is hard
3. Solution - How Teemops helps
4. Features overview - Key capabilities
5. How it works - 3-step process
6. Social proof - Logos, testimonials
7. Pricing teaser - Starting price + CTA
8. Final CTA - Get started

**Content File:** `pages/homepage.md`
**Mockup:** `html/index.html`

---

### 2. Features Page (/features)

**Purpose:** Detail all product capabilities for evaluators

**Sections:**
1. Hero - Features overview
2. AWS Security Scanning
3. Compliance Monitoring (CIS, PCI, SOC2)
4. Multi-Account Management
5. Real-time Alerts
6. Remediation Guidance
7. Reporting & Exports
8. Integrations (future)
9. CTA

**Content File:** `pages/features.md`
**Mockup:** `html/features.html`

---

### 3. Pricing Page (/pricing)

**Purpose:** Clear pricing, drive signup decisions

**Sections:**
1. Pricing tiers (Free, Starter, Pro, Business)
2. Feature comparison table
3. FAQ
4. Enterprise contact CTA

**Content File:** `pages/pricing.md`
**Mockup:** `html/pricing.html`

---

### 4. Security Page (/security)

**Purpose:** Build trust, show we practice what we preach

**Sections:**
1. Our security commitment
2. How we protect your data
3. Compliance certifications (future)
4. Responsible disclosure
5. FAQ

**Content File:** `pages/security.md`
**Mockup:** `html/security.html`

---

### 5. Use Case Pages (/use-cases/*)

**Purpose:** Industry-specific messaging and SEO

**Pages:**
- SaaS Companies
- Fintech & Payments
- Healthtech
- Digital Agencies

**Content File:** `pages/use-cases.md`
**Mockup:** `html/use-case.html` (template)

---

### 6. Compliance Pages (/compliance/*)

**Purpose:** Compliance-specific messaging and SEO

**Pages:**
- CIS AWS Benchmarks
- PCI-DSS
- SOC 2

**Content File:** `pages/compliance.md`
**Mockup:** `html/compliance.html` (template)

---

### 7. About Page (/about)

**Purpose:** Build trust, tell our story

**Sections:**
1. Our mission
2. The team (future)
3. Why we built Teemops
4. Contact info

**Content File:** `pages/about.md`

---

### 8. Contact Page (/contact)

**Purpose:** Lead capture, support inquiries

**Sections:**
1. Contact form
2. Email addresses
3. Social links

**Content File:** `pages/contact.md`

---

## Content Inventory

### Core Messaging

| Element | Content |
|---------|---------|
| **Tagline** | "AWS Security Scanning Made Simple" |
| **Subheadline** | "Find misconfigurations before attackers do. Get actionable fixes in minutes, not weeks." |
| **Value Prop 1** | See all your AWS security gaps in one dashboard |
| **Value Prop 2** | Continuous CIS, PCI-DSS, SOC 2 compliance monitoring |
| **Value Prop 3** | One-click remediation guidance for every finding |
| **CTA Primary** | "Start Free Scan" |
| **CTA Secondary** | "Book a Demo" |

### SEO Keywords (Primary)

| Keyword | Search Volume | Difficulty | Priority |
|---------|---------------|------------|----------|
| AWS security scanning | Medium | Medium | High |
| AWS misconfiguration scanner | Low | Low | High |
| Cloud security posture management | Medium | High | Medium |
| AWS CIS benchmark tool | Low | Low | High |
| AWS compliance monitoring | Medium | Medium | High |
| S3 bucket security checker | Low | Low | High |

### SEO Keywords (NZ-Specific)

| Keyword | Priority |
|---------|----------|
| AWS security NZ | High |
| Cloud security New Zealand | High |
| NZISM compliance | Medium |
| NZ Privacy Act compliance | Medium |

---

## Content Assets Required

### Images/Graphics

| Asset | Purpose | Status |
|-------|---------|--------|
| Hero illustration | Homepage hero | Needed |
| Feature icons (8x) | Features page | Needed |
| How it works diagram | Homepage | Needed |
| Dashboard screenshot | Social proof | Needed |
| Scan results screenshot | Features | Needed |
| Compliance badges | Trust signals | Needed |
| Team photos | About page | Future |
| Customer logos | Social proof | Future |

### Videos (Future)

| Video | Purpose | Priority |
|-------|---------|----------|
| Product demo (2 min) | Homepage, features | High |
| Getting started tutorial | Onboarding | Medium |
| Customer testimonials | Social proof | Medium |

---

## Page Priority for MVP

| Priority | Page | Reason |
|----------|------|--------|
| 1 | Homepage | Core conversion page |
| 2 | Pricing | Required for purchase decisions |
| 3 | Features | Evaluator research |
| 4 | Security | Trust building |
| 5 | Use Cases (SaaS) | Target market alignment |
| 6 | Compliance (CIS) | SEO + credibility |
| 7 | About | Trust building |
| 8 | Contact | Lead capture |

---

## Technical Requirements

### Performance
- Page load < 3 seconds
- Mobile-first responsive design
- Core Web Vitals optimized

### SEO
- Meta titles/descriptions for all pages
- Open Graph tags
- Schema markup (Organization, Product, FAQ)
- Sitemap.xml
- robots.txt

### Analytics
- Google Analytics 4
- Conversion tracking (signup, demo request)
- Heatmaps (Hotjar/similar)

### Integration
- CTA buttons link to app signup
- Contact form to CRM/email
- Chat widget (future - Intercom/Crisp)

---

## Content Timeline

| Week | Deliverable |
|------|-------------|
| 1 | Homepage copy + mockup |
| 1 | Pricing page copy + mockup |
| 2 | Features page copy + mockup |
| 2 | Security page copy |
| 3 | Use case pages (2) |
| 3 | Compliance pages (1) |
| 4 | About, Contact pages |
| 4 | Final review + polish |

---

*Last updated: January 2026*
