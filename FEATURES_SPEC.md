# Feature Specifications

This document contains feature specifications using our [User Story Template](../docs/templates/user-story-template.md). All features follow our [Practices](../docs/practices/) and [Feature Development Process](../docs/processes/feature-development.md).

## Feature 1: Organization Management

### User Story
As a user, I want to create and manage multiple organizations so that I can logically separate different projects or clients, with each organization completely isolated from others.

### Expected Behavior
When a user signs up, a default organization is automatically created. Users can create additional organizations, switch between them via a dropdown in the navigation, and manage organization settings. All AWS accounts, scans, and reports are scoped to the currently selected organization. Users can only see and manage organizations they own.

### User Acceptance Criteria
- [ ] Given I am a new user, when I sign up, then a default organization is automatically created for me
- [ ] Given I am logged in, when I click "Add Organization" and provide a name, then a new organization is created and available in the organization selector
- [ ] Given I have multiple organizations, when I select a different organization from the dropdown, then the UI updates to show data for that organization
- [ ] Given I am viewing an organization, when I look at the navigation, then I can see which organization is currently selected
- [ ] Given I have an organization with no AWS accounts, when I try to delete it, then the organization is deleted successfully
- [ ] Given I have an organization with AWS accounts, when I try to delete it, then I see an error preventing deletion
- [ ] Given I have only one organization, when I try to delete it, then I see an error preventing deletion of the last organization
- [ ] Given I own an organization, when I update its name, then the name is saved and reflected in the UI
- [ ] Given I try to access another user's organization, then I receive an authorization error

### Data Model

```typescript
interface Organization {
  id: string;           // UUID, primary key
  name: string;         // User-visible name
  orgId: string;        // UUID, unique identifier (NOT visible in UI)
  userId: string;       // Owner user ID
  isDefault: boolean;   // True for first org created
  createdAt: Date;
  updatedAt: Date;
}
```

### Business Rules

1. **Default Organization**: 
   - Created automatically on user signup
   - Named after username or "My Organization"
   - Cannot be deleted if it's the only organization

2. **OrgId Generation**:
   - Generated as UUID v4 on organization creation
   - Never displayed in UI
   - Used for all database queries and API calls
   - Ensures organization isolation

3. **Organization Switching**:
   - User can switch via dropdown in top navigation
   - Current organization stored in frontend state (Pinia)
   - All subsequent API calls include OrgId in context
   - UI updates to show organization-specific data

4. **Deletion Rules**:
   - Organization can only be deleted by owner
   - Cannot delete if AWS accounts are attached
   - Cannot delete if it's the only organization

### API Endpoints

#### List Organizations
```
GET /organizations
Response: {
  organizations: [
    {
      id: "uuid",
      name: "My Organization",
      orgId: "uuid-hidden",
      isDefault: true,
      createdAt: "2024-01-01T00:00:00Z"
    }
  ]
}
```

#### Create Organization
```
POST /organizations
Body: {
  name: "New Organization"
}
Response: {
  id: "uuid",
  name: "New Organization",
  orgId: "uuid-generated",
  isDefault: false,
  createdAt: "2024-01-01T00:00:00Z"
}
```

#### Update Organization
```
PUT /organizations/{orgId}
Body: {
  name: "Updated Name"
}
Response: {
  id: "uuid",
  name: "Updated Name",
  orgId: "uuid",
  updatedAt: "2024-01-01T00:00:00Z"
}
```

#### Delete Organization
```
DELETE /organizations/{orgId}
Response: {
  success: true
}
```

#### Get Current Organization
```
GET /organizations/current
Response: {
  id: "uuid",
  name: "My Organization",
  orgId: "uuid",
  isDefault: true
}
```

### UI Components

1. **Organization Selector** (Top Navigation):
   - Dropdown showing all user's organizations
   - Current organization highlighted
   - "Add Organization" option at bottom
   - Clicking organization switches context

2. **Organization Management Page**:
   - List of all organizations
   - Create new organization button
   - Edit/Delete actions per organization
   - Shows AWS account count per organization

