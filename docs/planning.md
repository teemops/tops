# Cloud Security Application - Planning Document

## Overview
A cloud security scanning application with:
- **Frontend**: Vue 3 with Inertia.js
- **Backend**: Laravel 12 (PHP 8.2+)
- **Deployment**: Single EC2 instance
- **Authentication**: Firebase Authentication
- **Initial Support**: AWS
- **Future Support**: Azure, GCP

## Practices Alignment

This project follows our [Practices](../docs/practices/) documents:
- [Product Practices](../docs/practices/product.md) - Simplicity first, startup agility
- [Security Practices](../docs/practices/security.md) - Security by design
- [Code Quality Practices](../docs/practices/code-quality.md) - Simple, maintainable code
- [Database Practices](../docs/practices/database.md) - Simple, normalized design
- [Architecture Practices](../docs/practices/architecture.md) - Simple, scalable architecture
- [Feature Development Practices](../docs/practices/feature-development.md) - Incremental development

All new features should:
1. Use the [User Story Template](../docs/templates/user-story-template.md)
2. Follow the [Feature Development Process](../docs/processes/feature-development.md)
3. Reference the [Practices Checklist](../docs/processes/practices-checklist.md) during development
4. See [PROGRESS.md](../docs/PROGRESS.md) for current implementation status

---

## Architecture Overview

### High-Level Architecture
```
┌─────────────────────────────────────────────────┐
│  Laravel Application (EC2 Instance)             │
│  ┌───────────────────────────────────────────┐  │
│  │  Vue 3 + Inertia.js Frontend              │  │
│  │  (SSR with TypeScript, Tailwind CSS)      │  │
│  └───────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────┐  │
│  │  Laravel 12 Backend (PHP 8.2+)            │  │
│  │  ┌─────────────────────────────────────┐  │  │
│  │  │  Controllers (API + Web)            │  │  │
│  │  │  - OrganizationsController          │  │  │
│  │  │  - AwsAccountsController            │  │  │
│  │  │  - ScansController                  │  │  │
│  │  │  - Auth Controllers (Firebase)      │  │  │
│  │  └─────────────────────────────────────┘  │  │
│  │  ┌─────────────────────────────────────┐  │  │
│  │  │  Services                           │  │  │
│  │  │  - AwsSecurityScanner               │  │  │
│  │  │  - RulesEngine                      │  │  │
│  │  │  - Scanners (S3, IAM, EC2, RDS)     │  │  │
│  │  └─────────────────────────────────────┘  │  │
│  │  ┌─────────────────────────────────────┐  │  │
│  │  │  Jobs (Queue Workers)               │  │  │
│  │  │  - ProcessScanJob                   │  │  │
│  │  │  - ProcessRegionScanJob             │  │  │
│  │  │  - ProcessAuditScanJob              │  │  │
│  │  └─────────────────────────────────────┘  │  │
│  └───────────────────────────────────────────┘  │
└────────┬────────────────────────────────────────┘
         │
    ┌────┴────┐
    │         │
┌───▼───┐ ┌──▼──────────┐ ┌──────────┐
│  AWS  │ │ Firebase    │ │ MySQL    │
│ Cloud │ │ Auth        │ │ (RDS)    │
└───────┘ └─────────────┘ └──────────┘
```

### Technology Stack

#### Frontend (Vue 3 + Inertia.js)
- **Framework**: Vue 3 with Inertia.js (SSR-capable SPA)
- **Build Tool**: Vite 7
- **Language**: TypeScript
- **Styling**: Tailwind CSS with @tailwindcss/forms
- **HTTP Client**: Axios
- **Authentication**: Firebase JS SDK v12+
- **State/Composables**: Vue 3 Composables (`useFirebase`, `useOrganizations`, `useAwsAccounts`, `useScans`)
- **Testing**: Playwright (E2E)

#### Backend (Laravel 12)
- **Runtime**: PHP 8.2+
- **Framework**: Laravel 12
- **Deployment**: Single EC2 instance
- **Process Manager**: Supervisor or systemd (for queue workers)
- **Database**: MySQL (AWS RDS)
- **ORM**: Eloquent (Laravel built-in)
- **AWS SDK**: AWS SDK for PHP v3.369+
- **Authentication**: Firebase PHP SDK (kreait/firebase-php v8.0) for token verification
- **Frontend Integration**: Inertia.js Laravel adapter
- **Validation**: Laravel Form Requests
- **Queue**: Laravel Queue (database/SQS driver)
- **Testing**: PHPUnit 11

#### Key Laravel Packages
- `inertiajs/inertia-laravel` - Vue/Laravel integration
- `kreait/firebase-php` - Firebase Admin SDK
- `aws/aws-sdk-php` - AWS service integration
- `laravel/sanctum` - API token authentication
- `laravel/breeze` - Authentication scaffolding
- `tightenco/ziggy` - Laravel routes in JavaScript

#### Infrastructure
- **Deployment**: AWS (EC2, RDS)
- **CI/CD**: GitHub Actions
- **Multi-tenancy**: Organization/Team based (Firebase Auth + MySQL)
- **Load Balancer**: Application Load Balancer (optional, for future scaling)
- **Reverse Proxy**: Nginx (optional, for SSL termination and static assets)

---

## Project Structure

```
saas/
├── app/                          # Laravel Application
│   ├── app/
│   │   ├── Console/
│   │   │   └── Commands/
│   │   │       └── ProcessSqsMessages.php    # SQS polling command
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Api/                      # API Controllers
│   │   │   │   │   ├── AwsAccountsController.php
│   │   │   │   │   ├── OrganizationsController.php
│   │   │   │   │   └── ScansController.php
│   │   │   │   ├── Auth/                     # Auth Controllers
│   │   │   │   │   ├── FirebaseAuthController.php
│   │   │   │   │   ├── OAuthController.php
│   │   │   │   │   └── RegisteredUserController.php
│   │   │   │   └── ProfileController.php
│   │   │   ├── Middleware/
│   │   │   │   ├── SetOrganizationContext.php
│   │   │   │   └── VerifyFirebaseToken.php
│   │   │   └── Requests/                     # Form Requests (Validation)
│   │   │       ├── InitAwsAccountRequest.php
│   │   │       ├── StoreAwsAccountRequest.php
│   │   │       ├── StoreOrganizationRequest.php
│   │   │       ├── StoreScanRequest.php
│   │   │       └── UpdateOrganizationRequest.php
│   │   ├── Jobs/                             # Queue Jobs
│   │   │   ├── ProcessAuditScanJob.php       # Individual audit scan
│   │   │   ├── ProcessRegionScanJob.php      # Per-region scanning
│   │   │   └── ProcessScanJob.php            # Main scan orchestrator
│   │   ├── Models/                           # Eloquent Models
│   │   │   ├── AwsAccount.php
│   │   │   ├── Organization.php
│   │   │   ├── Scan.php
│   │   │   ├── ScanDetail.php
│   │   │   ├── ScanResult.php
│   │   │   └── User.php
│   │   ├── Providers/
│   │   │   └── AppServiceProvider.php
│   │   └── Services/                         # Business Logic
│   │       ├── AwsSecurityScanner.php        # Main scanner service
│   │       ├── RulesEngine/                  # Rules evaluation
│   │       │   ├── ConditionEvaluator.php
│   │       │   ├── FindingsEngine.php
│   │       │   └── RulesEngine.php
│   │       ├── Scanners/                     # Service-specific scanners
│   │       │   ├── Ec2Scanner.php
│   │       │   ├── IamScanner.php
│   │       │   ├── RdsScanner.php
│   │       │   └── S3Scanner.php
│   │       ├── ScanTypesService.php
│   │       └── SnsSignatureVerifier.php
│   ├── config/                               # Laravel Configuration
│   ├── database/
│   │   ├── factories/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── resources/
│   │   ├── css/
│   │   │   └── app.css                       # Tailwind CSS
│   │   └── js/
│   │       ├── app.ts                        # Vue app entry
│   │       ├── Components/                   # Reusable Vue components
│   │       │   ├── OrganizationSelector.vue
│   │       │   ├── Notification.vue
│   │       │   └── ...
│   │       ├── composables/                  # Vue 3 Composables
│   │       │   ├── useAwsAccounts.ts
│   │       │   ├── useFirebase.ts
│   │       │   ├── useNotifications.ts
│   │       │   ├── useOrganizations.ts
│   │       │   └── useScans.ts
│   │       ├── Layouts/                      # Page layouts
│   │       │   ├── AuthenticatedLayout.vue
│   │       │   ├── GuestLayout.vue
│   │       │   └── SidebarAppLayout.vue
│   │       ├── Pages/                        # Inertia pages
│   │       │   ├── Auth/
│   │       │   │   ├── Login.vue
│   │       │   │   ├── Register.vue
│   │       │   │   └── ...
│   │       │   ├── AwsAccounts/
│   │       │   │   └── Index.vue
│   │       │   ├── Dashboard.vue
│   │       │   ├── Organizations/
│   │       │   │   ├── Index.vue
│   │       │   │   └── Settings.vue
│   │       │   ├── Profile/
│   │       │   │   └── Edit.vue
│   │       │   └── Scans/
│   │       │       ├── Index.vue
│   │       │       └── Show.vue
│   │       └── types/                        # TypeScript types
│   ├── routes/
│   │   ├── api.php                           # API routes
│   │   ├── auth.php                          # Auth routes
│   │   └── web.php                           # Web routes
│   ├── rules/                                # Security Rules
│   │   ├── rulesets/
│   │   │   ├── basic.json                    # Basic security rules
│   │   │   ├── cis.json                      # CIS benchmark rules
│   │   │   └── pci.json                      # PCI-DSS rules
│   │   └── tasks/                            # Scanner task definitions
│   │       ├── ec2/tasks.json
│   │       ├── iam/tasks.json
│   │       ├── rds/tasks.json
│   │       └── s3/tasks.json
│   ├── tests/
│   │   ├── e2e/                              # Playwright E2E tests
│   │   ├── Feature/                          # Laravel feature tests
│   │   └── Unit/                             # PHPUnit unit tests
│   ├── composer.json
│   ├── package.json
│   ├── tailwind.config.js
│   ├── tsconfig.json
│   └── vite.config.js
│
├── docs/                         # Documentation
├── design/                       # UI/UX designs
├── references/                   # Reference implementations
├── templates/                    # CloudFormation templates
│   ├── iam.role.audit.account.cfn.yaml
│   └── iam.role.child.account.cfn.yaml
│
├── .github/
│   └── workflows/                # GitHub Actions CI/CD
│
└── README.md
```

