# Organization & AWS Account Management Wireframes

## Organization Management

### Organization Selector (Top Navigation)
```
┌─────────────────────────────────────────────────────────────┐
│ [Logo] Cloud Security  [My Organization ▼] [🔔] [👤]       │
│                              │                               │
│                              ▼                               │
│                    ┌──────────────────┐                      │
│                    │ My Organization │ ← Current (highlighted)
│                    │ Client A        │                      │
│                    │ Client B        │                      │
│                    │ ─────────────── │                      │
│                    │ + Add Organization│                    │
│                    └──────────────────┘                      │
└─────────────────────────────────────────────────────────────┘
```

### Organizations List Page
```
┌─────────────────────────────────────────────────────────────┐
│ [Logo] Cloud Security  [Org ▼] [Notifications] [User Menu]  │
├──────────┬─────────────────────────────────────────────────┤
│          │  Organizations                    [+ Add Org]   │
│ Sidebar  ├─────────────────────────────────────────────────┤
│          │                                                 │
│ [Home]   │  ┌───────────────────────────────────────────┐ │
│          │  │ My Organization (Default)                │ │
│ [AWS     │  │ AWS Accounts: 3                          │ │
│ Accounts]│  │ [Edit] [Delete]                          │ │
│          │  └───────────────────────────────────────────┘ │
│ [Scans]  │                                                 │
│          │  ┌───────────────────────────────────────────┐ │
│ [Reports]│  │ Client A                                 │ │
│          │  │ AWS Accounts: 1                          │ │
│ [Insights│  │ [Edit] [Delete]                          │ │
│ & Analytics]│  └───────────────────────────────────────────┘ │
│          │                                                 │
│ [Settings]│  ┌───────────────────────────────────────────┐ │
│          │  │ Client B                                 │ │
│          │  │ AWS Accounts: 2                          │ │
│          │  │ [Edit] [Delete]                          │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
└──────────┴─────────────────────────────────────────────────┘
```

