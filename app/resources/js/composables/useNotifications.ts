import { ref } from 'vue';

export type NotificationType = 'success' | 'error' | 'warning' | 'info';

export interface Notification {
    id: string;
    type: NotificationType;
    message: string;
    duration?: number; // Auto-dismiss duration in ms (0 = no auto-dismiss)
}

const notifications = ref<Notification[]>([]);

export function useNotifications() {
    const showNotification = (
        type: NotificationType,
        message: string,
        duration: number = 5000
    ): string => {
        const id = `notification-${Date.now()}-${Math.random()}`;
        
        const notification: Notification = {
            id,
            type,
            message,
            duration,
        };

        notifications.value.push(notification);

        // Auto-dismiss if duration is set
        if (duration > 0) {
            setTimeout(() => {
                dismiss(id);
            }, duration);
        }

        return id;
    };

    const showSuccess = (message: string, duration: number = 5000): string => {
        return showNotification('success', message, duration);
    };

    const showError = (message: string, duration: number = 7000): string => {
        return showNotification('error', message, duration);
    };

    const showWarning = (message: string, duration: number = 6000): string => {
        return showNotification('warning', message, duration);
    };

    const showInfo = (message: string, duration: number = 5000): string => {
        return showNotification('info', message, duration);
    };

    const dismiss = (id: string): void => {
        const index = notifications.value.findIndex(n => n.id === id);
        if (index > -1) {
            notifications.value.splice(index, 1);
        }
    };

    const dismissAll = (): void => {
        notifications.value = [];
    };

    return {
        notifications,
        showNotification,
        showSuccess,
        showError,
        showWarning,
        showInfo,
        dismiss,
        dismissAll,
    };
}
