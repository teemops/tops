# Security Page Content

## Meta Information

```
Title: Security - How Teemops Protects Your Data
Description: Learn how Teemops secures your data. Read-only AWS access, encryption, and security practices for our cloud security platform.
```

---

## Hero Section

### Headline
**Security Is Our Business**

### Subheadline
We protect the data you trust us with. Here's how.

---

## Our Security Commitment

### Headline
**We Practice What We Scan**

### Body
As a security company, we hold ourselves to the highest standards. We use the same security practices we recommend to our customers—and more.

---

## AWS Access Model

### Headline
**Read-Only Access. Always.**

### How It Works

**IAM Role-Based Access**
We never ask for AWS access keys. Instead, you deploy a CloudFormation template that creates a read-only IAM role in your account.

**Least Privilege Principle**
Our IAM role has only the permissions needed to read resource configurations. We cannot create, modify, or delete any resources in your AWS account.

**Temporary Credentials**
We use AWS STS to assume the role with temporary credentials that expire automatically. No long-lived credentials.

**External ID Protection**
Each AWS account connection uses a unique External ID, preventing confused deputy attacks.

### What We Can Access

| Access Type | Permitted | Example |
|-------------|-----------|---------|
| Read configurations | ✓ | List S3 bucket policies |
| Read metadata | ✓ | Describe EC2 instances |
| Read data content | ✗ | Cannot read objects in S3 |
| Modify resources | ✗ | Cannot change configurations |
| Create resources | ✗ | Cannot create new resources |
| Delete resources | ✗ | Cannot delete anything |

### IAM Policy

Our IAM role uses these AWS managed policies:
- `SecurityAudit` — Standard AWS security audit permissions
- `ViewOnlyAccess` — Read-only access to configurations

Full IAM policy available in our [CloudFormation template](link).

---

## Data Protection

### Headline
**Your Data, Protected**

### Encryption

**In Transit**
All data transmitted between your browser, our servers, and AWS uses TLS 1.3 encryption.

**At Rest**
All data stored in our databases is encrypted using AES-256 encryption.

**Secrets**
AWS role ARNs and sensitive configuration data are encrypted with additional application-level encryption.

### Data Handling

**What We Store:**
- AWS resource configurations (metadata only)
- Security findings and scan results
- Account settings and preferences

**What We Never Store:**
- AWS access keys or secrets
- Actual data from your S3 buckets, databases, etc.
- Customer/user PII from your applications

### Data Retention

| Data Type | Retention | Notes |
|-----------|-----------|-------|
| Scan results | Per your plan | 7 days (Free) to unlimited (Business) |
| Account data | While active | Deleted on account closure |
| Audit logs | 1 year | For security investigations |

### Data Location

All data is stored in AWS data centers in [Sydney, Australia / or your region]. We do not transfer data outside this region without explicit consent.

---

## Infrastructure Security

### Headline
**Our Infrastructure, Secured**

### Cloud Infrastructure

We run on AWS and apply the same security standards we recommend:

- **VPC isolation** — All services in private subnets
- **Security groups** — Strict firewall rules
- **Encryption** — All storage encrypted
- **Monitoring** — CloudTrail, CloudWatch, GuardDuty enabled
- **Updates** — Regular patching and updates

### Application Security

- **Authentication** — Firebase Authentication with MFA support
- **Authorization** — Role-based access control
- **Input validation** — All inputs sanitized
- **HTTPS only** — HSTS enforced
- **Dependencies** — Automated vulnerability scanning

---

## Compliance

### Headline
**Our Compliance Status**

### Current

| Standard | Status |
|----------|--------|
| SOC 2 Type II | In progress (expected Q2 2026) |
| ISO 27001 | Planned |
| GDPR | Compliant |
| Privacy Act 2020 (NZ) | Compliant |

### Security Practices

We follow industry-standard security practices:

- Regular penetration testing
- Vulnerability scanning
- Security code reviews
- Employee security training
- Incident response procedures
- Business continuity planning

---

## Responsible Disclosure

### Headline
**Found a Vulnerability?**

### Body
We appreciate security researchers who help us keep Teemops secure.

### How to Report

Email: **security@teemops.com**

Please include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Your contact information

### Our Commitment

- Acknowledge receipt within 24 hours
- Investigate and provide updates
- Fix confirmed vulnerabilities promptly
- Credit reporters (if desired)
- No legal action against good-faith researchers

### Scope

**In scope:**
- teemops.com
- app.teemops.com
- API endpoints

**Out of scope:**
- Physical attacks
- Social engineering
- Denial of service
- Third-party services

---

## Security FAQ

**Can Teemops access my data in S3?**
No. We can only see bucket configurations (policies, encryption settings). We cannot read, download, or access the objects/files stored in your buckets.

**Can Teemops make changes to my AWS account?**
No. Our IAM role is strictly read-only. We cannot create, modify, or delete any resources.

**What happens if Teemops is breached?**
Attackers would not gain access to your AWS accounts. We don't store credentials—only temporary role ARNs. You can revoke the IAM role at any time to immediately remove our access.

**Can I revoke access?**
Yes, instantly. Simply delete the CloudFormation stack in your AWS account, and our access is immediately revoked.

**Do you share data with third parties?**
No. We never sell or share your data. We only use trusted sub-processors for infrastructure (AWS) and analytics.

**How do you handle incidents?**
We have a documented incident response plan. In the event of a security incident affecting your data, we will notify you within 72 hours as required by applicable regulations.

---

## Contact Security Team

### Questions?

For security-related questions or concerns:

**Email:** security@teemops.com

For non-security inquiries, please use our [Contact page](/contact).
