# AWS Account Onboarding Flow - Complete Specification

## Overview

This document describes the complete flow for adding an AWS account to the Cloud Security platform, including organization management and the automated CloudFormation integration.

## Prerequisites

- User has signed up and has at least one organization (default organization created on signup)
- User is authenticated via Firebase
- User has selected an organization context

## Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    USER INITIATES FLOW                         │
│  User clicks "Add AWS Account" button on AWS Accounts page    │
└───────────────────────┬───────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                    FRONTEND (Nuxt 3)                           │
│  1. Gets current organization.orgId from Pinia store          │
│  2. Calls POST /organizations/{orgId}/aws-accounts/init       │
└───────────────────────┬───────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│              BACKEND LAMBDA (Init Handler)                      │
│  1. Validates user authentication                               │
│  2. Validates organization ownership                            │
│  3. Generates UniqueId (UUID v4)                               │
│  4. Generates ExternalId (UUID v4)                              │
│  5. Creates pending record in database:                        │
│     - organizationId: orgId from request                        │
│     - uniqueId: generated UUID                                  │
│     - externalId: generated UUID                                │
│     - status: 'pending'                                         │
│  6. Constructs CloudFormation URL:                             │
│     https://console.aws.amazon.com/cloudformation/...          │
│     ?templateUrl={S3_URL}                                      │
│     &param_ParentAWSAccountId={PARENT_ID}                      │
│     &param_ExternalId={EXTERNAL_ID}                            │
│     &param_UniqueId={UNIQUE_ID}                                │
│  7. Returns URL and account record ID                          │
└───────────────────────┬───────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                    FRONTEND (Nuxt 3)                           │
│  1. Receives CloudFormation URL                                │
│  2. Opens URL in new window/tab                                │
│  3. Shows "Waiting for CloudFormation" status                  │
│  4. Starts polling GET /aws-accounts/{accountId}                │
└───────────────────────┬───────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│              AWS CONSOLE (Customer Account)                      │
│  User completes CloudFormation stack creation:                 │
│  1. Stack creates IAM Role (TeemOps)                           │
│  2. Stack creates CustomNotifier resource                      │
│  3. CustomNotifier sends SNS message to parent account         │
└───────────────────────┬───────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│          PARENT ACCOUNT SNS TOPIC                               │
│  Topic: arn:aws:sns:region:parent-account:teemops-sns          │
│  Receives notification with:                                    │
│  {                                                              │
│    TopsRoleArn: "arn:aws:iam::...:role/TeemOps",              │
│    TopsExternalId: "uuid-external-id",                        │
│    TopsUniqueId: "uuid-unique-id",                            │
│    TopsType: "org-uuid" (optional, can encode OrgId)          │
│  }                                                              │
└───────────────────────┬───────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│        BACKEND LAMBDA (SNS Subscriber)                         │
│  1. Receives SNS notification                                   │
│  2. Verifies SNS message signature                              │
│  3. Extracts: RoleArn, ExternalId, UniqueId, Type               │
│  4. Queries database for account with matching UniqueId         │
│  5. Validates ExternalId matches                               │
│  6. Encrypts IAM Role ARN (AES-256)                            │
│  7. Updates account record:                                     │
│     - iamRoleArn: encrypted role ARN                           │
│     - awsAccountId: extracted from Role ARN                    │
│     - status: 'active'                                          │
│  8. Optionally extracts OrgId from TopsType or metadata        │
└───────────────────────┬───────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                    FRONTEND (Nuxt 3)                           │
│  Polling detects status change:                                │
│  1. GET /aws-accounts/{accountId} returns status: 'active'      │
│  2. Updates UI to show "Active" status                         │
│  3. Stops polling                                              │
│  4. Shows success message                                      │
│  5. User can now initiate scans                                │
└─────────────────────────────────────────────────────────────────┘
```

## Data Flow

### Step 1: Init Request
```typescript
// Frontend Request
POST /organizations/{orgId}/aws-accounts/init
Headers: {
  Authorization: "Bearer <firebase-token>"
}

