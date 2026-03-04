# User Profile - MFA Enabled State

## Layout: Sidebar App Layout

Profile page when MFA is already enabled. No alert shown. MFA section displays device info and Remove option.

## Visual Mockup

```html
<main class="flex-1">
  <div class="py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 md:px-8">
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Profile Settings</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Manage your account information</p>
      </div>

      <!-- Profile Form -->
      <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Profile Information</h2>
        </div>
        <form class="px-6 py-6 space-y-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Full name</label>
            <input type="text" value="John Doe" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email address</label>
            <input type="email" value="john@example.com" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white" />
          </div>
          <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Save changes</button>
          </div>
        </form>
      </div>

      <!-- MFA Section: Enabled - View Device & Remove -->
      <div class="mt-8 bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Multi-Factor Authentication</h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Your account is protected with MFA</p>
        </div>
        <div class="px-6 py-6 space-y-4">
          <div class="flex items-center justify-between p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800 flex-wrap gap-4">
            <div class="flex items-center gap-3">
              <div class="flex-shrink-0 w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/40 flex items-center justify-center">
                <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
              <div>
                <p class="font-medium text-gray-900 dark:text-white">Authenticator app</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Added January 15, 2025</p>
              </div>
            </div>
            <a href="mfa-remove.html" class="inline-flex items-center px-4 py-2 border border-red-300 dark:border-red-700 text-sm font-medium rounded-md text-red-700 dark:text-red-400 bg-white dark:bg-gray-800 hover:bg-red-50 dark:hover:bg-red-900/20">
              Remove MFA
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
```

## Implementation Notes

- No MFA alert when enabled
- Device type and "Added" date from backend
- "Remove MFA" opens confirmation modal (see profile.md) or navigates to mfa-remove page
