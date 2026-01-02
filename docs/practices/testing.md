# Testing Practices

## Core Principle

**Simplicity first**: Testing should be simple to write, simple to maintain, and simple to understand. Tests should verify behavior, not implementation.

**Startup agility**: Write tests that provide confidence without slowing down development. Focus on critical paths first. Add comprehensive coverage when you have real problems or scale.

## Testing Strategy

### 1. Test Pyramid
- **Unit Tests**: Fast, isolated tests for business logic and utilities
- **Integration Tests**: Test API endpoints and database interactions
- **End-to-End Tests**: Test critical user flows (use sparingly)
- **Manual Smoke Tests**: Quick verification of core functionality

### 2. Testing Priorities
- Test the happy path first
- Test critical error cases
- Test business logic thoroughly
- Don't test framework code or trivial getters/setters
- Focus on what could break, not what can't

### 3. Test Quality Over Quantity
- Aim for meaningful coverage, not 100%
- Focus on critical paths and business logic
- Use coverage to find untested code, not as a goal
- Remove tests that don't add value

## Unit Testing

### 1. What to Test
- Business logic and domain rules
- Utility functions and helpers
- Complex calculations or transformations
- Error handling logic
- Edge cases in business rules

### 2. What NOT to Test
- Framework code (NestJS, Nuxt, Prisma)
- Simple getters/setters
- Trivial functions with no logic
- Third-party library code
- Database schema (tested via integration tests)

### 3. Unit Test Guidelines
- Keep tests simple and readable
- One assertion per test when possible
- Use descriptive test names that explain what they verify
- Test behavior, not implementation
- Mock external dependencies (database, APIs, services)
- Keep tests fast (run in milliseconds)

### 4. Tools and Frameworks

#### Backend (NestJS)
- **Jest**: Default testing framework (already configured)
- **@nestjs/testing**: NestJS testing utilities
- **ts-jest**: TypeScript support for Jest

#### Frontend (Nuxt/Vue)
- **Vitest**: Fast unit test framework (Vite-based)
- **@vue/test-utils**: Vue component testing utilities
- **@nuxt/test-utils**: Nuxt-specific testing helpers

### 5. Example Unit Test Structure

```typescript
// Backend example
describe('OrganizationsService', () => {
  describe('createOrganization', () => {
    it('should create organization with valid name', async () => {
      // Arrange
      const userId = 'user-123';
      const name = 'My Organization';
      
      // Act
      const result = await service.createOrganization(userId, name);
      
      // Assert
      expect(result.name).toBe(name);
      expect(result.userId).toBe(userId);
    });

    it('should throw error when name is empty', async () => {
      // Arrange
      const userId = 'user-123';
      const name = '';
      
      // Act & Assert
      await expect(
        service.createOrganization(userId, name)
      ).rejects.toThrow('Organization name is required');
    });
  });
});
```

## Integration / Endpoint Testing

### 1. What to Test
- API endpoint behavior (request/response)
- Database interactions and queries
- Authentication and authorization
- Request validation
- Error responses
- Multi-tenant isolation (organization scoping)

### 2. Integration Test Guidelines
- Test against real database (test database, not production)
- Use test fixtures for consistent test data
- Clean up test data after tests
- Test complete request/response cycles
- Verify database state after operations
- Test error scenarios (400, 401, 403, 500)

### 3. Tools and Frameworks

#### Backend (NestJS)
- **Jest**: Test framework
- **Supertest**: HTTP assertion library for API testing
- **@nestjs/testing**: NestJS testing module
- **Prisma**: Use test database for integration tests

### 4. Example Integration Test Structure

```typescript
// Backend API endpoint test
describe('OrganizationsController (e2e)', () => {
  let app: INestApplication;
  let authToken: string;

  beforeAll(async () => {
    // Setup test app and database
    const moduleFixture = await Test.createTestingModule({
      imports: [AppModule],
    }).compile();

    app = moduleFixture.createNestApplication();
    await app.init();
    
    // Get auth token for test user
    authToken = await getTestAuthToken();
  });

  afterAll(async () => {
    await app.close();
    // Clean up test database
  });

  describe('POST /api/organizations', () => {
    it('should create organization with valid name', () => {
      return request(app.getHttpServer())
        .post('/api/organizations')
        .set('Authorization', `Bearer ${authToken}`)
        .send({ name: 'Test Organization' })
        .expect(201)
        .expect((res) => {
          expect(res.body.data.name).toBe('Test Organization');
          expect(res.body.data.orgId).toBeDefined();
        });
    });

    it('should return 401 without authentication', () => {
      return request(app.getHttpServer())
        .post('/api/organizations')
        .send({ name: 'Test Organization' })
        .expect(401);
    });
  });
});
```