---

## Feature 2: AWS Account Management

### User Story
As a user, I want to securely connect my AWS accounts to my organization so that I can scan them for security issues. The connection process should be simple and secure, using cross-account IAM roles via CloudFormation.

### Expected Behavior
When a user clicks "Add AWS Account", they receive a CloudFormation URL that opens in a new window. After completing the CloudFormation stack in their AWS Console, the account is automatically registered via SNS notification. The account appears in the list with a status (pending, active, or error). Users can see all accounts for their current organization, update account names, and remove accounts they no longer need. If the automated process fails, users can manually enter account details.

### User Acceptance Criteria
- [ ] Given I am viewing an organization, when I click "Add AWS Account", then I receive a CloudFormation URL that opens in a new window
- [ ] Given I have initiated account addition, when I complete the CloudFormation stack in AWS Console, then the account is automatically registered and status changes to "active"
- [ ] Given I have initiated account addition, when the SNS notification is received, then the IAM Role ARN is stored encrypted and account status updates to "active"
- [ ] Given I am viewing an organization, when I view the AWS accounts list, then I see all accounts for that organization with their status
- [ ] Given I have an AWS account, when I update its name, then the name is saved and reflected in the list
- [ ] Given I have an AWS account, when I delete it, then the account is removed from the organization
- [ ] Given the automated registration fails, when I choose "Enter Manually", then I can provide AWS Account ID and IAM Role ARN to complete registration
- [ ] Given I try to add a duplicate AWS Account ID to the same organization, then I see an error preventing the duplicate
- [ ] Given I switch organizations, when I view AWS accounts, then I only see accounts for the current organization
- [ ] Given an account is in "pending" status, when I view the account, then I see clear instructions on next steps

### Success Metrics
- **Task Success Rate**: >90% of users successfully add AWS accounts
- **Time to Complete**: <5 minutes from initiation to active account (including CloudFormation)
- **Error Rate**: <10% of accounts fail to register (with manual fallback available)
- **User Satisfaction**: Account addition process is clear and straightforward

### Related Practices
- [Product Practices](../docs/practices/product.md) - Simplicity First, User Experience
- [Security Practices](../docs/practices/security.md) - Data Protection (Encryption), Secrets Management, API Security
- [Database Practices](../docs/practices/database.md) - Schema Design, Data Integrity
- [Architecture Practices](../docs/practices/architecture.md) - Simplicity First, Standard Patterns

### Technical Notes
- IAM Role ARN must be encrypted at rest using AES-256
- ExternalId and UniqueId are UUIDs used for security (AssumeRole and SNS matching)
- CloudFormation template is stored in S3 and URL is configurable via environment variable
- SNS webhook handler must verify message signatures
- Manual fallback allows users to complete setup if automation fails
- Account status polling in frontend checks for status updates

### Data Model

```typescript
interface AwsAccount {
  id: string;              // UUID, primary key
  name: string;            // User-defined name
  awsAccountId: string;    // 12-digit AWS account ID
  iamRoleArn: string;      // Encrypted IAM Role ARN
  externalId: string;      // UUID, for AssumeRole security
  uniqueId: string;        // UUID, for matching SNS notifications
  organizationId: string;   // Links to organization.orgId
  status: 'pending' | 'active' | 'error';
  createdAt: Date;
  updatedAt: Date;
  lastScanAt?: Date;
}
```

### Business Rules

1. **Account Naming**:
   - User can provide custom name (optional)
   - Defaults to "AWS Account {awsAccountId}" if not provided
   - Names are unique within an organization

2. **Status Flow**:
   - `pending`: CloudFormation URL generated, waiting for completion
   - `active`: SNS notification received, account ready for scanning
   - `error`: CloudFormation failed or notification timeout

3. **Security**:
   - IAM Role ARN encrypted at rest (AES-256)
   - ExternalId ensures only parent account can assume role
   - UniqueId prevents account hijacking
   - Each account has unique ExternalId and UniqueId

