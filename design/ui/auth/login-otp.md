# Login - Email OTP Verification

## Layout: Split Auth Layout

Fallback step when user cannot use authenticator app. OTP code is sent to user's email for each login.

## Visual Mockup

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify with Code - Teemops</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
  <div class="min-h-screen flex">
    <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-gray-900 to-gray-800 items-center justify-center p-12">
      <div class="max-w-md">
        <h1 class="text-4xl font-bold text-white mb-4">Simplify Cloud</h1>
        <p class="text-xl text-gray-300">Cloud security made simple for busy CTOs</p>
      </div>
    </div>
    <div class="flex-1 flex items-center justify-center p-8 lg:p-12">
      <div class="w-full max-w-md">
        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 dark:text-white mb-2">Verify your identity</h1>
        <p class="text-gray-600 dark:text-gray-400 mb-6">We've sent a 6-digit code to <strong class="text-gray-900 dark:text-white">j***@example.com</strong></p>
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-8 shadow-sm">
          <form class="space-y-6">
            <div>
              <label for="otp" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Verification code</label>
              <input
                type="text"
                id="otp"
                name="otp"
                maxlength="6"
                placeholder="000000"
                class="w-full px-4 py-3 text-center text-2xl font-mono tracking-widest border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
              />
              <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Code expires in 10 minutes</p>
            </div>
            <button type="submit" class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
              Verify & sign in
            </button>
          </form>
          <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <p class="text-sm text-gray-600 dark:text-gray-400 text-center">Didn't receive the code?</p>
            <button type="button" class="w-full mt-2 text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">
              Resend code
            </button>
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400 text-center">or</p>
            <a href="login.html" class="block mt-2 text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400 text-center">
              Use authenticator app instead
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
```

## Flow Context

- **Trigger**: After successful email/password login, when MFA is enabled but user selects "Email me a code" instead of TOTP
- **Fallback**: Available on every login for users who may have lost their authenticator device
- **Security**: OTP valid for 5–10 minutes; rate limit resend (e.g., 3 per 15 min)

## Implementation Notes

1. **When shown**: Post-password step in MFA flow; user chooses "Authenticator" or "Email code"
2. ** masked email**: Display partially masked (j***@example.com) for privacy
3. **Resend**: Show countdown before allowing resend
4. **Back option**: "Use authenticator app instead" returns to TOTP entry step
