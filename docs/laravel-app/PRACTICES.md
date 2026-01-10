# Laravel Application Practices

This document outlines how our [Practices](../practices/) apply specifically to the Laravel monolith application.

## Architecture Decision: Monolith

**Decision**: Keep Laravel and Vue in a single codebase (monolith).

**Rationale**:
- Simpler development and deployment
- No API layer needed for most features (Inertia.js handles it)
- Easier to maintain and debug
- Faster iteration
- Can add API layer later if needed without breaking changes

**When to Reconsider**:
- If we need to split frontend and backend teams
- If frontend and backend have different scaling needs
- If we need to serve multiple frontends (web, mobile, etc.)

**We Will NOT**:
- Split Laravel and Vue into separate applications prematurely
- Add microservices architecture without clear need
- Over-engineer for hypothetical future needs

## Practices Adaptations

### Product Practices ✅

All product practices apply as-is:
- Simplicity first
- MVP focus
- User stories
- Optimization triggers

**Laravel-Specific Notes**:
- Use Inertia.js for seamless Laravel-Vue integration
- No need for separate API layer for internal features
- API endpoints only needed for external integrations

### Security Practices ✅

All security practices apply, with Laravel-specific implementations:

**Authentication & Authorization**:
- ✅ Firebase Auth for authentication
- ✅ Laravel middleware for authorization
- ✅ Organization context middleware for multi-tenancy
- ✅ Form requests for validation

**Data Protection**:
- ✅ Eloquent encryption for sensitive fields (IAM Role ARNs)
- ✅ Laravel's built-in CSRF protection
- ✅ Environment variables for secrets

**Input Validation**:
- ✅ Laravel Form Requests for validation
- ✅ Vue form validation for UX
- ✅ Eloquent ORM (parameterized queries)

### Code Quality Practices ✅

**Code Organization**:
- Follow Laravel conventions:
  - Controllers in `app/Http/Controllers/`
  - Models in `app/Models/`
  - Services in `app/Services/`
  - Middleware in `app/Http/Middleware/`
  - Form Requests in `app/Http/Requests/`
- Vue components follow Inertia.js patterns:
  - Pages in `resources/js/Pages/`
  - Layouts in `resources/js/Layouts/`
  - Components in `resources/js/Components/`

**Testing**:
- Laravel Feature Tests for full request/response cycles
- Laravel Unit Tests for business logic
- PHPUnit for backend testing
- Vue component tests (when needed)

### Database Practices ✅

**Schema Design**:
- Use Laravel migrations
- Eloquent models for database access
- UUIDs for primary keys (where applicable)
- Soft deletes for data retention

**Query Design**:
- Use Eloquent ORM (automatically parameterized)
- Eager loading to avoid N+1 queries
- Database transactions for atomic operations
- Query builder for complex queries

**Migration Management**:
- All schema changes in migrations
- Reversible migrations when possible
- Test migrations before production

### Testing Practices ✅

**Test Strategy**:
- Feature tests for Inertia pages and API endpoints
- Unit tests for services and business logic
- Integration tests for database interactions
- E2E tests for critical user flows (when needed)

**Laravel Testing Tools**:
- PHPUnit (built-in)
- Laravel Testing utilities
- Database factories for test data
- Feature test helpers

**What to Test**:
- ✅ Business logic (services, models)
- ✅ API endpoints (feature tests)
- ✅ Inertia page rendering
- ✅ Authentication/authorization
- ✅ Multi-tenant isolation
- ❌ Framework code (Laravel, Vue, Inertia)
- ❌ Trivial getters/setters

### Architecture Practices ✅

**Simplicity First**:
- Monolith architecture (Laravel + Vue)
- Inertia.js for seamless integration
- No premature API layer
- No premature service splitting

**Design Principles**:
- Clear boundaries: Controllers → Services → Models
- Standard Laravel patterns
- Inertia.js for frontend-backend communication
- Vue components for UI

**Technology Choices**:
- Laravel 11 (PHP 8.2+)
- Vue 3 + TypeScript
- Inertia.js
- Tailwind CSS v4
- MySQL
- Firebase Auth

**Optimization**:
- Measure before optimizing
- Profile queries before optimizing
- Don't optimize prematurely
- Keep monolith simple

### Feature Development Practices ✅

**Development Process**:
1. Write user story
2. Design data model (migrations)
3. Create Eloquent models
4. Build controllers/services
5. Create Inertia pages/components
6. Write tests
7. Review and deploy

**Laravel-Specific Patterns**:
- Use Form Requests for validation
- Use Services for business logic
- Use Eloquent for database access
- Use Inertia for page rendering
- Use Vue components for UI

## Practices Checklist for Laravel

When developing features, ensure:

### Product
- [ ] Feature is MVP (minimum viable)
- [ ] User story is clear
- [ ] Simplicity first

### Security
- [ ] Firebase Auth for authentication
- [ ] Laravel middleware for authorization
- [ ] Organization context enforced
- [ ] Form Requests for validation
- [ ] Sensitive data encrypted

### Code Quality
- [ ] Follows Laravel conventions
- [ ] Services for business logic
- [ ] Clear, readable code
- [ ] Tests written

### Database
- [ ] Migrations for schema changes
- [ ] Eloquent models used
- [ ] Eager loading to avoid N+1
- [ ] Transactions for atomic operations

### Testing
- [ ] Feature tests for endpoints/pages
- [ ] Unit tests for services
- [ ] Integration tests for database
- [ ] Critical paths tested

### Architecture
- [ ] Monolith maintained
- [ ] Inertia.js for frontend-backend
- [ ] Standard Laravel patterns
- [ ] No premature optimization

## Common Patterns

### Creating a New Feature

1. **Create Migration**:
   ```bash
   php artisan make:migration create_example_table
   ```

2. **Create Model**:
   ```bash
   php artisan make:model Example
   ```

3. **Create Controller**:
   ```bash
   php artisan make:controller ExampleController
   ```

4. **Create Form Request** (if needed):
   ```bash
   php artisan make:request StoreExampleRequest
   ```

5. **Create Service** (if business logic needed):
   - Manually create `app/Services/ExampleService.php`

6. **Create Inertia Page**:
   - Create `resources/js/Pages/Example/Index.vue`

7. **Add Routes**:
   - Add to `routes/web.php` or `routes/api.php`

8. **Write Tests**:
   - Feature tests in `tests/Feature/`
   - Unit tests in `tests/Unit/`

### Testing Patterns

**Feature Test**:
```php
public function test_user_can_create_example()
{
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)
        ->post('/examples', [
            'name' => 'Test Example',
        ]);
    
    $response->assertRedirect();
    $this->assertDatabaseHas('examples', [
        'name' => 'Test Example',
    ]);
}
```

**Unit Test**:
```php
public function test_service_creates_example()
{
    $service = new ExampleService();
    $result = $service->create(['name' => 'Test']);
    
    $this->assertInstanceOf(Example::class, $result);
    $this->assertEquals('Test', $result->name);
}
```

## When to Add API Endpoints

Only add API endpoints (`routes/api.php`) when:
- External systems need to integrate
- Mobile app needs API access
- Third-party services need webhooks

For internal features, use Inertia.js pages (no API needed).

## Practices References

- [Full Practices Documentation](../practices/)
- [Feature Development Process](../processes/feature-development.md)
- [Practices Checklist](../processes/practices-checklist.md)
- [Architecture Documentation](../architecture.md)

