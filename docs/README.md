# Documentation

This directory contains all project documentation organized by topic.

## Quick Links

### Getting Started
- [Quick Start Guide](./quick-start.md) - Get up and running quickly
- [Laravel App Setup](./laravel-app/setup.md) - Detailed setup instructions

### Architecture & Planning
- [Architecture](./architecture.md) - System architecture and design decisions
- [Planning](./planning.md) - Project planning and roadmap

### Features
- [Feature Specifications](./features/features-spec.md) - All feature specifications and user stories
- [AWS Account Onboarding Flow](./features/onboarding-flow.md) - Detailed onboarding process

### Laravel Application
- [Laravel App Documentation](./laravel-app/README.md) - Laravel-specific documentation
- [API Documentation](./laravel-app/API_DOCUMENTATION.md) - API endpoints reference
- [Database Architecture](./laravel-app/database-architecture.md) - Database schema and design
- [Environment Setup](./laravel-app/ENV_SETUP.md) - Environment variables guide
- [OAuth Setup](./laravel-app/FIREBASE_OAUTH_SETUP.md) - Firebase OAuth configuration
- [Email Verification](./laravel-app/EMAIL_VERIFICATION_SETUP.md) - Email verification setup
- [Queue Setup](./laravel-app/QUEUE_SETUP.md) - Background job processing

### Development
- [Feature Development Process](./processes/feature-development.md) - How to develop features
- [Practices Checklist](./processes/practices-checklist.md) - Development practices checklist
- [User Story Template](./templates/user-story-template.md) - Template for writing user stories

### Practices
- [Product Practices](./practices/product.md) - Product development practices
- [Architecture Practices](./practices/architecture.md) - Architecture guidelines
- [Security Practices](./practices/security.md) - Security best practices
- [Testing Practices](./practices/testing.md) - Testing guidelines
- [Code Quality](./practices/code-quality.md) - Code quality standards
- [Database Practices](./practices/database.md) - Database design practices

### Progress & Status
- [Progress Tracking](./PROGRESS.md) - Feature completion status and progress

## Documentation Structure

```
docs/
├── README.md (this file)
├── architecture.md
├── planning.md
├── quick-start.md
├── PROGRESS.md
├── laravel-app/          # Laravel application documentation
│   ├── README.md
│   ├── setup.md
│   ├── API_DOCUMENTATION.md
│   └── ...
├── features/            # Feature specifications
│   ├── features-spec.md
│   └── onboarding-flow.md
├── design/              # Design documentation
│   └── wireframes.md
├── processes/           # Development processes
│   ├── feature-development.md
│   └── practices-checklist.md
├── practices/           # Development practices
│   ├── product.md
│   ├── architecture.md
│   └── ...
└── templates/           # Document templates
    └── user-story-template.md
```

## Contributing to Documentation

When adding new documentation:
1. Place it in the appropriate subdirectory
2. Update this README with a link
3. Follow existing documentation patterns
4. Keep documentation up to date with code changes

