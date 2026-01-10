# Cloud Security Application - Planning Document

## Overview
A cloud security scanning application with:
- **Frontend**: Nuxt 3
- **Backend**: NestJS API (Node.js/TypeScript)
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
┌─────────────────┐
│   Nuxt Frontend │  (User Interface - AWS Hosted)
└────────┬────────┘
         │ HTTP/REST API
         │
┌────────▼────────────────────────┐
│  NestJS API (EC2 Instance)      │
│  ┌──────────────────────────┐   │
│  │  Auth Module            │   │
│  │  Organizations Module   │   │
│  │  AWS Accounts Module    │   │
│  │  Scans Module           │   │
│  │  Results Module         │   │
│  │  Users Module           │   │
│  └──────────────────────────┘   │
└────────┬────────────────────────┘
         │
    ┌────┴────┐
    │         │
┌───▼───┐ ┌──▼──────────┐ ┌──────────┐
│  AWS  │ │ Firebase    │ │ MySQL    │
│ Cloud │ │ Auth        │ │ (RDS)    │
└───────┘ └─────────────┘ └──────────┘
```

### Technology Stack

#### Frontend (Nuxt 3)
- **Framework**: Nuxt 3 (Vue 3)
- **UI Library**: PrimeVue (Material Design)
- **State Management**: Pinia
- **HTTP Client**: $fetch (built-in) or Axios
- **Authentication**: Firebase Authentication (OAuth preferred, email/password supported)
- **Firebase SDK**: Firebase JS SDK v9+

#### Backend (NestJS API)
- **Runtime**: Node.js 18+ / TypeScript
- **Framework**: NestJS
- **Deployment**: Single EC2 instance
- **Process Manager**: PM2 or systemd
- **Database**: MySQL (AWS RDS)
- **ORM**: Prisma
- **AWS SDK**: AWS SDK v3 for JavaScript
- **Authentication**: Firebase Admin SDK (for token verification)
- **Validation**: class-validator, class-transformer (NestJS built-in)
- **HTTP**: Express (via NestJS)
- **API Documentation**: Swagger/OpenAPI

#### Infrastructure
- **Deployment**: AWS (EC2, RDS)
- **CI/CD**: GitHub Actions
- **Multi-tenancy**: Organization/Team based (Firebase Auth + MySQL)
- **Load Balancer**: Application Load Balancer (optional, for future scaling)
- **Reverse Proxy**: Nginx (optional, for SSL termination and static assets)

---

## Project Structure

```
tops/
├── frontend/              # Nuxt 3 application
│   ├── components/
│   ├── pages/
│   ├── composables/
│   ├── stores/           # Pinia stores
│   ├── plugins/          # Firebase plugin
│   ├── utils/
│   └── nuxt.config.ts
│
├── backend/              # NestJS API
│   ├── src/
│   │   ├── auth/         # Authentication module
│   │   │   ├── auth.controller.ts
│   │   │   ├── auth.service.ts
│   │   │   ├── auth.guard.ts
│   │   │   └── firebase.strategy.ts
│   │   ├── organizations/  # Organizations module
│   │   │   ├── organizations.controller.ts
│   │   │   ├── organizations.service.ts
│   │   │   └── organizations.module.ts
│   │   ├── aws-accounts/   # AWS Accounts module
│   │   │   ├── aws-accounts.controller.ts
│   │   │   ├── aws-accounts.service.ts
│   │   │   └── aws-accounts.module.ts
│   │   ├── scans/         # Scans module
│   │   │   ├── scans.controller.ts
│   │   │   ├── scans.service.ts
│   │   │   └── scans.module.ts
│   │   ├── results/       # Results module
│   │   │   ├── results.controller.ts
│   │   │   ├── results.service.ts
│   │   │   └── results.module.ts
│   │   ├── users/         # Users module
│   │   │   ├── users.controller.ts
│   │   │   ├── users.service.ts
│   │   │   └── users.module.ts
│   │   ├── common/        # Shared utilities
│   │   │   ├── guards/
│   │   │   ├── interceptors/
│   │   │   ├── filters/
│   │   │   └── decorators/
│   │   ├── prisma/        # Prisma service
│   │   │   └── prisma.service.ts
│   │   └── main.ts        # Application entry point
│   ├── prisma/           # Prisma schema and migrations
│   ├── test/
│   ├── nest-cli.json
│   └── package.json
│
├── shared/               # Shared types/interfaces
│   └── types/
│
├── .github/
│   └── workflows/        # GitHub Actions CI/CD
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
   - Optional MFA
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

