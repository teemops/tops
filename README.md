# Teemops - Cloud Security Scanning Application

A cloud security scanning application built with Laravel 11 and Vue 3, providing AWS security scanning and compliance monitoring.

## Project Structure

```
saas/
├── app/                 # Laravel application (monolith with Vue frontend)
│   ├── app/            # Laravel application code
│   ├── resources/js/   # Vue 3 frontend (Inertia.js)
│   ├── routes/         # Laravel routes
│   └── database/       # Migrations and seeders
├── docs/               # All project documentation
│   ├── laravel-app/    # Laravel-specific docs
│   ├── features/       # Feature specifications
│   └── practices/      # Development practices
├── design/             # UI designs and mockups
└── references/         # Reference files (CloudFormation templates, etc.)
```

## Getting Started

### Prerequisites

- **PHP 8.2+** (8.3 recommended)
- **Composer** (PHP dependency manager)
- **Node.js 18+** and npm
- **MySQL** database (local or AWS RDS)
- **Firebase project** (for authentication)
- **AWS account** (for scanning functionality)

### Quick Start

1. **Navigate to app directory**:
   ```bash
   cd app
   ```

2. **Follow setup instructions**:
   See [app/README.md](./app/README.md) for detailed setup instructions.

3. **Or use the setup script**:
   ```bash
   cd app
   ./setup-laravel.sh
   ```

### Development

**Terminal 1 (Laravel)**:
```bash
cd app
php artisan serve
```

**Terminal 2 (Vite)**:
```bash
cd app
npm run dev
```

Visit: http://localhost:8000

## Technology Stack

- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: Vue 3 + TypeScript + Inertia.js
- **Styling**: Tailwind CSS v4
- **Components**: shadcn-vue
- **Database**: MySQL
- **Authentication**: Firebase Auth
- **Queue**: Laravel Queues

## Documentation

### Getting Started
- [Quick Start Guide](./docs/quick-start.md) - Get up and running quickly
- [Laravel App Setup](./docs/laravel-app/setup.md) - Detailed setup instructions
- [Environment Setup](./docs/laravel-app/ENV_SETUP.md) - Environment variables

### Architecture & Planning
- [Architecture](./docs/architecture.md) - System architecture and design decisions
- [Planning](./docs/planning.md) - Project planning and roadmap

### Features
- [Feature Specifications](./docs/features/features-spec.md) - All features and user stories
- [AWS Account Onboarding](./docs/features/onboarding-flow.md) - Onboarding process
- [API Documentation](./docs/laravel-app/API_DOCUMENTATION.md) - API endpoints

### Development
- [Feature Development Process](./docs/processes/feature-development.md) - How to develop features
- [Practices Checklist](./docs/processes/practices-checklist.md) - Development practices
- [User Story Template](./docs/templates/user-story-template.md) - Template for user stories

### Full Documentation Index
See [docs/README.md](./docs/README.md) for complete documentation index.

## Key Features

- **Multi-tenant Organizations**: Isolated data per organization
- **AWS Account Management**: Secure cross-account IAM role setup
- **Security Scanning**: Custom checks for S3, IAM, EC2, RDS
- **OAuth Authentication**: Google, GitHub, Microsoft support
- **Email Verification**: Required for email/password users
- **Background Jobs**: Asynchronous scan processing

## License

[Add your license here]

## Support

For issues and questions, see the documentation or create an issue in the repository.
