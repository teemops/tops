# Organizations List Page Design

## Layout: Sidebar App Layout

The organizations list page allows users to view and manage all their organizations.

## Visual Mockup

```html
<!-- Uses same sidebar and top bar as dashboard, main content below -->

<main class="flex-1">
  <div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
      <!-- Page Header -->
      <div class="mb-8 flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Organizations</h1>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Manage your organizations and switch between them</p>
        </div>
        <button class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
          <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          Add Organization
        </button>
      </div>

      <!-- Organizations Grid -->
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <!-- Organization Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow">
          <div class="p-6">
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center">
                  <h3 class="text-lg font-semibold text-gray-900 dark:text-white">My Organization</h3>
                  <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                    Default
                  </span>
                </div>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">3 AWS accounts</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">Created Jan 15, 2024</p>
              </div>
              <div class="ml-4">
                <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                  </svg>
                </button>
              </div>
            </div>
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
              <button class="w-full text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">
                Switch to this organization
              </button>
            </div>
          </div>
        </div>

        <!-- Organization Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow">
          <div class="p-6">
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Client A</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">1 AWS account</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">Created Feb 1, 2024</p>
              </div>
              <div class="ml-4">
                <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                  </svg>
                </button>
              </div>
            </div>
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
              <button class="w-full text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">
                Switch to this organization
              </button>
            </div>
          </div>
        </div>

        <!-- Organization Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow">
          <div class="p-6">
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Client B</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">2 AWS accounts</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">Created Feb 10, 2024</p>
              </div>
              <div class="ml-4">
                <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                  </svg>
                </button>
              </div>
            </div>
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
              <button class="w-full text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">
                Switch to this organization
              </button>
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
- `Card` - Organization cards
- `Button` - Add organization and switch buttons
- `Badge` - Default organization badge
- `DropdownMenu` - Actions menu (edit/delete)

### Layout Structure
- Same sidebar and top bar as dashboard
- Grid layout for organization cards
- Add organization button in header

## Responsive Behavior

- **Desktop**: 3-column grid
- **Tablet**: 2-column grid
- **Mobile**: 1-column grid

## Interaction States

- **Card Hover**: Shadow increases
- **Switch Button**: Changes to "Current" when active
- **Actions Menu**: Dropdown with edit/delete options

## Implementation Notes

1. **Default Organization**: Cannot be deleted, marked with badge
2. **Switch Organization**: Updates context, refreshes all data
3. **Delete Protection**: Cannot delete if has AWS accounts
4. **Empty State**: Show message if no organizations (shouldn't happen, default always exists)

## Laravel Starter Kit Integration

- Page: `resources/js/Pages/Organizations/Index.vue`
- Uses organization store for context switching

