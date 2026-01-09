# Architecture Decisions

This document records key architectural decisions for the Laravel application, including rationale and when to reconsider.

## Decision: Monolith Architecture

**Decision**: Keep Laravel backend and Vue frontend in a single codebase (monolith).

**Status**: ✅ Active

**Date**: January 2026

### Context

We need to decide whether to:
1. Keep Laravel and Vue together in one codebase (monolith)
2. Split into separate frontend and backend applications

### Decision

We will maintain a **monolith architecture** with Laravel and Vue in the same codebase, using Inertia.js to bridge them.

### Rationale

**Benefits**:
- ✅ **Simpler Development**: Single codebase, single deployment
- ✅ **Faster Iteration**: No API layer needed for most features
- ✅ **Easier Debugging**: Full stack in one place
- ✅ **Better DX**: Hot reloading, shared types, simpler workflows
- ✅ **Cost Effective**: Single server, simpler infrastructure
- ✅ **Easier Maintenance**: One codebase to understand and maintain

**Trade-offs**:
- ⚠️ Frontend and backend must deploy together
- ⚠️ Can't scale frontend and backend independently (initially)
- ⚠️ Can't serve multiple frontends easily (web, mobile, etc.)

### Implementation

- Laravel handles all backend logic
- Vue 3 handles all frontend UI
- Inertia.js seamlessly bridges Laravel and Vue
- No API layer needed for internal features
- API endpoints (`routes/api.php`) only for external integrations

### When to Reconsider

We should reconsider this decision if:

1. **Team Structure Changes**:
   - Frontend and backend teams need to work independently
   - Different release cycles needed

2. **Scaling Needs**:
   - Frontend and backend have different scaling requirements
   - Need to scale frontend separately from backend

3. **Multiple Frontends**:
   - Need to serve web, mobile, and other clients
   - Each frontend needs different API contracts

4. **Technology Constraints**:
   - Need to use different technologies for frontend/backend
   - Performance requirements demand separation

### We Will NOT

- ❌ Split prematurely "just in case"
- ❌ Add microservices without clear need
- ❌ Over-engineer for hypothetical future needs
- ❌ Split because "that's how it's done"

### Related Decisions

- **Inertia.js**: Chosen specifically to enable monolith architecture
- **No API Layer**: Internal features don't need API (Inertia handles it)
- **API Routes**: Only for external integrations (webhooks, mobile, etc.)

### References

- [Architecture Documentation](../architecture.md)
- [Laravel Practices](./PRACTICES.md)
- [Inertia.js Documentation](https://inertiajs.com/)

---

## Decision: Firebase Authentication

**Decision**: Use Firebase Authentication for all user authentication.

**Status**: ✅ Active

**Date**: January 2026

### Rationale

- OAuth support (Google, GitHub, Microsoft)
- Email/password support
- Email verification built-in
- Token management handled by Firebase
- No need to manage passwords/security ourselves

### When to Reconsider

- If Firebase becomes too expensive
- If we need more control over authentication
- If we need features Firebase doesn't support

---

## Decision: MySQL Database

**Decision**: Use MySQL (RDS) for all relational data.

**Status**: ✅ Active

**Date**: January 2026

### Rationale

- Relational data fits our needs
- Laravel has excellent MySQL support
- RDS provides managed database
- Good performance for our scale
- Cost effective

### When to Reconsider

- If we need NoSQL features
- If we need better performance at scale
- If cost becomes prohibitive

---

## Decision: UUIDs for Primary Keys

**Decision**: Use UUIDs for primary keys in multi-tenant tables.

**Status**: ✅ Active

**Date**: January 2026

### Rationale

- Better for multi-tenant isolation
- No sequential IDs that reveal data
- Can generate IDs before database insert
- Works well with distributed systems

### Implementation

- Organizations, AWS Accounts, Scans, Scan Results use UUIDs
- Users table uses auto-incrementing IDs (Laravel default)

---

## Decision: Organization-Based Multi-Tenancy

**Decision**: Use organization-based multi-tenancy with database-level isolation.

**Status**: ✅ Active

**Date**: January 2026

### Rationale

- Simple to implement
- Clear data boundaries
- Easy to understand and maintain
- Good performance for our scale

### Implementation

- All data scoped to organizations
- Middleware enforces organization context
- Database queries filtered by organization

---

## Decision: Encryption for Sensitive Data

**Decision**: Encrypt IAM Role ARNs at rest using Laravel's encryption.

**Status**: ✅ Active

**Date**: January 2026

### Rationale

- IAM Role ARNs are sensitive credentials
- Encryption at rest protects data
- Laravel provides built-in encryption
- Simple to implement and maintain

### Implementation

- Eloquent model automatically encrypts/decrypts
- Uses Laravel's encryption key
- Transparent to application code

---

## Decision: Background Jobs for Scanning

**Decision**: Use Laravel Queues for asynchronous scan processing.

**Status**: ✅ Active

**Date**: January 2026

### Rationale

- Scans can take time
- Don't block user requests
- Can retry failed scans
- Can scale workers independently

### Implementation

- Database queue driver (simple, works well)
- Can upgrade to Redis/SQS when needed
- Jobs process scans asynchronously

---

## Documenting New Decisions

When making new architectural decisions:

1. Document the decision
2. Include context and rationale
3. Note trade-offs
4. Define when to reconsider
5. Link to related decisions

This helps future developers understand why decisions were made and when they might need to change.

