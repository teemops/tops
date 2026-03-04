# Insights & Analytics Page Design

## Layout: Sidebar App Layout

Analytics dashboard with charts, trends, compliance tracking, and historical insights. Helps users track security posture over time and identify patterns.

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
        <div class="flex items-center space-x-3">
          <select class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
            <option>Last 30 days</option>
            <option>Last 90 days</option>
            <option>Last year</option>
          </select>
          <button class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
            Export Report
          </button>
        </div>
      </div>

      <!-- Key Metrics -->
      <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <!-- Total Findings (vs period) -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                  <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                  </svg>
                </div>
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Findings</dt>
                  <dd class="flex items-baseline">
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">47</div>
                    <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600 dark:text-green-400">
                      <svg class="self-center flex-shrink-0 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                      </svg>
                      <span class="sr-only">Down from</span>
                      12%
                    </div>
                  </dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <!-- Critical Open -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="h-10 w-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                  <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                  </svg>
                </div>
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Critical Open</dt>
                  <dd class="text-2xl font-semibold text-gray-900 dark:text-white">7</dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <!-- Remediation Rate -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="h-10 w-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                  <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                  </svg>
                </div>
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Remediation Rate</dt>
                  <dd class="text-2xl font-semibold text-gray-900 dark:text-white">68%</dd>
                </dl>
              </div>
            </div>
          </div>
        </div>

        <!-- Avg Compliance -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="h-10 w-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                  <svg class="h-6 w-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                </div>
              </div>
              <div class="ml-5 w-0 flex-1">
                <dl>
                  <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Avg Compliance</dt>
                  <dd class="text-2xl font-semibold text-gray-900 dark:text-white">82%</dd>
                </dl>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-8">
        <!-- Findings Trend Chart -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700 p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Findings Trend</h3>
            <div class="flex items-center space-x-2 text-xs text-gray-500 dark:text-gray-400">
              <span class="flex items-center"><span class="w-3 h-3 rounded-full bg-blue-500 mr-1"></span>New</span>
              <span class="flex items-center"><span class="w-3 h-3 rounded-full bg-green-500 mr-1"></span>Resolved</span>
            </div>
          </div>
          <div class="h-64 flex items-center justify-center text-gray-400 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
            Line chart: X-axis (dates), Y-axis (count). Two series: New findings (blue), Resolved (green)
          </div>
          <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Daily aggregation for selected period</p>
        </div>

        <!-- Severity Distribution -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700 p-6">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Severity Distribution</h3>
          <div class="flex items-center gap-8">
            <div class="h-48 w-48 flex-shrink-0 flex items-center justify-center rounded-full bg-gray-50 dark:bg-gray-900/50 border-8 border-gray-200 dark:border-gray-700">
              Donut chart: Critical 8%, High 18%, Medium 42%, Low 32%
            </div>
            <div class="flex-1 space-y-3">
              <div class="flex items-center justify-between">
                <span class="inline-flex items-center"><span class="w-3 h-3 rounded-full bg-red-600 mr-2"></span>Critical</span>
                <span class="text-sm font-medium text-gray-900 dark:text-white">4</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="inline-flex items-center"><span class="w-3 h-3 rounded-full bg-orange-600 mr-2"></span>High</span>
                <span class="text-sm font-medium text-gray-900 dark:text-white">9</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="inline-flex items-center"><span class="w-3 h-3 rounded-full bg-amber-500 mr-2"></span>Medium</span>
                <span class="text-sm font-medium text-gray-900 dark:text-white">21</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="inline-flex items-center"><span class="w-3 h-3 rounded-full bg-blue-500 mr-2"></span>Low</span>
                <span class="text-sm font-medium text-gray-900 dark:text-white">16</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Row: Compliance + Top Services -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-8">
        <!-- Compliance Scores -->
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Compliance Scores</h2>
            <span class="text-xs text-gray-500 dark:text-gray-400">By framework</span>
          </div>
          <div class="px-6 py-6 space-y-5">
            <div>
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">CIS AWS Foundations</span>
                <span class="text-sm font-medium text-gray-900 dark:text-white">85%</span>
              </div>
              <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                <div class="bg-green-600 h-2.5 rounded-full" style="width: 85%"></div>
              </div>
            </div>
            <div>
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">CIS AWS Level 2</span>
                <span class="text-sm font-medium text-gray-900 dark:text-white">78%</span>
              </div>
              <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                <div class="bg-green-500 h-2.5 rounded-full" style="width: 78%"></div>
              </div>
            </div>
            <div>
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">PCI-DSS</span>
                <span class="text-sm font-medium text-gray-900 dark:text-white">92%</span>
              </div>
              <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                <div class="bg-green-600 h-2.5 rounded-full" style="width: 92%"></div>
              </div>
            </div>
            <div>
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">SOC 2</span>
                <span class="text-sm font-medium text-gray-900 dark:text-white">81%</span>
              </div>
              <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                <div class="bg-green-600 h-2.5 rounded-full" style="width: 81%"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Top Affected Services -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Top Affected Services</h2>
          </div>
          <div class="px-6 py-4 divide-y divide-gray-200 dark:divide-gray-700">
            <div class="py-3 flex items-center justify-between">
              <span class="text-sm font-medium text-gray-900 dark:text-white">S3</span>
              <span class="text-sm text-gray-500 dark:text-gray-400">12 findings</span>
            </div>
            <div class="py-3 flex items-center justify-between">
              <span class="text-sm font-medium text-gray-900 dark:text-white">IAM</span>
              <span class="text-sm text-gray-500 dark:text-gray-400">9 findings</span>
            </div>
            <div class="py-3 flex items-center justify-between">
              <span class="text-sm font-medium text-gray-900 dark:text-white">EC2</span>
              <span class="text-sm text-gray-500 dark:text-gray-400">7 findings</span>
            </div>
            <div class="py-3 flex items-center justify-between">
              <span class="text-sm font-medium text-gray-900 dark:text-white">CloudTrail</span>
              <span class="text-sm text-gray-500 dark:text-gray-400">5 findings</span>
            </div>
            <div class="py-3 flex items-center justify-between">
              <span class="text-sm font-medium text-gray-900 dark:text-white">RDS</span>
              <span class="text-sm text-gray-500 dark:text-gray-400">4 findings</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Trend Summary / Insights Alerts -->
      <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Key Insights</h2>
          <span class="text-xs text-gray-500 dark:text-gray-400">Last 30 days</span>
        </div>
        <div class="px-6 py-6">
          <ul class="space-y-4">
            <li class="flex items-start">
              <span class="flex-shrink-0 w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              </span>
              <div class="ml-3">
                <p class="text-sm font-medium text-gray-900 dark:text-white">Findings down 12% vs. previous period</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Remediation efforts are improving security posture</p>
              </div>
            </li>
            <li class="flex items-start">
              <span class="flex-shrink-0 w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
              </span>
              <div class="ml-3">
                <p class="text-sm font-medium text-gray-900 dark:text-white">S3 has most findings (12)</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Focus on bucket policies and public access</p>
              </div>
            </li>
            <li class="flex items-start">
              <span class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              </span>
              <div class="ml-3">
                <p class="text-sm font-medium text-gray-900 dark:text-white">PCI-DSS score at 92%</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Highest compliance score among frameworks</p>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</main>
