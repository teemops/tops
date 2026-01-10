# Teemops UI Design System

## Overview

This design system provides implementable UI mockups for the Teemops cloud security platform, built to work with Laravel Starter Kit Vue, shadcn-vue components, and Tailwind CSS V4.

## Brand Identity

- **Brand**: Teemops (by Teem, NZ-based)
- **Tagline**: "Simplify Cloud"
- **Tone**: Clear, concise, cut through industry noise
- **Target Users**: Time-poor CTOs in SMBs (20-200 staff)
- **Logo**: Orange network/connection logo on dark background

## Design Principles

1. **Clarity First**: Time-poor CTOs need information fast
2. **Action-Oriented**: Primary actions always visible
3. **Status Visibility**: Clear indicators for account/scan status
4. **Progressive Disclosure**: Complex flows broken into steps
5. **Error Prevention**: Clear validation and helpful error messages
6. **Mobile Responsive**: Works on all screen sizes

## Color Palette

### Primary Colors
- **Primary**: `blue-600` (#2563eb) - Trust, security, professional
- **Primary Dark**: `blue-700` (#1d4ed8) - Hover states
- **Primary Light**: `blue-50` (#eff6ff) - Backgrounds

### Semantic Colors
- **Success**: `green-600` (#16a34a) - Positive states, active accounts
- **Warning**: `amber-500` (#f59e0b) - Medium severity findings
- **Error**: `red-600` (#dc2626) - Critical/high severity, errors
- **Info**: `blue-500` (#3b82f6) - Informational messages

### Neutral Colors
- **Background**: `gray-50` (#f9fafb) - Light mode background
- **Background Dark**: `gray-900` (#111827) - Dark mode background
- **Surface**: `white` (#ffffff) - Card backgrounds (light)
- **Surface Dark**: `gray-800` (#1f2937) - Card backgrounds (dark)
- **Text Primary**: `gray-900` (#111827) - Light mode text
- **Text Primary Dark**: `gray-50` (#f9fafb) - Dark mode text
- **Text Secondary**: `gray-600` (#4b5563) - Secondary text
- **Text Secondary Dark**: `gray-400` (#9ca3af) - Dark mode secondary text
- **Border**: `gray-200` (#e5e7eb) - Borders (light)
- **Border Dark**: `gray-700` (#374151) - Borders (dark)

### Status Colors
- **Pending**: `yellow-500` (#eab308) - Waiting states
- **Active**: `green-600` (#16a34a) - Active/healthy states
- **Error**: `red-600` (#dc2626) - Error states
- **Critical**: `red-700` (#b91c1c) - Critical severity
- **High**: `orange-600` (#ea580c) - High severity
- **Medium**: `amber-500` (#f59e0b) - Medium severity
- **Low**: `blue-500` (#3b82f6) - Low severity

## Typography

### Font Families
- **Primary**: Inter (system font stack: `ui-sans-serif, system-ui, sans-serif`)
- **Monospace**: `ui-monospace, 'Courier New', monospace` (for AWS account IDs, ARNs)

### Font Sizes (Tailwind)
- **xs**: 0.75rem (12px) - Small labels, captions
- **sm**: 0.875rem (14px) - Body text, form labels
- **base**: 1rem (16px) - Default body text
- **lg**: 1.125rem (18px) - Large body text
- **xl**: 1.25rem (20px) - Small headings
- **2xl**: 1.5rem (24px) - Section headings
- **3xl**: 1.875rem (30px) - Page headings
- **4xl**: 2.25rem (36px) - Hero headings

### Font Weights
- **Regular**: 400 - Body text
- **Medium**: 500 - Emphasis, labels
- **Semibold**: 600 - Headings
- **Bold**: 700 - Strong emphasis

### Line Heights
- **Tight**: 1.25 - Headings
- **Normal**: 1.5 - Body text
- **Relaxed**: 1.75 - Long-form content

## Spacing System

Using Tailwind's spacing scale (4px base unit):

- **xs**: 0.25rem (4px)
- **sm**: 0.5rem (8px)
- **md**: 1rem (16px)
- **lg**: 1.5rem (24px)
- **xl**: 2rem (32px)
- **2xl**: 3rem (48px)
- **3xl**: 4rem (64px)

### Common Patterns
- **Card Padding**: `p-6` (24px)
- **Section Spacing**: `space-y-6` (24px vertical)
- **Form Field Gap**: `space-y-4` (16px vertical)
- **Button Padding**: `px-4 py-2` (16px horizontal, 8px vertical)
- **Container Max Width**: `max-w-7xl` (1280px)

## Border Radius

- **sm**: 0.125rem (2px) - Small elements
- **md**: 0.375rem (6px) - Default (buttons, inputs)
- **lg**: 0.5rem (8px) - Cards
- **xl**: 0.75rem (12px) - Large cards
- **full**: 9999px - Pills, avatars

## Shadows

- **sm**: `shadow-sm` - Subtle elevation
- **md**: `shadow-md` - Default cards
- **lg**: `shadow-lg` - Elevated cards, modals
- **xl**: `shadow-xl` - High elevation

## Layouts

### Split Auth Layout
- **Left Side (50%)**: Branding area with logo, tagline, visual elements
- **Right Side (50%)**: Authentication form
- **Responsive**: Stacks vertically on mobile (`flex-col` on small screens)

### Sidebar App Layout
- **Sidebar Width**: 256px (16rem) - Collapsible to 64px (4rem)
- **Top Bar Height**: 64px (4rem)
- **Main Content**: Flexible width with max container
- **Responsive**: Sidebar becomes drawer on mobile

## Component Library (shadcn-vue)

### Forms
- `Input` - Text inputs
- `Button` - Primary, secondary, ghost variants
- `Select` - Dropdown selects
- `Checkbox` - Checkboxes
- `Label` - Form labels
- `Textarea` - Multi-line text

### Layout
- `Card` - Content containers
- `Sheet` - Sidebar/drawer
- `Separator` - Dividers
- `Container` - Page containers

### Data Display
- `Table` - Data tables
- `Badge` - Status indicators
- `Avatar` - User avatars
- `Skeleton` - Loading states

### Feedback
- `Alert` - Alert messages
- `Toast` - Toast notifications
- `Progress` - Progress bars
- `Dialog` - Modal dialogs

### Navigation
- `DropdownMenu` - Dropdown menus
- `Tabs` - Tab navigation
- `Breadcrumb` - Breadcrumb navigation

## Responsive Breakpoints

- **sm**: 640px - Small tablets
- **md**: 768px - Tablets
- **lg**: 1024px - Desktops
- **xl**: 1280px - Large desktops
- **2xl**: 1536px - Extra large desktops

## Dark Mode

All designs support light and dark modes using Tailwind's `dark:` prefix:
- Background colors switch automatically
- Text colors adjust for contrast
- Borders adapt to theme
- Status colors remain consistent

## Accessibility

- **Color Contrast**: All text meets WCAG AA standards (4.5:1 minimum)
- **Keyboard Navigation**: All interactive elements keyboard accessible
- **Screen Readers**: Proper ARIA labels and semantic HTML
- **Focus Indicators**: Clear focus states (`ring-2 ring-blue-500`)
- **Alt Text**: Images and icons have descriptive alt text

## Implementation Notes

1. All designs use Tailwind CSS V4 utility classes
2. Components are from shadcn-vue library
3. Layouts map to Laravel Starter Kit Vue structure
4. TypeScript types should match component props
5. Inertia.js handles page navigation
6. Forms use Laravel validation patterns

## File Structure

```
design/ui/
├── README.md (this file)
├── auth/ - Authentication pages
├── app/ - Application pages
└── components/ - Reusable component patterns
```

