<script setup lang="ts">
import SplitAuthLayout from '@/Layouts/SplitAuthLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <SplitAuthLayout>
        <Head title="Reset Password" />

        <template #title>Reset password</template>
        <template #subtitle>Enter your email and we'll send you a reset link</template>
        <template #mobile-title>Reset password</template>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600 dark:text-green-400">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div>
                <InputLabel for="email" value="Email address" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-2 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="you@example.com"
                />

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    We'll send a password reset link to this email address.
                </p>

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <PrimaryButton
                    class="w-full flex justify-center"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Send reset link
                </PrimaryButton>
            </div>
        </form>

        <!-- Back to Login -->
        <div class="mt-6 text-center">
            <Link
                :href="route('login')"
                class="text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400"
            >
                ← Back to sign in
            </Link>
        </div>
    </SplitAuthLayout>
</template>
