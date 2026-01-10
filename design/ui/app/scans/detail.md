# Scan Detail Page Design

## Layout: Sidebar App Layout

Detailed view of a single scan with findings and remediation steps.

## Visual Mockup

```html
<main class="flex-1">
  <div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Production AWS Scan</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Completed 2 hours ago</p>
          </div>
          <div class="flex items-center space-x-3">
            <button class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
              Export PDF
            </button>
            <button class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
              Run New Scan
            </button>
          </div>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 gap-5 sm:grid-cols-4 mb-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="h-10 w-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                  <span class="text-red-600 dark:text-red-400 text-lg font-bold">2</span>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Critical</p>
              </div>
            </div>
          </div>
        </div>
        <!-- Similar cards for High, Medium, Low -->
      </div>

      <!-- Findings -->
      <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Findings</h2>
          <div class="flex items-center space-x-2">
            <select class="text-sm border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white">
              <option>All severities</option>
              <option>Critical</option>
              <option>High</option>
            </select>
          </div>
        </div>
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
          <!-- Finding Item -->
          <div class="px-6 py-4">
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 mr-3">
                    Critical
                  </span>
                  <h3 class="text-sm font-medium text-gray-900 dark:text-white">S3 Bucket Public Access</h3>
                </div>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                  Bucket "public-assets" has public read access enabled. This exposes all objects in the bucket to the internet.
                </p>
                <div class="mt-3 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                  <span>Resource: s3://public-assets</span>
                  <span>Service: S3</span>
                  <span>Region: us-east-1</span>
                </div>
              </div>
              <div class="ml-4">
                <button class="text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">
                  View Details
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
```

## Component Breakdown

### shadcn-vue Components Used
- `Card` - Summary and findings cards
- `Badge` - Severity indicators
- `Table` - Findings list (optional)
- `Button` - Actions

## Implementation Notes

1. **Findings Filter**: Filter by severity
2. **Export**: Generate PDF/CSV reports
3. **Remediation**: Show remediation steps for each finding
4. **Resource Links**: Link to AWS Console where applicable

## Laravel Starter Kit Integration

- Page: `resources/js/Pages/Scans/Show.vue`