---

## Core Features & Modules

### Phase 1: AWS Scanning (Initial)
1. **Authentication & Authorization**
   - User registration/login via Firebase Auth
   - OAuth support (Google, Apple, Microsoft)
   - Email/password authentication
   - **Multi-Factor Authentication (MFA)** ⭐ ROADMAP PRIORITY
     - TOTP (authenticator app) as primary method
     - Email OTP fallback for each login (if TOTP unavailable)
     - Profile settings: Add, view, remove MFA device
     - Persistent alert when MFA not enabled (shown at top of app until enabled)
   - Firebase token verification in backend
   - Multi-tenant organization/team support
   - Role-based access control (RBAC)

2. **AWS Account Management**
   - Add/remove AWS accounts
   - Store AWS credentials securely (encrypted)
   - Support for multiple AWS accounts per user

3. **AWS Security Scanning**
   - Scan AWS resources for security issues
   - Common checks:
     - S3 bucket public access
     - IAM policy misconfigurations
     - Security group rules
     - CloudTrail logging
     - Encryption settings
     - Compliance checks (CIS, AWS Well-Architected)

4. **Scan Results & Reporting (Findings)**
   - **Findings page (org-wide):** Summary panel (Executive Summary with Overall Security Score, total findings per severity) and Detailed Findings panel (all findings sorted by severity then oldest unresolved).
   - **Latest-status rule:** A finding is only hidden if the latest record for that issue (vs ruleset) has passed; list reflects current open issues.
   - **Filters:** AWS Account, Type (finding type / service). **Groupings:** Findings grouped by recommendation (remediation tips) for remediation workflows.
   - **Remediation:** Expand a finding to view steps/links from recommendations; each finding type has a detail page showing all related resources and remediation. Recommendations/tips stored in app (e.g. `app/rules/recommendations/`) and updated when rulesets change.
   - View scan results (per-scan); filter and search findings; export reports (PDF, CSV, JSON); dashboard with statistics.

5. **Scheduling**
   - Schedule recurring scans
   - Manual scan triggers

### Phase 2: Multi-Cloud (Future)
- Azure account integration
- GCP account integration
- Cross-cloud compliance reporting

---

## First Steps

### Step 1: Project Initialization ✅ COMPLETED
1. Create Laravel application structure
2. Initialize Vue 3 frontend with Inertia.js
3. Set up Laravel Breeze for authentication scaffolding
4. Configure Vite for frontend bundling
5. Set up basic project configuration

### Step 2: Development Environment Setup ✅ COMPLETED
1. Configure TypeScript for Vue frontend
2. Set up Laravel Pint for PHP code style
3. Configure environment variables (.env)
4. Set up Firebase project and configuration
5. Configure MySQL database connection

### Step 3: Backend Foundation ✅ COMPLETED
1. Set up Laravel project structure
2. Create API controllers (Organizations, AwsAccounts, Scans)
3. Set up Eloquent models and relationships
4. Configure database migrations
5. Set up Firebase PHP SDK for token verification
6. Create VerifyFirebaseToken middleware
7. Create user and organization models
8. Set up SetOrganizationContext middleware for multi-tenancy
9. Create Form Request classes for validation
10. Set up API routes with middleware groups

### Step 4: Frontend Foundation ✅ COMPLETED
1. Set up Vue 3 with Inertia.js
2. Install and configure Tailwind CSS
3. Configure Firebase Authentication (JS SDK)
4. Set up routing with Laravel/Inertia
5. Create Vue composables (useFirebase, useOrganizations, useAwsAccounts, useScans)
6. Create authentication pages (login/register)
7. Set up Axios for API requests
8. Create reusable Vue components

### Step 5: AWS Integration ✅ COMPLETED
1. Create AwsSecurityScanner service
2. Implement AWS credential management (IAM Role storage, encrypted)
3. Build CloudFormation link generation for cross-account roles
4. Implement scanning services (S3Scanner, IamScanner, Ec2Scanner, RdsScanner)
5. Create RulesEngine for findings evaluation
6. Create scan results storage (ScanResult, ScanDetail models)
7. Build API endpoints for scans (ScansController)
8. Set up AWS SDK PHP integration
9. Create SNS webhook handler with signature verification

### Step 6: Frontend Integration ✅ COMPLETED
1. Create AWS account management UI (Index.vue, AddAwsAccountModal.vue)
2. Build scan execution interface (Scans/Index.vue, NewScanModal.vue)
3. Create results dashboard (Scans/Show.vue)
4. Create organization/team management UI (Organizations/Index.vue, Settings.vue)

### Step 7: Findings Feature
1. Store recommendations/tips in app (e.g. `app/rules/recommendations/`) from `references/code/findings/tips.json`; load for groupings and remediation.
2. API: org-wide findings list (with latest-status logic), Executive Summary (security score, total per severity); GET/PUT single finding (details, status update); optional GET /api/results/summary for dashboard.
3. Findings page: Summary panel (Executive Summary) + Detailed Findings panel; filters (AWS Account, Type); group by recommendation; expand finding for remediation steps; per-finding-type detail page (all resources + remediation).
4. Wire Findings nav item to Findings page; status update UI (mark resolved/ignored).

### Step 8: EC2 Deployment
1. Set up EC2 instance:
   - Instance type: t3.small
   - Operating system: Ubuntu 22.04 LTS
   - Storage: 100GB EBS Volume (gp3)
   - Security groups: Allow HTTP (80), HTTPS (443), SSH (22)
2. Set up Application Load Balancer:
   - Configure ACM certificate for api.teemops.com
   - Set up target group pointing to EC2 instance
   - Configure health checks
3. Configure PHP-FPM and Nginx
4. Set up Supervisor for Laravel queue workers
5. Configure environment variables (use AWS Systems Manager Parameter Store)
6. Set up RDS connection and connection pooling
7. Configure IAM roles and permissions for EC2:
   - Access to RDS
   - Access to SNS topic
   - Access to Systems Manager Parameter Store
   - Access to Secrets Manager (if used)
8. Set up GitHub Actions for CI/CD deployment
9. Configure CloudWatch:
   - CloudWatch Logs for application logs
   - CloudWatch Metrics for monitoring
   - Set log retention (7 days default, configurable)
10. Set up auto-scaling group (for future horizontal scaling)

---

## Information Needed

### Critical Questions:

1. **Authentication & Users**
   - Do you need user registration, or will users be pre-created?
   Yes, user registration is required. We will use email/password authentication. With optional MFA. This can use Firebase Authentication.
   - What authentication method? (Email/password, SSO, OAuth)
   Email/password is required. MFA is optional. or OAuth with Google, Apple, Microsoft, etc. OAuth is preferred.
   - Do you need multi-tenancy? (Organizations/teams)
   Yes, multi-tenancy is required. We will use organizations/teams. This can use Firebase Authentication.

2. **AWS Credentials Management**
   - How should AWS credentials be stored? (AWS IAM roles, Access Keys, Cross-account roles)
   IAM Roles using Cross account roles. The workflow for that a user may add another AWS account to their account. When they do this, this will open
   a new window to the AWS Console to add the account. This will also create a new IAM Role for the user to use. This link is a CloudFormation link that looks like this:
   https://console.aws.amazon.com/cloudformation/home?#/stacks/quickcreate?templateUrl=https%3A%2F%2Fs3.amazonaws.com%2Fstorage.auditaws.com%2Fiam.role.child.account.cfn.yaml&stackName=teemops-dontdelete&param_ParentAWSAccountId=${process.env.aws_parent_account}
   The credentials will be stored as an IAM Role.
   - Should credentials be encrypted at rest?
   Yes.
   - Do you need support for AWS Organizations?
   No.

3. **Database**
   - Which database? MYSQL is preferred.
   - Do you have hosting preferences? (Local, AWS RDS, etc.)
   AWS RDS is preferred.

4. **Security Scanning**
   - Which security checks are priority? (S3, IAM, EC2, RDS, etc.)
   We will start with S3, IAM, EC2, RDS.
   - Do you want to use existing tools? (Prowler, Scout Suite, CloudSploit)
   No. We will build custom checks.
   - Or build custom checks?
   Yes. We will build custom checks.

5. **UI/UX**
   - Do you have design preferences? (Material Design, Tailwind, custom)
   Material Design is preferred.
   - Any specific UI components needed?
   Yes. We will build custom components. We will use the PrimeVue library.

