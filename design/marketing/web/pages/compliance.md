# Compliance Pages Content

---

## CIS AWS Benchmarks (/compliance/cis-benchmarks)

### Meta Information
```
Title: CIS AWS Foundations Benchmark Scanning | Teemops
Description: Automated CIS AWS Foundations Benchmark v5.0 compliance scanning. Check all 40+ controls and get audit-ready reports.
```

### Hero Section

**Headline:** CIS AWS Foundations Benchmark Compliance

**Subheadline:** Automated scanning against all CIS controls. Know your compliance score in minutes.

**CTA:** Start Free Scan

---

### What is CIS AWS Foundations Benchmark?

The CIS (Center for Internet Security) AWS Foundations Benchmark is the industry standard for securing AWS accounts. It provides prescriptive guidance for configuring security options across AWS services.

**Current Version:** v5.0.0 (2024)

**Why It Matters:**
- Required by many compliance frameworks
- Expected by enterprise customers
- Best practice baseline for AWS security
- Commonly referenced in audits

---

### CIS Sections We Cover

| Section | Controls | Description |
|---------|----------|-------------|
| 1. IAM | 22 controls | Identity and access management |
| 2. Storage | 8 controls | S3 bucket security |
| 3. Logging | 6 controls | CloudTrail and logging |
| 4. Monitoring | 15 controls | CloudWatch alarms |
| 5. Networking | 6 controls | VPC and network security |

**Total: 40+ automated checks**

---

### Key CIS Controls We Check

**Identity & Access Management (Section 1)**
- 1.4: Root account MFA enabled
- 1.5: Root account has no access keys
- 1.8: IAM password policy requirements
- 1.12: No credentials unused for 45+ days
- 1.16: IAM policies attached to groups, not users

**Storage (Section 2)**
- 2.1.1: S3 Block Public Access enabled
- 2.1.2: S3 buckets encrypted
- 2.1.4: S3 buckets have lifecycle policies

**Logging (Section 3)**
- 3.1: CloudTrail enabled in all regions
- 3.2: CloudTrail log file validation enabled
- 3.4: CloudTrail logs encrypted

**Networking (Section 5)**
- 5.1: No unrestricted SSH access
- 5.2: No unrestricted RDP access
- 5.3: VPC flow logging enabled

---

### CIS Compliance Report

Generate a comprehensive CIS Benchmark report including:

- **Overall compliance score** — Percentage of controls passing
- **Section-by-section breakdown** — Score per CIS section
- **Individual control status** — Pass/Fail for each control
- **Evidence** — Configuration details for auditors
- **Remediation guidance** — How to fix failing controls

**Export formats:** PDF, CSV, JSON

---

### CTA Section

**Headline:** Check Your CIS Compliance Now

**Body:** See how your AWS account scores against CIS Benchmarks. Takes 5 minutes.

**CTA:** Start Free Scan

---
---

## PCI-DSS Compliance (/compliance/pci-dss)

### Meta Information
```
Title: PCI-DSS AWS Compliance Scanning | Teemops
Description: Automated PCI-DSS compliance checks for AWS. Verify encryption, access controls, and logging requirements.
```

### Hero Section

**Headline:** PCI-DSS Compliance for AWS

**Subheadline:** Handle payment data on AWS? Verify your PCI-DSS controls are in place.

**CTA:** Start Free Scan

---

### What is PCI-DSS?

The Payment Card Industry Data Security Standard (PCI-DSS) is required for any organization that handles credit card data. Version 4.0 is the current standard.

**Who Needs PCI Compliance:**
- Payment processors
- E-commerce platforms
- SaaS handling payment info
- Anyone storing/transmitting card data

---

### PCI-DSS Requirements We Check

**Requirement 1: Network Security**
- Security group configurations
- Network segmentation
- Firewall rules

**Requirement 3: Protect Stored Data**
- S3 encryption at rest
- RDS encryption
- EBS volume encryption
- Key management

**Requirement 7: Restrict Access**
- IAM policies and permissions
- Least privilege verification
- Access review

**Requirement 8: Identify Users**
- MFA enforcement
- Password policies
- Credential management

**Requirement 10: Track Access**
- CloudTrail logging
- Access logging enabled
- Log retention

**Requirement 11: Test Security**
- Vulnerability identification
- Configuration review

---

### PCI Compliance Report

Our PCI-DSS report maps findings directly to PCI requirements:

| Requirement | Status | Findings | Details |
|-------------|--------|----------|---------|
| Req. 1 | ⚠️ Partial | 3 | SG allows 0.0.0.0/0 |
| Req. 3 | ✅ Pass | 0 | All storage encrypted |
| Req. 7 | ❌ Fail | 5 | IAM policies too broad |
| ... | ... | ... | ... |

---

### CTA Section

**Headline:** Verify Your PCI Controls

**Body:** Don't wait for your QSA to find issues. Find them first.

**CTA:** Start Free Scan

---
---

## SOC 2 Compliance (/compliance/soc2)

### Meta Information
```
Title: SOC 2 AWS Compliance Monitoring | Teemops
Description: Continuous SOC 2 compliance monitoring for AWS. Map security findings to Trust Service Criteria.
```

### Hero Section

**Headline:** SOC 2 Compliance Made Easier

**Subheadline:** Map your AWS security controls to SOC 2 Trust Service Criteria automatically.

**CTA:** Start Free Scan

---

### What is SOC 2?

SOC 2 (Service Organization Control 2) is a compliance framework for service providers storing customer data. It's based on five Trust Service Criteria:

1. **Security** — Protection against unauthorized access
2. **Availability** — System availability for operation
3. **Processing Integrity** — System processing is complete and accurate
4. **Confidentiality** — Data designated as confidential is protected
5. **Privacy** — Personal information is collected and used appropriately

**Who Needs SOC 2:**
- SaaS companies
- Cloud service providers
- Data processors
- Any company with enterprise customers

---

### SOC 2 Controls We Check

**Security (CC6)**
- Access control configuration
- Network security
- Encryption controls
- Authentication settings

**Availability (A1)**
- Multi-AZ deployments
- Backup configurations
- Disaster recovery readiness

**Confidentiality (C1)**
- Data encryption
- Access restrictions
- Data classification controls

**Change Management (CC8)**
- Audit logging
- Configuration tracking
- Access to production systems

---

### SOC 2 Evidence Collection

Our reports help you gather evidence for SOC 2 audits:

- **Point-in-time snapshots** — Show control status at audit time
- **Historical trends** — Demonstrate continuous compliance
- **Configuration evidence** — Actual AWS settings
- **Remediation history** — Show issues found and fixed

---

### CTA Section

**Headline:** Prepare for Your SOC 2 Audit

**Body:** Know your compliance gaps before your auditor does.

**CTA:** Start Free Scan
