<script setup lang="ts">
import { onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Notification from './Notification.vue';
import { useNotifications, type Notification as NotificationType } from '@/composables/useNotifications';

const { notifications, showSuccess, showError, showWarning, showInfo, dismiss } = useNotifications();
const page = usePage();

// Watch for flash messages from Laravel on page updates
watch(
    () => page.props.flash,
    (flash: any) => {
        if (!flash) return;

        // Show notifications from flash messages
        if (flash.success) {
            showSuccess(flash.success);
        }
        if (flash.error) {
            showError(flash.error);
        }
        if (flash.warning) {
            showWarning(flash.warning);
        }
        if (flash.info) {
            showInfo(flash.info);
        }
    },
    { deep: true, immediate: true }
);
</script>

<template>
    <div
        v-if="notifications.length > 0"
        class="fixed top-4 right-4 z-50 w-full max-w-sm space-y-2"
        role="region"
        aria-live="polite"
        aria-label="Notifications"
    >
        <TransitionGroup
            name="notification"
            tag="div"
        >
            <Notification
                v-for="notification in notifications"
                :key="notification.id"
                :notification="notification"
                @dismiss="dismiss"
            />
        </TransitionGroup>
    </div>
</template>

<style scoped>
.notification-enter-active {
    transition: all 0.3s ease-out;
}

.notification-leave-active {
    transition: all 0.3s ease-in;
}

.notification-enter-from {
    transform: translateX(100%);
    opacity: 0;
}

.notification-leave-to {
    transform: translateX(100%);
    opacity: 0;
}

.notification-move {
    transition: transform 0.3s ease;
}
</style>

