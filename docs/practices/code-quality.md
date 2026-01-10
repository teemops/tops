# Code Quality Practices

## Core Principle

**Simplicity first**: Code should be simple to read, simple to understand, and simple to change.

**Startup agility**: Write clear, working code first. Optimize performance and add robustness only when profiling shows bottlenecks or when you have real scale.

## Code Clarity

### 1. Readability Over Cleverness
- Write code for humans, not just compilers
- Use clear, descriptive names for variables, functions, and classes
- Prefer explicit code over implicit magic
- Avoid premature optimization

### 2. Small Functions and Classes
- Keep functions small and focused on one task
- Limit function complexity (cyclomatic complexity)
- Prefer composition over large classes
- Extract methods when functions grow too large

### 3. Self-Documenting Code
- Code should explain itself through good naming
- Use comments to explain "why", not "what"
- Remove commented-out code
- Keep comments current with code changes

## Code Organization

### 1. Consistent Structure
- Follow consistent project structure across the monorepo
- Group related code together
- Separate concerns clearly (UI, business logic, data access)
- Use standard patterns and conventions

### 2. Dependency Management
- Minimize dependencies
- Keep dependencies up to date
- Remove unused dependencies
- Prefer well-maintained, standard libraries

### 3. Module Boundaries
- Define clear module boundaries
- Minimize coupling between modules
- Maximize cohesion within modules
- Use interfaces to define contracts

## Testing

### 1. Test Strategy
- Write tests that verify behavior, not implementation
- Prefer integration tests over unit tests for business logic
- Test the happy path and critical error cases
- Keep tests simple and maintainable

### 2. Test Quality
- Tests should be readable and self-documenting
- Use descriptive test names that explain what they verify
- One assertion per test when possible
- Avoid test interdependencies

### 3. Test Coverage
- Aim for meaningful coverage, not 100%
- Focus on critical paths and business logic
- Don't test framework code or trivial getters/setters
- Use coverage to find untested code, not as a goal

## Code Review

### 1. Review Focus
- Review for correctness, clarity, and simplicity
- Check that code follows team conventions
- Verify tests are appropriate and passing
- Ensure documentation is updated

### 2. Review Process
- Keep reviews small and focused
- Provide constructive, actionable feedback
- Approve when code is good enough, not perfect
- Use reviews as learning opportunities

## Refactoring

### 1. Continuous Refactoring
- Refactor in small, incremental steps
- Refactor when you understand the code better
- Remove dead code and unused features
- Simplify before adding new functionality

### 2. Refactoring Safety
- Have tests before refactoring
- Refactor in separate commits from features
- Use automated refactoring tools when available
- Verify behavior hasn't changed after refactoring

## Error Handling

### 1. Clear Error Messages
- Provide clear, actionable error messages
- Include context in error messages
- Log errors with sufficient detail for debugging
- Handle errors at the appropriate level

### 2. Error Handling Strategy
- Fail fast when possible
- Use exceptions for exceptional cases
- Don't swallow errors silently
- Make error recovery paths clear

## Performance

### 1. Performance Considerations
- Write clear code first, optimize when needed
- Measure before optimizing - always profile first
- Optimize bottlenecks, not everything
- Consider performance in design, but don't over-engineer
- Ship working code - optimize later when you have data

### 2. When to Optimize Performance
- **After Profiling**: Only optimize code that profiling shows is slow
- **User Impact**: When performance issues affect real users
- **Scale**: When you have significantly more users/data than before
- **Business Impact**: When performance affects business metrics
- **Measured Bottlenecks**: When monitoring shows actual problems

### 3. Performance Optimization Process
1. **Profile First**: Use profiling tools to find real bottlenecks
2. **Measure Baseline**: Establish performance baselines before changes
3. **Optimize Incrementally**: Make one optimization at a time
4. **Validate Impact**: Measure after each optimization
5. **Document Why**: Record why optimizations were needed

### 4. Performance Trade-offs
- Balance performance with maintainability
- Document performance-critical code
- Use profiling to find real bottlenecks
- Avoid premature optimization
- Don't sacrifice code clarity for theoretical performance gains

### 5. What NOT to Optimize
- Don't optimize code that isn't a bottleneck
- Don't optimize without profiling first
- Don't add complexity for theoretical performance gains
- Don't optimize for scale you don't have
- Don't sacrifice readability for micro-optimizations

## Documentation

### 1. Code Documentation
- Document public APIs clearly
- Use code examples in documentation
- Keep documentation current with code
- Document design decisions and rationale

### 2. README and Guides
- Maintain clear README files for each project
- Document setup and development processes
- Keep documentation simple and focused
- Update documentation as code changes

## Code Standards

### 1. Formatting and Style
- Use automated formatting tools
- Follow consistent style guides
- Configure linters and formatters consistently
- Don't debate style - automate it

### 2. Language-Specific Practices
- Follow language idioms and best practices
- Use language features appropriately
- Avoid anti-patterns for the language
- Keep up with language evolution

## Anti-Patterns to Avoid

- Over-engineering and premature abstraction
- Copy-paste code instead of extracting commonality
- Ignoring compiler/linter warnings
- Writing tests that test the test framework
- Optimizing before measuring
- Complex solutions when simple ones work
- Optimizing code that isn't a bottleneck
- Adding robustness "just in case"
- Enterprise patterns without enterprise problems
- Micro-optimizations that hurt readability