## End-to-End / UI Testing

### 1. What to Test
- Critical user flows (login, create organization, add AWS account)
- Multi-step processes
- Cross-page navigation
- Form submissions and validations
- Error handling in UI

### 2. When to Use E2E Tests
- Critical user journeys that must work
- Complex multi-step workflows
- Features with high business impact
- Don't test every page - focus on critical paths

### 3. Tools and Frameworks

#### Playwright (Recommended)
- **Why Playwright**: 
  - Fast and reliable
  - Cross-browser testing (Chrome, Firefox, Safari)
  - Great debugging tools
  - Auto-waiting for elements
  - Screenshot and video recording
  - Good TypeScript support

#### Alternative: Cypress
- Good for component testing
- Real browser testing
- Good developer experience
- Slower than Playwright for E2E

### 4. Playwright Setup and Structure

```typescript
// tests/e2e/organizations.spec.ts
import { test, expect } from '@playwright/test';

test.describe('Organization Management', () => {
  test.beforeEach(async ({ page }) => {
    // Login before each test
    await page.goto('/login');
    await page.fill('input[type="email"]', 'test@example.com');
    await page.fill('input[type="password"]', 'password123');
    await page.click('button[type="submit"]');
    await page.waitForURL('/dashboard');
  });

  test('should create new organization', async ({ page }) => {
    // Navigate to organizations page
    await page.click('text=Organizations');
    await page.waitForURL('/organizations');

    // Click add organization button
    await page.click('text=Add Organization');

    // Fill form
    await page.fill('input[id="orgName"]', 'My New Organization');
    await page.click('button:has-text("Create")');

    // Verify organization appears in list
    await expect(page.locator('text=My New Organization')).toBeVisible();
  });

  test('should switch between organizations', async ({ page }) => {
    // Select organization from dropdown
    await page.click('[data-testid="organization-selector"]');
    await page.click('text=Organization 2');

    // Verify UI updates
    await expect(page.locator('text=Organization 2')).toBeVisible();
  });
});
```

### 5. Playwright Configuration

```typescript
// playwright.config.ts
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',
  use: {
    baseURL: 'http://localhost:3000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: 'npm run dev',
    url: 'http://localhost:3000',
    reuseExistingServer: !process.env.CI,
  },
});
```

## Manual Smoke Tests

### 1. Purpose
- Quick verification that core functionality works
- Catch obvious regressions before deployment
- Verify critical paths after major changes
- Not a replacement for automated tests

### 2. When to Perform
- Before deploying to production
- After major feature changes
- After infrastructure changes
- Weekly or before important releases

### 3. Smoke Test Checklist

#### Authentication
- [ ] Can register new user
- [ ] Can login with email/password
- [ ] Can login with Google OAuth
- [ ] Can logout
- [ ] Protected routes redirect to login when not authenticated

#### Organization Management
- [ ] Can view organizations list
- [ ] Can create new organization
- [ ] Can switch between organizations
- [ ] Can update organization name
- [ ] Can delete organization (when no AWS accounts)

#### AWS Account Management
- [ ] Can initiate AWS account addition
- [ ] CloudFormation URL opens correctly
- [ ] Can view AWS accounts list
- [ ] Account status displays correctly

#### Scanning (when implemented)
- [ ] Can start a scan
- [ ] Can view scan status
- [ ] Can view scan results

#### Reporting (when implemented)
- [ ] Can view reports
- [ ] Can export report (JSON)
- [ ] Dashboard displays correctly

### 4. Smoke Test Process
1. Create test checklist for current features
2. Perform tests manually in staging environment
3. Document any issues found
4. Fix critical issues before deployment
5. Update checklist as features are added

## Test Organization

### 1. Directory Structure

```
backend/
├── src/
│   └── [module]/
│       ├── [module].service.spec.ts    # Unit tests
│       └── [module].controller.spec.ts # Unit tests
├── test/
│   └── [module].e2e-spec.ts            # Integration tests
└── jest.config.js

frontend/
├── components/
│   └── [component].spec.ts             # Component unit tests
├── stores/
│   └── [store].spec.ts                 # Store unit tests
├── tests/
│   └── e2e/
│       └── [feature].spec.ts           # E2E tests
└── vitest.config.ts
```

