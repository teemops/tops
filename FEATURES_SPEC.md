# Feature Specifications

## Feature 1: Organization Management

### Overview
Users can create and manage multiple organizations to logically group AWS accounts, scans, and reports. Each organization is completely isolated from others.

### User Stories

**As a user, I want to:**
- Create multiple organizations to separate different projects/clients
- Switch between organizations easily
- See which organization I'm currently viewing
- Manage organization settings

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

### Overview
Securely connect customer AWS accounts using cross-account IAM roles via CloudFormation. The process is automated through SNS notifications from the CloudFormation stack.

### User Stories

**As a user, I want to:**
- Add AWS accounts to my organization
- See all AWS accounts for the current organization
- Remove AWS accounts I no longer need
- Know the status of my AWS account connections

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
- Region: Same as Lambda functions
- Subscriber: Lambda function for processing notifications
- Message Format: JSON with RoleArn, ExternalId, UniqueId, Type

### Lambda Functions
1. **Init Handler**: Generates UUIDs, creates pending record, returns CF URL
2. **SNS Subscriber**: Processes notifications, updates account status
3. **Account Management**: CRUD operations for AWS accounts

---

## Testing Scenarios

### Organization Management
- ✅ Create organization
- ✅ List organizations
- ✅ Switch organization
- ✅ Update organization name
- ✅ Delete organization (with/without accounts)
- ✅ Prevent deletion of last organization

### AWS Account Management
- ✅ Initiate account addition
- ✅ Generate CloudFormation URL with correct parameters
- ✅ Receive SNS notification and update account
- ✅ Manual fallback flow
- ✅ List accounts filtered by organization
- ✅ Update account name
- ✅ Delete account
- ✅ Handle duplicate account ID
- ✅ Handle invalid Role ARN
- ✅ Handle timeout scenarios

