<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { resetPasswordWithCode } from '@/composables/useFirebase';
import { ref, onMounted } from 'vue';

const props = defineProps<{
    email?: string;
    token?: string;
}>();

const form = useForm({
    token: props.token || '',
    email: props.email || '',
    password: '',
    password_confirmation: '',
});

const errorMessage = ref<string | null>(null);
const isFirebaseReset = ref(false);
const actionCode = ref<string | null>(null);

// Check if this is a Firebase password reset (has oobCode in URL)
onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const oobCode = urlParams.get('oobCode');
    const mode = urlParams.get('mode');
    
    if (oobCode && mode === 'resetPassword') {
        isFirebaseReset.value = true;
        actionCode.value = oobCode;
        // Extract email from URL if available
        // const emailParam = urlParams.get('email');
        // if (emailParam) {
        //     form.email = emailParam;
        // }
    }
});

const submit = async () => {
    form.clearErrors();
    errorMessage.value = null;
    
    // Validate password confirmation
    if (form.password !== form.password_confirmation) {
        form.setError('password_confirmation', 'The passwords do not match.');
        return;
    }
    
    // Validate password length
    if (form.password.length < 8) {
        form.setError('password', 'Password must be at least 8 characters.');
        return;
    }
    
    // Use Firebase reset if action code is present
    if (isFirebaseReset.value && actionCode.value) {
        try {
            await resetPasswordWithCode(actionCode.value, form.password);
            // Redirect to login with success message
            router.visit(route('login'), {
                data: { status: 'Your password has been reset successfully. Please sign in with your new password.' },
            });
        } catch (error: any) {
            errorMessage.value = error.message || 'Failed to reset password. Please try again.';
            form.setError('password', error.message || 'Failed to reset password.');
        }
    } else {
        // Fallback to Laravel password reset (for backward compatibility)
        form.post(route('password.store'), {
            onFinish: () => {
                form.reset('password', 'password_confirmation');
            },
        });
    }
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />

        <div v-if="errorMessage" class="mb-4 text-sm font-medium text-red-600 dark:text-red-400">
            {{ errorMessage }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    value="Confirm Password"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Reset Password
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
