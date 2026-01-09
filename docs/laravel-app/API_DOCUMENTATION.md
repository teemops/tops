# API Documentation

## Overview

This document describes the REST API endpoints for the Teemops Laravel application. All API endpoints require Firebase authentication and organization context.

## Authentication

All API endpoints (except SNS callback) require:
- **Firebase ID Token** in the `Authorization` header: `Bearer <token>`
- **Organization Context** via one of:
  - `X-Organization-Id` header
  - `org_id` query parameter
  - Route parameter `{orgId}`

The authentication middleware automatically:
1. Verifies the Firebase token
2. Creates or finds the user in the database
3. Sets the organization context
4. Attaches the authenticated user to the request

## Base URL

```
/api
```

## Endpoints

### Organizations

#### List Organizations
```
GET /api/organizations
```

Returns all organizations for the authenticated user.

**Response:**
```json
{
  "organizations": [
    {
      "id": "uuid",
      "name": "My Organization",
      "is_default": true,
      "aws_accounts_count": 2,
      "created_at": "2024-01-01T00:00:00.000000Z"
    }
  ]
}
```

#### Get Current Organization
```
GET /api/organizations/current
```

Returns the current organization from context.

**Response:**
```json
{
  "id": "uuid",
  "name": "My Organization",
  "org_id": "internal-id",
  "is_default": true,
  "created_at": "2024-01-01T00:00:00.000000Z"
}
```

#### Get Organization
```
GET /api/organizations/{orgId}
```

**Response:**
```json
{
  "id": "uuid",
  "name": "My Organization",
  "org_id": "internal-id",
  "is_default": true,
  "aws_accounts_count": 2,
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T00:00:00.000000Z"
}
```

#### Create Organization
```
POST /api/organizations
```

**Request Body:**
```json
{
  "name": "New Organization"
}
```

**Response:** `201 Created`
```json
{
  "id": "uuid",
  "name": "New Organization",
  "org_id": "internal-id",
  "is_default": false,
  "created_at": "2024-01-01T00:00:00.000000Z"
}
```

#### Update Organization
```
PUT /api/organizations/{orgId}
```

**Request Body:**
```json
{
  "name": "Updated Organization Name"
}
```

**Response:**
```json
{
  "id": "uuid",
  "name": "Updated Organization Name",
  "org_id": "internal-id",
  "is_default": true,
  "updated_at": "2024-01-01T00:00:00.000000Z"
}
```

#### Delete Organization
```
DELETE /api/organizations/{orgId}
```

**Response:**
```json
{
  "success": true
}
```

**Errors:**
- `422`: Cannot delete the last organization
- `422`: Cannot delete organization with AWS accounts

---

### AWS Accounts

#### Initialize AWS Account Addition
```
POST /api/organizations/{orgId}/aws-accounts/init
```

Creates a pending AWS account record and returns CloudFormation URL.

**Response:**
```json
{
  "accountId": "uuid",
  "uniqueId": "uuid",
  "externalId": "uuid",
  "cloudFormationUrl": "https://console.aws.amazon.com/cloudformation/...",
  "status": "pending"
}
```

#### List AWS Accounts
```
GET /api/organizations/{orgId}/aws-accounts
```

**Response:**
```json
{
  "accounts": [
    {
      "id": "uuid",
      "name": "Production AWS",
      "awsAccountId": "123456789012",
      "status": "active",
      "lastScanAt": "2024-01-01T00:00:00.000000Z",
      "createdAt": "2024-01-01T00:00:00.000000Z"
    }
  ]
}
```

#### Get AWS Account
```
GET /api/aws-accounts/{accountId}
```

**Response:**
```json
{
  "id": "uuid",
  "name": "Production AWS",
  "awsAccountId": "123456789012",
  "status": "active",
  "lastScanAt": "2024-01-01T00:00:00.000000Z",
  "createdAt": "2024-01-01T00:00:00.000000Z",
  "updatedAt": "2024-01-01T00:00:00.000000Z"
}
```

#### Create AWS Account (Manual Fallback)
```
POST /api/organizations/{orgId}/aws-accounts
```

**Request Body:**
```json
{
  "name": "Production AWS",
  "aws_account_id": "123456789012",
  "iam_role_arn": "arn:aws:iam::123456789012:role/TeemOps"
}
```

**Response:** `201 Created`
```json
{
  "id": "uuid",
  "name": "Production AWS",
  "awsAccountId": "123456789012",
  "status": "active",
  "createdAt": "2024-01-01T00:00:00.000000Z"
}
```

#### Update AWS Account
```
PUT /api/aws-accounts/{accountId}
```

**Request Body:**
```json
{
  "name": "Updated Account Name"
}
```

**Response:**
```json
{
  "id": "uuid",
  "name": "Updated Account Name",
  "awsAccountId": "123456789012",
  "status": "active",
  "updatedAt": "2024-01-01T00:00:00.000000Z"
}
```

#### Delete AWS Account
```
DELETE /api/aws-accounts/{accountId}
```

**Response:**
```json
{
  "success": true
}
```

