# Status Badge Components

## Overview

Status badges are used throughout the application to indicate the state of AWS accounts, scans, and findings. They use consistent colors and styling for quick recognition.

## Component: Status Badge

### Variants

#### Account Status Badges

**Pending**
```html
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
  <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-yellow-500"></span>
  Pending
</span>
```

**Active**
```html
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
  <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-green-500"></span>
  Active
</span>
```

**Error**
```html
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
  <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-red-500"></span>
  Error
</span>
```

#### Scan Status Badges

**Running**
```html
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
  <svg class="animate-spin -ml-1 mr-1.5 h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
  </svg>
  Running
</span>
```

**Completed**
```html
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
  <svg class="w-3 h-3 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
  </svg>
  Completed
</span>
```

**Failed**
```html
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
  <svg class="w-3 h-3 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
  </svg>
  Failed
</span>
```

## Implementation Notes

- Use shadcn-vue `Badge` component with custom variants
- Status colors are consistent across the application
- Include icons for visual clarity (spinner for running, checkmark for completed)
- Dark mode variants use opacity for backgrounds
- Badges are always rounded-full for pill shape

## Usage Examples

```vue
<Badge variant="pending">Pending</Badge>
<Badge variant="active">Active</Badge>
<Badge variant="error">Error</Badge>
<Badge variant="running">Running</Badge>
<Badge variant="completed">Completed</Badge>
```

