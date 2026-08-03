# Documentation

This directory contains all project documentation organized by topic.

## Quick Links

### Getting Started
- [Install TOPS](../README.md#install) - the canonical setup path, in the repository README
- [Laravel App Setup](./laravel-app/setup.md) - Detailed setup instructions

### Architecture & Planning
- [**Roadmap**](./roadmap.md) - **The plan of record.** Now / Next / Later, user stories, and the decisions log
- [Architecture](./architecture.md) - System architecture and design decisions
- [AWS Scanner Coverage & Research](./planning.md) - Which AWS services and misconfigurations to scan, current rule coverage, CIS alignment, and the staged plan for expanding it
- [Docker self-hosted deployment session (2026-06-21)](./sessions/2026-06-21-docker-self-hosted-deployment.md) - ECS/Lambda assessment, Docker Compose Phases 1–2, installer, region wiring

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

### AI Coding Assistants

The practices below are the single source of truth. These files load them into an
assistant's context automatically so no session starts without them:

- [`CLAUDE.md`](../CLAUDE.md) - loaded into every Claude Code session
- [`.cursor/rules/`](../.cursor/rules/) - loaded into every Cursor request; `00-practices.mdc`
  always applies, the others attach by file path
- [`.claude/skills/`](../.claude/skills/) - on-demand skills: `feature-development` drives the
  six-phase process, `practices-review` checks a diff against the checklist, `run-tests`
  covers the suite and its Docker fallback
- [`.claude/settings.json`](../.claude/settings.json) - informational hooks that surface the
  practices at session start and on feature-shaped prompts

Keep these in sync when the practices change - they summarise and link, they do not
duplicate.

### Practices
- [Product Practices](./practices/product.md) - Product development practices
- [Architecture Practices](./practices/architecture.md) - Architecture guidelines
- [Security Practices](./practices/security.md) - Security best practices
- [Testing Practices](./practices/testing.md) - Testing guidelines
- [Code Quality](./practices/code-quality.md) - Code quality standards
- [Database Practices](./practices/database.md) - Database design practices

### Progress & Status
- [Progress Tracking](./PROGRESS.md) - Feature completion status, known gaps, and open-source readiness. Records *state*; the [roadmap](./roadmap.md) records *plan*

## Documentation Structure

```
docs/
├── README.md (this file)
├── architecture.md
├── planning.md
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

