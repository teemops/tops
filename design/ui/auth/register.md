# Registration Page Design

## Layout: Split Auth Layout

The registration page uses the same split layout as login, with branding on the left and registration form on the right.

## Visual Mockup

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up - Teemops</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
  <div class="min-h-screen flex">
    <!-- Left Side: Branding (same as login) -->
    <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-gray-900 to-gray-800 items-center justify-center p-12">
      <div class="max-w-md">
        <div class="mb-8">
          <img src="/logo.png" alt="Teemops Logo" class="h-12 w-auto mb-6">
          <h1 class="text-4xl font-bold text-white mb-4">Simplify Cloud</h1>
          <p class="text-xl text-gray-300">Cloud security made simple for busy CTOs</p>
        </div>
        <div class="space-y-4 text-gray-400">
          <div class="flex items-start">
            <svg class="h-6 w-6 mr-3 mt-0.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
              <p class="font-medium text-white">Automated Security Scanning</p>
              <p class="text-sm">Find misconfigurations in minutes, not days</p>
            </div>
          </div>
          <div class="flex items-start">
            <svg class="h-6 w-6 mr-3 mt-0.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            <div>
              <p class="font-medium text-white">One-Click Remediation</p>
              <p class="text-sm">Fix issues with actionable playbooks</p>
            </div>
          </div>
          <div class="flex items-start">
            <svg class="h-6 w-6 mr-3 mt-0.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <div>
              <p class="font-medium text-white">Enterprise-Grade Security</p>
              <p class="text-sm">Built for compliance and peace of mind</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Side: Registration Form -->
    <div class="flex-1 flex items-center justify-center p-8 lg:p-12">
      <div class="w-full max-w-md">
        <!-- Mobile Logo -->
        <div class="lg:hidden mb-8 text-center">
          <img src="/logo.png" alt="Teemops Logo" class="h-10 w-auto mx-auto mb-4">
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create your account</h1>
        </div>

        <!-- Desktop Heading -->
        <div class="hidden lg:block mb-8">
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Create your account</h1>
          <p class="text-gray-600 dark:text-gray-400">Get started with Teemops in minutes</p>
        </div>

        <!-- Registration Form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-8 shadow-sm">
          <form class="space-y-5">
            <!-- Full Name Field -->
            <div>
              <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Full name
              </label>
              <input
                type="text"
                id="name"
                name="name"
                required
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="John Doe"
              />
            </div>

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
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="you@example.com"
              />
            </div>

            <!-- Password Field -->
            <div>
              <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Password
              </label>
              <input
                type="password"
                id="password"
                name="password"
                required
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="••••••••"
              />
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Must be at least 8 characters</p>
            </div>

            <!-- Confirm Password Field -->
            <div>
              <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Confirm password
              </label>
              <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                required
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="••••••••"
              />
            </div>

            <!-- Terms and Conditions -->
            <div class="flex items-start">
              <input
                id="terms"
                name="terms"
                type="checkbox"
                required
                class="h-4 w-4 mt-0.5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
              />
              <label for="terms" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                I agree to the{' '}
                <a href="/terms" class="text-blue-600 hover:text-blue-500 dark:text-blue-400">Terms of Service</a>
                {' '}and{' '}
                <a href="/privacy" class="text-blue-600 hover:text-blue-500 dark:text-blue-400">Privacy Policy</a>
              </label>
            </div>

            <!-- Submit Button -->
            <button
              type="submit"
              class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
            >
              Create account
            </button>
          </form>

          <!-- Divider -->
          <div class="mt-6">
            <div class="relative">
              <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-300 dark:border-gray-600"></div>
              </div>
              <div class="relative flex justify-center text-sm">
                <span class="px-2 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400">Or continue with</span>
              </div>
            </div>
          </div>

          <!-- OAuth Buttons -->
          <div class="mt-6 grid grid-cols-3 gap-3">
            <button
              type="button"
              class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
            >
              <svg class="h-5 w-5" viewBox="0 0 24 24">
                <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
              </svg>
            </button>
            <button
              type="button"
              class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
            >
              <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M11.4 24H0V12.6h11.4V24zM24 24h-11.4V12.6H24V24zM11.4 11.4H0V0h11.4v11.4zM24 11.4h-11.4V0H24v11.4z"/>
              </svg>
            </button>
            <button
              type="button"
              class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
            >
              <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.568 8.16c-.169 1.858-.896 3.405-2.043 4.588-1.193 1.228-2.693 1.858-4.525 1.858-.896 0-1.728-.152-2.496-.456l-.456.456v1.728H5.568V6.144h2.496v4.368c.608-.608 1.368-1.024 2.28-1.248.912-.224 1.824-.152 2.736.224.912.304 1.672.912 2.28 1.824.608.912.912 1.976.912 3.192v.304z"/>
              </svg>
            </button>
          </div>

          <!-- Sign In Link -->
          <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
            Already have an account?{' '}
            <a href="/login" class="font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">
              Sign in
            </a>
          </p>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
```

## Component Breakdown

### shadcn-vue Components Used
- `Input` - Name, email, password fields
- `Button` - Submit and OAuth buttons
- `Checkbox` - Terms and conditions
- `Label` - Form labels
- `Separator` - Divider between form and OAuth

### Layout Structure
- Same split layout as login page
- Additional fields: name, password confirmation
- Terms and conditions checkbox required

## Responsive Behavior

Same as login page:
- Desktop: Split 50/50 layout
- Mobile: Single column, branding hidden

## Interaction States

Same as login page, with additional:
- Password strength indicator (optional enhancement)
- Real-time password confirmation matching

## Color Tokens

Same as login page.

## Spacing Guide

- **Form Field Spacing**: `space-y-5` (20px vertical) - slightly tighter than login
- **Checkbox Spacing**: `mt-0.5` for alignment
- All other spacing matches login page

## Implementation Notes

1. **Password Validation**: Minimum 8 characters, enforce on backend
2. **Password Confirmation**: Validate match on both frontend and backend
3. **Terms Checkbox**: Required, must be checked to submit
4. **Default Organization**: Automatically create default organization on registration
5. **Email Verification**: Send verification email after registration
6. **OAuth Registration**: Same OAuth providers as login
7. **Error Handling**: Show validation errors for all fields

## Laravel Starter Kit Integration

- Layout: `resources/js/Layouts/Auth/AuthSplitLayout.vue`
- Page: `resources/js/Pages/Auth/Register.vue`
- Uses Laravel's registration controller
- Creates default organization after successful registration

