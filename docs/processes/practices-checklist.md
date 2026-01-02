# Practices Checklist

Quick reference checklist to ensure development aligns with our practices. Use this during development and code review.

## Product Practices

### Simplicity First
- [ ] Does this make it simpler for the end user?
- [ ] Does this make it simpler to maintain?
- [ ] Can we achieve the same outcome with less complexity?
- [ ] What are we willing to remove to add this?

### MVP Focus
- [ ] Is this the minimum viable feature?
- [ ] What's out of scope for this version?
- [ ] Are we building for current needs, not hypothetical future needs?

### User Story
- [ ] User story follows template format
- [ ] Expected behavior is clear
- [ ] Acceptance criteria are testable
- [ ] Success metrics are defined

### Optimization Triggers
- [ ] Are we optimizing based on actual user data/metrics?
- [ ] Or are we optimizing "just in case"?
- [ ] Have we measured first?

## Security Practices

### Authentication & Authorization
- [ ] All API endpoints require authentication
- [ ] Authorization is enforced (users can only access their data)
- [ ] Multi-tenant isolation is verified (organization scoping)
- [ ] Firebase tokens are verified on backend

### Data Protection
- [ ] Sensitive data (IAM Role ARNs) encrypted at rest
- [ ] Data in transit uses TLS/SSL
- [ ] Secrets are not committed to version control
- [ ] Principle of least privilege applied

### Input Validation
- [ ] All user input is validated
- [ ] Validation on both client and server
- [ ] SQL injection prevented (parameterized queries)
- [ ] Output is encoded appropriately

### API Security
- [ ] All API requests authenticated
- [ ] Rate limiting implemented (if needed)
- [ ] Appropriate HTTP status codes used
- [ ] Internal errors not exposed to clients

## Code Quality Practices

### Code Clarity
- [ ] Code is readable and self-documenting
- [ ] Clear, descriptive names for variables/functions/classes
- [ ] Functions are small and focused
- [ ] Comments explain "why", not "what"

### Code Organization
- [ ] Follows existing project structure
- [ ] Consistent patterns used
- [ ] Dependencies minimized
- [ ] Clear module boundaries

### Testing
- [ ] Tests written for happy path
- [ ] Critical error cases tested
- [ ] Tests are readable and maintainable
- [ ] Tests verify behavior, not implementation

### Error Handling
- [ ] Clear, actionable error messages
- [ ] Errors handled at appropriate level
- [ ] Errors logged with sufficient detail
- [ ] No errors swallowed silently

### Performance
- [ ] Code is clear first, optimized when needed
- [ ] Optimization only after profiling shows bottlenecks
- [ ] No premature optimization
- [ ] Performance measured before optimizing

## Database Practices

### Schema Design
- [ ] Normalized to 3NF (unless denormalization needed for performance)
- [ ] Clear, descriptive table and column names
- [ ] Appropriate data types used
- [ ] Database constraints used (foreign keys, unique, check)

### Query Design
- [ ] Queries are simple and clear
- [ ] Parameterized queries used (no SQL injection)
- [ ] N+1 query problems avoided
- [ ] Transactions used for atomic operations

### Optimization
- [ ] Queries profiled before optimizing
- [ ] Indexes added only when profiling shows need
- [ ] Not optimizing queries that aren't bottlenecks
- [ ] Not building for hypothetical scale

### Migration Management
- [ ] Schema changes in version-controlled migrations
- [ ] Migrations are reversible when possible
- [ ] Migrations tested before production
- [ ] Data migrations separate from schema migrations

## Testing Practices

### Test Strategy
- [ ] Tests verify behavior, not implementation
- [ ] Critical paths are tested
- [ ] Happy path tested first
- [ ] Critical error cases tested
- [ ] Not testing framework code or trivial functions

### Unit Testing
- [ ] Business logic has unit tests
- [ ] Tests are simple and readable
- [ ] Tests run fast (< 1s each)
- [ ] Tests are reliable (not flaky)

### Integration Testing
- [ ] API endpoints have integration tests
- [ ] Database interactions are tested
- [ ] Authentication/authorization tested
- [ ] Multi-tenant isolation tested

### E2E Testing
- [ ] Critical user flows have E2E tests (if needed)
- [ ] E2E tests use Playwright or similar tool
- [ ] E2E tests are focused on critical paths only

### Manual Smoke Tests
- [ ] Smoke test checklist exists
- [ ] Smoke tests performed before deployment
- [ ] Critical functionality verified manually

## Architecture Practices

### Simplicity First
- [ ] Architecture is simple to understand
- [ ] Simple to change and maintain
- [ ] Not over-engineered
- [ ] Building for current needs, not hypothetical scale

### Design Principles
- [ ] Clear boundaries and responsibilities
- [ ] Standard patterns and conventions used
- [ ] Observability designed in (logging, metrics)
- [ ] Stateless components when possible

### Technology Choices
- [ ] Technologies team knows well
- [ ] Battle-tested solutions preferred
- [ ] Minimal technology diversity
- [ ] Standard stack used

### Optimization
- [ ] Measured before optimizing
- [ ] Real bottlenecks identified
- [ ] Not optimizing for hypothetical scale
- [ ] Not adding complexity "just in case"

## Feature Development Practices

### Planning
- [ ] Problem clearly understood
- [ ] Simplest solution considered first
- [ ] MVP scope defined
- [ ] Out of scope items documented

### Development
- [ ] Smallest working version built first
- [ ] Functionality added incrementally
- [ ] Tests written as you develop
- [ ] Existing patterns followed

### Code Review
- [ ] Self-reviewed before requesting review
- [ ] Tests and linters pass
- [ ] Code follows team standards
- [ ] Documentation updated

### Deployment
- [ ] Feature tested in staging
- [ ] Monitoring configured
- [ ] Rollback plan prepared (if needed)
- [ ] User feedback collection planned

## Quick Decision Framework

When evaluating any decision, ask:

1. **Simplicity**: Does this make it simpler?
2. **Current Needs**: Are we building for current needs?
3. **Measurement**: Have we measured first?
4. **User Value**: Does this add clear user value?

If answer to any is "no", reconsider the approach.

## Practices by Phase

### During Planning
- Product Practices (simplicity, MVP)
- Feature Development Practices (problem-first, scope)

### During Design
- Architecture Practices (simplicity, patterns)
- Database Practices (normalization, schema)
- Security Practices (authentication, data protection)

### During Development
- Code Quality Practices (clarity, testing)
- Security Practices (input validation, API security)
- Database Practices (queries, migrations)
- Testing Practices (unit tests, integration tests)

### During Review
- All practices (verify alignment)

### During Deployment
- Product Practices (launch, metrics)
- Feature Development Practices (iteration)

## Common Anti-Patterns

Watch for these violations of practices:

- ❌ Building features "just in case"
- ❌ Optimizing before measuring
- ❌ Over-engineering for hypothetical scale
- ❌ Complex solutions when simple ones work
- ❌ Enterprise patterns without enterprise needs
- ❌ Skipping tests to move faster
- ❌ Waiting for perfection before shipping

