# Auth Testing Completion Plan

This plan outlines the steps needed to bring Authentication & Authorization up to completion standards per [Testing Practices](../practices/testing.md).

## Current Status

**Functional Implementation**: ✅ Complete
- Firebase Authentication integration (frontend & backend)
- Firebase token verification in backend
- Auth guards and middleware
- User registration and login
- OAuth support (Google)
- Auth state management (Pinia store)
- Auto-redirect on auth errors

**Test Coverage**: ❌ Missing
- Unit tests for auth components
- Integration tests for auth endpoints
- E2E tests for UI flows
- Error scenario coverage

## Testing Requirements

### 1. Backend Unit Tests

#### Files to Test:
- `backend/src/auth/auth.guard.ts`
- `backend/src/auth/firebase.strategy.ts`
- `backend/src/users/users.service.ts` (already has some tests, may need expansion)

#### Test Cases Needed:

**auth.guard.spec.ts**:
- [ ] Should allow request with valid Bearer token
- [ ] Should throw UnauthorizedException when authorization header is missing
- [ ] Should throw UnauthorizedException when authorization header doesn't start with "Bearer "
- [ ] Should create user in database if not exists
- [ ] Should update user in database if exists
- [ ] Should attach user to request object
- [ ] Should throw UnauthorizedException when token is invalid
- [ ] Should throw UnauthorizedException when token is expired

**firebase.strategy.spec.ts**:
- [ ] Should initialize Firebase Admin SDK with valid credentials
- [ ] Should log warning when Firebase credentials are missing
- [ ] Should validate valid Firebase token
- [ ] Should throw UnauthorizedException when token is invalid
- [ ] Should throw UnauthorizedException when token is expired
- [ ] Should throw UnauthorizedException when Firebase is not configured
- [ ] Should return user data from decoded token

**users.service.spec.ts** (expand existing):
- [ ] Should update existing user when syncing
- [ ] Should find user by Firebase UID
- [ ] Should throw NotFoundException when user not found by Firebase UID

### 2. Backend Integration Tests

#### Endpoints to Test:
- `POST /api/users/sync` - Sync user from Firebase
- `GET /api/users/me` - Get current user

#### Test Cases Needed:

**users.e2e-spec.ts**:
- [ ] POST /api/users/sync - Should create user with valid Firebase data
- [ ] POST /api/users/sync - Should update existing user
- [ ] POST /api/users/sync - Should return 401 without authentication
- [ ] POST /api/users/sync - Should validate request body
- [ ] GET /api/users/me - Should return current user with valid token
- [ ] GET /api/users/me - Should return 401 without authentication
- [ ] GET /api/users/me - Should return 401 with invalid token
- [ ] GET /api/users/me - Should return 401 with expired token

### 3. Frontend E2E Tests (Playwright)

#### Setup Required:
- [ ] Install Playwright and dependencies
- [ ] Create `playwright.config.ts`
- [ ] Set up test database/seeding for E2E tests
- [ ] Configure test environment variables

#### Test Cases Needed:

**auth.e2e.spec.ts**:
- [ ] Should display login page when not authenticated
- [ ] Should login with email and password
- [ ] Should redirect to dashboard after successful login
- [ ] Should show error message on invalid credentials
- [ ] Should register new user
- [ ] Should redirect to dashboard after successful registration
- [ ] Should show validation errors on registration form
- [ ] Should logout and redirect to login
- [ ] Should persist login state on page refresh
- [ ] Should redirect to login when accessing protected route without auth
- [ ] Should handle expired token gracefully

**auth-oauth.e2e.spec.ts** (optional, can be manual):
- [ ] Should initiate Google OAuth login
- [ ] Should handle OAuth callback

### 4. Error Scenario Coverage

#### Backend Error Tests:
- [ ] Invalid token format
- [ ] Expired tokens
- [ ] Missing Firebase configuration
- [ ] Database connection errors
- [ ] Malformed authorization headers

#### Frontend Error Tests:
- [ ] Network errors during login
- [ ] Invalid credentials
- [ ] Token expiration handling
- [ ] Auth state initialization failures

## Implementation Steps

### Step 1: Backend Unit Tests
1. Create `backend/src/auth/auth.guard.spec.ts`
2. Create `backend/src/auth/firebase.strategy.spec.ts`
3. Expand `backend/src/users/users.service.spec.ts` if needed
4. Run tests: `npm test` in backend directory
5. Verify coverage: `npm run test:cov`

### Step 2: Backend Integration Tests
1. Create `backend/test/users.e2e-spec.ts`
2. Set up test database configuration
3. Create test utilities for generating auth tokens
4. Run tests: `npm run test:e2e` in backend directory

### Step 3: Frontend E2E Setup
1. Install Playwright: `npm install -D @playwright/test` in frontend
2. Create `frontend/playwright.config.ts`
3. Create `frontend/tests/e2e/` directory
4. Set up test environment configuration
5. Create test utilities for auth helpers

### Step 4: Frontend E2E Tests
1. Create `frontend/tests/e2e/auth.e2e.spec.ts`
2. Create test fixtures for authenticated state
3. Run tests: `npx playwright test` in frontend directory

### Step 5: Verify Test Coverage
1. Run all tests (unit, integration, E2E)
2. Verify critical paths are covered
3. Check coverage reports
4. Update PROGRESS.md when complete

## Test Utilities Needed

### Backend Test Utilities
- Mock Firebase Admin SDK for unit tests
- Test database setup/teardown for integration tests
- Helper function to generate test auth tokens
- Test user fixtures

### Frontend Test Utilities
- Auth helper functions (login, logout, get auth state)
- Test user fixtures
- Page object models for login/register pages

## Success Criteria

Auth is considered complete when:
- [ ] All unit tests pass with >80% coverage for auth modules
- [ ] All integration tests pass for auth endpoints
- [ ] All E2E tests pass for login/register flows
- [ ] Error scenarios are tested
- [ ] Tests are maintainable and follow [Testing Practices](../practices/testing.md)
- [ ] PROGRESS.md updated to reflect completion

## Estimated Effort

- Backend Unit Tests: 2-3 hours
- Backend Integration Tests: 2-3 hours
- Frontend E2E Setup: 1-2 hours
- Frontend E2E Tests: 3-4 hours
- **Total**: ~8-12 hours

## Next Steps

1. Start with backend unit tests (simplest, fastest)
2. Add backend integration tests
3. Set up Playwright for frontend
4. Add frontend E2E tests
5. Verify all tests pass and update documentation

