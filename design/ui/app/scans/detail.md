# Scan Detail Page Design

## Layout: Sidebar App Layout

Detailed view of a single scan with severity summary cards, findings list with filters, and remediation steps. Primary page for reviewing and triaging security findings.

## Visual Mockup

```html
<main class="flex-1">
  <div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
      <!-- Breadcrumb -->
      <nav class="mb-6 flex" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-2 text-sm">
          <li><a href="/scans" class="text-gray-500 dark:text-gray-400 hover:text-gray-700">Scans</a></li>
          <li><span class="text-gray-400">/</span></li>
          <li class="text-gray-900 dark:text-white font-medium">Production AWS Scan</li>
        </ol>
      </nav>

      <!-- Header -->
      <div class="mb-8">
        <div class="flex items-center justify-between flex-wrap gap-4">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Production AWS Scan</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Completed 2 hours ago • Account: 123456789012 • Region: us-east-1</p>
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
      <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0 h-10 w-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                <span class="text-red-600 dark:text-red-400 text-lg font-bold">2</span>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Critical</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">Requires immediate action</p>
              </div>
            </div>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0 h-10 w-10 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                <span class="text-orange-600 dark:text-orange-400 text-lg font-bold">5</span>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">High</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">Address within days</p>
              </div>
            </div>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0 h-10 w-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                <span class="text-amber-600 dark:text-amber-400 text-lg font-bold">8</span>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Medium</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">Should be addressed</p>
              </div>
            </div>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
          <div class="p-5">
            <div class="flex items-center">
              <div class="flex-shrink-0 h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                <span class="text-blue-600 dark:text-blue-400 text-lg font-bold">8</span>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Low</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">Best practice</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Findings -->
      <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between flex-wrap gap-4">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Findings (23)</h2>
          <div class="flex items-center space-x-3">
            <select class="text-sm border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white px-3 py-1.5">
              <option>All severities</option>
              <option>Critical</option>
              <option>High</option>
              <option>Medium</option>
              <option>Low</option>
            </select>
            <select class="text-sm border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white px-3 py-1.5">
              <option>All services</option>
              <option>S3</option>
              <option>IAM</option>
              <option>RDS</option>
            </select>
            <input type="text" placeholder="Search findings..." class="text-sm border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white px-3 py-1.5 w-48"/>
          </div>
        </div>
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
          <!-- Finding Item (expandable) -->
          <div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
            <div class="flex items-start justify-between">
              <div class="flex-1 min-w-0">
                <div class="flex items-center flex-wrap gap-2">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">Critical</span>
                  <span class="text-xs text-gray-500 dark:text-gray-400">S3</span>
                  <h3 class="text-sm font-medium text-gray-900 dark:text-white">S3 Bucket Public Access</h3>
                </div>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                  Bucket "public-assets" has public read access enabled. This exposes all objects in the bucket to the internet.
                </p>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                  <span>Resource: s3://public-assets</span>
                  <span>Region: us-east-1</span>
                </div>
                <!-- Expanded: Remediation -->
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                  <h4 class="text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase">Remediation</h4>
                  <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">1. Navigate to S3 Console → public-assets → Permissions. 2. Block public access (recommended). 3. Remove bucket policy allowing public read.</p>
                  <a href="#" class="mt-2 inline-flex items-center text-xs font-medium text-blue-600 dark:text-blue-400">Open in AWS Console →</a>
                </div>
              </div>
              <div class="ml-4 flex-shrink-0 flex items-center space-x-2">
                <button class="text-sm font-medium text-blue-600 dark:text-blue-400">View Details</button>
                <button class="p-1.5 rounded hover:bg-gray-200 dark:hover:bg-gray-600" title="Dismiss">×</button>
              </div>
            </div>
          </div>
          <!-- More findings... -->
        </div>
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
          <p class="text-sm text-gray-500 dark:text-gray-400">Showing 1-10 of 23</p>
          <div class="flex space-x-2"><button class="...">Previous</button><button class="...">Next</button></div>
        </div>
      </div>
    </div>
  </div>
</main>
```

## Finding Item Structure

### Collapsed State
- Severity badge
- Service tag (S3, IAM, etc.)
- Title
- Short description (1–2 lines)
- Resource ID and region
- "View Details" button

### Expanded State (on click or "View Details")
- Remediation section with numbered steps
- "Open in AWS Console" link (when applicable)
- CIS/PCI control ID if mapped

### Finding Metadata
- **Severity**: Critical, High, Medium, Low
- **Service**: S3, IAM, EC2, RDS, CloudTrail, etc.
- **Resource**: ARN or identifier
- **Region**: us-east-1, global, etc.
- **Control ID**: e.g. CIS 1.1, PCI 3.4

## Component Breakdown

### shadcn-vue Components Used
- `Card` - Summary and findings cards
- `Badge` - Severity indicators
- `Select` - Severity and service filters
- `Input` - Search
- `Button` - Actions
- `Collapsible` or custom for expandable findings

### Layout Structure
- Breadcrumb (Scans → this scan)
- Header with scan name, metadata, actions
- 4 severity summary cards
- Findings list with toolbar (filters, search)
- Pagination

## Responsive Behavior

### Desktop (lg: 1024px+)
- 4-column severity cards
- Full filters in toolbar

### Tablet (md: 768px)
- 2-column severity cards
- Filters may wrap

### Mobile (< 768px)
- Single-column severity cards
- Stacked filters, simplified search
- Condensed finding rows

## Interaction States

- **Severity Cards**: Optional click to filter findings
- **Finding Row**: Expand/collapse for remediation
- **View Details**: Opens side panel or modal with full finding + remediation
- **Export PDF**: Generates report
- **Run New Scan**: Confirmation, then triggers scan
- **Dismiss**: Mark finding as acknowledged (with optional reason)
- **Search**: Debounced filter
- **Filters**: Refetch or filter client-side

## Color Tokens

- **Severity**: Red (Critical), Orange (High), Amber (Medium), Blue (Low) – from severity-indicators

## Spacing Guide

- **Page Padding**: `px-4 sm:px-6 md:px-8`
- **Section Spacing**: `mb-8`
- **Finding Row**: `px-6 py-4`
- **Remediation Block**: `mt-4 pt-4` with border-t

## Implementation Notes

1. **Findings Filter**: Filter by severity, service; combine filters
2. **Search**: Full-text on title, description, resource
3. **Export**: PDF/CSV of current filtered view
4. **Remediation**: Store per-finding; may include links to docs
5. **Resource Links**: Deep link to AWS Console when possible (region, service, resource)
6. **Dismiss/Acknowledge**: Track per finding; show in list
7. **Pagination**: Server-side for large scans
8. **Real-time**: If scan running, show progress and stream new findings

## Laravel Starter Kit Integration

- Page: `resources/js/Pages/Scans/Show.vue`
- Route: `scans/{scan}`
- API: Findings paginated, filterable
