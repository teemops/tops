# Application Wireframes

## Design System Notes
- **UI Library**: PrimeVue (Material Design)
- **Layout**: Responsive, desktop-first with mobile support
- **Color Scheme**: Material Design palette
- **Navigation**: Sidebar navigation for main app, top bar for auth pages

---

## 1. Login Flow

### 1.1 Login Page
```
┌─────────────────────────────────────────────────────────┐
│                                                          │
│                    [Logo/App Name]                      │
│                                                          │
│              ┌──────────────────────────┐               │
│              │                          │               │
│              │   Sign In to Your       │               │
│              │   Account               │               │
│              │                          │               │
│              │  ┌────────────────────┐ │               │
│              │  │ Email Address      │ │               │
│              │  └────────────────────┘ │               │
│              │                          │               │
│              │  ┌────────────────────┐ │               │
│              │  │ Password           │ │               │
│              │  └────────────────────┘ │               │
│              │                          │               │
│              │  [ ] Remember me         │               │
│              │                          │               │
│              │  [Sign In Button]       │               │
│              │                          │               │
│              │  ─────────── OR ─────── │               │
│              │                          │               │
│              │  [Google] [Microsoft]    │               │
│              │  [Apple]                 │               │
│              │                          │               │
│              │  Forgot Password?        │               │
│              │                          │               │
│              │  Don't have an account?  │               │
│              │  [Sign Up]               │               │
│              └──────────────────────────┘               │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

**Key Elements:**
- Centered login card
- Email/password fields
- OAuth buttons (Google, Microsoft, Apple)
- Links to registration and password reset
- Remember me checkbox

---

## 2. Registration Flow

### 2.1 Registration Page
```
┌─────────────────────────────────────────────────────────┐
│                                                          │
│                    [Logo/App Name]                      │
│                                                          │
│              ┌──────────────────────────┐               │
│              │                          │               │
│              │   Create Your Account    │               │
│              │                          │               │
│              │  ┌────────────────────┐ │               │
│              │  │ Full Name         │ │               │
│              │  └────────────────────┘ │               │
│              │                          │               │
│              │  ┌────────────────────┐ │               │
│              │  │ Email Address     │ │               │
│              │  └────────────────────┘ │               │
│              │                          │               │
│              │  ┌────────────────────┐ │               │
│              │  │ Password          │ │               │
│              │  └────────────────────┘ │               │
│              │                          │               │
│              │  ┌────────────────────┐ │               │
│              │  │ Confirm Password  │ │               │
│              │  └────────────────────┘ │               │
│              │                          │               │
│              │  [ ] I agree to Terms   │               │
│              │     and Privacy Policy   │               │
│              │                          │               │
│              │  [Create Account]        │               │
│              │                          │               │
│              │  ─────────── OR ─────── │               │
│              │                          │               │
│              │  [Google] [Microsoft]    │               │
│              │  [Apple]                 │               │
│              │                          │               │
│              │  Already have an account?│              │
│              │  [Sign In]               │               │
│              └──────────────────────────┘               │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

**Key Elements:**
- Full name, email, password fields
- Password confirmation
- Terms and conditions checkbox
- OAuth options
- Link to login

