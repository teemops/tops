# Feature Development Process

This process ensures all features are developed in alignment with our [Practices](../practices/) documents, following the "simplicity first" and "startup agility" principles.

## Overview

The feature development process follows these phases:
1. **Discovery** - Understand the problem
2. **Definition** - Write user story and acceptance criteria
3. **Design** - Simple design before code
4. **Development** - Incremental implementation
5. **Review** - Practices-aligned code review
6. **Deployment** - Ship and learn

## Phase 1: Discovery

**Goal**: Understand the user problem before proposing solutions.

### Activities
- Identify the user problem
- Validate the problem is real and worth solving
- Consider the simplest solution first
- Ask: "What's the minimum viable feature?"

### Practices Reference
- [Product Practices - Product Discovery](../practices/product.md#1-product-discovery)
- [Feature Development Practices - Start with the Problem](../practices/feature-development.md#1-start-with-the-problem)

### Checklist
- [ ] Problem is clearly defined
- [ ] Problem is validated (user feedback, data, etc.)
- [ ] Simplest solution is considered first
- [ ] MVP scope is identified

## Phase 2: Definition

**Goal**: Define clear, testable requirements using the user story template.

### Activities
- Write user story using [User Story Template](../templates/user-story-template.md)
- Define expected behavior
- Write acceptance criteria (Given-When-Then format)
- Identify success metrics
- Link to relevant practices

### Practices Reference
- [Product Practices - Product Definition](../practices/product.md#2-product-definition)
- [Feature Development Practices - Scope Definition](../practices/feature-development.md#2-scope-definition)

### Checklist
- [ ] User story follows template format
- [ ] Expected behavior is clear and testable
- [ ] Acceptance criteria cover happy path
- [ ] Critical error cases are identified
- [ ] Success metrics are defined
- [ ] Related practices are linked
- [ ] Out of scope items are documented

## Phase 3: Design

**Goal**: Design the user experience and technical approach before coding.

### Activities
- Design user experience (wireframes, flows)
- Consider edge cases but don't over-engineer
- Design data model (if needed)
- Design API endpoints (if needed)
- Get feedback on design before implementation

### Practices Reference
- [Product Practices - Product Development](../practices/product.md#3-product-development)
- [Feature Development Practices - Design Before Code](../practices/feature-development.md#3-design-before-code)
- [Architecture Practices - Design Principles](../practices/architecture.md#design-principles)

### Checklist
- [ ] User experience is designed
- [ ] Data model is simple and normalized
- [ ] API design follows RESTful conventions (if applicable)
- [ ] Edge cases are considered but not over-engineered
- [ ] Design feedback is gathered
- [ ] Design aligns with simplicity principles

## Phase 4: Development

**Goal**: Build incrementally, test as you go, ship fast.

### Activities
- Build smallest working version first
- Add functionality incrementally
- Write tests as you develop
- Follow existing patterns and conventions
- Use [Practices Checklist](../processes/practices-checklist.md) during development

### Practices Reference
- [Feature Development Practices - Incremental Development](../practices/feature-development.md#1-incremental-development)
- [Code Quality Practices](../practices/code-quality.md)
- [Security Practices](../practices/security.md)

### Development Checklist
- [ ] Follows existing code patterns
- [ ] Code is readable and self-documenting
- [ ] Tests written for happy path (see [Testing Practices](../practices/testing.md))
- [ ] Critical error cases are tested
- [ ] Security considerations addressed
- [ ] Database queries use parameterized queries
- [ ] Input validation implemented
- [ ] Error handling is clear
- [ ] No premature optimization
- [ ] Unit tests for business logic
- [ ] Integration tests for API endpoints (if applicable)

### Code Review Checklist
Use [Practices Checklist](../processes/practices-checklist.md) during review:
- [ ] Code follows simplicity principles
- [ ] Security practices are followed
- [ ] Database practices are followed
- [ ] Code quality practices are followed
- [ ] Architecture practices are followed

## Phase 5: Review

**Goal**: Ensure code aligns with practices and meets acceptance criteria.

### Activities
- Self-review before requesting review
- Run tests and linters locally
- Request review from team
- Address review feedback
- Verify acceptance criteria are met

### Practices Reference
- [Feature Development Practices - Code Review](../practices/feature-development.md#code-review)
- [Code Quality Practices - Code Review](../practices/code-quality.md#code-review)

### Review Checklist
- [ ] All tests pass
- [ ] Linter passes
- [ ] Code follows team conventions
- [ ] Acceptance criteria are met
- [ ] Practices checklist is verified
- [ ] Documentation is updated
- [ ] No obvious performance issues

## Phase 6: Deployment

**Goal**: Ship fast, learn from users, iterate.

### Activities
- Deploy to staging/test environment
- Verify feature works in staging
- Deploy to production
- Monitor for errors
- Gather user feedback
- Plan iterations based on feedback

### Practices Reference
- [Product Practices - Product Launch](../practices/product.md#4-product-launch)
- [Feature Development Practices - Integration and Deployment](../practices/feature-development.md#integration-and-deployment)

### Deployment Checklist
- [ ] Feature tested in staging
- [ ] Monitoring and alerts configured
- [ ] Rollback plan prepared (if needed)
- [ ] Documentation updated
- [ ] User feedback collection plan in place

## Post-Deployment

### Activities
- Monitor feature usage and errors
- Gather user feedback
- Measure against success metrics
- Plan iterations based on data
- Remove features that don't add value

### Practices Reference
- [Product Practices - Product Maintenance](../practices/product.md#5-product-maintenance)
- [Product Practices - Product Iteration](../practices/product.md#7-product-iteration)
- [Feature Development Practices - Feature Iteration](../practices/feature-development.md#feature-iteration)

## When to Optimize

Follow the practices guidance on optimization:

- **Product Practices**: Optimize only when metrics show users struggling
- **Code Quality Practices**: Optimize only after profiling shows bottlenecks
- **Database Practices**: Optimize queries only after profiling shows slowness
- **Architecture Practices**: Optimize only when you have real scale/bottlenecks

**Don't optimize:**
- Before measuring
- For hypothetical future needs
- Code that isn't a bottleneck
- Features users aren't complaining about

## Anti-Patterns to Avoid

- Building features "just in case"
- Over-engineering the first version
- Skipping tests to move faster
- Optimizing before measuring
- Waiting for perfection before shipping
- Adding features to fix feature problems

## Quality Gate

Before considering a feature complete, verify:
- [ ] User story acceptance criteria are met
- [ ] Feature solves the user problem simply
- [ ] Code follows all practices
- [ ] Tests are written and passing
- [ ] Documentation is updated
- [ ] No obvious performance issues
- [ ] Ready for production
- [ ] Can ship and learn from users

