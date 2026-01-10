# Data Card Components

## Overview

Data cards are used throughout the application to display information in a structured, scannable format. They provide consistent styling and spacing.

## Component: Stat Card

Used for displaying key metrics on the dashboard.

```html
<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6">
  <div class="flex items-center justify-between">
    <div>
      <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Accounts</p>
      <p class="text-2xl font-bold text-gray-900 dark:text-gray-50 mt-1">12</p>
    </div>
    <div class="h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
      <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
      </svg>
    </div>
  </div>
  <div class="mt-4">
    <span class="text-sm text-green-600 dark:text-green-400 font-medium">+2 from last month</span>
  </div>
</div>
```

## Component: Info Card

Used for displaying detailed information with actions.

```html
<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6">
  <div class="flex items-start justify-between">
    <div class="flex-1">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-50">Production AWS</h3>
      <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Account ID: 123456789012</p>
      <div class="mt-3">
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
          <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-green-500"></span>
          Active
        </span>
      </div>
    </div>
    <div class="flex items-center space-x-2">
      <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
        </svg>
      </button>
    </div>
  </div>
  <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between text-sm">
      <span class="text-gray-600 dark:text-gray-400">Last scan</span>
      <span class="text-gray-900 dark:text-gray-50 font-medium">2 hours ago</span>
    </div>
  </div>
</div>
```

## Component: List Card

Used for displaying lists of items.

```html
<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
  <div class="p-6 border-b border-gray-200 dark:border-gray-700">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-50">Recent Scans</h3>
  </div>
  <div class="divide-y divide-gray-200 dark:divide-gray-700">
    <div class="p-6 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-900 dark:text-gray-50">Production AWS Scan</p>
          <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Completed 2 hours ago</p>
        </div>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
          Completed
        </span>
      </div>
    </div>
    <!-- More items... -->
  </div>
</div>
```

## Component: Empty State Card

Used when there's no data to display.

```html
<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-12 text-center">
  <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
  </svg>
  <h3 class="mt-4 text-sm font-medium text-gray-900 dark:text-gray-50">No AWS accounts</h3>
  <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Get started by adding your first AWS account.</p>
  <div class="mt-6">
    <button class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
      Add AWS Account
    </button>
  </div>
</div>
```

## Implementation Notes

- All cards use consistent border radius (`rounded-lg`)
- Cards have subtle borders for definition
- Dark mode support with appropriate color variants
- Hover states on interactive cards
- Consistent padding (`p-6` for content)
- Use shadcn-vue `Card` component as base

## Spacing Patterns

- **Card Padding**: `p-6` (24px)
- **Card Gap**: `space-y-4` or `space-y-6` between elements
- **Section Spacing**: `mt-6` or `pt-6` for sections within cards
- **Border Spacing**: `border-t` or `border-b` with `pt-4` or `pb-4`

## Usage Examples

```vue
<Card>
  <CardHeader>
    <CardTitle>Total Accounts</CardTitle>
  </CardHeader>
  <CardContent>
    <div class="text-2xl font-bold">12</div>
  </CardContent>
</Card>
```

