<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import SplitAuthLayout from '@/Layouts/SplitAuthLayout.vue';
import { useOrganizationMembers } from '@/composables/useOrganizationMembers';
import { useNotifications } from '@/composables/useNotifications';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    token: string;
}>();

const { acceptInvitation, loading, error } = useOrganizationMembers();
const { showSuccess, showError } = useNotifications();
const page = usePage();

const invitation = ref<any>(null);
const loadingInvitation = ref(true);
const accepting = ref(false);

const isAuthenticated = computed(() => (page.props as any).auth?.user !== undefined);
const userEmail = computed(() => (page.props as any).auth?.user?.email || '');

onMounted(async () => {
    // Fetch invitation details
    try {
        // We'll need to create an endpoint to get invitation details
        // For now, we'll try to accept and handle errors
        loadingInvitation.value = false;
    } catch (err) {
        loadingInvitation.value = false;
    }
});

const handleAccept = async () => {
    if (!isAuthenticated.value) {
        // Redirect to login with return URL
        router.visit(route('login', { return: route('organizations.invitations.accept', { token: props.token }) }));
        return;
    }

    accepting.value = true;

    try {
        const result = await acceptInvitation(props.token);
        showSuccess('Invitation accepted! You have been added to the organization.');
        
        // Redirect to dashboard or organization
        setTimeout(() => {
            router.visit(route('dashboard'));
        }, 1500);
    } catch (err: any) {
        // Error handled by composable
        if (err.response?.status === 403 && err.response?.data?.error?.includes('email')) {
            showError('This invitation was sent to a different email address. Please log in with the correct account.');
        }
    } finally {
        accepting.value = false;
    }
};
</script>

<template>
    <SplitAuthLayout>
        <Head title="Accept Invitation" />

        <div class="flex flex-col justify-center py-12 sm:px-6 lg:px-8">
            <div class="sm:mx-auto sm:w-full sm:max-w-md">
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900 dark:text-white">
                    Accept Invitation
                </h2>
            </div>

            <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
                <div class="bg-white dark:bg-gray-800 py-8 px-4 shadow sm:rounded-lg sm:px-10 border border-gray-200 dark:border-gray-700">
                    <div v-if="loadingInvitation" class="text-center">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                        <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">Loading invitation...</p>
                    </div>

                    <div v-else-if="error" class="text-center">
                        <div class="rounded-md bg-red-50 dark:bg-red-900/20 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                        {{ error }}
                                    </h3>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <Link
                                :href="route('dashboard')"
                                class="text-sm text-blue-600 hover:text-blue-500 dark:text-blue-400"
                            >
                                Go to Dashboard
                            </Link>
                        </div>
                    </div>

                    <div v-else class="space-y-6">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                You have been invited to join an organization on Teemops.
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                After accepting, you will be added to the organization. A role will be assigned to you by the organization administrator.
                            </p>
                        </div>

                        <div v-if="!isAuthenticated" class="rounded-md bg-yellow-50 dark:bg-yellow-900/20 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                        Sign in required
                                    </h3>
                                    <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                        <p>You need to sign in or create an account to accept this invitation.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <PrimaryButton
                                @click="handleAccept"
                                :disabled="accepting"
                                class="w-full"
                            >
                                <span v-if="!isAuthenticated">Sign In to Accept</span>
                                <span v-else-if="accepting">Accepting...</span>
                                <span v-else>Accept Invitation</span>
                            </PrimaryButton>
                        </div>

                        <div class="text-center">
                            <Link
                                :href="route('dashboard')"
                                class="text-sm text-gray-600 hover:text-gray-500 dark:text-gray-400 dark:hover:text-gray-300"
                            >
                                Go to Dashboard
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SplitAuthLayout>
</template>
