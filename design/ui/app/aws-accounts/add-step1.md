# Add AWS Account - Step 1: CloudFormation Setup

## Layout: Sidebar App Layout

First step of the AWS account onboarding flow - CloudFormation setup.

## Visual Mockup

```html
<main class="flex-1">
  <div class="py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 md:px-8">
      <!-- Progress Indicator -->
      <div class="mb-8">
        <div class="flex items-center">
          <div class="flex items-center">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center">
              <span class="text-white text-sm font-medium">1</span>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-900 dark:text-white">CloudFormation Setup</p>
            </div>
          </div>
          <div class="flex-auto border-t-2 border-gray-300 dark:border-gray-600 mx-4"></div>
          <div class="flex items-center">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
              <span class="text-gray-600 dark:text-gray-400 text-sm font-medium">2</span>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Details</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Instructions Card -->
      <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Add AWS Account</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Connect your AWS account securely using CloudFormation</p>
        </div>
        <div class="px-6 py-6 space-y-6">
          <!-- Instructions -->
          <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Step 1: Deploy CloudFormation Stack</h2>
            <ol class="list-decimal list-inside space-y-3 text-sm text-gray-700 dark:text-gray-300">
              <li>Click the button below to open the AWS CloudFormation console</li>
              <li>Review the stack parameters (pre-filled for you)</li>
              <li>Click "Create stack" to deploy</li>
              <li>Wait for the stack creation to complete</li>
              <li>Return to this page - your account will be automatically connected</li>
            </ol>
          </div>

          <!-- CloudFormation Button -->
          <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-blue-900 dark:text-blue-200">Ready to deploy CloudFormation stack?</p>
                <p class="mt-1 text-xs text-blue-700 dark:text-blue-300">This will open AWS Console in a new window</p>
              </div>
              <button
                type="button"
                onclick="window.open('https://console.aws.amazon.com/cloudformation/...', '_blank')"
                class="ml-4 inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                Open AWS Console
                <svg class="ml-2 -mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Status Indicator -->
          <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
              </div>
              <div class="ml-3">
                <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Waiting for CloudFormation setup</p>
                <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">We're checking for your account connection. This page will update automatically when the stack is created.</p>
              </div>
            </div>
          </div>

          <!-- Manual Entry Option -->
          <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Having trouble with CloudFormation?</p>
            <button
              type="button"
              class="text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400"
            >
              Enter account details manually →
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
```

## Component Breakdown

### shadcn-vue Components Used
- `Card` - Instructions container
- `Button` - CloudFormation and manual entry buttons
- `Alert` - Status and warning messages
- `Progress` - Step indicator

## Implementation Notes

1. **CloudFormation URL**: Generated by backend, opens in new window
2. **Status Polling**: Poll account status every 5 seconds
3. **Auto-advance**: Move to step 2 when account becomes active
4. **Manual Fallback**: Link to manual entry page
5. **Progress Indicator**: Show 2-step progress

## Laravel Starter Kit Integration

- Page: `resources/js/Pages/AwsAccounts/Add/Step1.vue`
- Polls account status endpoint

