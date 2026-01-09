# Database Configuration Summary

## What's Been Set Up

✅ **Migrations Created**:
- `organizations` table (UUID primary key, org_id for isolation)
- `aws_accounts` table (with encrypted IAM Role ARN)
- `scans` table (scan execution tracking)
- `scan_results` table (security findings)
- `users` table updated (Firebase UID support)

✅ **Models Created**:
- `Organization` model with relationships
- `AwsAccount` model with encryption for IAM Role ARN
- `Scan` model
- `ScanResult` model
- `User` model updated with organizations relationship

✅ **Architecture Documentation**:
- `database/ARCHITECTURE.md` - Complete schema documentation
- `database/SETUP.md` - MySQL setup instructions

## Next Steps: Configure MySQL

### 1. Update .env File

Edit `/home/ben/dev/saas/app/.env` and change:

```env
# Change from:
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# To:
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=teemops
DB_USERNAME=teemops
DB_PASSWORD=your_password_here
```

### 2. Create MySQL Database

```bash
mysql -u root -p
```

Then:
```sql
CREATE DATABASE teemops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'teemops'@'localhost' IDENTIFIED BY 'your_password_here';
GRANT ALL PRIVILEGES ON teemops.* TO 'teemops'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Run Migrations

```bash
cd /home/ben/dev/saas/app
php artisan migrate
```

### 4. Verify Setup

```bash
php artisan tinker
```

Then:
```php
DB::connection()->getPdo(); // Should return PDO object
Schema::hasTable('organizations'); // Should return true
Organization::count(); // Should return 0 (no data yet)
```

## Database Schema Overview

### Tables

1. **users** - User accounts with Firebase UID
2. **organizations** - Multi-tenant organizations (UUID primary key)
3. **aws_accounts** - AWS accounts with encrypted IAM Role ARN
4. **scans** - Security scan executions
5. **scan_results** - Individual security findings

### Key Features

- **UUID Primary Keys**: All main tables use UUIDs
- **Multi-tenancy**: Organization-based isolation
- **Encryption**: IAM Role ARNs encrypted at rest
- **Soft Deletes**: Organizations and AWS accounts use soft deletes
- **Relationships**: Proper foreign keys and Eloquent relationships

## Model Relationships

```
User -> hasMany -> Organizations
Organization -> belongsTo -> User
Organization -> hasMany -> AwsAccounts
Organization -> hasMany -> Scans
AwsAccount -> belongsTo -> Organization
AwsAccount -> hasMany -> Scans
Scan -> belongsTo -> Organization
Scan -> belongsTo -> AwsAccount
Scan -> hasMany -> ScanResults
ScanResult -> belongsTo -> Scan
```

## Important Notes

1. **org_id vs id**: 
   - `id` is the primary key (UUID)
   - `org_id` is a separate UUID used for API queries (not exposed in UI)
   - Both are generated automatically

2. **IAM Role ARN Encryption**:
   - Automatically encrypted when saving
   - Automatically decrypted when retrieving
   - Uses Laravel's Crypt facade (AES-256)

3. **Organization Isolation**:
   - All queries must filter by `organization_id`
   - Use model scopes for automatic filtering