**Errors:**
- `422`: Cannot delete AWS account with scan history

#### SNS Callback (CloudFormation Webhook)
```
POST /api/aws-accounts/sns-callback
```

This endpoint receives SNS notifications from CloudFormation when a stack completes. No authentication required (uses AWS signature verification).

**Request Body:** (SNS message format)
```json
{
  "Message": {
    "TopsRoleArn": "arn:aws:iam::123456789012:role/TeemOps",
    "TopsExternalId": "uuid",
    "TopsUniqueId": "uuid",
    "TopsType": "optional-org-id"
  }
}
```

**Response:**
```json
{
  "success": true,
  "account_id": "uuid"
}
```

---

### Scans

#### List Scans
```
GET /api/organizations/{orgId}/scans
```

**Query Parameters:**
- `aws_account_id` (optional): Filter by AWS account
- `status` (optional): Filter by status (pending, running, completed, failed)
- `limit` (optional, default: 20, max: 100): Number of results
- `offset` (optional, default: 0): Pagination offset

**Response:**
```json
{
  "scans": [
    {
      "id": "uuid",
      "awsAccountId": "uuid",
      "awsAccountName": "Production AWS",
      "status": "completed",
      "findingsCount": 15,
      "createdAt": "2024-01-01T00:00:00.000000Z",
      "startedAt": "2024-01-01T00:05:00.000000Z",
      "completedAt": "2024-01-01T00:10:00.000000Z"
    }
  ],
  "total": 10,
  "limit": 20,
  "offset": 0
}
```

#### Get Scan
```
GET /api/scans/{scanId}
```

**Response:**
```json
{
  "id": "uuid",
  "awsAccountId": "uuid",
  "awsAccountName": "Production AWS",
  "status": "completed",
  "findingsCount": 15,
  "createdAt": "2024-01-01T00:00:00.000000Z",
  "startedAt": "2024-01-01T00:05:00.000000Z",
  "completedAt": "2024-01-01T00:10:00.000000Z",
  "errorMessage": null
}
```

#### Create Scan
```
POST /api/organizations/{orgId}/scans
```

**Request Body:**
```json
{
  "aws_account_id": "uuid",
  "scan_type": "full"
}
```

**Response:** `201 Created`
```json
{
  "id": "uuid",
  "awsAccountId": "uuid",
  "status": "pending",
  "createdAt": "2024-01-01T00:00:00.000000Z"
}
```

#### Get Scan Results
```
GET /api/scans/{scanId}/results
```

**Query Parameters:**
- `severity` (optional): Filter by severity (critical, high, medium, low)
- `service` (optional): Filter by AWS service
- `status` (optional): Filter by status (open, resolved, ignored)
- `limit` (optional, default: 50, max: 200): Number of results
- `offset` (optional, default: 0): Pagination offset

**Response:**
```json
{
  "scanId": "uuid",
  "findings": [
    {
      "id": "uuid",
      "title": "S3 Bucket Publicly Accessible",
      "description": "The bucket allows public read access",
      "severity": "high",
      "service": "s3",
      "resourceId": "my-bucket",
      "resourceType": "bucket",
      "status": "open",
      "remediation": "Remove public access policy",
      "createdAt": "2024-01-01T00:00:00.000000Z"
    }
  ],
  "summary": {
    "total": 15,
    "critical": 2,
    "high": 5,
    "medium": 6,
    "low": 2
  },
  "total": 15,
  "limit": 50,
  "offset": 0
}
```

#### Cancel Scan
```
POST /api/scans/{scanId}/cancel
```

**Response:**
```json
{
  "id": "uuid",
  "status": "cancelled"
}
```

**Errors:**
- `422`: Cannot cancel scan with status other than pending or running

---

## Error Responses

All errors follow this format:

```json
{
  "error": "Error message description"
}
```

**HTTP Status Codes:**
- `200`: Success
- `201`: Created
- `400`: Bad Request
- `401`: Unauthorized
- `404`: Not Found
- `422`: Unprocessable Entity (validation or business rule violation)
- `500`: Internal Server Error

---

## Environment Variables

Required environment variables for API functionality:

```env
# Firebase Authentication
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_PRIVATE_KEY_ID=your-key-id
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=your-service-account@project.iam.gserviceaccount.com
FIREBASE_CLIENT_ID=your-client-id
FIREBASE_CLIENT_X509_CERT_URL=https://www.googleapis.com/robot/v1/metadata/x509/...

# AWS Configuration
AWS_PARENT_ACCOUNT_ID=123456789012
AWS_CLOUDFORMATION_TEMPLATE_URL=https://s3.amazonaws.com/...
```

---

## Next Steps

1. **Queue System**: Implement background job processing for scans (Laravel Queues)
2. **AWS SDK Integration**: Add AWS SDK to perform actual security scans
3. **SNS Signature Verification**: Implement proper AWS SNS message signature verification
4. **Rate Limiting**: Add rate limiting middleware
5. **API Documentation**: Generate OpenAPI/Swagger documentation

