# Quick Start Guide

## Immediate First Steps

### 1. Prerequisites
- Node.js 18+ installed
- AWS CLI configured (for Serverless deployment)
- Firebase project created
- MySQL database (local or AWS RDS)
- Serverless Framework CLI: `npm install -g serverless`

### 2. Initialize Monorepo Structure

```bash
# Create project root
mkdir -p tops/{frontend,backend,shared}
cd tops

# Initialize frontend (Nuxt 3)
npx nuxi@latest init frontend
cd frontend
npm install

# Initialize backend (TypeScript Lambda Functions)
cd ../backend
npm init -y
npm install --save-dev typescript @types/node @types/aws-lambda ts-node
npx tsc --init

# Install Serverless Framework
npm install --save-dev serverless serverless-offline serverless-plugin-typescript
npm install --save-dev @types/serverless

# Initialize Prisma
npx prisma init
```

### 3. Essential Dependencies to Install

#### Backend (Lambda Functions + Serverless)
```bash
cd backend

# AWS Lambda Types
npm install --save-dev @types/aws-lambda

# Firebase Admin SDK
npm install firebase-admin

# Database
npm install @prisma/client
npm install prisma --save-dev

# AWS SDK v3
npm install @aws-sdk/client-s3 @aws-sdk/client-iam @aws-sdk/client-ec2 @aws-sdk/client-rds @aws-sdk/client-sts

# Validation (lightweight)
npm install zod

# Encryption (for IAM role storage)
npm install crypto-js
npm install @types/crypto-js --save-dev

# Serverless (dev dependencies)
npm install --save-dev serverless serverless-offline serverless-plugin-typescript
```

#### Frontend (Nuxt 3 + PrimeVue + Firebase)
```bash
cd frontend

# Core Nuxt
npm install @pinia/nuxt pinia

# PrimeVue
npm install primevue primeicons
npm install @primevue/themes

# Firebase
npm install firebase

# HTTP Client (optional, can use built-in $fetch)
npm install axios
```

### 4. Environment Variables Setup

#### Backend `.env` (for local development)
```env
# Database
DATABASE_URL="mysql://user:password@localhost:3306/cloudsecurity"

# Firebase Admin SDK
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=firebase-adminsdk-xxxxx@your-project.iam.gserviceaccount.com

# AWS
AWS_REGION=us-east-1
AWS_PARENT_ACCOUNT_ID=123456789012

# Encryption
ENCRYPTION_KEY=your-32-character-encryption-key

# Serverless (for local)
IS_OFFLINE=true
```

#### Frontend `.env`
```env
# Firebase
NUXT_PUBLIC_FIREBASE_API_KEY=your-api-key
NUXT_PUBLIC_FIREBASE_AUTH_DOMAIN=your-project.firebaseapp.com
NUXT_PUBLIC_FIREBASE_PROJECT_ID=your-project-id
NUXT_PUBLIC_FIREBASE_STORAGE_BUCKET=your-project.appspot.com
NUXT_PUBLIC_FIREBASE_MESSAGING_SENDER_ID=123456789
NUXT_PUBLIC_FIREBASE_APP_ID=1:123456789:web:abcdef

# API (local development)
NUXT_PUBLIC_API_BASE_URL=http://localhost:3000/dev
```

### 5. Firebase Setup

1. Create Firebase project at https://console.firebase.google.com
2. Enable Authentication:
   - Email/Password
   - Google (OAuth)
   - Apple (OAuth)
   - Microsoft (OAuth)
3. Create Service Account:
   - Project Settings → Service Accounts
   - Generate new private key
   - Download JSON file
   - Use values in backend `.env`

### 6. Serverless Framework Configuration

Create `backend/serverless.yml`:
```yaml
service: cloud-security-api

frameworkVersion: '3'

provider:
  name: aws
  runtime: nodejs18.x
  region: us-east-1
  stage: ${opt:stage, 'dev'}
  environment:
    DATABASE_URL: ${env:DATABASE_URL}
    FIREBASE_PROJECT_ID: ${env:FIREBASE_PROJECT_ID}
    # ... other env vars
  iam:
    role:
      statements:
        - Effect: Allow
          Action:
            - sts:AssumeRole
            - s3:*
            - iam:*
            - ec2:*
            - rds:*
          Resource: '*'

functions:
  # Authentication
  verifyAuth:
    handler: src/handlers/auth.handler.verify
    events:
      - http:
          path: /auth/verify
          method: POST
          cors: true

  # AWS Accounts
  getAwsAccounts:
    handler: src/handlers/aws-accounts.handler.getAccounts
    events:
      - http:
          path: /organizations/{orgId}/aws-accounts
          method: GET
          cors: true
  
  createAwsAccount:
    handler: src/handlers/aws-accounts.handler.create
    events:
      - http:
          path: /organizations/{orgId}/aws-accounts
          method: POST
          cors: true

  # Scans
  createScan:
    handler: src/handlers/scans.handler.create
    events:
      - http:
          path: /organizations/{orgId}/scans
          method: POST
          cors: true

  getScan:
    handler: src/handlers/scans.handler.get
    events:
      - http:
          path: /organizations/{orgId}/scans/{scanId}
          method: GET
          cors: true

  listScans:
    handler: src/handlers/scans.handler.list
    events:
      - http:
          path: /organizations/{orgId}/scans
          method: GET
          cors: true

plugins:
  - serverless-plugin-typescript
  - serverless-offline
```

---

## Confirmed Tech Stack

- **Monorepo structure** ✅
- **MySQL** database (AWS RDS) ✅
- **Prisma** ORM ✅
- **PrimeVue** for UI (Material Design) ✅
- **Firebase Authentication** (OAuth preferred) ✅
- **Serverless Framework** for backend deployment ✅

---

## What I Can Do Next

Based on your requirements in `PLANNING.md`, I can now:

1. ✅ Initialize monorepo structure with Nuxt 3 and Lambda functions
2. ✅ Set up Serverless Framework configuration
3. ✅ Configure Prisma with MySQL
4. ✅ Set up Firebase Authentication (frontend + backend)
5. ✅ Create Firebase Auth utility/middleware for Lambda functions
6. ✅ Set up PrimeVue in Nuxt 3
7. ✅ Create multi-tenant organization structure
8. ✅ Build AWS account management with cross-account role workflow
9. ✅ Implement AWS scanning service (S3, IAM, EC2, RDS)
10. ✅ Set up GitHub Actions CI/CD
11. ✅ Create initial UI components with PrimeVue

**Ready to proceed?** I'll start initializing the project structure with all the confirmed requirements!

