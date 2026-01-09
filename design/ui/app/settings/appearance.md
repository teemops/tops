# Appearance Settings Page Design

## Layout: Sidebar App Layout

Appearance and theme settings page.

## Visual Mockup

```html
<main class="flex-1">
  <div class="py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 md:px-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Appearance</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Customize your theme preferences</p>
      </div>

      <!-- Theme Selection -->
      <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Theme</h2>
        </div>
        <div class="px-6 py-6">
          <div class="space-y-4">
            <label class="flex items-center">
              <input type="radio" name="theme" value="light" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
              <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">Light</span>
            </label>
            <label class="flex items-center">
              <input type="radio" name="theme" value="dark" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
              <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">Dark</span>
            </label>
            <label class="flex items-center">
              <input type="radio" name="theme" value="system" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
              <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">System (follows your device settings)</span>
            </label>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
```

## Component Breakdown

### shadcn-vue Components Used
- `Card` - Settings container
- `RadioGroup` - Theme selection
- `Label` - Option labels

## Implementation Notes

1. **Theme Toggle**: Update theme immediately on selection
2. **System Theme**: Detect and follow OS preference
3. **Persistence**: Save preference to user settings

## Laravel Starter Kit Integration

- Page: `resources/js/Pages/Settings/Appearance.vue`
- Uses Laravel Starter Kit's theme system