4. **Organization Isolation**:
   - AWS accounts are scoped to organizations
   - Cannot see accounts from other organizations
   - Switching organization shows different accounts

### Onboarding Workflow

#### Step 1: Initiate Account Addition
```
User Action: Clicks "Add AWS Account" button
Frontend: 
  - Gets current organization.orgId from state
  - Calls POST /organizations/{orgId}/aws-accounts/init
Backend:
  - Generates UniqueId (UUID)
  - Generates ExternalId (UUID)
  - Creates pending record in database
  - Constructs CloudFormation URL
  - Returns URL to frontend
```

#### Step 2: CloudFormation Execution
```
Frontend:
  - Opens CloudFormation URL in new window
  - URL format:
    https://console.aws.amazon.com/cloudformation/home?#/stacks/quickcreate?
    templateUrl={S3_TEMPLATE_URL}&
    param_ParentAWSAccountId={PARENT_ACCOUNT_ID}&
    param_ExternalId={EXTERNAL_ID}&
    param_UniqueId={UNIQUE_ID}

User:
  - Completes CloudFormation stack in AWS Console
  - Stack creates:
    * IAM Role (TeemOps) with AssumeRole permissions
    * CustomNotifier that sends SNS notification
```

#### Step 3: Automatic Registration
```
CloudFormation CustomNotifier:
  - Sends SNS message to parent account
  - Topic: arn:aws:sns:region:parent-account-id:teemops-sns
  - Payload:
    {
      TopsRoleArn: "arn:aws:iam::123456789012:role/TeemOps",
      TopsExternalId: "uuid-external-id",
      TopsUniqueId: "uuid-unique-id",
      TopsType: "org-uuid-here" // Optional: can encode OrgId
    }

Backend SNS Subscriber Lambda:
  - Receives SNS notification
  - Extracts RoleArn, ExternalId, UniqueId
  - Queries database for account with matching UniqueId
  - Encrypts and stores IAM Role ARN
  - Updates status to "active"
  - Optionally extracts OrgId from TopsType or notification metadata
```

#### Step 4: Frontend Polling
```
Frontend:
  - Polls GET /aws-accounts/{accountId} for status
  - Shows "Pending" while status is pending
  - Shows "Active" when status becomes active
  - Shows error message if status becomes error
```

### API Endpoints

#### Initialize AWS Account Addition
```
POST /organizations/{orgId}/aws-accounts/init
Response: {
  cloudFormationUrl: "https://console.aws.amazon.com/cloudformation/...",
  accountId: "uuid-pending-record-id",
  uniqueId: "uuid-unique-id",
  status: "pending"
}
```

#### List AWS Accounts
```
GET /organizations/{orgId}/aws-accounts
Response: {
  accounts: [
    {
      id: "uuid",
      name: "Production AWS",
      awsAccountId: "123456789012",
      status: "active",
      lastScanAt: "2024-01-01T00:00:00Z"
    }
  ]
}
```

#### Get AWS Account Details
```
GET /aws-accounts/{accountId}
Response: {
  id: "uuid",
  name: "Production AWS",
  awsAccountId: "123456789012",
  status: "active",
  createdAt: "2024-01-01T00:00:00Z",
  lastScanAt: "2024-01-01T00:00:00Z"
}
```

#### Update AWS Account
```
PUT /aws-accounts/{accountId}
Body: {
  name: "Updated Name"
}
Response: {
  id: "uuid",
  name: "Updated Name",
  updatedAt: "2024-01-01T00:00:00Z"
}
```

#### Delete AWS Account
```
DELETE /aws-accounts/{accountId}
Response: {
  success: true
}
```

#### SNS Callback (Internal)
```
POST /aws-accounts/sns-callback
Headers: {
  'x-amz-sns-message-type': 'Notification'
}
Body: {
  // SNS message payload
  Message: {
    TopsRoleArn: "arn:aws:iam::...",
    TopsExternalId: "uuid",
    TopsUniqueId: "uuid",
    TopsType: "org-uuid"
  }
}
Response: {
  success: true,
  accountId: "uuid"
}
```

