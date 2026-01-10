# Feature Specifications

This document contains feature specifications using our [User Story Template](../docs/templates/user-story-template.md). All features follow our [Practices](../docs/practices/) and [Feature Development Process](../docs/processes/feature-development.md).

## Architecture Context

This application is built as a **Laravel monolith** with a **Vue 3 frontend** using **Inertia.js**. All features are implemented within this single codebase:

- **Backend**: Laravel 11 (PHP 8.2+) with MySQL database
- **Frontend**: Vue 3 with TypeScript, Tailwind CSS v4, and shadcn-vue components
- **Integration**: Inertia.js seamlessly bridges Laravel and Vue (no API layer needed for most features)
- **Authentication**: Firebase Authentication (OAuth and email/password)
- **State Management**: Pinia for Vue frontend state

For detailed architecture information, see [Architecture Documentation](../architecture.md) and [Laravel App Architecture Decisions](../laravel-app/ARCHITECTURE_DECISIONS.md).

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
   - Current organization stored in Vue frontend state (Pinia)
   - All subsequent API calls include OrgId in context
   - UI updates to show organization-specific data

4. **Deletion Rules**:
   - Organization can only be deleted by owner
   - Cannot delete if AWS accounts are attached
   - Cannot delete if it's the only organization

### API Endpoints

All API endpoints are prefixed with `/api/` and require Firebase authentication and organization context middleware.

#### List Organizations
```
GET /api/organizations
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
POST /api/organizations
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
PUT /api/organizations/{orgId}
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
DELETE /api/organizations/{orgId}
Response: {
  success: true
}
```

