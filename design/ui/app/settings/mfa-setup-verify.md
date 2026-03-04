# MFA Setup - Step 2: Verify Code

## Layout: Sidebar App Layout

Second step of the Add MFA flow. User enters 6-digit code from authenticator app to confirm setup.

## Visual Mockup

```html
<main class="flex-1">
  <div class="py-6">
    <div class="max-w-md mx-auto px-4 sm:px-6 md:px-8">
      <a href="mfa-setup.html" class="text-sm text-blue-600 hover:text-blue-500 dark:text-blue-400 mb-6 inline-block">← Back</a>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Verify setup</h1>
      <p class="text-gray-600 dark:text-gray-400 mb-6">Enter the 6-digit code from your authenticator app</p>
      <input type="text" maxlength="6" placeholder="000000" class="w-full px-4 py-3 text-center text-2xl font-mono tracking-widest border border-gray-300 dark:border-gray-600 rounded-md mb-6 dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500" />
      <div class="flex gap-3">
        <a href="mfa-setup.html" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300">Back</a>
        <button type="button" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Verify & enable MFA</button>
      </div>
    </div>
  </div>
</main>
```

## Implementation Notes

- Validate code against TOTP secret
- On success: Redirect to profile, show success toast, MFA section updates to "enabled" state
- On failure: Show inline error, allow retry
