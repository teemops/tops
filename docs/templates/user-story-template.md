# User Story Template

This template aligns with our [Product Practices](../practices/product.md) and [Feature Development Practices](../practices/feature-development.md). Use this template for all new features to ensure consistency and alignment with our development principles.

## Template

```markdown
# [Feature Name] - User Story

## User Story
As a [user type], I want to [action] so that [benefit].

## Expected Behavior
[Clear, concise description of what happens when this feature is complete. Focus on user experience and outcomes, not implementation details.]

## User Acceptance Criteria
- [ ] Given [context], when [action], then [expected result]
- [ ] Given [context], when [action], then [expected result]
- [ ] Given [context], when [action], then [expected result]

## Technical Notes
[Optional: Implementation considerations, dependencies, architectural decisions, or constraints. Keep this minimal - focus on what's necessary for implementation.]

## Success Metrics
[How we'll measure success - aligned with Product Practices. Examples:
- Task completion rate
- Time to complete
- Error rate
- User satisfaction
]

## Related Practices
- [Product Practices](../practices/product.md) - [relevant section]
- [Security Practices](../practices/security.md) - [relevant section]
- [Code Quality Practices](../practices/code-quality.md) - [relevant section]
- [Database Practices](../practices/database.md) - [relevant section]
- [Architecture Practices](../practices/architecture.md) - [relevant section]
```

## Guidelines

### User Story Format
- **User type**: Be specific (e.g., "security engineer", "organization admin", "end user")
- **Action**: Use active voice, describe what the user does
- **Benefit**: Explain the value or problem solved

### Expected Behavior
- Write from the user's perspective
- Focus on outcomes, not implementation
- Be specific enough to be testable
- Keep it simple - avoid edge cases unless critical

### User Acceptance Criteria
- Use Given-When-Then format for clarity
- Make each criterion testable and specific
- Focus on happy path first, then critical error cases
- Don't over-specify - keep it minimal and focused

### Technical Notes
- Only include if there are important implementation considerations
- Reference existing patterns or conventions
- Note dependencies or constraints
- Keep it brief - detailed design happens during implementation

### Success Metrics
- Align with [Product Practices - Usability Metrics](../practices/product.md#usability-metrics)
- Choose metrics that indicate simplicity and user value
- Focus on metrics that matter, not vanity metrics
- Consider: task success rate, time to complete, error rate

### Related Practices
- Link to relevant sections of practices documents
- Helps ensure implementation follows our principles
- Use during code review to verify alignment

## Example

```markdown
# Create Organization - User Story

## User Story
As a user, I want to create multiple organizations so that I can separate different projects or clients.

## Expected Behavior
When a user clicks "Add Organization" and provides a name, a new organization is created and immediately available in the organization selector. The user can switch to this organization and see it contains no AWS accounts yet.

## User Acceptance Criteria
- [ ] Given I am logged in, when I click "Add Organization" and enter a name, then a new organization is created
- [ ] Given I have created an organization, when I view the organization selector, then I see my new organization listed
- [ ] Given I have created an organization, when I switch to it, then I see an empty state with no AWS accounts
- [ ] Given I try to create an organization with an empty name, then I see a validation error

## Technical Notes
- Organization name must be unique per user
- Default organization is created automatically on signup
- Organization uses UUID for orgId (not displayed in UI)

## Success Metrics
- Task success rate: >95% of users successfully create organization
- Time to complete: <30 seconds from click to organization available
- Error rate: <5% validation errors

## Related Practices
- [Product Practices](../practices/product.md) - Product Development Process
- [Database Practices](../practices/database.md) - Schema Design
- [Security Practices](../practices/security.md) - Authorization
```

## Checklist Before Implementation

Before starting implementation, verify:
- [ ] User story follows the template format
- [ ] Expected behavior is clear and testable
- [ ] Acceptance criteria cover happy path and critical errors
- [ ] Success metrics are defined and measurable
- [ ] Related practices are identified
- [ ] Feature aligns with "simplicity first" principle
- [ ] MVP scope is defined (what's in vs. out of scope)