#### Get Current Organization
```
GET /api/organizations/current
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
As a User, I want to Add an AWS Account to my current Organization so that I can connect it to the cloud security app.

### Expected Behavior

**ExternalId Generation**: ExternalId is generated for each new AWS Account creation request. The UniqueId is derived from the OrgId. This does not change once an organization is created.

**Temporary Record Creation**: This generates a temporary AWS Account record in the database as "pending" status.

**CloudFormation URL**: When a new AWS account is added, a new window / tab is opened in the browser to redirect the user to the cloudformation url.

**CloudFormation Parameters**: The Cloudformation URL has 3 parameters: ExternalId, UniqueId, ParentAWSAccountId.

**CloudFormation URL Format**: 
```
https://console.aws.amazon.com/cloudformation/home?#/stacks/quickcreate?templateUrl={TOPS_CFN_TEMPLATE_URL}&stackName=tops-vendor-audit&param_ParentAWSAccountId={awsAccountId}&param_ExternalId={externalId}&param_UniqueId={uniqueId}
```

**CloudFormation Execution**: The Cloudformation will be created by the user within their AWS account.

**SNS to SQS Flow**: Once the Cloudformation template has been created it will send an SNS back to the parent AWS account. This SNS then sends to an SQS Queue. The SQS Queue can be polled by the Laravel backend. (Environment Variables: `TOPS_SQS_NAME` and `TOPS_SQS_ARN`)

**Database Update**: Database record for new AWS account will be updated. (This process will be handled by the Laravel backend Service that can be polling the SQS Queue for changes).

**Status Update**: AWS Account record in table will be updated with status "completed".

**Additional Behavior**: Users can see all accounts for their current organization, update account names, and remove accounts they no longer need. If the automated process fails, users can manually enter account details.

### User Acceptance Criteria
- [ ] Given I am viewing an organization, when I click "Add AWS Account", then I receive a CloudFormation URL that opens in a new window
- [ ] Given I initiate account addition, when the system generates the request, then ExternalId is generated and UniqueId is derived from the organization's OrgId
- [ ] Given I have initiated account addition, when I complete the CloudFormation stack in AWS Console, then the account is automatically registered via SNS→SQS flow and status changes to "completed"
- [ ] Given I have initiated account addition, when the SQS message is processed by Laravel backend, then the IAM Role ARN is stored encrypted and account status updates to "completed"
- [ ] Given I am viewing an organization, when I view the AWS accounts list, then I see all accounts for that organization with their status
- [ ] Given I have an AWS account, when I update its name, then the name is saved and reflected in the list
- [ ] Given I have an AWS account, when I delete it, then the account is removed from the organization
- [ ] Given the automated registration fails, when I choose "Enter Manually", then I can provide AWS Account ID and IAM Role ARN to complete registration
- [ ] Given I try to add a duplicate AWS Account ID to the same organization, then I see an error preventing the duplicate
- [ ] Given I switch organizations, when I view AWS accounts, then I only see accounts for the current organization
- [ ] Given an account is in "pending" status, when I view the account, then I see clear instructions on next steps
- [ ] Given an organization is created, when I add AWS accounts to it, then all accounts use the same UniqueId (derived from OrgId)

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
- ExternalId is generated as UUID for each AWS Account creation request
- UniqueId is derived from the organization's OrgId and does not change once an organization is created
- CloudFormation template URL is configurable via `TOPS_CFN_TEMPLATE_URL` environment variable
- CloudFormation URL includes `stackName=tops-vendor-audit` parameter
- SNS notification goes to parent AWS account, which forwards to SQS Queue
- SQS Queue name and ARN configured via `TOPS_SQS_NAME` and `TOPS_SQS_ARN` environment variables
- Laravel backend polls SQS Queue for new messages (instead of direct webhook)
- Manual fallback allows users to complete setup if automation fails
- Account status polling in frontend checks for status updates
- Status values: `pending`, `completed`, `error` (note: "completed" instead of "active")

### Data Model

```typescript
interface AwsAccount {
  id: string;              // UUID, primary key
  name: string;            // User-defined name
  awsAccountId: string;    // 12-digit AWS account ID
  iamRoleArn: string;      // Encrypted IAM Role ARN
  externalId: string;      // UUID, generated per account for AssumeRole security
  uniqueId: string;        // Derived from organization.orgId, same for all accounts in org
  organizationId: string;   // Links to organization.orgId
  status: 'pending' | 'completed' | 'error';
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
   - `completed`: SQS message processed, account ready for scanning
   - `error`: CloudFormation failed or notification timeout

3. **Security**:
   - IAM Role ARN encrypted at rest (AES-256)
   - ExternalId ensures only parent account can assume role (generated per account)
   - UniqueId derived from OrgId, same for all accounts in an organization
   - UniqueId prevents account hijacking and links accounts to organization

4. **Organization Isolation**:
   - AWS accounts are scoped to organizations
   - Cannot see accounts from other organizations
   - Switching organization shows different accounts

### Onboarding Workflow

#### Step 1: Initiate Account Addition
```
User Action: Clicks "Add AWS Account" button
Vue Frontend: 
  - Gets current organization.orgId from state
  - Calls POST /api/organizations/{orgId}/aws-accounts/init
Laravel Backend:
  - Derives UniqueId from organization.orgId (does not change)
  - Generates ExternalId (UUID) for this account
  - Creates pending record in MySQL database
  - Constructs CloudFormation URL with stackName parameter
  - Returns URL to frontend
```

#### Step 2: CloudFormation Execution
```
Vue Frontend:
  - Opens CloudFormation URL in new window
  - URL format:
    https://console.aws.amazon.com/cloudformation/home?#/stacks/quickcreate?
    templateUrl={TOPS_CFN_TEMPLATE_URL}&
    stackName=tops-vendor-audit&
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
      TopsUniqueId: "org-uuid-derived-from-orgid",
      TopsType: "org-uuid-here" // Optional: can encode OrgId
    }

AWS SNS → SQS:
  - SNS forwards message to SQS Queue
  - Queue name: {TOPS_SQS_NAME}
  - Queue ARN: {TOPS_SQS_ARN}

Laravel SQS Polling Service:
  - Polls SQS Queue for new messages (background job/command)
  - Extracts RoleArn, ExternalId, UniqueId from message
  - Queries MySQL database for account with matching UniqueId and ExternalId
  - Encrypts and stores IAM Role ARN using Laravel encryption
  - Updates status to "completed"
  - Deletes message from SQS queue after processing
```

#### Step 4: Frontend Polling
```
Vue Frontend:
  - Polls GET /api/aws-accounts/{accountId} for status
  - Shows "Pending" while status is pending
  - Shows "Completed" when status becomes completed
  - Shows error message if status becomes error
```

### API Endpoints

#### Initialize AWS Account Addition
```
POST /api/organizations/{orgId}/aws-accounts/init
Response: {
  cloudFormationUrl: "https://console.aws.amazon.com/cloudformation/...",
  accountId: "uuid-pending-record-id",
  uniqueId: "uuid-unique-id",
  status: "pending"
}
```

#### List AWS Accounts
```
GET /api/organizations/{orgId}/aws-accounts
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
GET /api/aws-accounts/{accountId}
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
PUT /api/aws-accounts/{accountId}
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
DELETE /api/aws-accounts/{accountId}
Response: {
  success: true
}
```

#### SQS Queue Polling (Internal)
```
Laravel Command/Job:
  - Polls SQS Queue: {TOPS_SQS_NAME}
  - Processes messages from queue
  - Extracts account information from SNS message
  - Updates database record
  - Deletes message from queue after successful processing

SQS Message Format (from SNS):
{
  "Type": "Notification",
  "Message": {
    "TopsRoleArn": "arn:aws:iam::123456789012:role/TeemOps",
    "TopsExternalId": "uuid-external-id",
    "TopsUniqueId": "org-uuid-derived-from-orgid",
    "TopsType": "org-uuid-here"
  }
}
```

### Manual Fallback Flow

If SNS notification fails or times out:

1. User clicks "Enter Manually" button
2. Vue frontend shows form:
   - AWS Account ID (12 digits)
   - IAM Role ARN (from CloudFormation outputs)
3. Laravel backend validates:
   - AWS Account ID format
   - IAM Role ARN format
   - Role exists and is assumable
4. Laravel stores account in MySQL and marks as "active"

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
   - Validate format on Laravel backend
   - Test AssumeRole capability using AWS SDK
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

## Feature 3: User Signup Flow Improvements

### User Story
As a user, when I sign up and receive a verification email, I need to see a message at the top of the screen confirming the email was sent. When I verify my email, I need to see a success message at the top of the screen. When I sign up via OAuth, I need to see a welcome message.

### Expected Behavior
When users interact with the authentication system, they receive clear, timely feedback through in-app notifications displayed at the top right of the screen. These notifications provide confirmation of actions taken and guide users through the signup and verification process.

**Registration Flow (Email/Password):**
1. User completes registration form
2. System creates account and sends verification email
3. User sees info notification: "Registration successful! Please check your email to verify your account."
4. User is redirected to email verification page
5. User clicks verification link in email
6. User sees success notification: "Your email has been verified successfully!"
7. User is redirected to dashboard

**Email Verification Resend:**
1. User clicks "Resend Verification Email" button
2. System sends new verification email
3. User sees info notification: "Verification email sent! Please check your inbox."

**OAuth Signup Flow:**
1. User clicks OAuth provider button (Google, GitHub, Microsoft)
2. User completes OAuth authorization
3. System creates account and automatically verifies email
4. User sees success notification: "Successfully logged in via [Provider]!"
5. User is redirected to dashboard (no verification required)

### User Acceptance Criteria
- [x] Given I am a new user, when I register with email and password, then I see an info notification confirming registration and email verification instructions
- [x] Given I have registered, when I receive the verification email and click the link, then I see a success notification confirming my email is verified
- [x] Given I am on the email verification page, when I click "Resend Verification Email", then I see an info notification confirming the email was sent
- [x] Given I sign up via OAuth (Google, GitHub, or Microsoft), when the OAuth flow completes, then I see a success notification welcoming me and confirming login
- [x] Given I am already verified, when I try to verify my email again, then I see an info notification that my email is already verified
- [x] Given I see a notification, when the notification appears, then it automatically dismisses after 5-7 seconds
- [x] Given I see a notification, when I click the dismiss button, then the notification is immediately removed
- [x] Given multiple notifications appear, when they stack, then they are displayed vertically without overlapping

### Notification Types

#### Success (Green)
- **Use for**: Successful operations, confirmations, OAuth signups
- **Auto-dismiss**: 5 seconds
- **Examples**:
  - "Your email has been verified successfully!"
  - "Successfully logged in via Google!"
  - "Account created and verified!"

#### Info (Blue)
- **Use for**: Informational messages, instructions
- **Auto-dismiss**: 5 seconds
- **Examples**:
  - "Registration successful! Please check your email to verify your account."
  - "Verification email sent! Please check your inbox."
  - "Your email is already verified."

#### Warning (Yellow)
- **Use for**: Warnings, cautions
- **Auto-dismiss**: 6 seconds
- **Examples**:
  - "Your session will expire soon."
  - "Please review your settings."

#### Error (Red)
- **Use for**: Errors, failures
- **Auto-dismiss**: 7 seconds (longer for errors)
- **Examples**:
  - "Authentication failed. Please try again."
  - "Failed to send verification email."

### Data Model

Notifications are ephemeral and do not require a database model. They are managed in the frontend using Vue composables and displayed via Vue components.

```typescript
interface Notification {
  id: string;              // UUID for tracking
  message: string;         // Notification text
  type: 'success' | 'error' | 'warning' | 'info';
  timeout?: number;        // Milliseconds before auto-dismiss (0 = no auto-dismiss)
}
```

### Business Rules

1. **Notification Display**:
   - Notifications appear at top right of screen
   - Maximum width: 384px (max-w-sm)
   - Stack vertically with spacing
   - Slide in from right with animation
   - Slide out to right when dismissed

2. **Auto-Dismiss**:
   - Success: 5 seconds
   - Info: 5 seconds
   - Warning: 6 seconds
   - Error: 7 seconds
   - Can be disabled by setting timeout to 0

3. **Manual Dismiss**:
   - All notifications have a dismiss button (X)
   - Clicking dismiss immediately removes notification
   - Dismiss button is accessible and keyboard navigable

4. **Flash Message Integration**:
   - Laravel flash messages are automatically converted to notifications
   - Flash messages are cleared after being displayed
   - Supports: `success`, `error`, `warning`, `info`

5. **Dark Mode**:
   - Notifications support both light and dark themes
   - Colors adapt based on system/user preference

### API Endpoints

Notifications are primarily frontend-driven, but Laravel controllers set flash messages that are converted to notifications:

#### Registration
```
POST /register
Response: Redirect to /verify-email
Flash Message: info - "Registration successful! Please check your email to verify your account."
```

#### Email Verification
```
GET /verify-email/{id}/{hash}
Response: Redirect to /dashboard?verified=1
Flash Message: success - "Your email has been verified successfully!"
```

#### Resend Verification Email
```
POST /email/verification-notification
Response: Redirect back
Flash Message: success - "A new verification link has been sent to your email address!"
```

#### OAuth Verification
```
POST /auth/firebase/verify
Response: Redirect to /dashboard
Flash Message: success - "Successfully logged in via [Provider]!"
```

### UI Components

1. **Notification Component** (`Notification.vue`):
   - Individual notification card
   - Type-specific styling (colors, icons)
   - Dismiss button
   - Slide-in animation
   - Accessible (ARIA labels, keyboard navigation)

2. **Notification Container** (`NotificationContainer.vue`):
   - Fixed position container (top right)
   - Manages multiple notifications
   - Watches for Laravel flash messages
   - Transition group for animations
   - Clears flash messages after display

3. **Notification Composable** (`useNotifications.ts`):
   - Global notification state
   - Helper functions: `showSuccess()`, `showError()`, `showWarning()`, `showInfo()`
   - Auto-dismiss timers
   - Notification management (add, dismiss)

### Integration Points

1. **Laravel Middleware** (`HandleInertiaRequests`):
   - Shares flash messages with Inertia frontend
   - Provides: `flash.success`, `flash.error`, `flash.warning`, `flash.info`

2. **Vue Layouts**:
   - `SidebarAppLayout.vue`: Includes `<NotificationContainer />` for app pages
   - `SplitAuthLayout.vue`: Includes `<NotificationContainer />` for auth pages

3. **Laravel Controllers**:
   - `RegisteredUserController`: Sets info flash on registration
   - `VerifyEmailController`: Sets success flash on verification
   - `EmailVerificationNotificationController`: Sets success flash on resend
   - `FirebaseAuthController`: Sets success flash on OAuth login

### Error Handling

1. **Missing Flash Messages**:
   - If flash message is null/undefined, notification is not shown
   - No errors thrown for missing flash messages

2. **Notification Overflow**:
   - Notifications stack vertically
   - Older notifications are pushed down
   - Maximum visible notifications: ~5-6 (depends on screen height)

3. **Animation Failures**:
   - Notifications still display even if animations fail
   - Graceful degradation for older browsers

### Security Considerations

1. **XSS Prevention**:
   - Notification messages are sanitized by Laravel
   - Vue automatically escapes content in templates
   - No user input directly in notification messages

2. **Flash Message Security**:
   - Flash messages are stored in Laravel session
   - Session is encrypted and secure
   - Flash messages cleared after display

### Implementation Status

- ✅ Notification component (`Notification.vue`)
- ✅ Notification container (`NotificationContainer.vue`)
- ✅ Notification composable (`useNotifications.ts`)
- ✅ Integration with `SidebarAppLayout`
- ✅ Integration with `SplitAuthLayout`
- ✅ Flash message sharing via middleware
- ✅ Registration flow messages
- ✅ Email verification messages
- ✅ OAuth signup messages
- ✅ Test page (`/test-notifications`)
- ✅ Status: **Complete**

### Related Practices

- [Product Practices](../docs/practices/product.md) - User Experience, Simplicity First
- [Frontend Practices](../docs/practices/frontend.md) - Component Design, Accessibility
- [Security Practices](../docs/practices/security.md) - XSS Prevention

---

## Integration Points

### CloudFormation Template
- Location: S3 bucket in parent account
- Parameters: ParentAWSAccountId, ExternalId, UniqueId
- Outputs: IAM Role ARN
- Custom Resource: SNS notification sender

### SNS Topic → SQS Queue
- SNS Topic Name: `teemops-sns` (in parent AWS account)
- SQS Queue Name: Configured via `TOPS_SQS_NAME` environment variable
- SQS Queue ARN: Configured via `TOPS_SQS_ARN` environment variable
- Flow: CloudFormation → SNS → SQS → Laravel polling service
- Message Format: JSON with RoleArn, ExternalId, UniqueId, Type

### Laravel API Endpoints
1. **Init Endpoint**: Derives UniqueId from orgId, generates ExternalId, creates pending record, returns CF URL
2. **SQS Polling Service**: Background command (`php artisan aws:process-sqs`) that polls SQS queue, processes messages, updates account status to "completed"
3. **Account Management**: CRUD operations for AWS accounts

### SQS Polling Command
The Laravel application includes a command to poll the SQS queue for AWS account registration messages:

```bash
# Run once (process messages and exit)
php artisan aws:process-sqs --once

# Run continuously (long-running process)
php artisan aws:process-sqs
```

**Recommended Setup**: Run as a supervisor/systemd service or schedule via Laravel scheduler:
```php
// In app/Console/Kernel.php
$schedule->command('aws:process-sqs --once')->everyMinute();
```

**Environment Variables Required**:
- `TOPS_SQS_NAME`: Name of the SQS queue
- `TOPS_SQS_ARN`: ARN of the SQS queue
- `AWS_DEFAULT_REGION`: AWS region (defaults to us-east-1)

---

## Implementation Status

### Organization Management
- ✅ Laravel Backend: CRUD operations implemented
- ✅ Vue Frontend: UI and state management implemented
- ✅ MySQL Database: Schema complete
- ✅ Status: **Complete**

### AWS Account Management
- ✅ MySQL Database: Schema complete (status enum: pending, completed, error)
- ✅ Vue Frontend: UI structure and state management implemented
- ✅ Laravel Backend: CloudFormation URL generation with stackName parameter
- ✅ Laravel Backend: UniqueId derived from organization.orgId
- ✅ Laravel Backend: ExternalId generated per account
- ✅ Laravel Backend: IAM Role ARN encryption implemented
- ✅ Vue Frontend: Status polling for pending accounts
- ✅ Vue Frontend: Manual fallback UI implemented
- ⚠️ Laravel Backend: SQS Queue polling service - **Not Started** (needs background job/command)
- ⚠️ Status: **Partial** - Core functionality complete, SQS polling service needed

---

## Notes

- All features follow our [Practices](../docs/practices/) documents
- Features are developed incrementally following [Feature Development Process](../docs/processes/feature-development.md)
- See [PROGRESS.md](../docs/PROGRESS.md) for detailed implementation status

