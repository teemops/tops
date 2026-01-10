# Architecture Summary

## Key Architecture Decisions

### Application: Laravel Monolith with Vue 3

The application is built as a **Laravel monolith** with a **Vue 3 frontend** using **Inertia.js**. This provides:

- **Single codebase**: Frontend and backend in one repository
- **Type safety**: TypeScript for frontend, PHP type hints for backend
- **Developer experience**: Hot reloading, excellent tooling
- **Simplicity**: No API layer needed for most features (Inertia handles it)
- **Cost efficiency**: Single deployment, easier to maintain
- **Future-ready**: Can add API layer when needed without breaking changes

#### Implementation Approach:
- Laravel controllers handle HTTP requests
- Inertia.js bridges Laravel and Vue seamlessly
- Vue components for UI (Pages, Layouts, Components)
- Eloquent models for database access
- Services for business logic
- Middleware for authentication and authorization
- Form requests for validation

### Authentication: Firebase Authentication

**Firebase Auth** handles all authentication, providing:

- **OAuth support**: Google, GitHub, Microsoft
- **Email/Password**: Traditional authentication method
- **Email verification**: Required for email/password users
- **Token management**: Secure token generation and validation

#### Integration Flow:
1. **Frontend**: User authenticates via Firebase SDK (OAuth or email/password)
2. **Frontend**: Receives Firebase ID token
3. **Frontend**: Sends token to Laravel backend
4. **Backend**: Verifies token using Firebase Admin SDK
5. **Backend**: Creates/updates user in MySQL database
6. **Backend**: Creates Laravel session
7. **Backend**: Redirects to dashboard (via Inertia)

### Multi-Tenancy: Organizations

- Each user belongs to **organizations**
- All data (AWS accounts, scans, results) scoped to organizations
- Firebase Auth provides user identity, MySQL stores organization relationships
- Organization context set via middleware on each request

### AWS Account Management: Cross-Account IAM Roles

**Security-first approach** using AWS cross-account IAM roles:

1. User initiates "Add AWS Account"
2. Backend generates CloudFormation stack URL
3. User opens URL in AWS Console (new window)
4. CloudFormation creates IAM role in customer's AWS account
5. User provides AWS Account ID and IAM Role ARN
6. Backend stores **encrypted** IAM Role ARN in MySQL
7. Backend uses `AssumeRole` to access customer AWS resources

**Benefits**:
- No long-lived access keys stored
- Customer controls IAM role permissions
- Can be revoked by customer at any time
- Follows AWS security best practices

### Database: MySQL

- **MySQL** for relational data (organizations, users, scans, results)
- **Eloquent ORM** for database access
- **Migrations** for schema management
- **Encryption at rest** for sensitive data (IAM role ARNs)
- **Soft deletes** for data retention
- **UUIDs** for primary keys

### Frontend: Vue 3 + Inertia.js + Tailwind CSS

- **Vue 3**: Modern reactive framework
- **Inertia.js**: Seamless Laravel-Vue integration (no API needed)
- **TypeScript**: Type safety for frontend
- **Tailwind CSS v4**: Utility-first styling
- **shadcn-vue**: Component library
- **Firebase SDK**: Client-side authentication

### Deployment: AWS Multi-Tenant SaaS

- **Application Load Balancer** with ACM certificate
- **EC2 instance(s)** behind ALB
- **Organization-based data isolation** in database
- **Laravel application** handles all requests
- **RDS MySQL** shared across all tenants (with proper isolation)
- **Laravel Queues** for background job processing
- **CloudWatch** for monitoring and logging
- **GitHub Actions** for CI/CD
- **Auto Scaling Group** ready for future scaling

### Security Scanning: Custom Checks

Building custom security checks for:
- **S3**: Public access, encryption, versioning
- **IAM**: Policy misconfigurations, unused roles
- **EC2**: Security groups, public IPs, encryption
- **RDS**: Public access, encryption, backups

Future: Integration with AWS Config and Security Hub for additional insights.

---

