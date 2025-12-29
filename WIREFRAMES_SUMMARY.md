# Wireframes Summary

## Overview

This document provides wireframes for all major pages and user flows in the Cloud Security application. The wireframes are designed using PrimeVue (Material Design) components and follow modern UX best practices.

## Files

1. **WIREFRAMES.md** - ASCII art wireframes with detailed layouts
2. **wireframes.html** - Interactive HTML wireframes (open in browser to view)
3. **WIREFRAMES_SUMMARY.md** - This document (design decisions and notes)

## Design Principles

### 1. Consistency
- **Sidebar Navigation**: Persistent across all main app pages
- **Top Bar**: Logo, notifications, and user menu on every page
- **Card-based Layout**: Consistent card styling for lists and content
- **Color Scheme**: Material Design colors (primary: #1976d2)

### 2. User Flow
- **Auth Pages**: Centered, focused design with clear CTAs
- **Progressive Disclosure**: Multi-step processes broken into digestible steps
- **Clear Hierarchy**: Important information prominently displayed
- **Action-Oriented**: Primary actions are always visible and accessible

### 3. Information Architecture
- **Dashboard**: Overview with key metrics and recent activity
- **Scans**: List view with filters and status indicators
- **Reports**: Detailed view with executive summary and findings
- **Insights**: Analytics dashboard with charts and trends

## Page-by-Page Breakdown

### Authentication Flow

#### Login Page
- **Purpose**: User authentication entry point
- **Key Elements**:
  - Email/password form
  - OAuth options (Google, Microsoft, Apple)
  - Links to registration and password reset
  - Remember me checkbox
- **Design Notes**: Centered card layout, minimal distractions

#### Registration Page
- **Purpose**: New user account creation
- **Key Elements**:
  - Full name, email, password fields
  - Password confirmation
  - Terms and conditions checkbox
  - OAuth options
- **Design Notes**: Similar to login for consistency, includes organization setup flow

### Main Application

#### Dashboard
- **Purpose**: Overview and quick actions
- **Key Elements**:
  - Stats cards (Total Accounts, Active Scans, Critical Findings)
  - Recent scans list
  - Quick action buttons
- **Design Notes**: Information-dense but organized, provides at-a-glance status

#### AWS Accounts Management
- **Purpose**: Manage connected AWS accounts
- **Key Elements**:
  - List of AWS accounts with status
  - Add account button
  - Account actions (View, Scan, Remove)
- **Design Notes**: Clear account information, easy to scan and manage

#### Add AWS Account Flow
- **Step 1**: CloudFormation setup instructions
  - Opens AWS Console in new window
  - Clear step-by-step instructions
- **Step 2**: Account details entry
  - AWS Account ID
  - IAM Role ARN
  - Optional account name
- **Design Notes**: Two-step process reduces complexity, clear progress indicator

#### Scans
- **Purpose**: View and manage security scans
- **Key Elements**:
  - Scan list with status and findings
  - Filters (Account, Status)
  - New scan button
  - Progress indicators for running scans
- **Design Notes**: Status clearly visible, easy to identify active vs completed scans

#### Reports
- **Purpose**: Detailed scan results and findings
- **Key Elements**:
  - Executive summary with key metrics
  - Findings list with severity indicators
  - Export options (PDF, CSV)
  - Remediation actions
- **Design Notes**: Color-coded severity, actionable findings, export capabilities

#### Insights & Analytics
- **Purpose**: Trend analysis and compliance tracking
- **Key Elements**:
  - Time range selector
  - Key metrics cards
  - Charts (trends, service breakdown)
  - Top risk areas
  - Compliance scores
- **Design Notes**: Data visualization focused, helps identify patterns

## Component Patterns

### Cards
- Used for: Lists, content sections, stats
- Style: White background, border, padding, rounded corners
- Actions: Buttons in card footer

### Forms
- Style: Clean inputs with labels
- Validation: Inline error messages (not shown in wireframes)
- Buttons: Primary action prominent, secondary actions less prominent

### Status Indicators
- **Colors**:
  - Critical: Red (#f44336)
  - High: Orange (#ff9800)
  - Medium: Yellow (#ffc107)
  - Low: Blue (#2196f3)
- **Progress Bars**: Visual progress for running scans

### Navigation
- **Sidebar**: Persistent, collapsible on mobile
- **Breadcrumbs**: For deep navigation (not shown but recommended)
- **Top Bar**: Global actions and user menu

## Responsive Considerations

### Desktop (>1024px)
- Full sidebar navigation
- Multi-column layouts
- Hover states for interactive elements

### Tablet (768px - 1024px)
- Collapsible sidebar
- Two-column layouts
- Touch-friendly targets (44px minimum)

### Mobile (<768px)
- Hamburger menu for sidebar
- Single column layouts
- Bottom navigation bar (optional)
- Full-width cards
- Stacked form elements

## Accessibility Considerations

- **Color Contrast**: All text meets WCAG AA standards
- **Keyboard Navigation**: All interactive elements keyboard accessible
- **Screen Readers**: Proper ARIA labels and semantic HTML
- **Focus Indicators**: Clear focus states for keyboard users
- **Alt Text**: Images and icons have descriptive alt text

## Next Steps

1. **Review Wireframes**: Get stakeholder feedback
2. **Create High-Fidelity Mockups**: Using PrimeVue components
3. **Prototype**: Build interactive prototype in Nuxt
4. **User Testing**: Test flows with target users
5. **Iterate**: Refine based on feedback

## PrimeVue Components to Use

Based on the wireframes, here are the PrimeVue components needed:

- **Forms**: InputText, InputPassword, Checkbox, Select, Button
- **Layout**: Card, Panel, Divider
- **Data Display**: DataTable, DataView, Badge
- **Feedback**: ProgressBar, Toast, Message
- **Navigation**: Menu, Sidebar, Breadcrumb
- **Charts**: Chart component (for analytics)
- **Dialogs**: Dialog, ConfirmDialog

## Color Palette

- **Primary**: #1976d2 (Blue)
- **Secondary**: #424242 (Dark Gray)
- **Success**: #4caf50 (Green)
- **Warning**: #ff9800 (Orange)
- **Error**: #f44336 (Red)
- **Info**: #2196f3 (Light Blue)
- **Background**: #f5f5f5 (Light Gray)
- **Surface**: #ffffff (White)

## Typography

- **Headings**: Roboto, Bold
- **Body**: Roboto, Regular
- **Labels**: Roboto, Medium
- **Code**: Roboto Mono, Regular

---

**Note**: These wireframes are a starting point. They should be refined based on user feedback and technical constraints during implementation.

