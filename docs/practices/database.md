# Database Practices

## Core Principle

**Simplicity first**: Database design should be simple to understand, simple to query, and simple to maintain.

**Startup agility**: Design for current needs. Optimize queries and add indexes only when profiling shows bottlenecks or when you have real scale.

## Database Design

### 1. Normalization
- Normalize to third normal form (3NF) as a starting point
- Denormalize only when there's a clear performance need
- Document denormalization decisions and rationale
- Keep data models simple and intuitive

### 2. Schema Design
- Use clear, descriptive table and column names
- Follow consistent naming conventions
- Keep schemas focused and cohesive
- Avoid over-abstracting with too many layers

### 3. Data Types
- Use appropriate data types for each column
- Avoid storing multiple values in single columns
- Use database-native types when possible
- Keep precision appropriate to use case

## Query Design

### 1. Simple and Clear Queries
- Write queries that are easy to read and understand
- Use clear table and column aliases
- Avoid overly complex nested subqueries
- Prefer JOINs over multiple queries when appropriate

### 2. Performance
- Index appropriately based on query patterns
- Avoid N+1 query problems
- Use query analysis tools to identify slow queries
- Optimize queries that are actually slow, not theoretically slow

### 3. Query Safety
- Use parameterized queries to prevent SQL injection
- Validate input before database operations
- Use transactions for multi-step operations
- Handle database errors gracefully

## Migration Management

### 1. Version Control
- All schema changes in version-controlled migrations
- Migrations should be reversible when possible
- Test migrations on sample data before production
- Keep migrations small and focused

### 2. Migration Strategy
- Plan migrations for zero-downtime when possible
- Test rollback procedures
- Document breaking changes
- Coordinate migrations across services

### 3. Data Migrations
- Separate schema migrations from data migrations
- Make data migrations idempotent when possible
- Test data migrations on copies of production data
- Have rollback plans for data migrations

## Data Access Patterns

### 1. ORM Usage
- Use ORMs appropriately - they're tools, not requirements
- Understand the SQL your ORM generates
- Use raw SQL when ORM makes queries unnecessarily complex
- Keep ORM models simple and focused

### 2. Caching Strategy
- Cache at the appropriate layer
- Invalidate cache when data changes
- Use cache for expensive queries, not all queries
- Keep caching logic simple and maintainable

### 3. Connection Management
- Use connection pooling appropriately
- Monitor connection usage
- Close connections properly
- Handle connection failures gracefully

## Data Integrity

### 1. Constraints
- Use database constraints (foreign keys, unique, check)
- Enforce data integrity at the database level
- Don't rely solely on application-level validation
- Keep constraints simple and clear

### 2. Transactions
- Use transactions for atomic operations
- Keep transactions short
- Avoid long-running transactions
- Handle deadlocks and timeouts appropriately

### 3. Backup and Recovery
- Automate regular backups
- Test restore procedures regularly
- Keep backups in multiple locations
- Document recovery procedures

## Performance

### 1. When to Optimize
- **After Profiling**: Only optimize queries that profiling shows are slow
- **User Impact**: When query performance affects real users
- **Scale**: When you have significantly more data/users than before
- **Measured Bottlenecks**: When monitoring shows actual query performance issues
- **Business Impact**: When slow queries affect business metrics

### 2. Indexing
- Start with indexes for obvious query patterns (primary keys, foreign keys)
- Add indexes only when profiling shows they're needed
- Don't over-index - each index has maintenance cost
- Monitor index usage and remove unused indexes
- Consider composite indexes for multi-column queries only when needed
- Don't pre-index columns "just in case"

### 3. Query Optimization Process
1. **Profile First**: Use EXPLAIN/query plans to understand execution
2. **Measure Baseline**: Establish query performance baselines
3. **Optimize Incrementally**: Make one optimization at a time
4. **Validate Impact**: Measure after each optimization
5. **Document Why**: Record why optimizations were needed

### 4. Query Optimization
- Measure query performance before optimizing
- Use EXPLAIN/query plans to understand execution
- Optimize based on actual usage patterns, not assumptions
- Consider materialized views for expensive aggregations only when needed
- Don't optimize queries that aren't bottlenecks

### 5. Scaling
- Start with a single database instance
- Scale vertically before horizontally
- Use read replicas for read-heavy workloads only when needed
- Consider partitioning only when you have actual scale problems
- Don't build for hypothetical scale

## Security

### 1. Access Control
- Use principle of least privilege for database users
- Separate read and write access when possible
- Use role-based access control
- Audit database access regularly

### 2. Data Protection
- Encrypt sensitive data at rest
- Encrypt data in transit
- Don't store sensitive data unless necessary
- Follow data retention policies

### 3. SQL Injection Prevention
- Always use parameterized queries
- Validate and sanitize all input
- Use ORM query builders when appropriate
- Never concatenate user input into SQL

## Documentation

### 1. Schema Documentation
- Document table purposes and relationships
- Explain non-obvious design decisions
- Keep ER diagrams current
- Document data dictionaries

### 2. Query Documentation
- Document complex queries and their purpose
- Explain performance optimizations
- Note any query-specific gotchas
- Keep query examples in documentation

## Anti-Patterns to Avoid

- Over-normalizing or under-normalizing
- Creating tables "just in case"
- Ignoring database constraints
- Writing queries without understanding execution plans
- Premature optimization without measurement
- Storing JSON when relational structure would be clearer
- Adding indexes before profiling shows they're needed
- Optimizing queries that aren't bottlenecks
- Building for scale you don't have
- Enterprise database patterns without enterprise needs

