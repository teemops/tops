# Features Page Content

## Meta Information

```
Title: Features - AWS Security Scanning & Compliance | Teemops
Description: Comprehensive AWS security scanning with CIS, PCI-DSS, SOC 2 compliance. Scan S3, IAM, EC2, RDS and more. Actionable remediation for every finding.
Keywords: AWS security features, cloud security scanning, CIS benchmarks, AWS compliance, S3 security, IAM security
```

---

## Hero Section

### Headline
**Complete AWS Security Visibility**

### Subheadline
Scan your entire AWS environment. Find misconfigurations. Fix them fast.

### CTA
**Start Free Scan →**

---

## Feature Sections

### 1. AWS Security Scanning

#### Headline
**Comprehensive AWS Coverage**

#### Body
Teemops scans your most critical AWS services for security misconfigurations, vulnerabilities, and compliance violations.

#### Services Covered

**S3 - Object Storage**
- Public bucket detection
- Encryption at rest verification
- Bucket policy analysis
- Access logging status
- Versioning configuration

**IAM - Identity & Access**
- Root account security
- MFA enforcement
- Password policy compliance
- Access key rotation
- Overly permissive policies
- Unused credentials detection

**EC2 & VPC - Compute & Network**
- Security group analysis
- Public IP exposure
- IMDSv2 enforcement
- VPC flow logs
- Network ACL review

**RDS - Databases**
- Public accessibility
- Encryption status
- Backup configuration
- Multi-AZ deployment
- Security group rules

**Coming Soon**
- CloudTrail audit logging
- Lambda serverless security
- KMS key management
- Secrets Manager
- EKS/ECS containers

---

### 2. Compliance Monitoring

#### Headline
**Stay Compliant, Automatically**

#### Body
Continuous compliance monitoring against industry standards. Know your compliance score at all times—not just before audits.

#### Compliance Frameworks

**CIS AWS Foundations Benchmark**
- 40+ controls across all CIS sections
- Automated scoring
- Detailed remediation steps
- Export-ready reports for auditors

**PCI-DSS**
- Payment card industry requirements
- Network security controls
- Access management checks
- Encryption verification

**SOC 2**
- Security controls mapping
- Availability requirements
- Confidentiality checks
- Privacy controls

**Coming Soon**
- ISO 27001
- HIPAA
- NZISM (NZ Information Security Manual)
- Custom frameworks

#### Compliance Dashboard
Real-time compliance percentage across all frameworks. Track improvement over time.

---

### 3. Multi-Account Management

#### Headline
**One Dashboard for All Your AWS Accounts**

#### Body
Whether you have 2 accounts or 200, see your security posture across your entire AWS footprint in a single view.

#### Capabilities

- **Centralized Dashboard** — Aggregate findings from all accounts
- **Per-Account Views** — Drill down into individual accounts
- **Organization Grouping** — Group accounts by team, environment, or client
- **Cross-Account Trends** — Compare security posture across accounts
- **Bulk Operations** — Run scans across multiple accounts at once

#### Use Cases
- **Agencies** — Manage client AWS accounts separately
- **Multi-Product Companies** — Isolate production, staging, dev
- **Enterprises** — Central visibility across business units

---

### 4. Actionable Remediation

#### Headline
**Don't Just Find Problems—Fix Them**

#### Body
Every finding includes clear, actionable remediation guidance. No security expertise required.

#### What You Get

**Plain English Explanations**
Understand why each finding matters and what the actual risk is—not just compliance jargon.

**Step-by-Step Instructions**
Detailed remediation steps anyone on your team can follow.

**Copy-Paste Commands**
AWS CLI commands ready to execute. Fix issues in seconds.

**CloudFormation Templates**
Infrastructure-as-code snippets for automated remediation.

**Documentation Links**
Direct links to relevant AWS documentation for deeper context.

---

### 5. Risk Prioritization

#### Headline
**Focus on What Actually Matters**

#### Body
Not all findings are equal. We prioritize by real-world risk so you fix critical issues first.

#### Severity Levels

**Critical** (Red)
Actively exploitable. Immediate action required.
*Example: S3 bucket publicly writable*

**High** (Orange)
Significant risk. Fix within days.
*Example: Root account without MFA*

**Medium** (Yellow)
Moderate risk. Plan remediation.
*Example: Access keys not rotated in 90+ days*

**Low** (Blue)
Minor risk or best practice.
*Example: CloudTrail not enabled in unused region*

**Info** (Gray)
Informational. No action required.
*Example: Resource inventory data*

#### Smart Prioritization
We consider:
- Exploitability (how easy to attack)
- Impact (what damage could occur)
- Exposure (public vs internal)
- Compliance requirements

---

### 6. Alerts & Notifications

#### Headline
**Know Immediately When Issues Arise**

#### Body
Get notified the moment critical security issues are detected. Never miss a misconfiguration.

#### Channels

**Email**
Instant email alerts for critical and high severity findings.

**Slack**
Direct to your team's Slack channel. Configurable by severity.

**Webhooks**
Integrate with any system—PagerDuty, Jira, custom workflows.

#### Alert Configuration
- Set severity thresholds
- Configure quiet hours
- Route alerts by AWS account
- Aggregate or individual notifications

---

### 7. Reporting & Exports

#### Headline
**Reports Your Auditors Will Love**

#### Body
Generate professional reports for compliance audits, board meetings, or internal reviews.

#### Report Types

**Executive Summary**
High-level security posture overview. Perfect for leadership.

**Compliance Reports**
Framework-specific reports (CIS, PCI-DSS, SOC 2) with pass/fail status.

**Technical Detail**
Full finding details for engineering teams.

**Trend Reports**
Security posture over time. Show improvement.

#### Export Formats
- PDF (formatted reports)
- CSV (raw data)
- JSON (API integration)

---

### 8. Easy Setup

#### Headline
**Secure in Minutes, Not Months**

#### Body
No agents to install. No complex configuration. Connect your AWS account in under 5 minutes.

#### How It Works

**Step 1: Create Account**
Sign up with email or Google/GitHub OAuth.

**Step 2: Connect AWS**
One-click CloudFormation template creates a read-only IAM role.

**Step 3: Scan**
We automatically scan your environment and show results.

#### Security
- Read-only access only
- No credentials stored
- IAM role with least privilege
- Audit trail of all access

---

## Feature Comparison

### Headline
**Teemops vs. The Alternatives**

| Capability | Teemops | AWS Security Hub | DIY Scripts | Enterprise CSPM |
|------------|---------|------------------|-------------|-----------------|
| Setup Time | 5 min | 30 min | Days/Weeks | Weeks |
| Pricing | From $0 | Pay-per-check | Free (your time) | $$$$ |
| Remediation Guidance | ✓ Detailed | Basic | None | Varies |
| Multi-Account | ✓ | Limited | Manual | ✓ |
| Compliance Reports | ✓ | Basic | None | ✓ |
| SMB Friendly | ✓ | Somewhat | No | No |

---

## Integration Section

### Headline
**Works With Your Stack**

### Integrations

- **Slack** — Alerts to your team channels
- **Email** — SMTP or native
- **Webhooks** — Custom integrations
- **API** — Build your own workflows

### Coming Soon
- Jira integration
- PagerDuty
- Microsoft Teams
- Terraform provider

---

## CTA Section

### Headline
**See All Your AWS Security Gaps**

### Subheadline
Start your first scan in under 5 minutes. Free tier available.

### CTA Buttons
- **Primary:** Start Free Scan
- **Secondary:** Book a Demo
