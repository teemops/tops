# End-to-End Testing with Playwright

This directory contains Playwright end-to-end tests for the Laravel + Vue application.

## Prerequisites

1. **Install Playwright browsers:**
   ```bash
   npx playwright install chromium
   ```
   
   **Note:** If you see a warning about "OS not officially supported" (e.g., ubuntu24.04-x64), this is **safe to ignore**. Playwright will download a fallback build that works perfectly fine.

2. **Ensure Laravel app is running:**
   ```bash
   php artisan serve
   ```

3. **Ensure database is set up:**
   ```bash
   php artisan migrate
   ```

## Running Tests

### Run all tests
```bash
npm run test:e2e
```

### Run tests in UI mode (interactive)
```bash
npm run test:e2e:ui
```

### Run tests in headed mode (see browser)
```bash
npm run test:e2e:headed
```

### Run tests in debug mode
```bash
npm run test:e2e:debug
```

### View test report
```bash
npm run test:e2e:report
```

## Test Structure

```
tests/e2e/
├── helpers/           # Test helper classes
│   ├── auth.ts       # Authentication helpers
│   └── organizations.ts  # Organization helpers
├── organizations.spec.ts  # Organization feature tests
└── README.md         # This file
```

## Writing Tests

### Basic Test Structure

```typescript
import { test, expect } from '@playwright/test';
import { AuthHelper } from './helpers/auth';

test.describe('Feature Name', () => {
    test('should do something', async ({ page }) => {
        const authHelper = new AuthHelper(page);
        await authHelper.login('test@example.com', 'password');
        
        // Your test code here
        await expect(page.locator('text=Dashboard')).toBeVisible();
    });
});
```

### Using Helpers

Helpers provide reusable functions for common operations:

- **AuthHelper**: Login, register, logout
- **OrganizationHelper**: Create, update, delete organizations

### Best Practices

1. **Use helpers** for common operations
2. **Use descriptive test names** that explain what they verify
3. **Test user flows**, not implementation details
4. **Use data-testid** attributes for stable selectors (when needed)
5. **Clean up test data** after tests (or use unique identifiers)

## Configuration

Playwright configuration is in `playwright.config.ts` at the root of the app directory.

Key settings:
- **Base URL**: `http://localhost:8000` (configurable via `PLAYWRIGHT_TEST_BASE_URL`)
- **Browsers**: Chromium by default (can add Firefox, WebKit)
- **Web Server**: Automatically starts Laravel server if not running

## CI/CD Integration

Playwright can run in CI environments. The configuration includes:
- Retry logic for flaky tests
- HTML and GitHub reporters
- Screenshot and video on failure

## Troubleshooting

### Tests fail with "page.goto: net::ERR_CONNECTION_REFUSED"
- Ensure Laravel server is running: `php artisan serve`
- Or set `PLAYWRIGHT_TEST_BASE_URL` to your server URL

### Tests fail with authentication errors
- Seed test user: `php artisan db:seed --class=TestUserSeeder`
- Test user email: `test@auditaws.cloud`, password: `password`
- Check that email verification is not blocking login (test user is pre-verified)

### Selectors not found
- Use Playwright's codegen to find selectors: `npx playwright codegen`
- Check browser console for errors
- Verify page has loaded: `await page.waitForLoadState('networkidle')`

## Test Data

For consistent test data, consider:
1. Using database seeders for test users
2. Using unique identifiers (timestamps) for test data
3. Cleaning up test data after tests
4. Using factories for generating test data

## Next Steps

- Add more feature tests as features are implemented
- Add visual regression testing
- Add accessibility testing
- Add performance testing

