#!/bin/bash

# Setup script for environment variables

echo "Setting up environment variables..."

# Check if .env already exists
if [ -f .env ]; then
    echo ".env file already exists. Skipping creation."
    exit 0
fi

# Generate encryption key
ENCRYPTION_KEY=$(openssl rand -hex 16)

# Create .env file from template
cat > .env << EOF
# Database Configuration
DATABASE_URL=mysql://cloudsecurity:cloudsecurity@localhost:3306/cloudsecurity

# Firebase Admin SDK Configuration
# Get these from Firebase Console > Project Settings > Service Accounts
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=firebase-adminsdk-xxxxx@your-project-id.iam.gserviceaccount.com

# AWS Configuration
AWS_REGION=us-east-1
AWS_PARENT_ACCOUNT_ID=123456789012

# Encryption Key (32 characters) - Auto-generated
ENCRYPTION_KEY=${ENCRYPTION_KEY}

# CloudFormation Template URL
CLOUDFORMATION_TEMPLATE_URL=https://s3.amazonaws.com/storage.auditaws.com/iam.role.child.account.cfn.yaml

# Frontend Environment Variables
NUXT_PUBLIC_FIREBASE_API_KEY=your-api-key
NUXT_PUBLIC_FIREBASE_AUTH_DOMAIN=your-project.firebaseapp.com
NUXT_PUBLIC_FIREBASE_PROJECT_ID=your-project-id
NUXT_PUBLIC_FIREBASE_STORAGE_BUCKET=your-project.appspot.com
NUXT_PUBLIC_FIREBASE_MESSAGING_SENDER_ID=123456789
NUXT_PUBLIC_FIREBASE_APP_ID=1:123456789:web:abcdef
NUXT_PUBLIC_API_BASE_URL=http://localhost:3000/dev
EOF

echo ".env file created successfully!"
echo "Generated ENCRYPTION_KEY: ${ENCRYPTION_KEY}"
echo ""
echo "⚠️  IMPORTANT: Please update the .env file with your actual values:"
echo "   - Firebase credentials (from Firebase Console)"
echo "   - AWS Parent Account ID"
echo "   - Frontend Firebase configuration"
echo ""

