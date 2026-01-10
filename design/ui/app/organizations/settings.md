# Organization Settings Page Design

## Layout: Sidebar App Layout

Page for editing organization details.

## Visual Mockup

```html
<main class="flex-1">
  <div class="py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 md:px-8">
      <!-- Page Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Organization Settings</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Manage your organization details</p>
      </div>

      <!-- Settings Form -->
      <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">General</h2>
        </div>
        <form class="px-6 py-6 space-y-6">
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Organization name
            </label>
            <input
              type="text"
              id="name"
              name="name"
              required
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
              value="My Organization"
            />
          </div>

          <div class="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
            <button
              type="button"
              class="mr-3 inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              Save changes
            </button>
          </div>
        </form>
      </div>

      <!-- Danger Zone -->
      <div class="mt-8 bg-white dark:bg-gray-800 shadow rounded-lg border border-red-200 dark:border-red-800">
        <div class="px-6 py-4 border-b border-red-200 dark:border-red-800">
          <h2 class="text-lg font-semibold text-red-600 dark:text-red-400">Danger Zone</h2>
        </div>
        <div class="px-6 py-6">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-medium text-gray-900 dark:text-white">Delete organization</h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Once deleted, this organization and all its data cannot be recovered. This action cannot be undone.
              </p>
            </div>
            <button
              type="button"
              class="ml-4 inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
            >
              Delete
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
- `Card` - Settings sections
- `Input` - Organization name field
- `Button` - Save and delete buttons
- `Alert` - Warning for danger zone

## Implementation Notes

1. **Delete Protection**: Cannot delete if has AWS accounts or is default
2. **Confirmation**: Show confirmation dialog before deletion
3. **Validation**: Name required, show errors inline

## Laravel Starter Kit Integration

- Page: `resources/js/Pages/Organizations/Settings.vue`

