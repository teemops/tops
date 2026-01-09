# Database Architecture

## Overview

This document describes the database schema for the Teemops cloud security platform. The database uses MySQL and follows Laravel conventions with proper indexing and relationships.

## Database Connection

- **Database**: MySQL 8.0+
- **Connection**: Configured via `.env` file
- **ORM**: Laravel Eloquent
- **Migrations**: Version controlled database schema

## Schema Design Principles

1. **Multi-tenancy**: Organization-based isolation using `organization_id` foreign keys
2. **UUID Primary Keys**: All tables use UUIDs for primary keys (except pivot tables)
3. **Timestamps**: All tables include `created_at` and `updated_at`
4. **Soft Deletes**: Critical tables use soft deletes for data retention
5. **Indexing**: Proper indexes on foreign keys and frequently queried columns
6. **Encryption**: Sensitive data (IAM Role ARNs) encrypted at application level

## Tables

### 1. users
Laravel's default users table, extended for Firebase integration.

**Columns**:
- `id` (bigint, primary key)
- `firebase_uid` (string, unique) - Firebase user ID
- `name` (string)
- `email` (string, unique)
- `email_verified_at` (timestamp, nullable)
- `password` (string, nullable) - Only for email/password auth
- `remember_token` (string, nullable)
- `created_at` (timestamp)
- `updated_at` (timestamp)

**Indexes**:
- Primary key on `id`
- Unique index on `firebase_uid`
- Unique index on `email`

### 2. organizations
Organizations/tenants for multi-tenancy.

**Columns**:
- `id` (uuid, primary key)
- `org_id` (uuid, unique) - Internal identifier (not exposed in UI)
- `user_id` (bigint, foreign key -> users.id)
- `name` (string)
- `is_default` (boolean, default false)
- `created_at` (timestamp)
- `updated_at` (timestamp)
- `deleted_at` (timestamp, nullable) - Soft deletes

**Indexes**:
- Primary key on `id`
- Unique index on `org_id`
- Index on `user_id`
- Index on `deleted_at`

**Business Rules**:
- `org_id` is generated as UUID v4 on creation
- `org_id` is never displayed in UI, used for API queries
- Default organization created on user signup
- Cannot delete if it's the only organization
- Cannot delete if has AWS accounts

### 3. aws_accounts
AWS accounts connected to organizations.

**Columns**:
- `id` (uuid, primary key)
- `organization_id` (uuid, foreign key -> organizations.id)
- `name` (string, nullable) - User-defined name
- `aws_account_id` (string, 12 digits)
- `iam_role_arn` (text, encrypted) - Encrypted IAM Role ARN
- `external_id` (uuid) - For AssumeRole security
- `unique_id` (uuid, unique) - For matching SNS notifications
- `status` (enum: 'pending', 'active', 'error')
- `last_scan_at` (timestamp, nullable)
- `created_at` (timestamp)
- `updated_at` (timestamp)
- `deleted_at` (timestamp, nullable) - Soft deletes

**Indexes**:
- Primary key on `id`
- Unique index on `unique_id`
- Index on `organization_id`
- Index on `aws_account_id`
- Index on `status`
- Unique composite index on (`organization_id`, `aws_account_id`) - Prevent duplicates

**Business Rules**:
- `aws_account_id` must be 12 digits
- `iam_role_arn` encrypted at rest (AES-256)
- `external_id` and `unique_id` are UUIDs
- Names unique within organization
- Cannot delete organization if has AWS accounts

### 4. scans
Security scans executed on AWS accounts.

**Columns**:
- `id` (uuid, primary key)
- `organization_id` (uuid, foreign key -> organizations.id)
- `aws_account_id` (uuid, foreign key -> aws_accounts.id)
- `status` (enum: 'pending', 'running', 'completed', 'failed')
- `started_at` (timestamp, nullable)
- `completed_at` (timestamp, nullable)
- `error_message` (text, nullable)
- `created_at` (timestamp)
- `updated_at` (timestamp)

**Indexes**:
- Primary key on `id`
- Index on `organization_id`
- Index on `aws_account_id`
- Index on `status`
- Index on `created_at` (for recent scans query)

### 5. scan_results
Individual findings from security scans.

**Columns**:
- `id` (uuid, primary key)
- `scan_id` (uuid, foreign key -> scans.id)
- `severity` (enum: 'critical', 'high', 'medium', 'low')
- `service` (string) - AWS service (S3, IAM, EC2, etc.)
- `resource_type` (string) - Resource type
- `resource_id` (string) - Resource identifier
- `finding_type` (string) - Type of finding
- `title` (string)
- `description` (text)
- `remediation` (text, nullable) - Remediation steps
- `status` (enum: 'open', 'resolved', 'ignored', default 'open')
- `resolved_at` (timestamp, nullable)
- `created_at` (timestamp)
- `updated_at` (timestamp)

**Indexes**:
- Primary key on `id`
- Index on `scan_id`
- Index on `severity`
- Index on `status`
- Index on `service`
- Composite index on (`scan_id`, `severity`)

### 6. personal_access_tokens
Laravel Sanctum tokens (if needed for API access).

## Relationships

```
users (1) -> (many) organizations
organizations (1) -> (many) aws_accounts
organizations (1) -> (many) scans
aws_accounts (1) -> (many) scans
scans (1) -> (many) scan_results
```

## Data Isolation

All queries must filter by `organization_id` to ensure multi-tenant isolation:

```php
// Example: Get AWS accounts for organization
AwsAccount::where('organization_id', $organizationId)->get();

// Example: Get scans for organization
Scan::where('organization_id', $organizationId)->get();
```

## Encryption

Sensitive data (IAM Role ARNs) are encrypted using Laravel's encryption:

```php
// Encrypt
$encrypted = encrypt($iamRoleArn);

// Decrypt
$decrypted = decrypt($encrypted);
```

## Migration Order

1. Create users table (already exists)
2. Create organizations table
3. Create aws_accounts table
4. Create scans table
5. Create scan_results table