### 2.2 Organization Setup (Post-Registration)
```
┌─────────────────────────────────────────────────────────┐
│  [Logo]                                    [User Menu]  │
├─────────────────────────────────────────────────────────┤
│                                                          │
│         Welcome! Let's set up your organization        │
│                                                          │
│         ┌──────────────────────────────┐                │
│         │                              │                │
│         │  Organization Name           │                │
│         │  ┌────────────────────────┐ │                │
│         │  │ My Organization        │ │                │
│         │  └────────────────────────┘ │                │
│         │                              │                │
│         │  [Continue]                  │                │
│         │                              │                │
│         │  Skip for now                │                │
│         └──────────────────────────────┘                │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## 3. Main Application Layout

### 3.1 Dashboard (Default View)
```
┌─────────────────────────────────────────────────────────────┐
│ [Logo] Cloud Security  [Org ▼] [Notifications] [User Menu] │
├──────────┬─────────────────────────────────────────────────┤
│          │  Dashboard                                      │
│          ├─────────────────────────────────────────────────┤
│ Sidebar  │  ┌────────────┐ ┌────────────┐ ┌────────────┐ │
│          │  │ Total      │ │ Active     │ │ Critical   │ │
│ [Home]   │  │ Accounts   │ │ Scans      │ │ Findings   │ │
│          │  │    5       │ │    3       │ │    12      │ │
│ [AWS     │  └────────────┘ └────────────┘ └────────────┘ │
│ Accounts]│                                                 │
│          │  Recent Scans                                   │
│ [Scans]  │  ┌───────────────────────────────────────────┐ │
│          │  │ Account: prod-aws | Status: Complete     │ │
│ [Reports]│  │ Findings: 5 Critical, 12 High           │ │
│          │  │ [View Details]                          │ │
│ [Insights│  ├───────────────────────────────────────────┤ │
│ & Analytics]│ Account: dev-aws | Status: Running        │ │
│          │  │ Findings: 0 Critical, 3 High           │ │
│ [Settings]│  │ [View Details]                          │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
│          │  Quick Actions                                 │
│          │  [New Scan] [Add AWS Account]                 │
│          └─────────────────────────────────────────────────┤
└──────────┴─────────────────────────────────────────────────┘
```

**Key Elements:**
- Sidebar navigation
- Top bar with logo, notifications, user menu
- Dashboard cards with key metrics
- Recent scans list
- Quick action buttons

---

## 4. Adding AWS Account

### 4.1 AWS Accounts List Page
```
┌─────────────────────────────────────────────────────────────┐
│ [Logo] Cloud Security          [Notifications] [User Menu]  │
├──────────┬─────────────────────────────────────────────────┤
│          │  AWS Accounts                    [+ Add Account]│
│ Sidebar  ├─────────────────────────────────────────────────┤
│          │                                                 │
│ [Home]   │  ┌───────────────────────────────────────────┐ │
│          │  │ Account: prod-aws                          │ │
│ [AWS     │  │ Account ID: 123456789012                   │ │
│ Accounts]│  │ Status: Active | Last Scan: 2 hours ago   │ │
│          │  │ [View] [Scan] [Remove]                    │ │
│ [Scans]  │  └───────────────────────────────────────────┘ │
│          │                                                 │
│ [Reports]│  ┌───────────────────────────────────────────┐ │
│          │  │ Account: dev-aws                           │ │
│ [Insights│  │ Account ID: 987654321098                   │ │
│ & Analytics]│ Status: Active | Last Scan: 1 day ago     │ │
│          │  │ [View] [Scan] [Remove]                    │ │
│ [Settings]│  └───────────────────────────────────────────┘ │
│          │                                                 │
│          │  [Empty State if no accounts]                  │
│          │                                                 │
└──────────┴─────────────────────────────────────────────────┘
```

### 4.2 Add AWS Account - Step 1: Initiate
```
┌─────────────────────────────────────────────────────────────┐
│ [Logo] Cloud Security          [Notifications] [User Menu]  │
├──────────┬─────────────────────────────────────────────────┤
│          │  Add AWS Account                                │
│ Sidebar  ├─────────────────────────────────────────────────┤
│          │                                                 │
│          │  Step 1 of 2: Create IAM Role                  │
│          │                                                 │
│          │  ┌───────────────────────────────────────────┐ │
│          │  │ To securely access your AWS account, we  │ │
│          │  │ need to create an IAM role using          │ │
│          │  │ CloudFormation.                           │ │
│          │  │                                           │ │
│          │  │ 1. Click the button below                 │ │
│          │  │ 2. Complete the CloudFormation stack      │ │
│          │  │ 3. Return here with the Role ARN          │ │
│          │  │                                           │ │
│          │  │ [Open AWS Console]                        │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
│          │  [Cancel]                                      │
│          └─────────────────────────────────────────────────┤
└──────────┴─────────────────────────────────────────────────┘
```

### 4.3 Add AWS Account - Step 2: Complete Setup
```
┌─────────────────────────────────────────────────────────────┐
│ [Logo] Cloud Security          [Notifications] [User Menu]  │
├──────────┬─────────────────────────────────────────────────┤
│          │  Add AWS Account                                │
│ Sidebar  ├─────────────────────────────────────────────────┤
│          │                                                 │
│          │  Step 2 of 2: Enter Account Details             │
│          │                                                 │
│          │  ┌───────────────────────────────────────────┐ │
│          │  │                                           │ │
│          │  │  AWS Account ID                           │ │
│          │  │  ┌─────────────────────────────────────┐ │ │
│          │  │  │ 123456789012                       │ │ │
│          │  │  └─────────────────────────────────────┘ │ │
│          │  │                                           │ │
│          │  │  IAM Role ARN                            │ │
│          │  │  ┌─────────────────────────────────────┐ │ │
│          │  │  │ arn:aws:iam::123456789012:role/... │ │ │
│          │  │  └─────────────────────────────────────┘ │ │
│          │  │                                           │ │
│          │  │  Account Name (optional)                  │ │
│          │  │  ┌─────────────────────────────────────┐ │ │
│          │  │  │ Production AWS                     │ │ │
│          │  │  └─────────────────────────────────────┘ │ │
│          │  │                                           │ │
│          │  │  [Add Account] [Back]                    │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
│          └─────────────────────────────────────────────────┤
└──────────┴─────────────────────────────────────────────────┘
```

---

## 5. Scanning

### 5.1 Scans List Page
```
┌─────────────────────────────────────────────────────────────┐
│ [Logo] Cloud Security          [Notifications] [User Menu]  │
├──────────┬─────────────────────────────────────────────────┤
│          │  Scans                            [+ New Scan]  │
│ Sidebar  ├─────────────────────────────────────────────────┤
│          │                                                 │
│ [Home]   │  Filters: [All Accounts ▼] [All Status ▼]     │
│          │                                                 │
│ [AWS     │  ┌───────────────────────────────────────────┐ │
│ Accounts]│  │ Scan #1234 | prod-aws                     │ │
│          │  │ Started: 2 hours ago | Status: Complete    │ │
│ [Scans]  │  │ Findings: 5 Critical, 12 High, 8 Medium   │ │
│          │  │ [View Report] [Download]                  │ │
│ [Reports]│  └───────────────────────────────────────────┘ │
│          │                                                 │
│ [Insights│  ┌───────────────────────────────────────────┐ │
│ & Analytics]│ Scan #1233 | dev-aws                     │ │
│          │  │ Started: 1 day ago | Status: Complete    │ │
│ [Settings]│  │ Findings: 0 Critical, 3 High, 5 Medium  │ │
│          │  │ [View Report] [Download]                  │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
│          │  ┌───────────────────────────────────────────┐ │
│          │  │ Scan #1232 | prod-aws                     │ │
│          │  │ Started: 5 minutes ago | Status: Running  │ │
│          │  │ Progress: 65% [████████████░░░░░░░░]       │ │
│          │  │ [View Progress]                            │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
└──────────┴─────────────────────────────────────────────────┘
```

### 5.2 New Scan Dialog/Page
```
┌─────────────────────────────────────────────────────────────┐
│ [Logo] Cloud Security          [Notifications] [User Menu]  │
├──────────┬─────────────────────────────────────────────────┤
│          │  New Scan                                       │
│ Sidebar  ├─────────────────────────────────────────────────┤
│          │                                                 │
│          │  ┌───────────────────────────────────────────┐ │
│          │  │                                           │ │
│          │  │  Select AWS Account                       │ │
│          │  │  ┌─────────────────────────────────────┐ │ │
│          │  │  │ [Select Account ▼]                 │ │ │
│          │  │  │ • prod-aws (123456789012)          │ │ │
│          │  │  │ • dev-aws (987654321098)           │ │ │
│          │  │  └─────────────────────────────────────┘ │ │
│          │  │                                           │ │
│          │  │  Scan Type                               │ │
│          │  │  ( ) Full Scan                           │ │
│          │  │  ( ) Quick Scan                          │ │
│          │  │  (•) Custom                             │ │
│          │  │                                           │ │
│          │  │  Services to Scan                        │ │
│          │  │  [✓] S3                                  │ │
│          │  │  [✓] IAM                                 │ │
│          │  │  [✓] EC2                                 │ │
│          │  │  [✓] RDS                                 │ │
│          │  │  [ ] CloudTrail                         │ │
│          │  │  [ ] VPC                                 │ │
│          │  │                                           │ │
│          │  │  [Start Scan] [Cancel]                   │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
│          └─────────────────────────────────────────────────┤
└──────────┴─────────────────────────────────────────────────┘
```

### 5.3 Scan Progress/Details Page
```
┌─────────────────────────────────────────────────────────────┐
│ [Logo] Cloud Security          [Notifications] [User Menu]  │
├──────────┬─────────────────────────────────────────────────┤
│          │  Scan #1234 - prod-aws                          │
│ Sidebar  ├─────────────────────────────────────────────────┤
│          │                                                 │
│          │  Status: Running                                │
│          │  Progress: 65% [████████████░░░░░░░░]          │
│          │  Started: 5 minutes ago                          │
│          │                                                 │
│          │  ┌───────────────────────────────────────────┐ │
│          │  │ Scan Progress                             │ │
│          │  │                                           │ │
│          │  │ [✓] S3 Scan - Complete                    │ │
│          │  │ [✓] IAM Scan - Complete                   │ │
│          │  │ [⟳] EC2 Scan - In Progress (65%)         │ │
│          │  │ [ ] RDS Scan - Pending                    │ │
│          │  │                                           │ │
│          │  │ Current: Checking security groups...      │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
│          │  Preliminary Findings                          │
│          │  ┌───────────────────────────────────────────┐ │
│          │  │ Critical: 2  High: 5  Medium: 3          │ │
│          │  │                                           │ │
│          │  │ [View Findings]                          │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
│          │  [Cancel Scan]                                 │
│          │                                                 │
└──────────┴─────────────────────────────────────────────────┘
```

---

## 6. Reports and Insights

### 6.1 Reports List Page
```
┌─────────────────────────────────────────────────────────────┐
│ [Logo] Cloud Security          [Notifications] [User Menu]  │
├──────────┬───────────────────────────────────────────────────────┤
│            │  Reports & Insights                            │
│ Sidebar    ├─────────────────────────────────────────────────┤
│            │                                                 │
│ [Home]     │  ┌───────────────────────────────────────────┐ │
│            │  │ Report: prod-aws - Scan #1234           │ │
│ [AWS       │  │ Generated: 2 hours ago                    │ │
│ Accounts]  │  │ Findings: 5 Critical, 12 High, 8 Medium  │ │
│            │  │ [View] [Download PDF] [Download CSV]     │ │
│ [Scans]    │  └───────────────────────────────────────────┘ │
│            │                                                 │
│ [Reports]  │  ┌───────────────────────────────────────────┐ │
│            │  │ Report: dev-aws - Scan #1233              │ │
│ [Insights  │  │ Generated: 1 day ago                      │ │
│ & Analytics]│ Findings: 0 Critical, 3 High, 5 Medium    │ │
│            │  │ [View] [Download PDF] [Download CSV]      │ │
│ [Settings] │  └───────────────────────────────────────────┘ │
│            │                                                 │
│            │  [Generate New Report]                         │
│            │                                                 │
└────────────┴─────────────────────────────────────────────────┘
```

### 6.2 Report Detail/View Page
```
┌─────────────────────────────────────────────────────────────┐
│ [Logo] Cloud Security          [Notifications] [User Menu]  │
├──────────┬─────────────────────────────────────────────────┤
│          │  Scan Report #1234 - prod-aws                  │
│ Sidebar  │  [Download PDF] [Download CSV] [Share]          │
│          ├─────────────────────────────────────────────────┤
│          │                                                 │
│          │  ┌───────────────────────────────────────────┐ │
│          │  │ Executive Summary                         │ │
│          │  │                                           │ │
│          │  │ Total Findings: 25                        │ │
│          │  │ Critical: 5  High: 12  Medium: 8         │ │
│          │  │                                           │ │
│          │  │ Compliance Score: 72%                     │ │
│          │ │ │ │ │ │ │ │ │ │ │ │ │ │ │ │ │ │ │ │ │ │ │ │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
│          │  Findings by Service                           │
│          │  ┌───────────────────────────────────────────┐ │
│          │  │ S3: 8 findings (3 Critical)               │ │
│          │  │ IAM: 10 findings (2 Critical)            │ │
│          │  │ EC2: 5 findings (0 Critical)             │ │
│          │  │ RDS: 2 findings (0 Critical)             │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
│          │  Critical Findings                             │
│          │  ┌───────────────────────────────────────────┐ │
│          │  │ [1] S3 Bucket Public Access               │ │
│          │  │    Bucket: my-public-bucket              │ │
│          │  │    Severity: Critical                     │ │
│          │  │    [View Details] [Remediate]           │ │
│          │  ├───────────────────────────────────────────┤ │
│          │  │ [2] IAM Policy Allows * Actions          │ │
│          │  │    Policy: AdminPolicy                    │ │
│          │  │    Severity: Critical                     │ │
│          │  │    [View Details] [Remediate]           │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
│          │  [View All Findings] [Filter by Severity]     │
│          │                                                 │
└──────────┴─────────────────────────────────────────────────┘
```

### 6.3 Insights & Analytics Dashboard
```
┌─────────────────────────────────────────────────────────────┐
│ [Logo] Cloud Security          [Notifications] [User Menu]  │
├──────────┬─────────────────────────────────────────────────┤
│          │  Insights & Analytics                           │
│ Sidebar  ├─────────────────────────────────────────────────┤
│          │                                                 │
│          │  Time Range: [Last 30 Days ▼]                  │
│          │                                                 │
│          │  ┌────────────┐ ┌────────────┐ ┌────────────┐ │
│          │  │ Total      │ │ Avg        │ │ Compliance │ │
│          │  │ Findings   │ │ Scan Time  │ │ Score      │ │
│          │  │    127     │ │  12 min    │ │    78%     │ │
│          │  └────────────┘ └────────────┘ └────────────┘ │
│          │                                                 │
│          │  Findings Trend                                │
│          │  ┌───────────────────────────────────────────┐ │
│          │  │     Chart showing findings over time      │ │
│          │  │     (Line/Bar chart)                      │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
│          │  Findings by Service                           │
│          │  ┌───────────────────────────────────────────┐ │
│          │  │     Pie/Donut chart                        │ │
│          │  │     S3: 35%  IAM: 40%  EC2: 15%  RDS: 10% │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
│          │  Top Risk Areas                                │
│          │  ┌───────────────────────────────────────────┐ │
│          │  │ 1. IAM Policy Misconfigurations           │ │
│          │  │ 2. S3 Public Access                       │ │
│          │  │ 3. Unencrypted RDS Instances             │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
│          │  Compliance Status                             │
│          │  ┌───────────────────────────────────────────┐ │
│          │  │ CIS Benchmarks: 78%                        │ │
│          │  │ SOC 2: 82%                                │ │
│          │  │ PCI-DSS: 65%                              │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
└──────────┴─────────────────────────────────────────────────┘
```

---

## Navigation Structure

### Main Navigation (Sidebar)
- **Home/Dashboard** - Overview and quick actions
- **AWS Accounts** - Manage connected AWS accounts
- **Scans** - View and manage security scans
- **Reports** - View detailed reports
- **Insights & Analytics** - Analytics dashboard
- **Settings** - User and organization settings

### Top Bar (All Pages)
- Logo/App Name
- Organization Selector (dropdown with current org and list of all orgs)
- Notifications icon
- User menu (Profile, Settings, Logout)

---

## Responsive Considerations

### Mobile Layout
- Sidebar collapses to hamburger menu
- Cards stack vertically
- Tables become scrollable or card-based
- Forms use full width
- Bottom navigation bar for mobile

### Tablet Layout
- Sidebar can be collapsible
- Two-column layouts where appropriate
- Maintains desktop functionality with touch-friendly targets

