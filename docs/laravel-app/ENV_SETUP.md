# Environment Variables Setup

This document describes all required and optional environment variables for the Teemops Laravel application.

## Required Variables

### Application
```env
APP_NAME="Teemops"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://localhost:8000
```

Generate `APP_KEY` using:
```bash
php artisan key:generate
```

### Database (MySQL)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=teemops
DB_USERNAME=teemops
DB_PASSWORD=your_secure_password
```

### Queue Configuration
```env
QUEUE_CONNECTION=database
```

For production, consider using Redis:
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## Firebase Authentication

Required for user authentication via Firebase:

```env
FIREBASE_PROJECT_ID=your-firebase-project-id
FIREBASE_PRIVATE_KEY_ID=your-private-key-id
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nYour\nPrivate\nKey\nHere\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=your-service-account@project-id.iam.gserviceaccount.com
FIREBASE_CLIENT_ID=your-client-id
FIREBASE_CLIENT_X509_CERT_URL=https://www.googleapis.com/robot/v1/metadata/x509/your-service-account%40project-id.iam.gserviceaccount.com
```

**How to get Firebase credentials:**
1. Go to Firebase Console → Project Settings → Service Accounts
2. Click "Generate New Private Key"
3. Download the JSON file
4. Extract the values and add them to your `.env` file

**Important:** The `FIREBASE_PRIVATE_KEY` must include the `\n` characters for newlines. You can either:
- Use the format shown above with `\n` in the string
- Or use a single-line format and replace actual newlines with `\n`

## AWS Configuration

Required for AWS account onboarding and scanning:

```env
AWS_PARENT_ACCOUNT_ID=123456789012
AWS_CLOUDFORMATION_TEMPLATE_URL=https://s3.amazonaws.com/your-bucket/teemops-cloudformation-template.json
AWS_DEFAULT_REGION=us-east-1
```

**Optional AWS credentials** (if running scans from the same account):
```env
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
```

**Note:** In production, AWS credentials should be managed via IAM roles (EC2 instance roles, ECS task roles, etc.) rather than environment variables.

## Mail Configuration

### Maildev (Local Development)

For local development with Maildev (running as Docker container):

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@teemops.com"
MAIL_FROM_NAME="${APP_NAME}"
```

**Maildev Setup:**
1. Run Maildev container:
   ```bash
   docker run -d -p 1025:1025 -p 1080:1080 --name maildev maildev/maildev
   ```
2. Access Maildev web UI at: `http://localhost:1080`
3. All emails sent by Laravel will be captured in Maildev
4. Port 1025 is the SMTP server (for Laravel)
5. Port 1080 is the web interface (to view emails)

**Note:** If Maildev is running on a different host, update `MAIL_HOST` accordingly (e.g., `maildev` if using Docker Compose).

### Production Mail Configuration

For production, use a proper mail service:

**SMTP (Generic):**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

**AWS SES:**
```env
MAIL_MAILER=ses
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

**Mailgun:**
```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=your-domain.com
MAILGUN_SECRET=your-mailgun-secret
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

## Logging (Optional)

```env
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug
```

## Session & Cache (Optional)

```env
SESSION_DRIVER=database
SESSION_LIFETIME=120

CACHE_STORE=database
CACHE_PREFIX=
```

## Example .env File

```env
APP_NAME="Teemops"
APP_ENV=local
APP_KEY=base64:your-generated-key-here
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=teemops
DB_USERNAME=teemops
DB_PASSWORD=your_secure_password

QUEUE_CONNECTION=database

FIREBASE_PROJECT_ID=your-firebase-project-id
FIREBASE_PRIVATE_KEY_ID=your-private-key-id
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nYour\nPrivate\nKey\nHere\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=your-service-account@project-id.iam.gserviceaccount.com
FIREBASE_CLIENT_ID=your-client-id
FIREBASE_CLIENT_X509_CERT_URL=https://www.googleapis.com/robot/v1/metadata/x509/your-service-account%40project-id.iam.gserviceaccount.com

AWS_PARENT_ACCOUNT_ID=123456789012
AWS_CLOUDFORMATION_TEMPLATE_URL=https://s3.amazonaws.com/your-bucket/teemops-cloudformation-template.json
AWS_DEFAULT_REGION=us-east-1

# Firebase Frontend Configuration (Required for OAuth - see FIREBASE_OAUTH_SETUP.md)
VITE_FIREBASE_API_KEY=
VITE_FIREBASE_AUTH_DOMAIN=
VITE_FIREBASE_PROJECT_ID=
VITE_FIREBASE_STORAGE_BUCKET=
VITE_FIREBASE_MESSAGING_SENDER_ID=
VITE_FIREBASE_APP_ID=
```

## Setup Steps

1. **Copy `.env.example` to `.env`** (if it exists, or create from scratch)
2. **Generate application key:**
   ```bash
   php artisan key:generate
   ```
3. **Configure database:**
   - Create MySQL database
   - Update `.env` with database credentials
   - Run migrations: `php artisan migrate`
4. **Set up queue tables:**
   ```bash
   php artisan migrate
   ```
   (Queue migrations should already be included)
5. **Configure Firebase:**
   - Get service account credentials from Firebase Console
   - Add to `.env` file
6. **Configure AWS:**
   - Set parent AWS account ID
   - Set CloudFormation template URL
7. **Configure Firebase Frontend (Required for OAuth):**
   - Get Firebase config from Firebase Console → Project Settings
   - Add `VITE_FIREBASE_*` variables to `.env`
   - See `FIREBASE_OAUTH_SETUP.md` for detailed instructions
8. **Start queue worker** (for background scan processing):
   ```bash
   php artisan queue:work
   ```

## Production Considerations

1. **Security:**
   - Set `APP_DEBUG=false`
   - Use strong database passwords
   - Store sensitive credentials in secure vaults (AWS Secrets Manager, etc.)
   - Never commit `.env` file to version control

2. **Performance:**
   - Use Redis for queues in production
   - Use Redis for cache and sessions
   - Configure proper queue workers (Supervisor, systemd, etc.)

3. **Monitoring:**
   - Set up log aggregation
   - Monitor queue failures: `php artisan queue:failed`
   - Set up alerts for failed scans

4. **Scalability:**
   - Use multiple queue workers
   - Consider using AWS SQS for queues
   - Use load balancers for API endpoints