## Deployment Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    AWS Cloud                             │
│                                                          │
│  ┌──────────────────────────────────────────────────┐   │
│  │   Application Load Balancer (app.teemops.com)   │   │
│  │   - ACM Certificate (SSL/TLS)                    │   │
│  │   - Health Checks                                │   │
│  └──────────────────┬──────────────────────────────┘   │
│                     │                                    │
│  ┌──────────────────▼──────────────────────────────┐   │
│  │   Auto Scaling Group (1-10 instances)          │   │
│  │  ┌──────────────────────────────────────────┐   │   │
│  │  │  EC2 Instance (t3.small, Ubuntu)        │   │   │
│  │  │  ┌────────────────────────────────────┐  │   │   │
│  │  │  │  Laravel Application (Port 8000)  │  │   │   │
│  │  │  │  ┌──────────────────────────────┐  │  │   │   │
│  │  │  │  │  Controllers (Web/API)      │  │  │   │   │
│  │  │  │  │  Middleware (Auth, Org)     │  │  │   │   │
│  │  │  │  │  Services (AWS Scanner)     │  │  │   │   │
│  │  │  │  │  Jobs (Background Tasks)    │  │  │   │   │
│  │  │  │  └──────────────────────────────┘  │  │   │   │
│  │  │  │  ┌──────────────────────────────┐  │  │   │   │
│  │  │  │  │  Vue 3 Frontend (Inertia)   │  │  │   │   │
│  │  │  │  │  - Pages, Layouts, Components│  │  │   │   │
│  │  │  │  └──────────────────────────────┘  │  │   │   │
│  │  │  └────────────────────────────────────┘  │   │   │
│  │  └──────────────────────────────────────────┘   │   │
│  └──────────────────┬──────────────────────────────┘   │
│                     │                                    │
│  ┌──────────────────▼──────────────────────────────┐   │
│  │         RDS MySQL (Multi-tenant)                 │   │
│  │  - Users, Organizations, AWS Accounts           │   │
│  │  - Scans, Results, Findings                     │   │
│  └──────────────────────────────────────────────────┘   │
│                                                          │
│  ┌──────────────────────────────────────────────────┐   │
│  │         External Services                        │   │
│  │  - Firebase Auth                                 │   │
│  │  - Customer AWS Accounts (via AssumeRole)        │   │
│  │  - SNS Topic (for account registration)          │   │
│  │  - CloudWatch (monitoring & logging)            │   │
│  └──────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

---

## Data Flow Examples

### User Registration Flow (Email/Password)
1. User fills registration form (Vue frontend)
2. Laravel creates user record in MySQL
3. Laravel sends verification email
4. User verifies email via link
5. User redirected to dashboard

### User Registration Flow (OAuth)
1. User clicks OAuth button (Google/GitHub/Microsoft)
2. Firebase Auth handles OAuth flow
3. Frontend receives Firebase ID token
4. Frontend sends token to Laravel: `POST /auth/firebase/verify`
5. Laravel verifies token, creates/updates user
6. Laravel creates default organization
7. Laravel creates session
8. User redirected to dashboard (automatically verified)

### AWS Account Addition Flow
1. User clicks "Add AWS Account" (Vue frontend)
2. Frontend calls Laravel: `POST /api/aws-accounts/init`
3. Laravel generates CloudFormation URL
4. Laravel returns URL to frontend
5. Frontend opens URL in new window (AWS Console)
6. User completes CloudFormation stack creation
7. User returns to app, provides AWS Account ID and Role ARN
8. Frontend calls: `POST /api/aws-accounts` with account details
9. Laravel encrypts IAM Role ARN, stores in MySQL
10. AWS account linked to user's organization

### Security Scan Flow
1. User initiates scan for AWS account (Vue frontend)
2. Frontend calls: `POST /api/scans` with AWS account ID
3. Laravel service:
   - Retrieves encrypted IAM Role ARN from MySQL
   - Decrypts IAM Role ARN
   - Assumes role in customer AWS account
   - Queues background job for scanning
4. Background job executes security checks (S3, IAM, EC2, RDS)
5. Results stored in MySQL
6. Frontend polls: `GET /api/scans/{scanId}` for status
7. When complete, frontend displays results

---

## Technology Stack Summary

### Backend
- **Laravel 11** (PHP 8.2+)
- **MySQL** (RDS)
- **Eloquent ORM**
- **Laravel Queues** (background jobs)
- **Firebase Admin SDK** (authentication)

### Frontend
- **Vue 3** with TypeScript
- **Inertia.js** (Laravel-Vue bridge)
- **Tailwind CSS v4** (styling)
- **shadcn-vue** (components)
- **Firebase SDK** (authentication)

### Infrastructure
- **AWS EC2** (application hosting)
- **AWS RDS MySQL** (database)
- **AWS SNS** (notifications)
- **CloudWatch** (monitoring)

---

## Next Steps

See [Planning](./planning.md) for detailed implementation steps and [Quick Start](./quick-start.md) for setup instructions.