6. **Deployment**
   - Where will this be deployed? (AWS, Vercel, self-hosted)
   AWS as a Single SaaS App that we host and run multi-tenant.
   - Do you need Docker containerization?
   No.
   - CI/CD requirements?
   We will use GitHub Actions for CI/CD.

7. **Features Priority**
   - What's the MVP (Minimum Viable Product)?
   The MVP is the core features that are required to be working.
   Ability to register, login, add AWS accounts, scan AWS accounts, view scan results, and report on scan results.
   - Sign up for a new account.
   - Login to the account.
   - Add AWS accounts to the account.
   - Scan AWS accounts.
   - View scan results.
   - Report on scan results.
   - Which features are must-have vs nice-to-have?
   All are must-have.

8. **Compliance & Standards**
   - Which compliance frameworks? (CIS Benchmarks, SOC 2, PCI-DSS, etc.)
   CIS Benchmarks is preferred.
   - Any specific security standards to follow?
   PCI, SOC 2, ISO 27001, etc.

---

## Recommended Tech Stack Decisions

### Confirmed Tech Stack:

1. **Database**: MySQL (AWS RDS)
2. **ORM**: Eloquent (Laravel built-in)
3. **UI Framework**: Tailwind CSS with custom Vue components
4. **State Management**: Vue 3 Composables
5. **Authentication**: Firebase Authentication (OAuth preferred, email/password supported)
6. **Backend Deployment**: Laravel on EC2 instance
7. **AWS Scanning**: AWS SDK PHP v3 + custom checks (S3, IAM, EC2, RDS)
8. **Monorepo**: Yes, single repository for the project
9. **CI/CD**: GitHub Actions
10. **Multi-tenancy**: Organizations/Teams (Firebase Auth + MySQL)

### Laravel Application Considerations:

- **Service-Based Architecture**: Services for business logic (AwsSecurityScanner, RulesEngine, individual Scanners)
- **Stateless API**: API routes are stateless for horizontal scaling
- **Session-Based Web**: Web routes use Laravel sessions for Inertia.js
- **Database Connections**: Eloquent with connection pooling (RDS Proxy recommended for multiple instances)
- **Environment Variables**: Store in `.env` or AWS Systems Manager Parameter Store
- **CORS**: Laravel CORS middleware configured for API access
- **Rate Limiting**: Laravel rate limiting middleware (per IP/user)
- **Process Management**: Supervisor for queue workers, systemd for PHP-FPM
- **Health Checks**: Implement health check endpoints for ALB monitoring
- **Logging**: Laravel logging to CloudWatch via log channels
- **Queue Processing**: Laravel Queue with database/SQS driver for async scans
- **Horizontal Scaling**: Ready for Auto Scaling Group with multiple instances behind ALB

---

## Laravel API Integration

### Laravel Application Setup

The backend uses Laravel 12 framework for a structured, scalable API with PHP 8.2+.

#### Key Components:

1. **Laravel Controller Structure**
   ```php
   // app/Http/Controllers/Api/OrganizationsController.php
   namespace App\Http\Controllers\Api;

   use App\Http\Controllers\Controller;
   use App\Http\Requests\StoreOrganizationRequest;
   use App\Models\Organization;
   use Illuminate\Http\Request;

   class OrganizationsController extends Controller
   {
       public function index(Request $request)
       {
           $organizations = $request->user()->organizations;
           return response()->json(['success' => true, 'data' => $organizations]);
       }

       public function store(StoreOrganizationRequest $request)
       {
           $organization = $request->user()->organizations()->create($request->validated());
           return response()->json(['success' => true, 'data' => $organization], 201);
       }
   }
   ```

2. **Laravel Architecture Patterns**
   - Controllers for HTTP request handling
   - Form Requests for validation
   - Middleware for authentication and authorization
   - Services for business logic
   - Jobs for async processing (queue workers)
   - Eloquent models for database access

3. **Code Organization**
   - API Controllers in `app/Http/Controllers/Api/`
   - Services in `app/Services/` (AwsSecurityScanner, RulesEngine, Scanners)
   - Queue Jobs in `app/Jobs/`
   - Middleware in `app/Http/Middleware/`
   - Form Requests in `app/Http/Requests/`

### Firebase Authentication Integration

#### Frontend (Vue 3 + Inertia.js):
- Firebase JS SDK installed via npm (`firebase` package)
- `useFirebase.ts` composable for authentication state
- Handle OAuth flows (Google, Apple, Microsoft)
- Firebase tokens sent in API requests (Authorization header)
- Session-based auth for web routes, token-based for API routes

#### Backend (Laravel):
- Firebase PHP SDK (`kreait/firebase-php` package)
- `VerifyFirebaseToken` middleware for API authentication
- `FirebaseAuthController` for auth endpoints
- Map Firebase UID to database user records via `firebase_uid` column
- `SetOrganizationContext` middleware for multi-tenant context

#### Authentication Flow:
```
1. User authenticates via Firebase (OAuth or email/password)
2. Firebase returns ID token
3. Frontend sends token in Authorization header (API routes)
   OR uses Laravel session auth (web routes via Inertia)
4. Laravel middleware verifies token with Firebase PHP SDK
5. Middleware extracts user info and sets organization context
6. Request proceeds with authenticated user context
```

#### Key Files:
- `app/Http/Middleware/VerifyFirebaseToken.php` - Token verification
- `app/Http/Middleware/SetOrganizationContext.php` - Multi-tenancy
- `app/Http/Controllers/Auth/FirebaseAuthController.php` - Auth endpoints
- `resources/js/composables/useFirebase.ts` - Frontend auth composable

### Multi-Tenancy Architecture

#### Organization Management Feature

**Purpose**: Allow users to create and manage multiple organizations for logical grouping of AWS accounts and resources.

**Data Stored**:
- `Organization Name` (user-defined, visible in UI)
- `OrgId` (auto-generated unique identifier, UUID format, NOT visible in UI)
- `Created At` (timestamp)
- `Updated At` (timestamp)

**User Experience**:
- On user signup, a default organization is automatically created (named after username or "My Organization")
- Users can create additional organizations via "Add Organization" button
- Organization selector dropdown in top navigation bar allows switching between organizations
- All AWS accounts, scans, and reports are scoped to the currently selected organization
- OrgId is used internally for all organization-related queries and data relationships

**API Endpoints**:
- `GET /organizations` - List all organizations for current user
- `POST /organizations` - Create new organization
- `PUT /organizations/{orgId}` - Update organization name
- `DELETE /organizations/{orgId}` - Delete organization (if no AWS accounts attached)
- `GET /organizations/current` - Get currently selected organization

**Database Schema**:
```sql
organizations:
  - id (UUID, primary key)
  - name (VARCHAR, user-visible)
  - orgId (UUID, unique, generated, NOT visible in UI)
  - userId (UUID, foreign key to users)
  - createdAt (TIMESTAMP)
  - updatedAt (TIMESTAMP)
  - isDefault (BOOLEAN, true for first org created)
```

#### AWS Account Management Feature

**Purpose**: Securely connect customer AWS accounts to the security scanning platform using cross-account IAM roles.

**Data Stored**:
- `AWS Account Name` (user-defined, visible in UI)
- `IAM Role ARN` (encrypted at rest, from CloudFormation output)
- `ExternalId` (UUID, generated per account, used for AssumeRole security)
- `UniqueId` (UUID, generated per account, used for identification)
- `Organization OrgId` (links AWS account to organization)
- `AWS Account ID` (12-digit AWS account ID)
- `Status` (pending, active, error)
- `Created At` (timestamp)
- `Last Scan At` (timestamp)

**Onboarding Workflow**:

1. **User Initiates**: User clicks "Add AWS Account" button while viewing an organization
2. **Backend Preparation**:
   - Backend receives current `Organization OrgId` from request context
   - Backend generates:
     - `UniqueId` (UUID) - unique identifier for this AWS account
     - `ExternalId` (UUID) - used for secure AssumeRole
   - Backend constructs CloudFormation URL with parameters:
     ```
     https://console.aws.amazon.com/cloudformation/home?#/stacks/quickcreate?templateUrl={S3_TEMPLATE_URL}&param_ParentAWSAccountId={PARENT_ACCOUNT_ID}&param_ExternalId={EXTERNAL_ID}&param_UniqueId={UNIQUE_ID}
     ```
3. **CloudFormation Execution**:
   - Frontend opens CloudFormation URL in new window/tab
   - User completes CloudFormation stack creation in their AWS Console
   - CloudFormation template creates:
     - IAM Role (`TeemOps`) with AssumeRole permissions for parent account
     - CustomNotifier resource that sends notification to parent account SNS topic
4. **Automatic Registration**:
   - CustomNotifier sends SNS message to parent account with:
     - `TopsRoleArn` - The IAM Role ARN created
     - `TopsExternalId` - The ExternalId parameter
     - `TopsUniqueId` - The UniqueId parameter
     - `TopsType` - Can be used to pass OrgId or other metadata
   - Backend NestJS endpoint (SNS webhook handler) receives notification
   - Backend matches notification by `UniqueId` to pending account record
   - Backend stores IAM Role ARN (encrypted) and marks account as "active"
5. **Manual Fallback** (if notification fails):
   - User can manually enter AWS Account ID and IAM Role ARN
   - Backend validates and stores account information

