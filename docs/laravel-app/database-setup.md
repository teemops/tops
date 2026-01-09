# Database Setup Guide

## MySQL Configuration

### 1. Install MySQL (if not already installed)

#### Ubuntu/Debian:
```bash
sudo apt update
sudo apt install mysql-server
sudo mysql_secure_installation
```

#### macOS (Homebrew):
```bash
brew install mysql
brew services start mysql
```

### 2. Create Database

```bash
mysql -u root -p
```

Then in MySQL:
```sql
CREATE DATABASE teemops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'teemops'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON teemops.* TO 'teemops'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Configure Laravel

Edit `.env` file:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=teemops
DB_USERNAME=teemops
DB_PASSWORD=your_secure_password
```

### 4. Run Migrations

```bash
cd /home/ben/dev/saas/app
php artisan migrate
```

This will create:
- `users` table (with firebase_uid)
- `organizations` table
- `aws_accounts` table
- `scans` table
- `scan_results` table

### 5. Verify Database

```bash
php artisan tinker
```

Then:
```php
DB::connection()->getPdo();
// Should return PDO object if connected

Schema::hasTable('organizations');
// Should return true
```

## Database Schema Overview

### Tables Created

1. **users** - User accounts (Firebase integration)
2. **organizations** - Multi-tenant organizations
3. **aws_accounts** - Connected AWS accounts
4. **scans** - Security scan executions
5. **scan_results** - Individual security findings

See `ARCHITECTURE.md` for detailed schema documentation.

## Using Docker MySQL (Alternative)

If you prefer Docker:

```bash
docker run --name teemops-mysql \
  -e MYSQL_ROOT_PASSWORD=rootpassword \
  -e MYSQL_DATABASE=teemops \
  -e MYSQL_USER=teemops \
  -e MYSQL_PASSWORD=teemopspassword \
  -p 3306:3306 \
  -d mysql:8.0
```

Then update `.env`:
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=teemops
DB_USERNAME=teemops
DB_PASSWORD=teemopspassword
```

## Troubleshooting

### Connection Refused
- Check MySQL is running: `sudo systemctl status mysql`
- Verify port 3306 is open
- Check firewall settings

### Access Denied
- Verify username/password in `.env`
- Check MySQL user has proper permissions
- Try connecting manually: `mysql -u teemops -p teemops`

### Migration Errors
- Ensure database exists
- Check user has CREATE TABLE permissions
- Review migration files for syntax errors

