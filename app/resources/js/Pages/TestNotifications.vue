<script setup lang="ts">
import SidebarAppLayout from '@/Layouts/SidebarAppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head } from '@inertiajs/vue3';
import { useNotifications } from '@/composables/useNotifications';

const { showSuccess, showError, showWarning, showInfo } = useNotifications();

const testSuccess = () => {
    showSuccess('This is a success notification!');
};

const testError = () => {
    showError('This is an error notification!');
};

const testWarning = () => {
    showWarning('This is a warning notification!');
};

const testInfo = () => {
    showInfo('This is an info notification!');
};
</script>

<template>
    <SidebarAppLayout>
        <Head title="Test Notifications" />

        <div class="max-w-2xl mx-auto">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
                Notification System Test
            </h1>

            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    Click the buttons below to test different notification types. Notifications will appear at the top right of the screen.
                </p>

                <div class="grid grid-cols-2 gap-4">
                    <PrimaryButton @click="testSuccess" class="bg-green-600 hover:bg-green-700">
                        Test Success
                    </PrimaryButton>
                    <PrimaryButton @click="testError" class="bg-red-600 hover:bg-red-700">
                        Test Error
                    </PrimaryButton>
                    <PrimaryButton @click="testWarning" class="bg-yellow-600 hover:bg-yellow-700">
                        Test Warning
                    </PrimaryButton>
                    <PrimaryButton @click="testInfo" class="bg-blue-600 hover:bg-blue-700">
                        Test Info
                    </PrimaryButton>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                        Notification Features
                    </h2>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <li>✅ Auto-dismiss after 5-7 seconds (configurable)</li>
                        <li>✅ Manual dismiss with close button</li>
                        <li>✅ Slide-in animation</li>
                        <li>✅ Color-coded by type (success, error, warning, info)</li>
                        <li>✅ Dark mode support</li>
                        <li>✅ Multiple notifications stack vertically</li>
                        <li>✅ Integrated with Laravel flash messages</li>
                    </ul>
                </div>
            </div>
        </div>
    </SidebarAppLayout>
</template>

