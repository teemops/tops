# Cloud Security Application

A cloud security scanning application built with Nuxt 3 frontend and AWS Lambda backend.

## Project Structure

```
tops/
├── frontend/          # Nuxt 3 application
├── backend/           # Lambda functions with Serverless Framework
├── shared/            # Shared types and utilities
└── references/        # Reference files (CloudFormation templates, etc.)
```

## Getting Started

### Prerequisites

- Node.js 18+
- Docker and Docker Compose (for local MySQL)
- MySQL database (local via Docker or AWS RDS)
- Firebase project
- AWS account with Serverless Framework configured
- Serverless Framework CLI: `npm install -g serverless`

### Initial Setup

1. **Set up environment variables**:
   ```bash
   # Run the setup script (generates .env with encryption key)
   ./setup-env.sh
   
   # Then edit .env with your actual Firebase and AWS credentials
   nano .env
   ```

2. **Start MySQL using Docker Compose**:
   ```bash
   # Start MySQL container
   docker-compose up -d
   
   # Check status
   docker-compose ps
   ```

   The MySQL data will be stored in `./mysql-data` directory.

### Backend Setup

```bash
cd backend
npm install
npx prisma generate
npx prisma migrate dev
npm run dev  # Start serverless offline
```

### Frontend Setup

```bash
cd frontend
npm install
npm run dev
```

## Environment Variables

### Backend (.env)

```env
DATABASE_URL="mysql://user:password@localhost:3306/cloudsecurity"
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=firebase-adminsdk-xxxxx@your-project.iam.gserviceaccount.com
AWS_REGION=us-east-1
AWS_PARENT_ACCOUNT_ID=123456789012
ENCRYPTION_KEY=your-32-character-encryption-key
CLOUDFORMATION_TEMPLATE_URL=https://s3.amazonaws.com/storage.auditaws.com/iam.role.child.account.cfn.yaml
```

### Frontend (.env)

```env
NUXT_PUBLIC_FIREBASE_API_KEY=your-api-key
NUXT_PUBLIC_FIREBASE_AUTH_DOMAIN=your-project.firebaseapp.com
NUXT_PUBLIC_FIREBASE_PROJECT_ID=your-project-id
NUXT_PUBLIC_API_BASE_URL=http://localhost:3000/dev
```

## Development

### Backend

```bash
cd backend
npm run dev          # Start serverless offline
npm run build        # Build TypeScript
npm run deploy:dev   # Deploy to AWS dev stage
```

### Frontend

```bash
cd frontend
npm run dev          # Start Nuxt dev server
npm run build        # Build for production
```

## Documentation

- [PLANNING.md](./PLANNING.md) - Project planning and architecture
- [FEATURES_SPEC.md](./FEATURES_SPEC.md) - Feature specifications
- [ONBOARDING_FLOW.md](./ONBOARDING_FLOW.md) - AWS account onboarding flow
- [WIREFRAMES.md](./WIREFRAMES.md) - Application wireframes

