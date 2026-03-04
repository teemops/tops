# User Profile Settings Page Design

## Layout: Sidebar App Layout

User profile management page with MFA settings. Two states documented: MFA disabled (default) and MFA enabled.

## State 1: MFA Disabled (Default)

When MFA is not enabled, a persistent alert is shown at the top of the screen across all app pages.

## Visual Mockup

```html
<main class="flex-1">
  <!-- MFA Disabled Alert: Persistent at top of screen (app-wide when MFA off) -->
  <div class="bg-amber-50 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8 py-3">
      <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-3">
          <svg class="h-5 w-5 text-amber-600 dark:text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
          <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
            Multi-factor authentication is not enabled. Your account is less secure.
          </p>
        </div>
        <a href="#mfa-section" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-amber-900 bg-amber-400 hover:bg-amber-300 dark:bg-amber-600 dark:hover:bg-amber-500 dark:text-amber-900 transition-colors">
          Enable MFA
        </a>
      </div>
    </div>
  </div>

  <div class="py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 md:px-8">
      <!-- Header -->
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
            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Full name
            </label>
            <input
              type="text"
              id="name"
              name="name"
              required
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
              value="John Doe"
            />
          </div>

          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Email address
            </label>
            <input
              type="email"
              id="email"
              name="email"
              required
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
              value="john@example.com"
            />
          </div>

          <div class="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
            <button
              type="submit"
              class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
            >
              Save changes
            </button>
          </div>
        </form>
      </div>

      <!-- Password Section -->
      <div class="mt-8 bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Change Password</h2>
        </div>
        <form class="px-6 py-6 space-y-6">
          <div>
            <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Current password
            </label>
            <input
              type="password"
              id="current_password"
              name="current_password"
              required
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
            />
          </div>

          <div>
            <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              New password
            </label>
            <input
              type="password"
              id="new_password"
              name="new_password"
              required
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
            />
          </div>

          <div>
            <label for="new_password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Confirm new password
            </label>
            <input
              type="password"
              id="new_password_confirmation"
              name="new_password_confirmation"
              required
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
            />
          </div>

          <div class="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
            <button
              type="submit"
              class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
            >
              Update password
            </button>
          </div>
        </form>
      </div>

      <!-- MFA Section: State 1 - MFA Disabled (Enable CTA) -->
      <div id="mfa-section" class="mt-8 bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Multi-Factor Authentication</h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Add an extra layer of security to your account</p>
        </div>
        <div class="px-6 py-6">
          <div class="flex items-start gap-4">
            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
              <svg class="h-6 w-6 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
              </svg>
            </div>
            <div class="flex-1">
              <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Use an authenticator app (Google Authenticator, Authy) or receive codes by email. Required for stronger account protection.
              </p>
              <a href="mfa-setup.html" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                Enable MFA
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
```

## State 2: MFA Enabled (View Device & Remove)

When MFA is enabled, the alert is hidden. The MFA section shows the registered device and a remove option.

```html
<!-- MFA Section: State 2 - MFA Enabled (View & Remove) -->
<div class="mt-8 bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
  <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Multi-Factor Authentication</h2>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Your account is protected with MFA</p>
  </div>
  <div class="px-6 py-6 space-y-4">
    <div class="flex items-center justify-between p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
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
      <button type="button" class="inline-flex items-center px-4 py-2 border border-red-300 dark:border-red-700 text-sm font-medium rounded-md text-red-700 dark:text-red-400 bg-white dark:bg-gray-800 hover:bg-red-50 dark:hover:bg-red-900/20">
        Remove MFA
      </button>
    </div>
  </div>
</div>
```

## Add MFA Setup Flow (Multi-Step)

Two-step wizard for enabling MFA.

### Step 1: Scan QR Code

```html
<div class="max-w-md mx-auto py-8">
  <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Set up authenticator app</h2>
  <p class="text-gray-600 dark:text-gray-400 mb-6">Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)</p>
  <div class="bg-white dark:bg-gray-800 p-6 rounded-lg border border-gray-200 dark:border-gray-700 inline-block mb-6">
    <div class="w-48 h-48 bg-gray-200 dark:bg-gray-600 rounded flex items-center justify-center text-gray-400">QR Code</div>
  </div>
  <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Can't scan? Enter this code manually:</p>
  <code class="block p-3 bg-gray-100 dark:bg-gray-700 rounded text-sm font-mono text-gray-900 dark:text-white mb-6">ABCD EFGH IJKL MNOP QRST</code>
  <a href="mfa-setup-verify.html" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Continue</a>
</div>
```

### Step 2: Verify Code

```html
<div class="max-w-md mx-auto py-8">
  <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Verify setup</h2>
  <p class="text-gray-600 dark:text-gray-400 mb-6">Enter the 6-digit code from your authenticator app</p>
  <input type="text" maxlength="6" placeholder="000000" class="w-full px-4 py-3 text-center text-2xl font-mono tracking-widest border border-gray-300 dark:border-gray-600 rounded-md mb-6 dark:bg-gray-700 dark:text-white" />
  <div class="flex gap-3">
    <button type="button" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300">Back</button>
    <button type="button" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Verify & enable MFA</button>
  </div>
</div>
```

### Remove MFA Confirmation (Modal)

```html
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
  <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Remove multi-factor authentication?</h3>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Your account will be less secure. You'll need to enter your password to confirm.</p>
    <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Password</label>
      <input type="password" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white" placeholder="Enter your password" />
    </div>
    <div class="flex gap-3 justify-end">
      <button type="button" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300">Cancel</button>
      <button type="button" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Remove MFA</button>
    </div>
  </div>
</div>
```

## Component Breakdown

### shadcn-vue Components Used
- `Card` - Settings sections
- `Input` - Form fields
- `Button` - Save buttons
- `Label` - Form labels

## Implementation Notes

1. **Profile Update**: Update name and email
2. **Password Change**: Separate form with current password verification
3. **MFA Alert**: When MFA is disabled, show persistent alert at top of all app pages until enabled
4. **MFA Section**: Dynamic based on state—disabled shows "Enable MFA" CTA; enabled shows device info and "Remove MFA"
5. **Validation**: Show errors inline
6. **Success Messages**: Toast notifications on success

## Laravel Starter Kit Integration

- Page: `resources/js/Pages/Settings/Profile.vue`

