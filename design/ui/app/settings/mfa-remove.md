# Remove MFA - Confirmation

## Layout: Full Page Modal / Dialog

Confirmation step before removing MFA. User must enter password to confirm.

## Visual Mockup

```html
<main class="flex-1 flex items-center justify-center min-h-[60vh]">
  <div class="max-w-md w-full mx-4">
    <a href="profile-mfa-enabled.html" class="text-sm text-blue-600 hover:text-blue-500 dark:text-blue-400 mb-6 inline-block">← Back to Profile</a>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-6">
      <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Remove multi-factor authentication?</h1>
      <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Your account will be less secure. You'll need to enter your password to confirm.</p>
      <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Password</label>
        <input type="password" placeholder="Enter your password" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white" />
      </div>
      <div class="flex gap-3 justify-end">
        <a href="profile-mfa-enabled.html" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300">Cancel</a>
        <button type="button" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Remove MFA</button>
      </div>
    </div>
  </div>
</main>
```

## Implementation Notes

- Require current password or valid TOTP code to confirm removal
- On success: Redirect to profile (MFA disabled state), show success toast
- Alternative: Implement as modal overlay on profile page instead of separate page
