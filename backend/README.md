# TeemOps Security API - Backend

NestJS backend API for cloud security scanning application.

## Prerequisites

- Node.js 18+
- MySQL database (local via Docker or AWS RDS)
- Firebase project with Admin SDK credentials
- AWS account with appropriate permissions

## Setup

1. **Install dependencies**:
   ```bash
   npm install
   ```

2. **Set up environment variables**:
   ```bash
   cp .env.example .env
   # Edit .env with your actual credentials
   ```

3. **Set up database**:
   ```bash
   # Generate Prisma client
   npm run prisma:generate

   # Run migrations
   npm run prisma:migrate
   ```

4. **Start development server**:
   ```bash
   npm run start:dev
   ```

   The API will be available at `http://localhost:3000/api`

## Development

### Running the application

```bash
# Development mode with hot reload
npm run start:dev

# Debug mode (for VS Code debugging)
npm run start:debug

# Production mode
npm run start:prod
```

### Database

```bash
# Generate Prisma client after schema changes
npm run prisma:generate

# Create and run migrations
npm run prisma:migrate

# Open Prisma Studio (database GUI)
npm run prisma:studio
```

### Testing

```bash
# Run all tests
npm test

# Run tests in watch mode
npm run test:watch

# Run tests with coverage
npm run test:cov

# Debug tests (for VS Code debugging)
npm run test:debug
```

### VS Code Debugging

The project includes VS Code debugging configurations:

1. **Debug NestJS**: Debug the running application
   - Press F5 or go to Run > Start Debugging
   - Select "Debug NestJS"
   - Set breakpoints in your code

2. **Debug Jest Tests**: Debug unit tests
   - Select "Debug Jest Tests" configuration
   - Set breakpoints in test files

## Project Structure

```
backend/
├── src/
│   ├── auth/              # Firebase authentication
│   ├── users/             # User management
│   ├── organizations/    # Organization management
│   ├── aws-accounts/      # AWS account management
│   ├── scans/             # Security scanning
│   ├── results/           # Scan results
│   ├── common/            # Shared utilities
│   │   ├── guards/        # Authentication guards
│   │   ├── interceptors/  # Response interceptors
│   │   ├── filters/       # Exception filters
│   │   ├── decorators/    # Custom decorators
│   │   └── utils/         # Utility functions
│   ├── prisma/            # Prisma service
│   └── main.ts            # Application entry point
├── prisma/
│   └── schema.prisma      # Database schema
└── .vscode/
    └── launch.json         # VS Code debug config
```

## Code Quality Requirements

- **File Size**: Keep code files under 1,000 lines
- **Reusability**: Maximize code reuse through shared utilities and services
- **Testing**: Write unit tests for all services
- **Authentication**: All protected endpoints use Firebase Auth via `@UseGuards(AuthGuard)`

## API Documentation

Swagger documentation is available at:
- Development: `http://localhost:3000/api/docs`
- Production: `https://api.teemops.com/api/docs`

## Environment Variables

See `.env.example` for all required environment variables.

## License

Private - TeemOps
