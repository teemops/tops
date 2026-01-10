# Playwright E2E Testing Setup

Playwright has been successfully set up for end-to-end UI testing of the Laravel + Vue application.

## ✅ What's Been Set Up

1. **Playwright installed** (`@playwright/test`)
2. **Configuration file** (`playwright.config.ts`)
3. **Test helpers** (`tests/e2e/helpers/`)
   - `auth.ts` - Authentication helpers
   - `organizations.ts` - Organization helpers
4. **Initial test suite** (`tests/e2e/organizations.spec.ts`)
   - 13 tests covering Organizations feature
5. **NPM scripts** added to `package.json`
6. **Documentation** (`tests/e2e/README.md`)

## 🚀 Quick Start

### 1. Install Playwright Browsers

```bash
cd /home/ben/dev/saas/app
npx playwright install chromium
```

**Note:** 
- If you see a warning about "OS not officially supported" (e.g., ubuntu24.04-x64), this is **safe to ignore**. Playwright will download a fallback build that works fine.
- If you get a sudo password prompt for system dependencies, you can:
  - Run with sudo: `sudo npx playwright install chromium`
  - Or install system dependencies manually (see Playwright docs)
  - Or skip system dependencies if you only need browser binaries: `npx playwright install chromium --with-deps=false`

### 2. Run Tests

```bash
# Run all tests
npm run test:e2e

# Run in UI mode (interactive)
npm run test:e2e:ui

# Run in headed mode (see browser)
npm run test:e2e:headed

# Debug tests
npm run test:e2e:debug
```

### 3. View Test Report

```bash
npm run test:e2e:report
```

## 📋 Test Coverage

### Organizations Feature (13 tests)

✅ **Organizations List**
- Display default organization
- Show add organization button
- Navigate from sidebar

✅ **Create Organization**
- Create new organization
- Validation error for empty name
- Auto-switch to new organization

✅ **Switch Organization**
- Switch between organizations

✅ **Organization Settings**
- Open settings page
- Update organization name

✅ **Delete Organization**
- Delete organization without AWS accounts
- Prevent deleting default organization

✅ **Responsive Design**
- Mobile viewport
- Tablet viewport

## 🛠️ Configuration

### Base URL
- Default: `http://localhost:8000`
- Override: Set `PLAYWRIGHT_TEST_BASE_URL` environment variable

### Web Server
- Playwright automatically starts Laravel server if not running
- Server URL: `http://localhost:8000`

### Browsers
- **Default**: Chromium
- Can add Firefox and WebKit in `playwright.config.ts`

## 📝 Writing New Tests

### Example Test

```typescript
import { test, expect } from '@playwright/test';
import { AuthHelper } from './helpers/auth';
import { OrganizationHelper } from './helpers/organizations';

test.describe('My Feature', () => {
    test('should do something', async ({ page }) => {
        const authHelper = new AuthHelper(page);
        await authHelper.login('test@example.com', 'password');
        
        // Your test code
        await expect(page.locator('text=Dashboard')).toBeVisible();
    });
});
```

### Using Helpers

Helpers provide reusable functions:
- `AuthHelper`: Login, register, logout
- `OrganizationHelper`: Create, update, delete organizations

## 🔧 Test Data

**Test User:**
- Email: `test@auditaws.cloud`
- Password: `password`
- Created via: `TestUserSeeder`

**Seed Test User:**
```bash
php artisan db:seed --class=TestUserSeeder
```

See `SEED_TEST_USER.md` for details.

## 📚 Documentation

See `tests/e2e/README.md` for detailed documentation on:
- Test structure
- Best practices
- Troubleshooting
- CI/CD integration

## 🎯 Next Steps

1. **Install browsers**: `npx playwright install chromium`
2. **Seed test user**: `php artisan db:seed --class=TestUserSeeder`
3. **Run tests**: `npm run test:e2e`
4. **Add more tests** as features are implemented

## 🐛 Troubleshooting

### "Cannot find package '@playwright/test'"
- Run: `npm install -D @playwright/test --legacy-peer-deps`

### "Browser not found"
- Run: `npx playwright install chromium`
- If you see "OS not officially supported" warning, it's safe to ignore - the fallback build works fine

### "Connection refused"
- Ensure Laravel server is running: `php artisan serve`
- Or set `PLAYWRIGHT_TEST_BASE_URL` environment variable

### Tests fail with authentication errors
- Seed test user: `php artisan db:seed --class=TestUserSeeder`
- Verify user exists: Check database or run seeder again

## 📊 Test Results

Test results are saved in:
- `test-results/` - Screenshots, videos, traces
- `playwright-report/` - HTML report

View report: `npm run test:e2e:report`

