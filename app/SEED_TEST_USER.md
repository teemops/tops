# Test User Seeder

A test user seeder has been created for Playwright E2E tests.

## Test User Details

- **Email**: `test@auditaws.cloud`
- **Password**: `password`
- **Name**: `Test User`
- **Email Verified**: Yes (pre-verified for testing)
- **Default Organization**: Automatically created

## Running the Seeder

### Option 1: Run seeder directly
```bash
cd /home/ben/dev/saas/app
php artisan db:seed --class=TestUserSeeder
```

### Option 2: Run all seeders (includes test user in local/testing)
```bash
php artisan db:seed
```

### Option 3: Fresh migration with seeders
```bash
php artisan migrate:fresh --seed
```

## Automatic Seeding

The test user is automatically seeded when running `php artisan db:seed` in:
- `local` environment
- `testing` environment

It will **NOT** be seeded in `production` environment.

## Usage in Tests

The test helpers use this user by default:

```typescript
// Uses test@auditaws.cloud automatically
await authHelper.login();

// Or specify different credentials
await authHelper.login('other@example.com', 'password');
```

## Updating the Test User

The seeder uses `updateOrCreate`, so running it multiple times is safe:
- If user exists, it updates the password and verification status
- If user doesn't exist, it creates a new one
- Always ensures a default organization exists

## Resetting Test Data

To reset the test user:
```bash
php artisan db:seed --class=TestUserSeeder
```

Or to start fresh:
```bash
php artisan migrate:fresh --seed
```

