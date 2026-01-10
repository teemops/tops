# User Profile Settings Page Design

## Layout: Sidebar App Layout

User profile management page.

## Visual Mockup

```html
<main class="flex-1">
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
    </div>
  </div>
</main>
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
3. **Validation**: Show errors inline
4. **Success Messages**: Toast notifications on success

## Laravel Starter Kit Integration

- Page: `resources/js/Pages/Settings/Profile.vue`

