# Email Verification Setup

This document explains how email verification works for users who register with username/password (non-OAuth).

## How It Works

1. **User registers** with email/password
2. **Laravel sends verification email** automatically via the `Registered` event
3. **User is redirected** to the email verification page (`/verify-email`)
4. **User clicks link** in email to verify their account
5. **User is redirected** to dashboard after verification

## User Model

The `User` model implements `MustVerifyEmail`, which:
- Requires email verification before accessing protected routes
- Provides methods like `hasVerifiedEmail()` and `markEmailAsVerified()`
- Automatically sends verification emails when a user registers

## Registration Flow

When a user registers via `RegisteredUserController`:

1. User is created in database
2. `Registered` event is fired (triggers email sending)
3. User is logged in
4. User is redirected to `/verify-email` (email verification page)

## Email Verification Routes

- **GET `/verify-email`** - Shows email verification prompt page
- **GET `/verify-email/{id}/{hash}`** - Verifies email when user clicks link
- **POST `/email/verification-notification`** - Resends verification email

All routes require authentication (`auth` middleware).

## Middleware Protection

To protect routes that require verified emails, add the `verified` middleware:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    // Protected routes that require email verification
});
```

Currently, the dashboard and other app routes should require email verification.

## Email Configuration

See `ENV_SETUP.md` for mail configuration. For local development with Maildev:

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@teemops.com"
MAIL_FROM_NAME="Teemops"
```

## OAuth Users

Users who register via OAuth (Google, GitHub, Microsoft) are automatically verified because:
- Their email is already verified by the OAuth provider
- The `FirebaseAuthController` sets `email_verified_at` when creating the user

## Testing Email Verification

1. **Register a new user** with email/password
2. **Check Maildev** at `http://localhost:1080` (or your mail log)
3. **Click the verification link** in the email
4. **You should be redirected** to dashboard with `?verified=1` in the URL

## Troubleshooting

### Email not sending
- Check mail configuration in `.env`
- Verify Maildev is running: `docker ps | grep maildev`
- Check Laravel logs: `storage/logs/laravel.log`
- Test mail sending: `php artisan tinker` → `Mail::raw('Test', function($m) { $m->to('test@example.com')->subject('Test'); });`

### Verification link not working
- Check that `APP_URL` in `.env` matches your application URL
- Verify the link hasn't expired (default: 60 minutes)
- Check Laravel logs for errors

### User can access dashboard without verification
- Ensure routes are protected with `verified` middleware
- Check that `User` model implements `MustVerifyEmail`

## Customization

### Change verification email template

Publish the email verification notification:
```bash
php artisan vendor:publish --tag=laravel-notifications
```

Edit: `resources/views/vendor/notifications/email.blade.php`

### Change verification link expiration

In `app/Providers/AppServiceProvider.php`:
```php
use Illuminate\Auth\Notifications\VerifyEmail;

public function boot(): void
{
    VerifyEmail::createUrlUsing(function ($notifiable) {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24), // Change from default 60 minutes
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
        );
    });
}
```