```

## Chart Specifications

### Findings Trend (Line Chart)
- **X-axis**: Dates (formatted by period: daily for 30d, weekly for 90d, monthly for 1y)
- **Y-axis**: Count of findings
- **Series 1**: New findings discovered (blue line)
- **Series 2**: Findings resolved (green line)
- **Tooltip**: Show exact counts for each date on hover
- **Empty state**: "No scan data for selected period"

### Severity Distribution (Donut Chart)
- **Segments**: Critical (red), High (orange), Medium (amber), Low (blue)
- **Center**: Total count or "View all" link
- **Legend**: Inline with counts, right of chart
- **Colors**: Use severity-indicators component palette

## Component Breakdown

### shadcn-vue Components Used
- `Card` - Chart containers, compliance section, insights list
- `Progress` - Compliance score bars
- `Select` - Time range selector
- `Button` - Export Report
- Chart library (Chart.js, Recharts, or similar)

### Layout Structure
- Full-width header with title and controls
- 4-column metric grid (matches dashboard)
- 2-column chart grid
- 3-column bottom section (2:1 compliance : top services)
- Full-width Key Insights summary

## Responsive Behavior

### Desktop (lg: 1024px+)
- 4-column metrics grid
- Charts side-by-side
- Compliance 2 cols + Top Services 1 col

### Tablet (md: 768px)
- 2-column metrics grid
- Charts stack vertically
- Compliance and Top Services stack

### Mobile (< 768px)
- 1-column metrics grid
- Charts full-width, stacked
- Severity distribution: donut above legend
- Reduced padding (`px-4`)

## Interaction States

- **Time Range Select**: Refreshes all data when changed
- **Export Report**: Generates PDF/CSV of current view
- **Chart Hover**: Tooltip with exact values
- **Top Services Rows**: Optional click-through to filtered scans
- **Compliance Bars**: Hover shows framework details
- **Key Insights**: Static summary (or link to detailed report)

## Color Tokens

- **Metric Cards**: Same as dashboard (blue, red, green, emerald icons)
- **Chart Colors**: Severity palette for donut; blue/green for trend lines
- **Progress Bars**: `bg-green-600` (80%+), `bg-green-500` (60–79%), `bg-amber-500` (<60%)

## Spacing Guide

- **Page Padding**: `px-4 sm:px-6 md:px-8`
- **Section Spacing**: `mb-8` (32px)
- **Card Padding**: `p-6` for charts, `px-6 py-4` for list headers
- **Grid Gap**: `gap-5` metrics, `gap-6` charts

## Implementation Notes

1. **Charts**: Use Chart.js, Recharts, or ApexCharts; responsive with `resize` handler
2. **Time Range**: Filter all data by selected period; persist in URL query for shareability
3. **Compliance**: Track CIS AWS Foundations, CIS Level 2, PCI-DSS, SOC 2
4. **Trends**: Calculate new vs resolved from scan diff over time
5. **Top Services**: Aggregate by AWS service (S3, IAM, EC2, etc.)
6. **Key Insights**: Generated from data (e.g., trend %, top service, best/worst framework)
7. **Export**: Include metrics, charts (as images), compliance, insights in PDF
8. **Empty States**: Show "Run a scan" CTA when no data for period

## Laravel Starter Kit Integration

- Page: `resources/js/Pages/Insights/Index.vue`
- Chart components from shadcn-vue or dedicated chart library
- API: `GET /api/insights?period=30d` returning aggregated metrics and time-series