### Manual Fallback Flow

If SNS notification fails or times out:

1. User clicks "Enter Manually" button
2. Frontend shows form:
   - AWS Account ID (12 digits)
   - IAM Role ARN (from CloudFormation outputs)
3. Backend validates:
   - AWS Account ID format
   - IAM Role ARN format
   - Role exists and is assumable
4. Backend stores account and marks as "active"

### UI Components

1. **AWS Accounts List Page**:
   - Shows all accounts for current organization
   - Status badges (Pending, Active, Error)
   - Last scan timestamp
   - Actions: View, Scan, Remove

2. **Add AWS Account Flow**:
   - Step 1: Shows CloudFormation instructions
   - "Open AWS Console" button
   - "Enter Manually" fallback option
   - Step 2: Manual entry form (if needed)
   - Status polling indicator

3. **Account Status Indicators**:
   - 🟡 Pending: Waiting for CloudFormation
   - 🟢 Active: Ready for scanning
   - 🔴 Error: Setup failed

### Error Handling

1. **CloudFormation Timeout**:
   - After 5 minutes, show "Enter Manually" option
   - Allow user to complete setup manually

2. **SNS Notification Failure**:
   - Log error for debugging
   - Allow manual entry
   - Show error message to user

3. **Duplicate Account**:
   - Prevent adding same AWS Account ID twice in same organization
   - Show error: "This AWS account is already connected"

4. **Invalid Role ARN**:
   - Validate format on backend
   - Test AssumeRole capability
   - Show specific error message

### Security Considerations

1. **Encryption**:
   - IAM Role ARN encrypted at rest
   - ExternalId and UniqueId stored as plain UUIDs (not sensitive)

2. **AssumeRole Security**:
   - ExternalId condition ensures only parent account can assume
   - IAM role has least-privilege permissions
   - Role can be revoked by customer at any time

3. **SNS Verification**:
   - Verify SNS message signature
   - Validate message source (parent account SNS topic)
   - Rate limit callback endpoint

4. **Data Isolation**:
   - All queries filtered by organization.orgId
   - Users cannot access accounts from other organizations
   - API validates organization membership

---

## Integration Points

### CloudFormation Template
- Location: S3 bucket in parent account
- Parameters: ParentAWSAccountId, ExternalId, UniqueId
- Outputs: IAM Role ARN
- Custom Resource: SNS notification sender

### SNS Topic
- Topic Name: `teemops-sns`
- Region: Same as backend API
- Subscriber: NestJS webhook endpoint for processing notifications
- Message Format: JSON with RoleArn, ExternalId, UniqueId, Type

### Backend Endpoints
1. **Init Endpoint**: Generates UUIDs, creates pending record, returns CF URL
2. **SNS Webhook**: Processes notifications, updates account status
3. **Account Management**: CRUD operations for AWS accounts

---

## Implementation Status

### Organization Management
- ✅ Backend: CRUD operations implemented
- ✅ Frontend: UI and stores implemented
- ✅ Database: Schema complete
- ✅ Status: **Complete**

### AWS Account Management
- ✅ Database: Schema complete
- ✅ Frontend: UI structure and stores implemented
- ⚠️ Backend: CloudFormation URL generation - **In Progress**
- ⚠️ Backend: SNS webhook handler - **Not Started**
- ⚠️ Backend: IAM Role ARN encryption - **Not Started**
- ⚠️ Frontend: Status polling - **Not Started**
- ⚠️ Frontend: Manual fallback UI - **Not Started**
- ⚠️ Status: **Partial** - Core structure exists, integration needed

---

## Notes

- All features follow our [Practices](../docs/practices/) documents
- Features are developed incrementally following [Feature Development Process](../docs/processes/feature-development.md)
- See [PROGRESS.md](../docs/PROGRESS.md) for detailed implementation status

