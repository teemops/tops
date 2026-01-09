# Password Reset Page Design

## Layout: Split Auth Layout

The password reset page uses the split layout, with a simple form to request a password reset link.

## Visual Mockup

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - Teemops</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
  <div class="min-h-screen flex">
    <!-- Left Side: Branding -->
    <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-gray-900 to-gray-800 items-center justify-center p-12">
      <div class="max-w-md">
        <div class="mb-8">
          <img src="/logo.png" alt="Teemops Logo" class="h-12 w-auto mb-6">
          <h1 class="text-4xl font-bold text-white mb-4">Forgot your password?</h1>
          <p class="text-xl text-gray-300">No worries, we'll help you reset it</p>
        </div>
        <div class="space-y-4 text-gray-400">
          <div class="flex items-start">
            <svg class="h-6 w-6 mr-3 mt-0.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <div>
              <p class="font-medium text-white">Check your email</p>
              <p class="text-sm">We'll send you a secure reset link</p>
            </div>
          </div>
          <div class="flex items-start">
            <svg class="h-6 w-6 mr-3 mt-0.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <div>
              <p class="font-medium text-white">Secure & Private</p>
              <p class="text-sm">Your reset link expires in 1 hour</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Side: Reset Form -->
    <div class="flex-1 flex items-center justify-center p-8 lg:p-12">
      <div class="w-full max-w-md">
        <!-- Mobile Logo -->
        <div class="lg:hidden mb-8 text-center">
          <img src="/logo.png" alt="Teemops Logo" class="h-10 w-auto mx-auto mb-4">
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Reset password</h1>
        </div>

        <!-- Desktop Heading -->
        <div class="hidden lg:block mb-8">
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Reset password</h1>
          <p class="text-gray-600 dark:text-gray-400">Enter your email and we'll send you a reset link</p>
        </div>

        <!-- Reset Form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-8 shadow-sm">
          <form class="space-y-6">
            <!-- Email Field -->
            <div>
              <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Email address
              </label>
              <input
                type="email"
                id="email"
                name="email"
                required
                autofocus
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="you@example.com"
              />
              <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                We'll send a password reset link to this email address.
              </p>
            </div>

            <!-- Submit Button -->
            <button
              type="submit"
              class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
            >
              Send reset link
            </button>
          </form>

          <!-- Back to Login -->
          <div class="mt-6 text-center">
            <a href="/login" class="text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">
              ← Back to sign in
            </a>
          </div>
        </div>

        <!-- Success Message (shown after submission) -->
        <div class="mt-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 hidden">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-green-800 dark:text-green-200">
                Check your email
              </p>
              <p class="mt-1 text-sm text-green-700 dark:text-green-300">
                We've sent a password reset link to your email address.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
```

## Component Breakdown

### shadcn-vue Components Used
- `Input` - Email field
- `Button` - Submit button
- `Label` - Form label
- `Alert` - Success message (shown after submission)

### Layout Structure
- Same split layout as login/register
- Simpler form with single email field
- Success message appears after submission

## Responsive Behavior

Same as login page.

## Interaction States

- **Email Input**: Same as login page
- **Submit Button**: Shows loading state during request
- **Success Message**: Appears after successful submission, replaces form

## Color Tokens

Same as login page, plus:
- **Success Alert**: `bg-green-50`, `border-green-200`, `text-green-800`

## Spacing Guide

- **Form Padding**: `p-8` (32px)
- **Field Spacing**: `space-y-6` (24px vertical)
- **Help Text**: `mt-2` (8px top margin)

## Implementation Notes

1. **Email Validation**: Validate email format on frontend and backend
2. **Rate Limiting**: Prevent abuse with rate limiting (Laravel throttle)
3. **Success State**: Show success message after submission, hide form
4. **Error Handling**: Show error if email not found (but don't reveal if account exists for security)
5. **Email Sending**: Use Laravel's mail system to send reset link
6. **Link Expiration**: Reset links expire after 1 hour (configurable)
7. **Security**: Use secure tokens for reset links

## Laravel Starter Kit Integration

- Layout: `resources/js/Layouts/Auth/AuthSplitLayout.vue`
- Page: `resources/js/Pages/Auth/ForgotPassword.vue`
- Uses Laravel's password reset functionality
- Integrates with Laravel's notification system

## Password Reset Email

The reset email should contain:
- Clear subject line: "Reset Your Teemops Password"
- Reset link with secure token
- Expiration notice (1 hour)
- Security notice if user didn't request reset