4. **Scan Results & Reporting**
   - View scan results
   - Filter and search findings
   - Export reports (PDF, CSV, JSON)
   - Dashboard with statistics

5. **Scheduling**
   - Schedule recurring scans
   - Manual scan triggers

### Phase 2: Multi-Cloud (Future)
- Azure account integration
- GCP account integration
- Cross-cloud compliance reporting

---

## First Steps

### Step 1: Project Initialization
1. Create monorepo structure
2. Initialize Nuxt 3 frontend
3. Initialize NestJS backend
4. Set up NestJS project configuration
5. Set up basic project configuration

### Step 2: Development Environment Setup
1. Configure TypeScript for both projects
2. Set up ESLint/Prettier
3. Configure environment variables
4. Set up Firebase project and configuration
5. Set up Prisma with MySQL

### Step 3: Backend Foundation
1. Set up NestJS project structure
2. Configure NestJS modules and controllers
3. Set up Prisma service for database access
4. Configure database connection (Prisma + MySQL)
5. Set up Firebase Admin SDK for token verification
6. Create authentication guard and strategy
7. Create user and organization modules
8. Set up multi-tenancy support
9. Create API response interceptors and DTOs
10. Set up Swagger/OpenAPI documentation

### Step 4: Frontend Foundation
1. Set up Nuxt 3 project structure
2. Install and configure PrimeVue
3. Configure Firebase Authentication
4. Set up routing with authentication guards
5. Set up Pinia stores (auth, organizations, scans)
6. Create authentication pages (login/register)
7. Set up API client/service layer
8. Create Firebase Auth composables

### Step 5: AWS Integration
1. Create AWS module in NestJS
2. Implement AWS credential management (IAM Role storage, encrypted)
3. Build CloudFormation link generation for cross-account roles
4. Implement AWS scanning service (S3, IAM, EC2, RDS checks)
5. Create scan results storage in MySQL
6. Build API endpoints for scans (NestJS controllers)
7. Set up AWS SDK v3 integration
8. Create SNS webhook handler for account registration

### Step 6: Frontend Integration
1. Create AWS account management UI (with CloudFormation link workflow)
2. Build scan execution interface
3. Create results dashboard with PrimeVue components
4. Implement reporting features (export PDF, CSV, JSON)
5. Create organization/team management UI

### Step 7: EC2 Deployment
1. Set up EC2 instance:
   - Instance type: t3.small
   - Operating system: Ubuntu 22.04 LTS
   - Storage: 100GB EBS Volume (gp3)
   - Security groups: Allow HTTP (80), HTTPS (443), SSH (22)
2. Set up Application Load Balancer:
   - Configure ACM certificate for api.teemops.com
   - Set up target group pointing to EC2 instance
   - Configure health checks
3. Configure Node.js and PM2 process manager
4. Set up Nginx reverse proxy (optional, for static assets)
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
2. **ORM**: Prisma (modern, type-safe, great DX)
3. **UI Framework**: PrimeVue (Material Design)
4. **State Management**: Pinia (official Vue 3 state management)
5. **Authentication**: Firebase Authentication (OAuth preferred, email/password supported)
6. **Backend Deployment**: NestJS API on EC2 instance
7. **AWS Scanning**: AWS SDK v3 + custom checks (S3, IAM, EC2, RDS)
8. **Monorepo**: Yes, single repository for the project
9. **CI/CD**: GitHub Actions
10. **Multi-tenancy**: Organizations/Teams (Firebase Auth + MySQL)

### NestJS API Considerations:

