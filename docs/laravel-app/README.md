# Laravel Application Documentation

This directory contains all documentation specific to the Laravel application.

## Quick Reference

For essential setup and configuration, see the main [app/README.md](../../app/README.md) in the application folder.

## Documentation Index

### Setup & Configuration
- [Setup Guide](./setup.md) - Complete setup instructions
- [Environment Variables](./ENV_SETUP.md) - All environment variables explained
- [Database Setup](./database-setup.md) - Database configuration and setup
- [Database Architecture](./database-architecture.md) - Database schema and design

### Authentication & Security
- [Firebase OAuth Setup](./FIREBASE_OAUTH_SETUP.md) - OAuth provider configuration
- [Email Verification](./EMAIL_VERIFICATION_SETUP.md) - Email verification setup
- [OAuth Troubleshooting](./TROUBLESHOOTING_OAUTH.md) - OAuth debugging guide
- [OAuth Verification Testing](./TEST_OAUTH_VERIFICATION.md) - Testing OAuth flows

### API & Development
- [API Documentation](./API_DOCUMENTATION.md) - Complete API reference
- [Queue Setup](./QUEUE_SETUP.md) - Background job processing

### Planning & Tasks
- [TODO](./TODO.md) - Current tasks and improvements
- [Implementation Plan](./IMPLEMENTATION_PLAN.md) - Implementation roadmap
- [Architecture Decisions](./ARCHITECTURE_DECISIONS.md) - Key architectural decisions

### Practices
- [Laravel Practices](./PRACTICES.md) - How practices apply to Laravel

## Application Structure

The Laravel application is a monolith with Vue 3 frontend using Inertia.js:

```
app/
├── app/                 # Laravel application code
│   ├── Http/
│   │   ├── Controllers/ # API and web controllers
│   │   ├── Middleware/  # Custom middleware
│   │   └── Requests/   # Form request validation
│   ├── Models/         # Eloquent models
│   ├── Services/       # Business logic services
│   └── Jobs/          # Background jobs
├── resources/
│   └── js/            # Vue 3 frontend
│       ├── Layouts/   # Layout components
│       ├── Pages/     # Inertia pages
│       └── Components/# Reusable components
├── routes/            # Route definitions
├── database/          # Migrations and seeders
└── public/           # Public assets
```

## Technology Stack

- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: Vue 3 + TypeScript + Inertia.js
- **Styling**: Tailwind CSS v4
- **Components**: shadcn-vue
- **Database**: MySQL
- **Authentication**: Firebase Auth
- **Queue**: Laravel Queues (database driver)

## Getting Help

- Check the [main documentation README](../README.md) for general project docs
- See [app/README.md](../../app/README.md) for quick start
- Review [Setup Guide](./setup.md) for detailed setup instructions