### Add Organization Dialog
```
┌─────────────────────────────────────────────────────────────┐
│                    Add New Organization                     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Organization Name                                          │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ Client C                                            │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  [Create Organization] [Cancel]                            │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## AWS Account Management (Updated Flow)

### AWS Accounts List (Organization-Scoped)
```
┌─────────────────────────────────────────────────────────────┐
│ [Logo] Cloud Security  [My Organization ▼] [🔔] [👤]      │
├──────────┬─────────────────────────────────────────────────┤
│          │  AWS Accounts                    [+ Add Account] │
│ Sidebar  ├─────────────────────────────────────────────────┤
│          │  Organization: My Organization                  │
│ [Home]   │                                                 │
│          │  ┌───────────────────────────────────────────┐ │
│ [AWS     │  │ Production AWS                            │ │
│ Accounts]│  │ Account ID: 123456789012                  │ │
│          │  │ Status: 🟢 Active | Last Scan: 2h ago    │ │
│ [Scans]  │  │ [View] [Scan] [Remove]                   │ │
│          │  └───────────────────────────────────────────┘ │
│ [Reports]│                                                 │
│          │  ┌───────────────────────────────────────────┐ │
│ [Insights│  │ Development AWS                           │ │
│ & Analytics]│  │ Account ID: 987654321098                  │ │
│          │  │ Status: 🟢 Active | Last Scan: 1d ago    │ │
│ [Settings]│  │ [View] [Scan] [Remove]                   │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
│          │  ┌───────────────────────────────────────────┐ │
│          │  │ Staging AWS                               │ │
│          │  │ Account ID: 555555555555                  │ │
│          │  │ Status: 🟡 Pending | Waiting for setup   │ │
│          │  │ [View] [Cancel]                          │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
└──────────┴─────────────────────────────────────────────────┘
```

### Add AWS Account - Step 1: CloudFormation Setup
```
┌─────────────────────────────────────────────────────────────┐
│ [Logo] Cloud Security  [My Organization ▼] [🔔] [👤]      │
├──────────┬─────────────────────────────────────────────────┤
│          │  Add AWS Account                                │
│ Sidebar  ├─────────────────────────────────────────────────┤
│          │  Organization: My Organization                  │
│          │                                                 │
│          │  Step 1 of 2: Create IAM Role in AWS          │
│          │                                                 │
│          │  ┌───────────────────────────────────────────┐ │
│          │  │ To securely access your AWS account, we  │ │
│          │  │ need to create an IAM role using         │ │
│          │  │ CloudFormation.                           │ │
│          │  │                                           │ │
│          │  │ Instructions:                             │ │
│          │  │ 1. Click "Open AWS Console" below        │ │
│          │  │ 2. Complete the CloudFormation stack     │ │
│          │  │ 3. The account will be added automatically│ │
│          │  │                                           │ │
│          │  │ [Open AWS Console]                        │ │
│          │  │                                           │ │
│          │  │ Having trouble?                           │ │
│          │  │ [Enter Details Manually]                  │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
│          │  Status: 🟡 Waiting for CloudFormation...     │
│          │                                                 │
│          │  [Cancel]                                      │
│          │                                                 │
└──────────┴─────────────────────────────────────────────────┘
```

### Add AWS Account - Step 2: Manual Entry (Fallback)
```
┌─────────────────────────────────────────────────────────────┐
│ [Logo] Cloud Security  [My Organization ▼] [🔔] [👤]      │
├──────────┬─────────────────────────────────────────────────┤
│          │  Add AWS Account                                │
│ Sidebar  ├─────────────────────────────────────────────────┤
│          │  Organization: My Organization                  │
│          │                                                 │
│          │  Step 2 of 2: Enter Account Details            │
│          │                                                 │
│          │  ┌───────────────────────────────────────────┐ │
│          │  │                                           │ │
│          │  │  AWS Account ID                           │ │
│          │  │  ┌─────────────────────────────────────┐ │ │
│          │  │  │ 123456789012                        │ │ │
│          │  │  └─────────────────────────────────────┘ │ │
│          │  │                                           │ │
│          │  │  IAM Role ARN (from CloudFormation)      │ │
│          │  │  ┌─────────────────────────────────────┐ │ │
│          │  │  │ arn:aws:iam::123456789012:role/... │ │ │
│          │  │  └─────────────────────────────────────┘ │ │
│          │  │                                           │ │
│          │  │  Account Name (optional)                  │ │
│          │  │  ┌─────────────────────────────────────┐ │ │
│          │  │  │ Production AWS                       │ │ │
│          │  │  └─────────────────────────────────────┘ │ │
│          │  │                                           │ │
│          │  │  [Add Account] [Back]                    │ │
│          │  └───────────────────────────────────────────┘ │
│          │                                                 │
└──────────┴─────────────────────────────────────────────────┘
```

### AWS Account Status Indicators

**Pending Status** (Waiting for CloudFormation):
```
┌───────────────────────────────────────────┐
│ Staging AWS                               │
│ Account ID: 555555555555                  │
│ Status: 🟡 Pending                        │
│ Waiting for CloudFormation completion...  │
│ [Refresh Status] [Cancel]                 │
└───────────────────────────────────────────┘
```

**Active Status** (Ready for Scanning):
```
┌───────────────────────────────────────────┐
│ Production AWS                            │
│ Account ID: 123456789012                  │
│ Status: 🟢 Active                         │
│ Last Scan: 2 hours ago                    │
│ [View] [Scan] [Remove]                    │
└───────────────────────────────────────────┘
```

**Error Status** (Setup Failed):
```
┌───────────────────────────────────────────┐
│ Failed AWS Account                       │
│ Account ID: 111111111111                  │
│ Status: 🔴 Error                          │
│ CloudFormation setup failed.             │
│ [Retry] [Enter Manually] [Remove]        │
└───────────────────────────────────────────┘
```

---

## Key UI Elements

### Organization Selector Dropdown
- Shows current organization name
- Lists all user's organizations
- "+ Add Organization" option at bottom
- Clicking organization switches context
- All page content updates to show organization-specific data

### AWS Account Status Badges
- 🟡 **Pending**: Waiting for CloudFormation/SNS notification
- 🟢 **Active**: Successfully connected, ready for scanning
- 🔴 **Error**: Setup failed, needs attention

### Organization Context Display
- Shows current organization name on relevant pages
- All AWS accounts filtered by current organization
- Scans and reports scoped to current organization
- Clear indication when switching organizations

---

## User Flow: Complete Onboarding

1. **User Signs Up** → Default organization created
2. **User Views Dashboard** → Sees empty state, "Add AWS Account" CTA
3. **User Clicks "Add AWS Account"** → Step 1: CloudFormation instructions
4. **User Opens AWS Console** → Completes CloudFormation stack
5. **SNS Notification Sent** → Backend receives notification
6. **Account Status Updates** → Frontend polls, shows "Active"
7. **User Can Now Scan** → AWS account ready for security scanning

### Alternative Flow (Manual Entry)
1. **User Clicks "Enter Manually"** → Step 2: Manual entry form
2. **User Enters Details** → AWS Account ID, IAM Role ARN, Name
3. **Backend Validates** → Checks role is assumable
4. **Account Created** → Status set to "Active"