- **Modular Architecture**: NestJS modules for each feature (organizations, aws-accounts, scans, etc.)
- **Stateless Design**: Application is stateless to support horizontal scaling
- **Database Connections**: Use Prisma connection pooling for MySQL (RDS Proxy recommended for multiple instances)
- **Environment Variables**: Store in AWS Systems Manager Parameter Store or Secrets Manager
- **CORS**: Configure CORS middleware for frontend access (api.teemops.com)
- **Rate Limiting**: Implement rate limiting middleware (per IP/user)
- **Process Management**: Use PM2 for process management and auto-restart
- **Health Checks**: Implement health check endpoints for ALB monitoring
- **Logging**: Use Winston or Pino for structured logging to CloudWatch
- **Session Management**: No server-side sessions (stateless, uses Firebase tokens)
- **Horizontal Scaling**: Ready for Auto Scaling Group with multiple instances behind ALB

---

## NestJS API Integration

### NestJS Application Setup

The backend uses NestJS framework for a structured, scalable API with TypeScript support.

#### Key Components:

1. **NestJS Module Structure**
   ```typescript
   // src/organizations/organizations.controller.ts
   import { Controller, Get, Post, Put, Delete, Param, Body, UseGuards } from '@nestjs/common';
   import { AuthGuard } from '../auth/auth.guard';
   import { OrganizationsService } from './organizations.service';

   @Controller('organizations')
   @UseGuards(AuthGuard)
   export class OrganizationsController {
     constructor(private readonly organizationsService: OrganizationsService) {}

     @Get()
     async getOrganizations(@CurrentUser() user) {
       return this.organizationsService.findAll(user.id);
     }

     @Post()
     async createOrganization(@CurrentUser() user, @Body() createDto: CreateOrganizationDto) {
       return this.organizationsService.create(user.id, createDto);
     }
   }
   ```

2. **NestJS Configuration**
   - Modular architecture with feature modules
   - Dependency injection for services
   - Guards for authentication and authorization
   - Interceptors for response transformation
   - Exception filters for error handling
   - DTOs for request/response validation

3. **Module Organization**
   - Separate modules per feature (organizations, aws-accounts, scans, etc.)
   - Shared common module for utilities
   - Prisma service for database access
   - Auth module for Firebase token verification

### Firebase Authentication Integration

#### Frontend (Nuxt 3):
- Install Firebase JS SDK
- Create Firebase plugin for Nuxt
- Set up authentication composables
- Handle OAuth flows (Google, Apple, Microsoft)
- Store Firebase tokens in Pinia store
- Send tokens in API requests (Authorization header)

#### Backend (NestJS API):
- Install Firebase Admin SDK
- Create authentication guard to verify Firebase tokens
- Create custom decorator to extract user information
- Map Firebase UID to database user records
- Support multi-tenant organization context
- Use NestJS guards and interceptors

#### Authentication Flow:
```
1. User authenticates via Firebase (OAuth or email/password)
2. Firebase returns ID token
3. Frontend sends token in Authorization header
4. Backend verifies token with Firebase Admin SDK
5. Backend extracts user info and organization context
6. Request proceeds with authenticated user context
```

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
│  Frontend (Nuxt)                │
│  - Gets current OrgId           │
│  - Calls API to init account    │
└──────┬──────────────────────────┘
       │ 2. POST /organizations/{orgId}/aws-accounts/init
       ▼
┌─────────────────────────────────┐
│  Backend Lambda                 │
│  - Generates UniqueId (UUID)   │
│  - Generates ExternalId (UUID)   │
│  - Creates pending record       │
│  - Returns CloudFormation URL   │
└──────┬──────────────────────────┘
       │ 3. Returns URL with params
       ▼
┌─────────────────────────────────┐
│  Frontend                       │
│  - Opens CloudFormation URL     │
│  - New window/tab              │
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
│  - Triggers Lambda subscriber   │
└──────┬──────────────────────────┘
       │ 6. Lambda processes notification
       ▼
┌─────────────────────────────────┐
│  Backend Lambda (SNS Handler)   │
│  - Extracts: RoleArn, ExternalId,│
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

