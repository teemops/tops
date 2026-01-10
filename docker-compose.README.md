# Docker Compose Setup

This directory contains a Docker Compose configuration for running MySQL locally during development.

## Quick Start

1. **Start MySQL**:
   ```bash
   docker-compose up -d
   ```

2. **Check MySQL status**:
   ```bash
   docker-compose ps
   ```

3. **View MySQL logs**:
   ```bash
   docker-compose logs -f mysql
   ```

4. **Stop MySQL**:
   ```bash
   docker-compose down
   ```

5. **Stop and remove volumes** (⚠️ This deletes all data):
   ```bash
   docker-compose down -v
   ```

## Environment Variables

Create a `.env` file in the root directory with the following variables:

```env
MYSQL_ROOT_PASSWORD=mysql
MYSQL_DATABASE=teemops
MYSQL_USER=teem
MYSQL_PASSWORD=b43c8ef4c93eb502
MYSQL_PORT=3306
```

Or use the provided `.env.example` as a template.

**Note:** These are the default values from `docker-compose.yml`. You can override them by setting these environment variables in your `.env` file.

## Database Connection

The MySQL container exposes port 3306 by default. 

**For Laravel applications**, configure your `app/.env` file:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=teemops
DB_USERNAME=teem
DB_PASSWORD=b43c8ef4c93eb502
```

**For other applications**, use this connection string:
```
mysql://teem:b43c8ef4c93eb502@localhost:3306/teemops
```

## Data Persistence

MySQL data is stored in the `./mysql-data` directory, which is gitignored. This ensures:
- Data persists between container restarts
- Data is not committed to git
- Easy to reset by deleting the directory

## Laravel Database Migrations

### Configure Laravel .env

After starting MySQL, configure your Laravel application's `.env` file in the `app/` directory. Make sure to set both the environment and database settings:

```env
APP_ENV=local
APP_DEBUG=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=teemops
DB_USERNAME=teem
DB_PASSWORD=b43c8ef4c93eb502
```

**Important:** 
- `APP_ENV=local` is required for local development (Laravel defaults to `production` which requires confirmation prompts)
- `DB_CONNECTION=mysql` must be set (Laravel defaults to `sqlite`)
- The default database credentials match the docker-compose.yml configuration. Adjust if you've changed the environment variables.

### Clear Configuration Cache

If you've updated your `.env` file, clear the configuration cache:

```bash
cd app
php artisan config:clear
php artisan cache:clear
```

### Run Migrations

Navigate to the Laravel app directory and run migrations:

```bash
cd app
php artisan migrate
```

**If you get a "production environment" warning**, you can either:
1. Fix your `.env` file (set `APP_ENV=local`) and clear config cache, or
2. Use the `--force` flag (not recommended for production):
   ```bash
   php artisan migrate --force
   ```

**If it's still using SQLite**, check your `.env` file has `DB_CONNECTION=mysql` and run:
```bash
php artisan config:clear
php artisan migrate
```

This will create all database tables defined in your migration files.

### Common Migration Commands

**Check migration status:**
```bash
cd app
php artisan migrate:status
```

**Rollback last migration batch:**
```bash
cd app
php artisan migrate:rollback
```

**Rollback all migrations:**
```bash
cd app
php artisan migrate:reset
```

**Rollback and re-run all migrations:**
```bash
cd app
php artisan migrate:refresh
```

**Drop all tables and re-run migrations (⚠️ Deletes all data):**
```bash
cd app
php artisan migrate:fresh
```

**Run migrations with seeders:**
```bash
cd app
php artisan migrate --seed
```

**Fresh migration with seeders:**
```bash
cd app
php artisan migrate:fresh --seed
```

### Create New Migrations

**Create a new migration:**
```bash
cd app
php artisan make:migration create_table_name
```

**Create a migration with model:**
```bash
cd app
php artisan make:model ModelName -m
```

### Database Seeders

**Run seeders:**
```bash
cd app
php artisan db:seed
```

**Run a specific seeder:**
```bash
cd app
php artisan db:seed --class=DatabaseSeeder
```

### Verify Database Connection

Test the database connection using Laravel Tinker:

```bash
cd app
php artisan tinker
```

Then in Tinker:
```php
DB::connection()->getPdo(); // Should return PDO object if connected
Schema::hasTable('users'); // Should return true
```

### Troubleshooting Migration Issues

**Issue: "Application is in production" warning**

This means `APP_ENV` is set to `production` or not set (defaults to production). Fix by:

1. Edit `app/.env` and set:
   ```env
   APP_ENV=local
   ```

2. Clear config cache:
   ```bash
   cd app
   php artisan config:clear
   ```

3. Run migrations again:
   ```bash
   php artisan migrate
   ```

**Issue: Using SQLite instead of MySQL**

This means `DB_CONNECTION` is set to `sqlite` or not set. Fix by:

1. Edit `app/.env` and set:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=teemops
   DB_USERNAME=teem
   DB_PASSWORD=b43c8ef4c93eb502
   ```

2. Clear config cache:
   ```bash
   cd app
   php artisan config:clear
   ```

3. Verify MySQL is running:
   ```bash
   docker-compose ps
   ```

4. Run migrations again:
   ```bash
   php artisan migrate
   ```

**Issue: Database connection refused**

1. Check MySQL container is running:
   ```bash
   docker-compose ps
   ```

2. If not running, start it:
   ```bash
   docker-compose up -d
   ```

3. Wait a few seconds for MySQL to fully start, then try again.

**Quick Fix: Force environment**

If you need to run migrations immediately without fixing `.env`:

```bash
cd app
php artisan migrate --env=local --force
```

However, it's better to fix your `.env` file properly.

## Prisma Migrations

After starting MySQL, run Prisma migrations:

```bash
cd backend
npx prisma migrate dev
```

Or generate Prisma client:

```bash
cd backend
npx prisma generate
```

## Accessing MySQL

You can connect to MySQL using any MySQL client:

```bash
# Using MySQL CLI
mysql -h localhost -P 3306 -u teem -pb43c8ef4c93eb502 teemops

# Or using Docker
docker-compose exec mysql mysql -u teem -pb43c8ef4c93eb502 teemops

# Connect as root user
docker-compose exec mysql mysql -u root -pmysql teemops
```

## Troubleshooting

### Port Already in Use

If port 3306 is already in use, change `MYSQL_PORT` in your `.env` file:

```env
MYSQL_PORT=3307
```

Then update your `DATABASE_URL` accordingly.

### Reset Database

To completely reset the database:

```bash
docker-compose down -v
rm -rf mysql-data
docker-compose up -d
```

### Check MySQL Health

```bash
docker-compose exec mysql mysqladmin ping -h localhost -u root -pmysql
```