**API Endpoints**:
- `GET /organizations/{orgId}/aws-accounts` - List AWS accounts for organization
- `POST /organizations/{orgId}/aws-accounts/init` - Generate CloudFormation URL
- `POST /organizations/{orgId}/aws-accounts` - Create AWS account (manual fallback)
- `GET /aws-accounts/{accountId}` - Get AWS account details
- `PUT /aws-accounts/{accountId}` - Update AWS account (name, etc.)
- `DELETE /aws-accounts/{accountId}` - Remove AWS account
- `POST /aws-accounts/sns-callback` - SNS webhook for CloudFormation notifications

**Database Schema**:
```sql
aws_accounts:
  - id (UUID, primary key)
  - name (VARCHAR, user-defined)
  - awsAccountId (VARCHAR(12), AWS account ID)
  - iamRoleArn (TEXT, encrypted)
  - externalId (UUID, unique, for AssumeRole)
  - uniqueId (UUID, unique, for matching SNS notifications)
  - organizationId (UUID, foreign key to organizations.orgId)
  - status (ENUM: pending, active, error)
  - createdAt (TIMESTAMP)
  - updatedAt (TIMESTAMP)
  - lastScanAt (TIMESTAMP, nullable)
```

**Security Considerations**:
- IAM Role ARN encrypted at rest using AES-256
- ExternalId ensures only parent account can assume role
- UniqueId prevents account hijacking
- Organization OrgId ensures proper data isolation
- SNS notification includes signature verification

### AWS Cross-Account Role Workflow (Detailed)

**Complete Flow**:

```
┌─────────────┐
│   User      │
└──────┬──────┘
       │ 1. Clicks "Add AWS Account"
       ▼
┌─────────────────────────────────┐
│  Frontend (Vue/Inertia)         │
│  - Gets current OrgId           │
│  - Calls API to init account    │
└──────┬──────────────────────────┘
       │ 2. POST /organizations/{orgId}/aws-accounts/init
       ▼
┌─────────────────────────────────┐
│  Backend (Laravel)              │
│  - Generates UniqueId (UUID)    │
│  - Generates ExternalId (UUID)  │
│  - Creates pending record       │
│  - Returns CloudFormation URL   │
└──────┬──────────────────────────┘
       │ 3. Returns URL with params
       ▼
┌─────────────────────────────────┐
│  Frontend                       │
│  - Opens CloudFormation URL     │
│  - New window/tab               │
└──────┬──────────────────────────┘
       │ 4. User completes CF stack
       ▼
┌─────────────────────────────────┐
│  AWS Console                    │
│  - CloudFormation creates:      │
│    • IAM Role (TeemOps)         │
│    • CustomNotifier             │
└──────┬──────────────────────────┘
       │ 5. CustomNotifier sends SNS
       ▼
┌─────────────────────────────────┐
│  Parent Account SNS Topic       │
│  - Receives notification        │
│  - Sends to Laravel endpoint    │
└──────┬──────────────────────────┘
       │ 6. Laravel processes notification
       ▼
┌─────────────────────────────────┐
│  Backend (Laravel SNS Handler)  │
│  - Verifies SNS signature       │
│  - Extracts: RoleArn, ExternalId│
│    UniqueId, OrgId              │
│  - Matches by UniqueId          │
│  - Stores encrypted RoleArn     │
│  - Marks account as "active"    │
└──────┬──────────────────────────┘
       │ 7. Account ready for scanning
       ▼
┌─────────────────────────────────┐
│  Frontend                       │
│  - Polls for account status     │
│  - Shows "Active" when ready    │
└─────────────────────────────────┘
```

**CloudFormation Template Parameters**:
- `ParentAWSAccountId` - Parent account ID (from environment variable)
- `ExternalId` - Generated UUID for secure AssumeRole
- `UniqueId` - Generated UUID for account identification

**SNS Notification Payload**:
```json
{
  "TopsRoleArn": "arn:aws:iam::123456789012:role/TeemOps",
  "TopsExternalId": "uuid-external-id",
  "TopsUniqueId": "uuid-unique-id",
  "TopsType": "org-uuid-here" // Can encode OrgId here
}
```

### Environment Variables Needed

#### Laravel Application (.env):
```
# Application
APP_NAME=TeemOps
APP_ENV=production
APP_KEY=base64:your-app-key
APP_DEBUG=false
APP_URL=https://app.teemops.com

# Database
DB_CONNECTION=mysql
DB_HOST=rds-endpoint.region.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=teemops
DB_USERNAME=teemops
DB_PASSWORD=your-db-password

# Firebase Authentication
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_CREDENTIALS=/path/to/firebase-credentials.json

# AWS Configuration
AWS_REGION=us-west-2
AWS_PARENT_ACCOUNT_ID=123456789012
AWS_ACCESS_KEY_ID=your-access-key  # Or use EC2 instance role
AWS_SECRET_ACCESS_KEY=your-secret-key

# SNS Configuration
SNS_TOPIC_ARN=arn:aws:sns:us-west-2:account-id:teemops-sns

# CloudFormation Template
CLOUDFORMATION_TEMPLATE_URL=https://s3.amazonaws.com/storage.auditaws.com/iam.role.child.account.cfn.yaml

# Queue Configuration
QUEUE_CONNECTION=database  # Or 'sqs' for AWS SQS

# Session/Cache
SESSION_DRIVER=database
CACHE_STORE=database
```

#### Frontend (Vite environment variables):
```
VITE_FIREBASE_API_KEY=your-api-key
VITE_FIREBASE_AUTH_DOMAIN=your-project.firebaseapp.com
VITE_FIREBASE_PROJECT_ID=your-project-id
VITE_FIREBASE_STORAGE_BUCKET=your-project.appspot.com
VITE_FIREBASE_MESSAGING_SENDER_ID=your-sender-id
VITE_FIREBASE_APP_ID=your-app-id
```

---

## Deployment Infrastructure Specifications

### EC2 Instance Configuration
- **Instance Type**: t3.small
- **Operating System**: Ubuntu 22.04 LTS
- **Storage**: 100GB EBS Volume (gp3)
- **Region**: us-west-2
- **Software Stack**:
  - PHP 8.2+ with PHP-FPM
  - Nginx web server
  - Composer (PHP package manager)
  - Node.js 18+ (for Vite build)
  - Supervisor (for queue workers)
- **Security Groups**:
  - HTTP (80) from ALB
  - HTTPS (443) from ALB
  - SSH (22) from management IP only

### Application Load Balancer (Day 1)
- **Type**: Application Load Balancer
- **Domain**: app.teemops.com
- **SSL/TLS**: ACM certificate
- **Health Checks**: Configured for Laravel health endpoint (`/up` or custom)
- **Target Group**: EC2 instance(s) on port 80 (Nginx)
- **Region**: us-west-2

### SNS Configuration
- **Topic Name**: teemops-sns (created via CloudFormation)
- **Region**: us-west-2
- **Purpose**: Receive notifications from customer CloudFormation stacks
- **Subscriber**: Laravel webhook endpoint `/api/aws-accounts/sns-callback`
- **Signature Verification**: `SnsSignatureVerifier.php` service validates SNS message authenticity

### CloudFormation Template
- **S3 URL**: Configurable via environment variable `CLOUDFORMATION_TEMPLATE_URL`
- **Default**: `https://s3.amazonaws.com/storage.auditaws.com/iam.role.child.account.cfn.yaml`
- **Parameters**:
  - `ParentAWSAccountId` - From environment variable
  - `ExternalId` - Generated UUID
  - `UniqueId` - Generated UUID

### Monitoring & Logging
- **CloudWatch Logs**: Application logs with 7-day retention (configurable)
- **Laravel Logging**: Configured via `config/logging.php` with CloudWatch channel
- **CloudWatch Metrics**: 
  - EC2 instance metrics (CPU, memory, network)
  - Application metrics (request count, latency, errors)
  - Database connection pool metrics
  - Queue worker metrics (jobs processed, failed)
- **Log Groups**:
  - `/aws/ec2/teemops/laravel` - Laravel application logs
  - `/aws/ec2/teemops/nginx` - Nginx access/error logs
  - `/aws/ec2/teemops/queue` - Queue worker logs

### Scaling Strategy
- **Initial Load**: 20 users
- **Architecture**: Stateless API, session-based web (horizontal scaling ready with sticky sessions)
- **Day 1**: Application Load Balancer with single EC2 instance
- **Queue Workers**: Supervisor managing Laravel queue workers for async scan processing
- **Future Scaling**: 
  - Auto Scaling Group behind ALB
  - Sticky sessions for web routes (Inertia.js)
  - Scale based on CPU utilization or request count
  - Minimum: 1 instance, Maximum: 10 instances (configurable)
  - Target: 70% CPU utilization
  - Separate queue worker instances for heavy scanning loads

### IAM Roles & Permissions
EC2 instance role requires:
- **RDS Access**: Connect to MySQL database
- **SNS Access**: Subscribe to SNS topic for CloudFormation notifications
- **SQS Access**: Send/receive messages (if using SQS queue driver)
- **Systems Manager**: Read Parameter Store values
- **Secrets Manager**: Read secrets (if used)
- **CloudWatch**: Write logs and metrics
- **STS AssumeRole**: Assume roles in customer AWS accounts for scanning
- **S3 Access**: Read CloudFormation templates (if self-hosted)

---

## API Endpoints Specification

### Base URL
All API endpoints are prefixed with `/api` (e.g., `/api/organizations`)

