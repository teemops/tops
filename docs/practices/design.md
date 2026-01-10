# Design Practices

## Core Principle

**Simplicity first**: Design should be intuitive for users and maintainable for developers.

**Startup agility**: Ship designs fast, test with users, iterate quickly. Optimize UX workflows only when usage data shows problems.

## User Experience Principles

### 1. Clarity Over Cleverness
- Use familiar patterns and conventions
- Make actions and their consequences obvious
- Avoid hidden features and "power user" modes
- Prefer explicit over implicit

### 2. Progressive Disclosure
- Show only what's needed, when it's needed
- Hide complexity behind simple interfaces
- Provide advanced options only when necessary
- Default to the most common use case

### 3. Consistency
- Use consistent patterns across the application
- Follow platform conventions (web, mobile, desktop)
- Maintain visual and interaction consistency
- Reuse components and patterns

### 4. Error Prevention and Recovery
- Prevent errors before they happen
- Provide clear, actionable error messages
- Make recovery simple and obvious
- Validate early and provide helpful feedback

### 5. Performance and Responsiveness
- Optimize for perceived performance
- Provide immediate feedback for user actions
- Load progressively, don't block on everything
- Make waiting states clear and informative
- Ship fast - optimize performance when users report issues or metrics show problems

## Design Process

### 1. Start with User Goals
- Understand what users are trying to accomplish
- Design workflows that match user mental models
- Remove unnecessary steps
- Test with real users early

### 2. Prototype and Iterate
- Build low-fidelity prototypes first
- Test assumptions before building
- Iterate based on feedback
- Keep prototypes simple and focused

### 3. Design System
- Establish a simple, reusable design system
- Document components and patterns
- Keep the system small and focused
- Evolve based on actual usage

### 4. Accessibility
- Design for all users from the start
- Follow WCAG guidelines
- Test with assistive technologies
- Simple designs are often more accessible

## Implementation Guidelines

### Frontend Architecture
- Keep components small and focused
- Prefer composition over configuration
- Use clear, descriptive names
- Minimize component nesting depth

### State Management
- Keep state as local as possible
- Avoid global state unless necessary
- Use simple state management patterns
- Make state changes predictable

### Styling
- Use consistent spacing and typography scales
- Prefer semantic HTML and CSS
- Keep styles maintainable and reusable
- Avoid deep nesting and specificity wars

## Design Review Checklist

- [ ] Is this the simplest way to accomplish the user's goal?
- [ ] Can a new user understand this without documentation?
- [ ] Are there fewer steps than before?
- [ ] Is this consistent with existing patterns?
- [ ] Can this be maintained easily?

## When to Optimize Design

### Optimization Triggers
- **User Feedback**: When users consistently struggle with workflows
- **Usage Metrics**: When analytics show high drop-off or error rates
- **Task Completion**: When users fail to complete key tasks
- **Performance Issues**: When UI performance affects user experience
- **Business Impact**: When design issues affect conversion or retention

### Optimization Approach
1. **Measure First**: Use analytics and user testing to identify real problems
2. **Test Changes**: A/B test design improvements when possible
3. **Iterate Quickly**: Make small improvements and measure impact
4. **Focus on Core Flows**: Optimize the most-used workflows first
5. **Remove Friction**: Simplify before adding features

### What NOT to Optimize
- Don't optimize designs that users aren't complaining about
- Don't add complex interactions "just in case"
- Don't optimize for edge cases before common cases
- Don't wait for perfect designs before shipping
- Don't add enterprise UI patterns without enterprise needs

## Anti-Patterns to Avoid

- Over-customization when standard patterns work
- Complex animations that don't add value
- Hidden features that require discovery
- Inconsistent patterns across the application
- Designing for edge cases before common cases
- Optimizing designs before measuring user behavior
- Waiting for perfect designs before shipping

