# AWS Account Status - Pending

## Layout: Sidebar App Layout

Shown when an AWS account is in pending status, waiting for CloudFormation completion.

## Visual Mockup

```html
<!-- Similar to add-step1, but shown on accounts list or detail page -->

<div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-6">
  <div class="flex">
    <div class="flex-shrink-0">
      <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
      </svg>
    </div>
    <div class="ml-3 flex-1">
      <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Account setup in progress</h3>
      <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
        <p>This account is waiting for CloudFormation stack creation. Once you complete the stack in AWS Console, the account will be automatically connected.</p>
      </div>
      <div class="mt-4">
        <div class="flex space-x-3">
          <button
            type="button"
            onclick="window.open('https://console.aws.amazon.com/cloudformation/...', '_blank')"
            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-yellow-800 bg-yellow-100 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 dark:bg-yellow-900/30 dark:text-yellow-200 dark:hover:bg-yellow-900/50"
          >
            Open CloudFormation
            <svg class="ml-2 -mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
          </button>
          <button
            type="button"
            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          >
            Enter Manually
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
```

## Component Breakdown

### shadcn-vue Components Used
- `Alert` - Warning alert
- `Button` - Action buttons

## Implementation Notes

1. **Auto-refresh**: Poll account status every 5 seconds
2. **CloudFormation Link**: Opens pre-filled CloudFormation URL
3. **Manual Fallback**: Link to manual entry
4. **Status Update**: Automatically updates when account becomes active

