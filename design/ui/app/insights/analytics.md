# Insights & Analytics Page Design

## Layout: Sidebar App Layout

Analytics dashboard with charts, trends, and compliance tracking.

## Visual Mockup

```html
<main class="flex-1">
  <div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
      <!-- Header -->
      <div class="mb-8 flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Insights & Analytics</h1>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Track trends and compliance over time</p>
        </div>
        <select class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
          <option>Last 30 days</option>
          <option>Last 90 days</option>
          <option>Last year</option>
        </select>
      </div>

      <!-- Key Metrics -->
      <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <!-- Metric cards similar to dashboard -->
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-8">
        <!-- Findings Trend Chart -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700 p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Findings Trend</h3>
          <!-- Chart.js or similar chart component -->
          <div class="h-64 flex items-center justify-center text-gray-400">
            [Chart: Line chart showing findings over time]
          </div>
        </div>

        <!-- Severity Distribution -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700 p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Severity Distribution</h3>
          <!-- Chart component -->
          <div class="h-64 flex items-center justify-center text-gray-400">
            [Chart: Pie or donut chart]
          </div>
        </div>
      </div>

      <!-- Compliance Scores -->
      <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Compliance Scores</h2>
        </div>
        <div class="px-6 py-6">
          <div class="space-y-4">
            <div>
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">CIS Benchmarks</span>
                <span class="text-sm font-medium text-gray-900 dark:text-white">85%</span>
              </div>
              <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div class="bg-green-600 h-2 rounded-full" style="width: 85%"></div>
              </div>
            </div>
            <!-- More compliance frameworks -->
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
```

## Component Breakdown

### shadcn-vue Components Used
- `Card` - Chart containers
- `Progress` - Compliance score bars
- `Select` - Time range selector
- Chart library (Chart.js or similar)

## Implementation Notes

1. **Charts**: Use Chart.js or similar library
2. **Time Range**: Filter data by selected period
3. **Compliance**: Track multiple frameworks
4. **Trends**: Show improvement/decline over time

## Laravel Starter Kit Integration

- Page: `resources/js/Pages/Insights/Index.vue`
- Chart components from shadcn-vue or Chart.js

