<script setup lang="ts">
import { computed } from 'vue';
import SplitAuthLayout from '@/Layouts/SplitAuthLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <SplitAuthLayout>
        <Head title="Verify Email" />

        <template #title>Verify your email</template>
        <template #subtitle>We've sent a verification link to your email address</template>
        <template #mobile-title>Verify your email</template>

        <!-- Email Icon -->
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 dark:bg-blue-900/30 mb-6">
            <svg class="h-8 w-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </div>

        <!-- Instructions -->
        <div class="space-y-4 text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                We've sent a verification link to:
            </p>
            <p class="text-base font-medium text-gray-900 dark:text-white">
                {{ $page.props.auth?.user?.email || 'your email address' }}
            </p>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Please click the link in the email to verify your account. The link will expire in 24 hours.
            </p>
        </div>

        <div
            class="mt-6 text-sm font-medium text-green-600 dark:text-green-400"
            v-if="verificationLinkSent"
        >
            A new verification link has been sent to the email address you provided during registration.
        </div>

        <!-- Resend Button -->
        <div class="mt-8 space-y-4">
            <form @submit.prevent="submit">
                <PrimaryButton
                    class="w-full flex justify-center"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Resend verification email
                </PrimaryButton>
            </form>
            <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
                Didn't receive the email? Check your spam folder or try resending.
            </p>
        </div>

        <!-- Logout Link -->
        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700 text-center">
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
            >
                Sign out
            </Link>
        </div>
    </SplitAuthLayout>
</template>
