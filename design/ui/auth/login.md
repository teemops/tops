# Login Page Design

## Layout: Split Auth Layout

The login page uses the split layout with branding on the left and the login form on the right.

## Visual Mockup

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Teemops</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
  <div class="min-h-screen flex">
    <!-- Left Side: Branding -->
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

    <!-- Right Side: Login Form -->
    <div class="flex-1 flex items-center justify-center p-8 lg:p-12">
      <div class="w-full max-w-md">
        <!-- Mobile Logo -->
        <div class="lg:hidden mb-8 text-center">
          <img src="/logo.png" alt="Teemops Logo" class="h-10 w-auto mx-auto mb-4">
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Welcome back</h1>
        </div>

        <!-- Desktop Heading -->
        <div class="hidden lg:block mb-8">
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Welcome back</h1>
          <p class="text-gray-600 dark:text-gray-400">Sign in to your account to continue</p>
        </div>

        <!-- Login Form -->
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
            </div>

            <!-- Remember Me & Forgot Password -->
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <input
                  id="remember"
                  name="remember"
                  type="checkbox"
                  class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                />
                <label for="remember" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                  Remember me
                </label>
              </div>
              <div class="text-sm">
                <a href="/forgot-password" class="font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">
                  Forgot password?
                </a>
              </div>
            </div>

            <!-- Submit Button -->
            <button
              type="submit"
              class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
            >
              Sign in
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

          <!-- Sign Up Link -->
          <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
            Don't have an account?{' '}
            <a href="/register" class="font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">
              Sign up
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
- `Input` - Email and password fields
- `Button` - Submit and OAuth buttons
- `Checkbox` - Remember me checkbox
- `Label` - Form labels
- `Separator` - Divider between form and OAuth

### Layout Structure
- Uses Laravel Starter Kit Vue's split auth layout
- Left side: Branding area (hidden on mobile, shown on lg+)
- Right side: Login form (full width on mobile, half on desktop)

## Responsive Behavior

### Desktop (lg: 1024px+)
- Split layout: 50/50 left/right
- Branding visible on left
- Form centered on right

### Mobile (< 1024px)
- Single column layout
- Branding hidden (logo shown in form area)
- Full-width form

## Interaction States

### Input Fields
- **Default**: Gray border
- **Focus**: Blue ring and border (`ring-2 ring-blue-500`)
- **Error**: Red border (not shown in mockup, but should be implemented)

### Buttons
- **Default**: Blue background (`bg-blue-600`)
- **Hover**: Darker blue (`hover:bg-blue-700`)
- **Focus**: Blue ring (`focus:ring-2 focus:ring-blue-500`)
- **Disabled**: Gray background, reduced opacity

### OAuth Buttons
- **Default**: White background with border
- **Hover**: Light gray background (`hover:bg-gray-50`)

## Color Tokens

- **Background**: `bg-gray-50` (light), `bg-gray-900` (dark)
- **Card Background**: `bg-white` (light), `bg-gray-800` (dark)
- **Primary Button**: `bg-blue-600`, `hover:bg-blue-700`
- **Text Primary**: `text-gray-900` (light), `text-white` (dark)
- **Text Secondary**: `text-gray-600` (light), `text-gray-400` (dark)
- **Border**: `border-gray-200` (light), `border-gray-700` (dark)

## Spacing Guide

- **Container Padding**: `p-8 lg:p-12` (32px mobile, 48px desktop)
- **Form Padding**: `p-8` (32px)
- **Field Spacing**: `space-y-6` (24px vertical)
- **Button Padding**: `py-2.5 px-4` (10px vertical, 16px horizontal)
- **OAuth Grid Gap**: `gap-3` (12px)

## Implementation Notes

1. **Form Validation**: Use Laravel validation with Inertia.js error handling
2. **OAuth Integration**: Buttons should trigger Laravel Socialite flows
3. **Remember Me**: Store authentication token in cookie
4. **Dark Mode**: All colors have dark mode variants
5. **Accessibility**: Proper labels, ARIA attributes, keyboard navigation
6. **Loading States**: Show loading spinner on submit button during authentication
7. **Error Display**: Show validation errors below form fields using Inertia's error bag

## Laravel Starter Kit Integration

This design maps to:
- Layout: `resources/js/Layouts/Auth/AuthSplitLayout.vue`
- Page: `resources/js/Pages/Auth/Login.vue`
- Uses Inertia.js form helper for submission
- Integrates with Laravel authentication