#### Backend (NestJS on EC2):
```
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_PRIVATE_KEY=your-private-key
FIREBASE_CLIENT_EMAIL=your-client-email
DATABASE_URL=mysql://user:pass@rds-endpoint:3306/dbname
AWS_REGION=us-west-2
AWS_PARENT_ACCOUNT_ID=123456789012
ENCRYPTION_KEY=your-encryption-key-for-iam-roles
PORT=3000
NODE_ENV=production
SNS_TOPIC_ARN=arn:aws:sns:us-west-2:account-id:teemops-sns
CLOUDFORMATION_TEMPLATE_URL=https://s3.amazonaws.com/storage.auditaws.com/iam.role.child.account.cfn.yaml
```

#### Frontend:
```
NUXT_PUBLIC_FIREBASE_API_KEY=your-api-key
NUXT_PUBLIC_FIREBASE_AUTH_DOMAIN=your-project.firebaseapp.com
NUXT_PUBLIC_FIREBASE_PROJECT_ID=your-project-id
NUXT_PUBLIC_API_BASE_URL=https://api.teemops.com
```

---

## Deployment Infrastructure Specifications

### EC2 Instance Configuration
- **Instance Type**: t3.small
- **Operating System**: Ubuntu 22.04 LTS
- **Storage**: 100GB EBS Volume (gp3)
- **Region**: us-west-2
- **Security Groups**:
  - HTTP (80) from ALB
  - HTTPS (443) from ALB
  - SSH (22) from management IP only

### Application Load Balancer (Day 1)
- **Type**: Application Load Balancer
- **Domain**: api.teemops.com
- **SSL/TLS**: ACM certificate
- **Health Checks**: Configured for NestJS health endpoint
- **Target Group**: EC2 instance(s) on port 3000
- **Region**: us-west-2

### SNS Configuration
- **Topic Name**: teemops-sns (created via CloudFormation)
- **Region**: us-west-2
- **Purpose**: Receive notifications from customer CloudFormation stacks
- **Subscriber**: NestJS webhook endpoint `/api/aws-accounts/sns-callback`

### CloudFormation Template
- **S3 URL**: Configurable via environment variable `CLOUDFORMATION_TEMPLATE_URL`
- **Default**: `https://s3.amazonaws.com/storage.auditaws.com/iam.role.child.account.cfn.yaml`
- **Parameters**:
  - `ParentAWSAccountId` - From environment variable
  - `ExternalId` - Generated UUID
  - `UniqueId` - Generated UUID

### Monitoring & Logging
- **CloudWatch Logs**: Application logs with 7-day retention (configurable)
- **CloudWatch Metrics**: 
  - EC2 instance metrics (CPU, memory, network)
  - Application metrics (request count, latency, errors)
  - Database connection pool metrics
- **Log Groups**:
  - `/aws/ec2/teemops-api` - Application logs
  - `/aws/ec2/teemops-api/errors` - Error logs

### Scaling Strategy
- **Initial Load**: 20 users
- **Architecture**: Stateless application (horizontal scaling ready)
- **Day 1**: Application Load Balancer with single EC2 instance
- **Future Scaling**: 
  - Auto Scaling Group behind ALB
  - Scale based on CPU utilization or request count
  - Minimum: 1 instance, Maximum: 10 instances (configurable)
  - Target: 70% CPU utilization

### IAM Roles & Permissions
EC2 instance role requires:
- **RDS Access**: Connect to MySQL database
- **SNS Access**: Publish/subscribe to SNS topic
- **Systems Manager**: Read Parameter Store values
- **Secrets Manager**: Read secrets (if used)
- **CloudWatch**: Write logs and metrics
- **STS AssumeRole**: Assume roles in customer AWS accounts

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

## Next Actions

Once you provide answers to the questions above, I can:
1. Initialize the project structure
2. Set up both frontend and backend with proper configuration
3. Implement authentication
4. Build the AWS scanning foundation
5. Create the initial UI

Would you like me to proceed with the recommended defaults, or do you have specific preferences for any of the questions above?

