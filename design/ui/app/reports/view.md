# Report View Page Design

## Layout: Sidebar App Layout

Detailed report view with executive summary and findings.

## Visual Mockup

```html
<main class="flex-1">
  <div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
      <!-- Header -->
      <div class="mb-8 flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Security Report</h1>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Production AWS • Generated Jan 15, 2024</p>
        </div>
        <div class="flex items-center space-x-3">
          <button class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50">
            Export PDF
          </button>
          <button class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50">
            Export CSV
          </button>
        </div>
      </div>

      <!-- Executive Summary -->
      <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700 mb-8">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Executive Summary</h2>
        </div>
        <div class="px-6 py-6">
          <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
              <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">Overall Security Score</h3>
              <div class="text-4xl font-bold text-gray-900 dark:text-white mb-2">85%</div>
              <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div class="bg-green-600 h-2 rounded-full" style="width: 85%"></div>
              </div>
            </div>
            <div>
              <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">Findings Summary</h3>
              <div class="space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-600 dark:text-gray-400">Critical</span>
                  <span class="text-sm font-medium text-red-600 dark:text-red-400">2</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-600 dark:text-gray-400">High</span>
                  <span class="text-sm font-medium text-orange-600 dark:text-orange-400">5</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-600 dark:text-gray-400">Medium</span>
                  <span class="text-sm font-medium text-amber-600 dark:text-amber-400">8</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-600 dark:text-gray-400">Low</span>
                  <span class="text-sm font-medium text-blue-600 dark:text-blue-400">8</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Findings Section (similar to scan detail) -->
      <!-- ... -->
    </div>
  </div>
</main>
```

## Component Breakdown

### shadcn-vue Components Used
- `Card` - Summary sections
- `Progress` - Security score indicator
- `Button` - Export buttons

## Implementation Notes

1. **Export Formats**: PDF and CSV export
2. **Executive Summary**: High-level overview for CTOs
3. **Findings**: Same structure as scan detail page

## Laravel Starter Kit Integration

- Page: `resources/js/Pages/Reports/Show.vue`

