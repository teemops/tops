# Architecture Summary

## Key Architecture Decisions

### Backend: NestJS API on EC2

The backend uses **NestJS framework** deployed on a **single EC2 instance**. This provides:

- **Structured architecture**: Modular design with dependency injection
- **Type safety**: Full TypeScript support with decorators and DTOs
- **Scalability**: Can scale horizontally with load balancer when needed
- **Developer experience**: Excellent tooling, testing, and documentation support
- **Performance**: Express.js under the hood with connection pooling
- **Cost efficiency**: Single instance deployment for initial scale
- **Future-ready**: Easy migration to containerized deployment (ECS/EKS) if needed

#### Implementation Approach:
- NestJS modules for each feature (organizations, aws-accounts, scans, results)
- Controllers handle HTTP requests and responses
- Services contain business logic
- Guards for authentication and authorization
- Interceptors for response transformation
- Exception filters for error handling
- Prisma service for database access

### Authentication: Firebase Authentication

**Firebase Auth** handles all authentication, providing:

- **OAuth support**: Google, Apple, Microsoft (preferred)
- **Email/Password**: Fallback authentication method
- **MFA**: Optional multi-factor authentication
- **User management**: Built-in user management UI
- **Token management**: Secure token generation and validation

#### Integration Flow:
1. **Frontend**: User authenticates via Firebase SDK
2. **Frontend**: Receives Firebase ID token
3. **Frontend**: Sends token in `Authorization: Bearer <token>` header
4. **Backend**: Verifies token using Firebase Admin SDK
5. **Backend**: Extracts user info and organization context
6. **Backend**: Proceeds with authenticated request

### Multi-Tenancy: Organizations/Teams

- Each user belongs to **organizations**
- Organizations can have **teams**
- All data (AWS accounts, scans, results) scoped to organizations
- Firebase Auth provides user identity, MySQL stores organization relationships

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

### Database: MySQL (AWS RDS)

- **MySQL** for relational data (organizations, users, scans, results)
- **Prisma ORM** for type-safe database access
- **Connection pooling** required for Lambda (consider RDS Proxy)
- **Encryption at rest** for sensitive data (IAM role ARNs)

### Frontend: Nuxt 3 + PrimeVue

- **Nuxt 3**: Vue 3 framework with SSR/SSG capabilities
- **PrimeVue**: Material Design component library
- **Pinia**: State management
- **Firebase SDK**: Client-side authentication

### Deployment: AWS Multi-Tenant SaaS on EC2

- **Application Load Balancer (Day 1)** with ACM certificate for api.teemops.com
- **EC2 instance(s)** behind ALB (t3.small, Ubuntu 22.04, 100GB EBS)
- **Organization-based data isolation** in database
- **NestJS API** handles all requests with connection pooling
- **RDS MySQL** shared across all tenants (with proper isolation)
- **PM2 process manager** for application lifecycle management
- **Stateless application** ready for horizontal scaling
- **CloudWatch** for monitoring and logging
- **GitHub Actions** for CI/CD
- **Auto Scaling Group** ready for future scaling (1-10 instances)

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
│  ┌──────────────┐         ┌──────────────────┐         │
│  │   CloudFront │────────▶│   S3 (Nuxt App)  │         │
│  │  (CDN)       │         │   (Static Host)  │         │
│  └──────────────┘         └──────────────────┘         │
│                                                          │
│  ┌──────────────────────────────────────────────────┐   │
│  │   Application Load Balancer (api.teemops.com)    │   │
│  │   - ACM Certificate (SSL/TLS)                    │   │
│  │   - Health Checks                                │   │
│  └──────────────────┬──────────────────────────────┘   │
│                     │                                    │
│  ┌──────────────────▼──────────────────────────────┐   │
│  │   Auto Scaling Group (1-10 instances)          │   │
│  │  ┌──────────────────────────────────────────┐   │   │
│  │  │  EC2 Instance (t3.small, Ubuntu)        │   │   │
│  │  │  ┌────────────────────────────────────┐  │   │   │
│  │  │  │  NestJS Application (Port 3000)    │  │   │   │
│  │  │  │  ┌──────────────────────────────┐  │  │   │   │
│  │  │  │  │  Auth Module (Firebase)      │  │  │   │   │
│  │  │  │  │  Organizations Module        │  │  │   │   │
│  │  │  │  │  AWS Accounts Module         │  │  │   │   │
│  │  │  │  │  Scans Module                │  │  │   │   │
│  │  │  │  │  Results Module              │  │  │   │   │
│  │  │  │  │  Users Module                │  │  │   │   │
│  │  │  │  └──────────────────────────────┘  │  │   │   │
│  │  │  │  PM2 Process Manager               │  │   │   │
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
│  │  - SNS Topic (us-west-2, for account registration)│   │
│  │  - CloudWatch (monitoring & logging)            │   │
│  └──────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

---

## Data Flow Examples

### User Registration Flow
1. User fills registration form (Nuxt frontend)
2. Firebase Auth creates user account
3. Frontend receives Firebase ID token
4. Frontend calls backend API with token
5. Backend verifies token, creates user record in MySQL
6. Backend creates default organization for user
7. User redirected to dashboard

### AWS Account Addition Flow
1. User clicks "Add AWS Account" (Nuxt frontend)
2. Frontend calls backend API: `POST /api/aws-accounts/init`
3. Backend generates CloudFormation URL with parent account ID
4. Backend returns URL to frontend
5. Frontend opens URL in new window (AWS Console)
6. User completes CloudFormation stack creation
7. User returns to app, provides AWS Account ID and Role ARN
8. Frontend calls: `POST /api/aws-accounts` with account details
9. Backend encrypts IAM Role ARN, stores in MySQL
10. AWS account linked to user's organization

### Security Scan Flow
1. User initiates scan for AWS account (Nuxt frontend)
2. Frontend calls: `POST /api/scans` with AWS account ID
3. Backend NestJS service:
   - Retrieves encrypted IAM Role ARN from MySQL
   - Decrypts IAM Role ARN
   - Assumes role in customer AWS account
   - Executes security checks (S3, IAM, EC2, RDS) asynchronously
   - Stores results in MySQL
4. Backend returns scan ID
5. Frontend polls: `GET /api/scans/{scanId}` for status
6. When complete, frontend displays results

---

## Next Steps

See `PLANNING.md` for detailed implementation steps and `QUICK_START.md` for setup instructions.

