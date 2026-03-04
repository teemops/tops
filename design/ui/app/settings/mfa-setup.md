# MFA Setup - Step 1: Scan QR Code

## Layout: Sidebar App Layout

First step of the Add MFA flow. User scans QR code with authenticator app.

## Visual Mockup

```html
<main class="flex-1">
  <div class="py-6">
    <div class="max-w-md mx-auto px-4 sm:px-6 md:px-8">
      <a href="profile.html" class="text-sm text-blue-600 hover:text-blue-500 dark:text-blue-400 mb-6 inline-block">← Back to Profile</a>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Set up authenticator app</h1>
      <p class="text-gray-600 dark:text-gray-400 mb-6">Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)</p>
      <div class="bg-white dark:bg-gray-800 p-6 rounded-lg border border-gray-200 dark:border-gray-700 inline-block mb-6">
        <div class="w-48 h-48 bg-gray-200 dark:bg-gray-600 rounded flex items-center justify-center text-gray-400 dark:text-gray-500">QR Code</div>
      </div>
      <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Can't scan? Enter this code manually:</p>
      <code class="block p-3 bg-gray-100 dark:bg-gray-700 rounded text-sm font-mono text-gray-900 dark:text-white mb-6">ABCD EFGH IJKL MNOP QRST</code>
      <a href="mfa-setup-verify.html" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Continue</a>
    </div>
  </div>
</main>
```

## Implementation Notes

- QR code contains TOTP secret (e.g., otpauth://totp/...)
- Secret should be displayed as backup for manual entry
- "Continue" navigates to verification step