### 2. Naming Conventions
- Unit tests: `*.spec.ts` or `*.test.ts`
- Integration tests: `*.e2e-spec.ts`
- E2E tests: `*.spec.ts` in `tests/e2e/`
- Use descriptive test names: `should create organization when name is valid`

## Test Data Management

### 1. Test Fixtures
- Create reusable test data factories
- Use factories instead of hardcoded data
- Keep fixtures simple and focused
- Clean up test data after tests

### 2. Test Database
- Use separate test database
- Reset database between test runs (or use transactions)
- Use migrations for test database schema
- Don't use production data in tests

### 3. Mocking Strategy
- Mock external services (AWS SDK, Firebase)
- Mock database in unit tests
- Use real database in integration tests
- Mock API calls in frontend unit tests

## Continuous Integration

### 1. CI Pipeline
- Run unit tests on every commit
- Run integration tests on pull requests
- Run E2E tests before merging to main
- Fail build if tests fail
- Generate coverage reports

### 2. Test Execution
- Run tests in parallel when possible
- Cache dependencies to speed up runs
- Use test matrix for multiple environments
- Set reasonable timeouts

## When to Add More Tests

### Test Enhancement Triggers
- **Bugs Found**: When bugs are found in production, add tests to prevent regression
- **Complex Logic**: When business logic becomes complex, add more tests
- **Critical Features**: When features are critical to business, ensure thorough testing
- **User Reports**: When users report issues, add tests to verify fixes

### Test Enhancement Approach
1. **Identify Gaps**: Use coverage reports to find untested code
2. **Prioritize**: Focus on critical paths and business logic
3. **Add Incrementally**: Add tests as you work on features
4. **Maintain**: Keep tests updated as code changes

### What NOT to Do
- Don't aim for 100% coverage (aim for meaningful coverage)
- Don't test framework code
- Don't write tests that test the test framework
- Don't add tests "just in case" - add them when needed
- Don't skip tests to move faster (they'll slow you down later)

## Performance Testing

### 1. When to Performance Test
- **After Profiling**: Only test performance of code that profiling shows is slow
- **User Impact**: When performance issues affect real users
- **Scale**: When you have significantly more users/data
- **Business Impact**: When performance affects business metrics

### 2. Performance Test Approach
1. **Measure Baseline**: Establish performance baselines
2. **Identify Bottlenecks**: Use profiling to find slow code
3. **Test Incrementally**: Test one optimization at a time
4. **Validate Impact**: Measure after each optimization
5. **Document Why**: Record why performance tests were needed

### 3. What NOT to Performance Test
- Don't performance test code that isn't a bottleneck
- Don't performance test without profiling first
- Don't optimize for hypothetical scale
- Don't add performance tests "just in case"

## Test Maintenance

### 1. Keep Tests Simple
- Tests should be easy to read and understand
- Remove tests that don't add value
- Refactor tests when they become complex
- Keep test setup minimal

### 2. Update Tests with Code
- Update tests when code changes
- Remove tests for removed features
- Fix flaky tests immediately
- Don't ignore test failures

### 3. Test Documentation
- Document complex test scenarios
- Explain why tests exist (link to bugs/requirements)
- Keep test data factories documented
- Document test environment setup

## Anti-Patterns to Avoid

- Writing tests that test the test framework
- Aiming for 100% coverage instead of meaningful coverage
- Testing framework code or trivial functions
- Skipping tests to move faster
- Ignoring flaky tests
- Writing tests that are hard to understand
- Testing implementation instead of behavior
- Adding performance tests without profiling first
- Testing for hypothetical problems
- Complex test setups that are hard to maintain

## Tools Summary

### Backend Testing
- **Jest**: Unit and integration testing
- **Supertest**: API endpoint testing
- **@nestjs/testing**: NestJS testing utilities

### Frontend Testing
- **Vitest**: Unit testing (fast, Vite-based)
- **@vue/test-utils**: Vue component testing
- **Playwright**: End-to-end UI testing (recommended)
- **Cypress**: Alternative E2E testing (if preferred)

### Test Utilities
- **Test Containers**: For database testing (optional)
- **MSW (Mock Service Worker)**: API mocking in frontend tests
- **Faker**: Generate test data

## Quality Checklist

Before considering tests complete:
- [ ] Tests verify behavior, not implementation
- [ ] Tests are readable and maintainable
- [ ] Critical paths are tested
- [ ] Error cases are tested
- [ ] Tests run fast (unit tests < 1s each)
- [ ] Tests are reliable (not flaky)
- [ ] Test data is properly managed
- [ ] Tests are updated when code changes