### Authentication
All endpoints (except SNS webhook) require Firebase ID token in the `Authorization` header:
```
Authorization: Bearer <firebase-id-token>
```

### Response Format
All responses follow a consistent format:
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional message"
}
```

Error responses:
```json
{
  "success": false,
  "error": "Error message",
  "statusCode": 400
}
```

---

### 1. Authentication & Users

#### Sync User from Firebase
```
POST /api/users/sync
Description: Creates or updates user record in database after Firebase authentication
Request Body: {
  firebaseUid: string,
  email: string,
  displayName?: string
}
Response: {
  success: true,
  data: {
    id: string,
    firebaseUid: string,
    email: string,
    displayName: string,
    createdAt: string
  }
}
```

#### Get Current User
```
GET /api/users/me
Description: Get current authenticated user information
Response: {
  success: true,
  data: {
    id: string,
    firebaseUid: string,
    email: string,
    displayName: string,
    defaultOrgId: string
  }
}
```

---

### 2. Organizations

#### List Organizations
```
GET /api/organizations
Description: Get all organizations for current user
Response: {
  success: true,
  data: {
    organizations: [
      {
        id: string,
        name: string,
        orgId: string,
        isDefault: boolean,
        createdAt: string,
        updatedAt: string
      }
    ]
  }
}
```

#### Create Organization
```
POST /api/organizations
Request Body: {
  name: string
}
Response: {
  success: true,
  data: {
    id: string,
    name: string,
    orgId: string,
    isDefault: boolean,
    createdAt: string
  }
}
```

#### Get Organization by ID
```
GET /api/organizations/{orgId}
Response: {
  success: true,
  data: {
    id: string,
    name: string,
    orgId: string,
    isDefault: boolean,
    createdAt: string,
    updatedAt: string
  }
}
```

#### Update Organization
```
PUT /api/organizations/{orgId}
Request Body: {
  name: string
}
Response: {
  success: true,
  data: {
    id: string,
    name: string,
    orgId: string,
    updatedAt: string
  }
}
```

#### Delete Organization
```
DELETE /api/organizations/{orgId}
Response: {
  success: true,
  data: {
    message: "Organization deleted successfully"
  }
}
```

#### Get Current Organization
```
GET /api/organizations/current
Description: Get currently selected/default organization
Response: {
  success: true,
  data: {
    id: string,
    name: string,
    orgId: string,
    isDefault: boolean
  }
}
```

---

### 3. AWS Accounts

#### Initialize AWS Account Addition
```
POST /api/organizations/{orgId}/aws-accounts/init
Description: Generate CloudFormation URL and create pending account record
Response: {
  success: true,
  data: {
    accountId: string,
    uniqueId: string,
    cloudFormationUrl: string,
    status: "pending"
  }
}
```

#### List AWS Accounts
```
GET /api/organizations/{orgId}/aws-accounts
Response: {
  success: true,
  data: {
    accounts: [
      {
        id: string,
        name: string,
        awsAccountId: string,
        status: "pending" | "active" | "error",
        lastScanAt: string | null,
        createdAt: string
      }
    ]
  }
}
```

#### Get AWS Account Details
```
GET /api/aws-accounts/{accountId}
Response: {
  success: true,
  data: {
    id: string,
    name: string,
    awsAccountId: string,
    status: "pending" | "active" | "error",
    createdAt: string,
    updatedAt: string,
    lastScanAt: string | null
  }
}
```

#### Create AWS Account (Manual Fallback)
```
POST /api/organizations/{orgId}/aws-accounts
Request Body: {
  name: string,
  awsAccountId: string,
  iamRoleArn: string
}
Response: {
  success: true,
  data: {
    id: string,
    name: string,
    awsAccountId: string,
    status: "active",
    createdAt: string
  }
}
```

#### Update AWS Account
```
PUT /api/aws-accounts/{accountId}
Request Body: {
  name: string
}
Response: {
  success: true,
  data: {
    id: string,
    name: string,
    updatedAt: string
  }
}
```

#### Delete AWS Account
```
DELETE /api/aws-accounts/{accountId}
Response: {
  success: true,
  data: {
    message: "AWS account deleted successfully"
  }
}
```

#### SNS Webhook Callback
```
POST /api/aws-accounts/sns-callback
Description: Receives SNS notification from CloudFormation stack
Headers: {
  'x-amz-sns-message-type': 'Notification'
}
Request Body: {
  // SNS message payload
  Message: {
    TopsRoleArn: string,
    TopsExternalId: string,
    TopsUniqueId: string,
    TopsType?: string
  }
}
Response: {
  success: true,
  data: {
    accountId: string,
    status: "active"
  }
}
```

---

### 4. Scans

#### Create Scan
```
POST /api/scans
Request Body: {
  awsAccountId: string,
  scanType?: "full" | "quick",
  scanOptions?: {
    services?: string[],  // ["s3", "iam", "ec2", "rds"]
    complianceFrameworks?: string[]  // ["cis", "pci", "soc2"]
  }
}
Response: {
  success: true,
  data: {
    id: string,
    awsAccountId: string,
    status: "pending" | "running" | "completed" | "failed",
    createdAt: string
  }
}
```

#### List Scans
```
GET /api/scans
Query Parameters: {
  organizationId?: string,
  awsAccountId?: string,
  status?: "pending" | "running" | "completed" | "failed",
  limit?: number,
  offset?: number
}
Response: {
  success: true,
  data: {
    scans: [
      {
        id: string,
        awsAccountId: string,
        awsAccountName: string,
        status: string,
        findingsCount: number,
        createdAt: string,
        completedAt: string | null
      }
    ],
    total: number,
    limit: number,
    offset: number
  }
}
```

#### Get Scan Details
```
GET /api/scans/{scanId}
Response: {
  success: true,
  data: {
    id: string,
    awsAccountId: string,
    awsAccountName: string,
    status: "pending" | "running" | "completed" | "failed",
    scanType: string,
    findingsCount: number,
    createdAt: string,
    startedAt: string | null,
    completedAt: string | null,
    error: string | null
  }
}
```

#### Cancel Scan
```
POST /api/scans/{scanId}/cancel
Response: {
  success: true,
  data: {
    id: string,
    status: "cancelled"
  }
}
```

---

### Findings Feature (User Story & Expected Behaviour)

**User story:** As a user I want to view and analyse findings so I can remediate issues.

**Sections:**
1. **Summary panel (top):** Executive Summary — Overall Security Score; total findings per severity (critical, high, medium, low).
2. **Detailed Findings panel (below):** View all findings sorted by severity first, then date (oldest unresolved first). Findings only take into consideration the latest status of an issue; an issue is only hidden if the latest record for that issue (compared against the ruleset) has passed as not an issue.
3. **Filters:** AWS Account, Type (finding type / service).
4. **Groupings for remediation:** Findings can be grouped by recommendation (see recommendations/tips file, e.g. `references/code/findings/tips.json`). Each recommendation (e.g. tops-rec-001) groups one or more rules (e.g. tops-s3-001, tops-s3-002). Tips/recommendations must be stored in the app (e.g. `app/rules/recommendations/`) and updated when new rulesets are added.
5. **Expand a finding:** View remediation steps (steps, links, description, impact from the recommendation that contains that rule).
6. **Per-finding-type page:** Each finding type (rule ID) can be viewed on a separate page showing all resources related to that finding and remediation.

**Data source:** Recommendations/tips JSON (schema: recommendations with name, recommendation, impact, links, description, steps, rules[]). Scan results use `finding_type` = rule ID; recommendations reference rules via `rules: ["tops-iam-001", ...]`.

---

### 5. Scan Results

#### Get Scan Results
```
GET /api/scans/{scanId}/results
Query Parameters: {
  severity?: "critical" | "high" | "medium" | "low" | "info",
  service?: string,  // "s3", "iam", "ec2", "rds"
  status?: "open" | "resolved" | "ignored",
  limit?: number,
  offset?: number
}
Response: {
  success: true,
  data: {
    scanId: string,
    findings: [
      {
        id: string,
        title: string,
        description: string,
        severity: "critical" | "high" | "medium" | "low" | "info",
        service: string,
        resourceId: string,
        resourceType: string,
        status: "open" | "resolved" | "ignored",
        complianceFrameworks: string[],
        remediation: string,
        createdAt: string
      }
    ],
    summary: {
      total: number,
      critical: number,
      high: number,
      medium: number,
      low: number,
      info: number
    },
    total: number,
    limit: number,
    offset: number
  }
}
```

#### Get Finding Details
```
GET /api/results/{findingId}
Response: {
  success: true,
  data: {
    id: string,
    scanId: string,
    title: string,
    description: string,
    severity: string,
    service: string,
    resourceId: string,
    resourceType: string,
    resourceArn: string,
    status: string,
    complianceFrameworks: string[],
    remediation: string,
    evidence: object,
    createdAt: string,
    updatedAt: string
  }
}
```

#### Update Finding Status
```
PUT /api/results/{findingId}
Request Body: {
  status: "open" | "resolved" | "ignored",
  comment?: string
}
Response: {
  success: true,
  data: {
    id: string,
    status: string,
    updatedAt: string
  }
}
```

#### Export Scan Results
```
GET /api/scans/{scanId}/export
Query Parameters: {
  format: "pdf" | "csv" | "json"
}
Response: File download or JSON response
```

#### Get Results Summary/Dashboard
```
GET /api/results/summary
Query Parameters: {
  organizationId?: string,
  awsAccountId?: string,
  dateFrom?: string,
  dateTo?: string
}
Response: {
  success: true,
  data: {
    totalFindings: number,
    bySeverity: {
      critical: number,
      high: number,
      medium: number,
      low: number,
      info: number
    },
    byService: {
      s3: number,
      iam: number,
      ec2: number,
      rds: number
    },
    byStatus: {
      open: number,
      resolved: number,
      ignored: number
    },
    trends: [
      {
        date: string,
        findings: number
      }
    ]
  }
}
```

---

### 6. Health & Status

#### Health Check
```
GET /api/health
Description: Check API health status
Response: {
  status: "ok",
  timestamp: string,
  uptime: number
}
```

#### API Status
```
GET /api/status
Description: Get API version and status information
Response: {
  status: "ok",
  version: string,
  environment: string,
  database: "connected" | "disconnected"
}
```

---

## Future Roadmap

### High Priority Items

#### Multi-Factor Authentication (MFA)
**Feature**: Full MFA support for user accounts

**Description**:
- **Primary**: TOTP (authenticator app: Google Authenticator, Authy, etc.)
- **Fallback**: Email OTP sent for each login when TOTP is unavailable (e.g., lost device)
- **Profile UI**: Add MFA (setup wizard), view MFA device info, remove MFA (with re-auth)
- **Security UX**: Persistent alert at top of screen when MFA is not enabled—visible until user enables MFA or explicitly dismisses (optional: allow dismiss with reminder)

**User flows**:
1. **Add MFA**: Profile → MFA section → "Enable MFA" → Scan QR / enter secret → Verify with code → Done
2. **View MFA**: Profile → MFA section → Shows device type, last used (if available)
3. **Remove MFA**: Profile → MFA section → "Remove MFA" → Confirm with password or current TOTP → MFA disabled
4. **Login OTP fallback**: Login → Password correct → "Use authenticator app" OR "Email me a code" → If email: enter 6-digit OTP from email → Authenticated

**Implementation Notes**:
- Firebase Auth supports TOTP MFA natively; use Firebase MFA APIs
- Email OTP: Backend sends one-time code to user email; code valid for 5–10 minutes
- Rate limit OTP requests (e.g., 3 per 15 min per user) to prevent abuse

---

### Low Priority Items

#### Firebase Authentication Feature Flag
**Feature**: Add environment variable feature flag for Firebase vs Laravel authentication

**Description**: 
- Add a feature flag as an environment variable `FIREBASE_USER_AUTH=true`
- When `FIREBASE_USER_AUTH=true`: Username/password authentication uses Firebase User/pass authentication
- When `FIREBASE_USER_AUTH=false`: Fallback to native Laravel user/password authentication for login/register

**Implementation Notes**:
- This allows switching between Firebase Authentication and Laravel's built-in authentication system
- The feature flag should be checked at runtime to determine which authentication provider to use
- Both authentication methods should maintain the same user experience and API contracts

#### Secure Custom Rulesets Protection for Conditions
**Feature**: Implement security measures to prevent arbitrary code execution in custom ruleset conditions

**Description**:
- Currently, the `ConditionEvaluator` uses PHP `eval()` to evaluate condition expressions from rulesets (e.g., `basic.json`, `cis.json`, `pci.json`)
- The current implementation is safe because we control all ruleset files
- In the future, we may allow customers to create and upload their own custom rulesets
- Without proper security measures, malicious conditions could execute arbitrary PHP code, leading to:
  - Remote code execution (RCE)
  - Data exfiltration
  - System compromise
  - Privilege escalation

**Security Requirements**:
- Prevent execution of arbitrary PHP code in condition expressions
- Allow only safe, whitelisted PHP functions and operations
- Validate and sanitize all condition expressions before evaluation
- Implement sandboxing or restricted execution environment
- Log all condition evaluations for security auditing

**Potential Implementation Approaches**:
1. **AST-based Parser**: Parse PHP expressions into an Abstract Syntax Tree (AST) and validate against a whitelist of allowed operations
2. **Expression Language Library**: Use a dedicated expression evaluator library (e.g., Symfony ExpressionLanguage) that provides built-in security
3. **Sandboxed Execution**: Run condition evaluation in an isolated environment with restricted capabilities
4. **Whitelist Validation**: Pre-validate conditions against a strict whitelist of allowed functions, operators, and patterns
5. **Template-based Approach**: Provide a template system where customers can only use predefined condition templates

**Implementation Notes**:
- This is a critical security feature that must be implemented before allowing customer-uploaded rulesets
- Consider implementing this as a separate "secure" mode that can be enabled when custom rulesets are allowed
- Maintain backward compatibility with existing rulesets (basic.json, cis.json, pci.json)
- Document allowed condition patterns and provide validation feedback to customers
- Consider rate limiting condition evaluations to prevent resource exhaustion attacks

**Related Files**:
- `app/app/Services/RulesEngine/ConditionEvaluator.php` - Current implementation using `eval()`
- `app/rules/rulesets/*.json` - Ruleset files containing condition expressions (basic.json, cis.json, pci.json)

---

## Next Actions

Once you provide answers to the questions above, I can:
1. Initialize the project structure
2. Set up both frontend and backend with proper configuration
3. Implement authentication
4. Build the AWS scanning foundation
5. Create the initial UI

Would you like me to proceed with the recommended defaults, or do you have specific preferences for any of the questions above?

---

## AWS Security Scanning Feature Roadmap

### Research Summary

This roadmap is based on comprehensive research of:
- Top 20 AWS services requiring security auditing
- Top 100 AWS security misconfigurations and vulnerabilities
- CIS AWS Foundations Benchmark v5.0.0 (40 controls)
- AWS Security Hub CSPM controls (500+ controls)
- Prowler security checks (584+ checks across 85 AWS services)
- Industry best practices from 2025-2026

### Current Implementation Status

**Currently Implemented Services:**
- ✅ S3 (4 checks)
- ✅ IAM (10 checks)
- ✅ EC2/VPC (5 checks)
- ✅ RDS (6 checks)

**Total Current Checks:** ~25 rules

---

### Top 20 AWS Services Requiring Security Auditing

Prioritized by enterprise adoption and security impact:

| Priority | Service | Description | Current Status |
|----------|---------|-------------|----------------|
| 1 | **IAM** | Identity and Access Management | ✅ Partial |
| 2 | **S3** | Object Storage | ✅ Partial |
| 3 | **EC2** | Compute Instances | ✅ Partial |
| 4 | **VPC** | Network Security | ✅ Partial |
| 5 | **RDS** | Relational Databases | ✅ Partial |
| 6 | **CloudTrail** | Audit Logging | ❌ Not Implemented |
| 7 | **Lambda** | Serverless Functions | ❌ Not Implemented |
| 8 | **KMS** | Key Management | ❌ Not Implemented |
| 9 | **Secrets Manager** | Secrets Storage | ❌ Not Implemented |
| 10 | **CloudWatch** | Monitoring & Logging | ❌ Not Implemented |
| 11 | **EKS/ECS** | Container Services | ❌ Not Implemented |
| 12 | **SNS/SQS** | Messaging Services | ❌ Not Implemented |
| 13 | **API Gateway** | API Management | ❌ Not Implemented |
| 14 | **CloudFront** | CDN & Edge Security | ❌ Not Implemented |
| 15 | **ELB/ALB** | Load Balancers | ❌ Not Implemented |
| 16 | **DynamoDB** | NoSQL Database | ❌ Not Implemented |
| 17 | **Route 53** | DNS Security | ❌ Not Implemented |
| 18 | **ACM** | Certificate Management | ❌ Not Implemented |
| 19 | **Config** | Configuration Compliance | ❌ Not Implemented |
| 20 | **GuardDuty** | Threat Detection | ❌ Not Implemented |

---

### Top 100 AWS Security Issues by Category

#### Category 1: Identity & Access Management (IAM) - 20 Issues

**HIGH SEVERITY:**
1. Root account without MFA enabled
2. Root account access keys exist
3. IAM users without MFA
4. IAM policies with wildcard (*:*) permissions
5. IAM users with AdministratorAccess policy
6. IAM users with console access but no MFA
7. Inactive IAM users (90+ days)
8. Access keys not rotated (90+ days)
9. IAM password policy not compliant
10. Cross-account IAM role trust relationships too permissive

**MEDIUM SEVERITY:**
11. IAM users with inline policies
12. IAM groups with inline policies
13. IAM roles with inline policies
14. IAM users not in groups
15. IAM policies attached directly to users
16. IAM users with multiple access keys
17. Console passwords not rotated (90+ days)
18. IAM roles with excessive permissions
19. Service-linked roles with overly permissive policies
20. IAM Access Analyzer not enabled

#### Category 2: Storage Security (S3, EBS, EFS) - 15 Issues

**HIGH SEVERITY:**
21. S3 buckets publicly accessible
22. S3 buckets without encryption at rest
23. S3 bucket policies allowing public access
24. S3 buckets with ACL allowing AllUsers
25. EBS volumes unencrypted
26. EBS snapshots shared publicly

**MEDIUM SEVERITY:**
27. S3 buckets without versioning
28. S3 buckets without logging enabled
29. S3 buckets without lifecycle policies
30. S3 Object Lock not enabled for compliance data
31. EFS file systems unencrypted
32. EFS without backup policy
33. S3 buckets without MFA delete enabled
34. S3 cross-region replication not enabled for DR
35. S3 bucket keys not enabled for cost optimization

#### Category 3: Network Security (VPC, Security Groups, NACLs) - 15 Issues

**HIGH SEVERITY:**
36. Security groups allowing 0.0.0.0/0 on SSH (22)
37. Security groups allowing 0.0.0.0/0 on RDP (3389)
38. Security groups allowing 0.0.0.0/0 on all ports
39. Default VPC in use
40. VPC flow logs not enabled
41. Network ACLs allowing unrestricted inbound traffic

**MEDIUM SEVERITY:**
42. Security groups with unrestricted outbound rules
43. Subnets auto-assign public IP enabled
44. Missing NAT Gateway for private subnet internet access
45. VPC endpoints not configured for AWS services
46. Unused security groups
47. Unused Elastic IP addresses
48. VPC peering without proper route table configuration
49. Transit Gateway attachments without encryption
50. Network firewall not configured

#### Category 4: Compute Security (EC2, Lambda, ECS/EKS) - 15 Issues

**HIGH SEVERITY:**
51. EC2 instances with public IP addresses
52. EC2 instances with IMDSv1 enabled (SSRF vulnerable)
53. Lambda functions with wildcard IAM permissions
54. Lambda functions in public subnet
55. EKS cluster endpoint publicly accessible
56. ECS tasks running as root

**MEDIUM SEVERITY:**
57. EC2 instances without termination protection
58. EC2 instances using default security group
59. Lambda functions without VPC configuration
60. Lambda environment variables with secrets in plaintext
61. ECS tasks without logging enabled
62. EKS cluster logging not enabled
63. EC2 instances without detailed monitoring
64. Auto Scaling groups without health checks
65. Lambda functions with deprecated runtimes

#### Category 5: Database Security (RDS, DynamoDB, ElastiCache) - 12 Issues

**HIGH SEVERITY:**
66. RDS instances publicly accessible
67. RDS instances without encryption
68. RDS snapshots shared publicly
69. DynamoDB tables without encryption
70. ElastiCache clusters without encryption in transit

**MEDIUM SEVERITY:**
71. RDS instances without Multi-AZ
72. RDS automated backups disabled
73. RDS instances with default parameter groups
74. DynamoDB tables without point-in-time recovery
75. ElastiCache without automatic failover
76. RDS instances without enhanced monitoring
77. Aurora clusters without deletion protection

#### Category 6: Logging & Monitoring (CloudTrail, CloudWatch, Config) - 10 Issues

**HIGH SEVERITY:**
78. CloudTrail not enabled in all regions
79. CloudTrail logs not encrypted
80. CloudTrail log file validation disabled
81. CloudWatch Log Groups without retention policy

**MEDIUM SEVERITY:**
82. CloudTrail not integrated with CloudWatch
83. AWS Config not enabled
84. GuardDuty not enabled
85. Security Hub not enabled
86. CloudWatch alarms not configured for root login
87. VPC flow logs not sent to CloudWatch

#### Category 7: Encryption & Key Management (KMS, ACM, Secrets Manager) - 8 Issues

**HIGH SEVERITY:**
88. KMS keys without rotation enabled
89. KMS keys scheduled for deletion
90. Secrets Manager secrets without rotation
91. ACM certificates expiring soon (<30 days)

**MEDIUM SEVERITY:**
92. KMS keys with overly permissive policies
93. Secrets Manager without VPC endpoint
94. ACM certificates using RSA-1024
95. Customer managed keys not used for sensitive data

#### Category 8: Application Security (API Gateway, CloudFront, ELB) - 5 Issues

**HIGH SEVERITY:**
96. API Gateway without authentication
97. CloudFront without WAF
98. ALB without HTTPS listener
99. ALB using outdated TLS policy

**MEDIUM SEVERITY:**
100. CloudFront without access logging

---

### Prioritized Feature Roadmap

Features are prioritized by:
1. **Security Impact** - How critical is this for AWS account security
2. **Implementation Simplicity** - How easy is it to implement

#### Stage 1: Foundation Security (HIGH Impact, SIMPLE Implementation)
*Timeline: Sprint 1-2*

**1.1 CloudTrail Security Scanner** ⭐ HIGHEST PRIORITY
- [ ] Check CloudTrail enabled in all regions
- [ ] Check CloudTrail log encryption enabled
- [ ] Check CloudTrail log file validation enabled
- [ ] Check CloudTrail integrated with CloudWatch
- [ ] Check CloudTrail S3 bucket not publicly accessible
- [ ] Check CloudTrail logging for global services

*Impact: Critical for audit compliance and incident response*
*Complexity: Low - Simple API calls*

**1.2 Enhanced IAM Scanner**
- [ ] Root account MFA check
- [ ] Root account access keys check
- [ ] IAM password policy compliance
- [ ] Access key rotation check (90+ days)
- [ ] Inactive user detection (90+ days)
- [ ] Console password rotation check
- [ ] IAM Access Analyzer enabled check
- [ ] Support policy analysis for wildcards

*Impact: Prevents account compromise*
*Complexity: Low - Extends existing scanner*

**1.3 Enhanced S3 Scanner**
- [ ] S3 bucket logging enabled check
- [ ] S3 bucket lifecycle policy check
- [ ] S3 MFA delete enabled check
- [ ] S3 object lock check for compliance data
- [ ] S3 cross-region replication check

*Impact: Data protection and compliance*
*Complexity: Low - Extends existing scanner*

**1.4 EBS Volume Scanner**
- [ ] EBS volume encryption check
- [ ] EBS snapshot encryption check
- [ ] EBS snapshot public sharing check
- [ ] Unused EBS volumes detection

*Impact: Data protection*
*Complexity: Low - Simple API calls*

#### Stage 2: Network & Compute Security (HIGH Impact, MEDIUM Implementation)
*Timeline: Sprint 3-4*

**2.1 Enhanced VPC/Network Scanner**
- [ ] VPC flow logs enabled check
- [ ] Security group SSH/RDP from 0.0.0.0/0 check
- [ ] Security group all ports from 0.0.0.0/0 check
- [ ] Network ACL unrestricted access check
- [ ] Unused security groups detection
- [ ] Unused Elastic IPs detection
- [ ] VPC endpoint configuration check
- [ ] NAT Gateway configuration check

*Impact: Prevents unauthorized access*
*Complexity: Medium - Multiple related checks*

**2.2 EC2 Security Scanner Enhancements**
- [ ] IMDSv2 enforcement check
- [ ] EC2 detailed monitoring check
- [ ] EC2 using default security group check
- [ ] Auto Scaling health check configuration

*Impact: Prevents SSRF and improves visibility*
*Complexity: Medium - Requires instance metadata checks*

**2.3 Lambda Security Scanner** ⭐ NEW SERVICE
- [ ] Lambda IAM role permissions check
- [ ] Lambda VPC configuration check
- [ ] Lambda environment variable secrets check
- [ ] Lambda deprecated runtime check
- [ ] Lambda public URL check
- [ ] Lambda reserved concurrency check

*Impact: Serverless security*
*Complexity: Medium - New scanner implementation*

**2.4 RDS/Database Scanner Enhancements**
- [ ] RDS enhanced monitoring check
- [ ] RDS deletion protection check
- [ ] RDS using default parameter group check
- [ ] RDS Performance Insights check
- [ ] RDS minor version auto-upgrade check

*Impact: Database reliability and security*
*Complexity: Low - Extends existing scanner*

#### Stage 3: Encryption & Secrets (HIGH Impact, MEDIUM Implementation)
*Timeline: Sprint 5-6*

**3.1 KMS Security Scanner** ⭐ NEW SERVICE
- [ ] KMS key rotation enabled check
- [ ] KMS key deletion scheduled check
- [ ] KMS key policy permissions check
- [ ] Customer managed keys usage check
- [ ] KMS key cross-account access check

*Impact: Encryption key management*
*Complexity: Medium - New scanner implementation*

**3.2 Secrets Manager Scanner** ⭐ NEW SERVICE
- [ ] Secrets rotation enabled check
- [ ] Secrets rotation schedule check
- [ ] Secrets without recent access check
- [ ] Secrets with overly permissive policies
- [ ] VPC endpoint for Secrets Manager check

*Impact: Credentials security*
*Complexity: Medium - New scanner implementation*

**3.3 ACM Certificate Scanner** ⭐ NEW SERVICE
- [ ] Certificate expiration check (<30, <7 days)
- [ ] Certificate validation method check
- [ ] Certificate key algorithm check (RSA-2048+)
- [ ] Unused certificates detection
- [ ] Certificate transparency logging check

*Impact: TLS/SSL security*
*Complexity: Low - Simple API calls*

#### Stage 4: Security Services Integration (MEDIUM Impact, MEDIUM Implementation)
*Timeline: Sprint 7-8*

**4.1 GuardDuty Scanner** ⭐ NEW SERVICE
- [ ] GuardDuty enabled in all regions check
- [ ] GuardDuty findings severity check
- [ ] GuardDuty S3 protection enabled check
- [ ] GuardDuty EKS protection enabled check
- [ ] GuardDuty malware protection enabled check

*Impact: Threat detection coverage*
*Complexity: Medium - New scanner implementation*

**4.2 AWS Config Scanner** ⭐ NEW SERVICE
- [ ] Config enabled in all regions check
- [ ] Config recording all resource types check
- [ ] Config delivery channel configured check
- [ ] Config rules compliance status check

*Impact: Configuration compliance*
*Complexity: Medium - New scanner implementation*

**4.3 Security Hub Scanner** ⭐ NEW SERVICE
- [ ] Security Hub enabled check
- [ ] Security Hub standards enabled check
- [ ] Security Hub findings integration check
- [ ] Security Hub cross-region aggregation check

*Impact: Centralized security view*
*Complexity: Medium - New scanner implementation*

**4.4 CloudWatch Security Scanner** ⭐ NEW SERVICE
- [ ] CloudWatch log groups retention check
- [ ] CloudWatch log groups encryption check
- [ ] Root login alarm configured check
- [ ] Unauthorized API call alarm check
- [ ] IAM policy change alarm check
- [ ] Security group change alarm check

*Impact: Monitoring and alerting*
*Complexity: Medium - New scanner implementation*

#### Stage 5: Application & Container Security (MEDIUM Impact, HIGH Implementation)
*Timeline: Sprint 9-12*

**5.1 API Gateway Scanner** ⭐ NEW SERVICE
- [ ] API Gateway authentication check
- [ ] API Gateway authorization check
- [ ] API Gateway WAF integration check
- [ ] API Gateway logging enabled check
- [ ] API Gateway throttling configured check
- [ ] API Gateway TLS version check

*Impact: API security*
*Complexity: High - Complex API structure*

**5.2 CloudFront Scanner** ⭐ NEW SERVICE
- [ ] CloudFront HTTPS enforcement check
- [ ] CloudFront TLS version check
- [ ] CloudFront WAF integration check
- [ ] CloudFront access logging check
- [ ] CloudFront origin access control check
- [ ] CloudFront geo-restriction check

*Impact: CDN and edge security*
*Complexity: High - Multiple configuration points*

**5.3 ELB/ALB Scanner** ⭐ NEW SERVICE
- [ ] ALB HTTPS listener check
- [ ] ALB TLS security policy check
- [ ] ALB access logging check
- [ ] ALB deletion protection check
- [ ] ALB WAF integration check
- [ ] NLB cross-zone load balancing check

*Impact: Load balancer security*
*Complexity: Medium - Multiple load balancer types*

**5.4 EKS Security Scanner** ⭐ NEW SERVICE
- [ ] EKS cluster endpoint private check
- [ ] EKS cluster logging enabled check
- [ ] EKS cluster secrets encryption check
- [ ] EKS node group configuration check
- [ ] EKS pod security policy check

*Impact: Kubernetes security*
*Complexity: Very High - Complex K8s integration*

**5.5 ECS Security Scanner** ⭐ NEW SERVICE
- [ ] ECS task definition secrets check
- [ ] ECS task execution role check
- [ ] ECS cluster Container Insights check
- [ ] ECS service network configuration check
- [ ] Fargate platform version check

*Impact: Container security*
*Complexity: High - Multiple ECS configurations*

#### Stage 6: Messaging & Data Services (MEDIUM Impact, MEDIUM Implementation)
*Timeline: Sprint 13-14*

**6.1 SNS Security Scanner** ⭐ NEW SERVICE
- [ ] SNS topic encryption check
- [ ] SNS topic policy cross-account check
- [ ] SNS topic HTTPS delivery check
- [ ] SNS subscription protocol check

*Impact: Messaging security*
*Complexity: Medium - New scanner implementation*

**6.2 SQS Security Scanner** ⭐ NEW SERVICE
- [ ] SQS queue encryption check
- [ ] SQS queue policy cross-account check
- [ ] SQS dead letter queue configured check
- [ ] SQS VPC endpoint check

*Impact: Queue security*
*Complexity: Medium - New scanner implementation*

**6.3 DynamoDB Scanner** ⭐ NEW SERVICE
- [ ] DynamoDB encryption check
- [ ] DynamoDB point-in-time recovery check
- [ ] DynamoDB deletion protection check
- [ ] DynamoDB auto-scaling check
- [ ] DynamoDB stream encryption check

*Impact: NoSQL database security*
*Complexity: Medium - New scanner implementation*

**6.4 ElastiCache Scanner** ⭐ NEW SERVICE
- [ ] ElastiCache encryption at rest check
- [ ] ElastiCache encryption in transit check
- [ ] ElastiCache automatic failover check
- [ ] ElastiCache auth token check (Redis)
- [ ] ElastiCache automatic backup check

*Impact: Cache security*
*Complexity: Medium - New scanner implementation*

#### Stage 7: Advanced Features (MEDIUM Impact, HIGH Implementation)
*Timeline: Sprint 15-18*

**7.1 EFS Security Scanner** ⭐ NEW SERVICE
- [ ] EFS encryption at rest check
- [ ] EFS encryption in transit check
- [ ] EFS backup policy check
- [ ] EFS lifecycle policy check
- [ ] EFS access point configuration check

*Impact: File storage security*
*Complexity: Medium - New scanner implementation*

**7.2 ECR Security Scanner** ⭐ NEW SERVICE
- [ ] ECR image scanning enabled check
- [ ] ECR encryption check
- [ ] ECR lifecycle policy check
- [ ] ECR repository policy check
- [ ] ECR immutable tags check

*Impact: Container image security*
*Complexity: Medium - New scanner implementation*

**7.3 Route 53 Scanner** ⭐ NEW SERVICE
- [ ] Route 53 DNSSEC enabled check
- [ ] Route 53 health check configuration
- [ ] Route 53 query logging check
- [ ] Route 53 Resolver DNSSEC validation check

*Impact: DNS security*
*Complexity: High - DNS configuration complexity*

**7.4 Cognito Scanner** ⭐ NEW SERVICE
- [ ] Cognito MFA configuration check
- [ ] Cognito password policy check
- [ ] Cognito advanced security check
- [ ] Cognito unauthenticated identities check
- [ ] Cognito WAF integration check

*Impact: Authentication security*
*Complexity: High - Multiple Cognito features*

**7.5 Redshift Scanner** ⭐ NEW SERVICE
- [ ] Redshift encryption check
- [ ] Redshift public accessibility check
- [ ] Redshift SSL enforcement check
- [ ] Redshift audit logging check
- [ ] Redshift automated snapshot check

*Impact: Data warehouse security*
*Complexity: Medium - Similar to RDS*

---

### CIS AWS Foundations Benchmark v5.0 Alignment

The roadmap aligns with CIS AWS Foundations Benchmark v5.0.0 sections:

| CIS Section | Coverage | Implementation Stage |
|-------------|----------|---------------------|
| 1. IAM | Partial → Full | Stage 1 |
| 2. Storage | Partial → Full | Stage 1 |
| 3. Logging | Not Started | Stage 1 |
| 4. Monitoring | Not Started | Stage 4 |
| 5. Networking | Partial → Full | Stage 2 |

---

### Implementation Summary

| Stage | New Services | New Checks | Estimated Sprints |
|-------|--------------|------------|-------------------|
| Stage 1 | 1 (CloudTrail) | ~25 | 2 |
| Stage 2 | 1 (Lambda) | ~25 | 2 |
| Stage 3 | 3 (KMS, Secrets, ACM) | ~20 | 2 |
| Stage 4 | 4 (GuardDuty, Config, Security Hub, CloudWatch) | ~25 | 2 |
| Stage 5 | 5 (API GW, CloudFront, ELB, EKS, ECS) | ~30 | 4 |
| Stage 6 | 4 (SNS, SQS, DynamoDB, ElastiCache) | ~20 | 2 |
| Stage 7 | 5 (EFS, ECR, Route53, Cognito, Redshift) | ~25 | 4 |

**Total New Checks:** ~170 additional security checks
**Total Services:** 24 AWS services (from current 4)
**Total Timeline:** ~18 sprints

---

### Quick Wins (Can be implemented immediately)

These checks can be added to existing scanners with minimal effort:

1. **IAM Scanner Additions** (1-2 days each):
   - Root account MFA check
   - Password policy check
   - Access key age check
   - Inactive user check

2. **S3 Scanner Additions** (1 day each):
   - Bucket logging check
   - Lifecycle policy check

3. **EC2 Scanner Additions** (1 day each):
   - IMDSv2 check
   - Detailed monitoring check

4. **RDS Scanner Additions** (1 day each):
   - Deletion protection check
   - Enhanced monitoring check

---

### Architecture Considerations

For the new scanners, follow the existing Laravel pattern:

1. **Create Scanner Class**: `app/app/Services/Scanners/{Service}Scanner.php`
2. **Create Tasks File**: `app/rules/tasks/{service}/tasks.json`
3. **Add Rules**: `app/rules/rulesets/basic.json` (and `cis.json`, `pci.json` as needed)
4. **Register Scanner**: Update `app/app/Services/ScanTypesService.php`

Each new scanner should:
- Extend the base scanner pattern (see `S3Scanner.php`, `IamScanner.php`)
- Use AWS SDK PHP v3 (`aws/aws-sdk-php`)
- Follow the existing task/action pattern defined in tasks.json
- Support regional and global resources appropriately
- Include proper error handling for missing IAM permissions
- Return data in format compatible with RulesEngine/FindingsEngine

#### Scanner Architecture:
```
ProcessScanJob (main orchestrator)
    └── ProcessRegionScanJob (per-region)
            └── ProcessAuditScanJob (individual scan tasks)
                    └── AwsSecurityScanner
                            ├── S3Scanner
                            ├── IamScanner
                            ├── Ec2Scanner
                            └── RdsScanner
                                    └── RulesEngine
                                            ├── ConditionEvaluator
                                            └── FindingsEngine
```

