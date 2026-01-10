# Severity Indicator Components

## Overview

Severity indicators are used to display the severity level of security findings. They use a consistent color-coding system that aligns with industry standards.

## Component: Severity Indicator

### Variants

#### Critical Severity
```html
<div class="inline-flex items-center">
  <span class="w-3 h-3 rounded-full bg-red-600 mr-2"></span>
  <span class="text-sm font-medium text-red-600 dark:text-red-400">Critical</span>
</div>
```

#### High Severity
```html
<div class="inline-flex items-center">
  <span class="w-3 h-3 rounded-full bg-orange-600 mr-2"></span>
  <span class="text-sm font-medium text-orange-600 dark:text-orange-400">High</span>
</div>
```

#### Medium Severity
```html
<div class="inline-flex items-center">
  <span class="w-3 h-3 rounded-full bg-amber-500 mr-2"></span>
  <span class="text-sm font-medium text-amber-600 dark:text-amber-400">Medium</span>
</div>
```

#### Low Severity
```html
<div class="inline-flex items-center">
  <span class="w-3 h-3 rounded-full bg-blue-500 mr-2"></span>
  <span class="text-sm font-medium text-blue-600 dark:text-blue-400">Low</span>
</div>
```

### Badge Variants

**Critical Badge**
```html
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
  Critical
</span>
```

**High Badge**
```html
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400">
  High
</span>
```

**Medium Badge**
```html
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
  Medium
</span>
```

**Low Badge**
```html
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
  Low
</span>
```

## Color Mapping

| Severity | Color | Tailwind Class | Use Case |
|----------|-------|----------------|----------|
| Critical | Red | `red-600` | Immediate action required |
| High | Orange | `orange-600` | Action required soon |
| Medium | Amber | `amber-500` | Should be addressed |
| Low | Blue | `blue-500` | Nice to have |

## Implementation Notes

- Use consistent colors across all severity displays
- Badges are rounded-full for pill shape
- Include dark mode variants with appropriate opacity
- Severity indicators can be used in tables, cards, and detail views
- Consider using icons alongside colors for accessibility

## Usage Examples

```vue
<SeverityBadge level="critical" />
<SeverityBadge level="high" />
<SeverityBadge level="medium" />
<SeverityBadge level="low" />
```

## Accessibility

- Color is not the only indicator (includes text label)
- Meets WCAG AA contrast requirements
- Screen reader friendly with proper ARIA labels