// Backend Response
{
  cloudFormationUrl: "https://console.aws.amazon.com/cloudformation/...",
  accountId: "uuid-pending-record",
  uniqueId: "uuid-unique-id",
  status: "pending"
}
```

### Step 2: Database Record (Pending)
```sql
INSERT INTO aws_accounts (
  id,
  organizationId,
  uniqueId,
  externalId,
  status,
  createdAt
) VALUES (
  'uuid-account-id',
  'uuid-org-id',
  'uuid-unique-id',
  'uuid-external-id',
  'pending',
  NOW()
);
```

### Step 3: SNS Notification
```json
{
  "Type": "Notification",
  "Message": {
    "TopsRoleArn": "arn:aws:iam::123456789012:role/TeemOps",
    "TopsExternalId": "uuid-external-id",
    "TopsUniqueId": "uuid-unique-id",
    "TopsType": "uuid-org-id"
  }
}
```

### Step 4: Database Record (Active)
```sql
UPDATE aws_accounts
SET 
  iamRoleArn = '<encrypted-role-arn>',
  awsAccountId = '123456789012',
  status = 'active',
  updatedAt = NOW()
WHERE uniqueId = 'uuid-unique-id';
```

## Error Handling

### Scenario 1: CloudFormation Timeout
- **Detection**: No SNS notification received within 5 minutes
- **Action**: Show "Enter Manually" option
- **User Flow**: User can manually enter AWS Account ID and IAM Role ARN

### Scenario 2: Invalid SNS Notification
- **Detection**: UniqueId doesn't match any pending account
- **Action**: Log error, ignore notification
- **User Flow**: User must use manual entry

### Scenario 3: Duplicate Account
- **Detection**: AWS Account ID already exists in organization
- **Action**: Return error, prevent duplicate
- **User Flow**: Show error message, suggest viewing existing account

### Scenario 4: AssumeRole Failure
- **Detection**: Cannot assume IAM role (invalid ExternalId, role deleted, etc.)
- **Action**: Mark account as 'error', log details
- **User Flow**: Show error status, allow retry or manual update

## Security Considerations

1. **ExternalId**: Ensures only parent account can assume role
2. **UniqueId**: Prevents account hijacking, matches notifications
3. **Encryption**: IAM Role ARN encrypted at rest
4. **SNS Verification**: Verify message signature and source
5. **Organization Isolation**: All queries filtered by organization.orgId

## Manual Entry Fallback

If automated flow fails, user can manually enter:

1. **AWS Account ID**: 12-digit account identifier
2. **IAM Role ARN**: From CloudFormation stack outputs
3. **Account Name**: Optional friendly name

Backend validates:
- AWS Account ID format (12 digits)
- IAM Role ARN format
- Role is assumable with correct ExternalId
- Account not already added to organization

## Testing Checklist

- [ ] User can initiate account addition
- [ ] CloudFormation URL generated with correct parameters
- [ ] Pending record created in database
- [ ] SNS notification received and processed
- [ ] Account status updates to 'active'
- [ ] Frontend polling detects status change
- [ ] Manual entry fallback works
- [ ] Duplicate account prevention works
- [ ] Organization isolation enforced
- [ ] Error states handled correctly

## API Endpoints Summary

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/organizations/{orgId}/aws-accounts/init` | Generate CloudFormation URL |
| GET | `/organizations/{orgId}/aws-accounts` | List accounts for organization |
| GET | `/aws-accounts/{accountId}` | Get account details (for polling) |
| POST | `/organizations/{orgId}/aws-accounts` | Manual account creation |
| POST | `/aws-accounts/sns-callback` | SNS webhook handler |
| PUT | `/aws-accounts/{accountId}` | Update account (name, etc.) |
| DELETE | `/aws-accounts/{accountId}` | Remove account |

---

**Note**: This flow ensures seamless onboarding while maintaining security through encrypted storage, ExternalId validation, and organization-based isolation.

