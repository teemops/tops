# Notifications System

The in-app notification system displays messages at the top right of the screen when users are logged in.

## Features

- ✅ **Four notification types**: Success (green), Error (red), Warning (yellow), Info (blue)
- ✅ **Auto-dismiss**: Notifications automatically disappear after 5-7 seconds
- ✅ **Manual dismiss**: Users can close notifications with the X button
- ✅ **Animations**: Smooth slide-in and slide-out animations
- ✅ **Dark mode**: Full dark mode support
- ✅ **Stacking**: Multiple notifications stack vertically
- ✅ **Laravel integration**: Automatically displays Laravel flash messages

## Usage

### From Laravel Controllers

Use Laravel's `with()` method to set flash messages:

```php
// Success message
return redirect()->route('dashboard')
    ->with('success', 'Your email has been verified successfully!');

// Error message
return redirect()->back()
    ->with('error', 'Something went wrong. Please try again.');

// Warning message
return redirect()->back()
    ->with('warning', 'Please review your settings.');

// Info message
return redirect()->back()
    ->with('info', 'Verification email sent! Please check your inbox.');
```

### From Vue Components

Use the `useNotifications` composable:

```vue
<script setup lang="ts">
import { useNotifications } from '@/composables/useNotifications';

const { showSuccess, showError, showWarning, showInfo } = useNotifications();

// Show a notification
showSuccess('Operation completed successfully!');
showError('An error occurred');
showWarning('Please be careful');
showInfo('Here is some information');
</script>
```

## Notification Types

### Success (Green)
- Use for: Successful operations, confirmations
- Auto-dismiss: 5 seconds
- Example: "Your email has been verified successfully!"

### Error (Red)
- Use for: Errors, failures
- Auto-dismiss: 7 seconds (longer for errors)
- Example: "Failed to save changes. Please try again."

### Warning (Yellow)
- Use for: Warnings, cautions
- Auto-dismiss: 6 seconds
- Example: "Your session will expire soon."

### Info (Blue)
- Use for: Informational messages
- Auto-dismiss: 5 seconds
- Example: "Verification email sent! Please check your inbox."

## Custom Duration

You can specify a custom duration (in milliseconds):

```typescript
showSuccess('Message', 10000); // 10 seconds
showError('Message', 0); // No auto-dismiss
```

## Testing

Visit `/test-notifications` (when logged in) to test all notification types.

## Implementation Details

### Components
- `Notification.vue` - Individual notification component
- `NotificationContainer.vue` - Container that manages all notifications

### Composables
- `useNotifications.ts` - Composable for showing/managing notifications

### Integration
- Added to `SidebarAppLayout.vue` (app pages)
- Added to `SplitAuthLayout.vue` (auth pages)
- Flash messages shared via `HandleInertiaRequests` middleware

## Current Usage

The notification system is already integrated into:

1. **Email Verification**: Success message when email is verified
2. **Registration**: Info message after registration
3. **OAuth Signup**: Success message for new OAuth users
4. **Verification Email Sent**: Info message when resending verification email

## Future Enhancements

- Persistent notifications (don't auto-dismiss)
- Action buttons in notifications
- Notification history
- Sound alerts (optional)

