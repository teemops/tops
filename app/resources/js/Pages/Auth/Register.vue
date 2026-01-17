<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import SplitAuthLayout from '@/Layouts/SplitAuthLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { signInWithOAuth, createUserWithEmailPassword, getIdToken } from '@/composables/useFirebase';
import { ref } from 'vue';

defineProps<{
    status?: string;
}>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const oauthLoading = ref<string | null>(null);
const oauthError = ref<string | null>(null);

const submit = async () => {
    form.clearErrors();
    
    // Validate password confirmation on frontend
    if (form.password !== form.password_confirmation) {
        form.setError('password_confirmation', 'The passwords do not match.');
        return;
    }
    
    // Validate password length
    if (form.password.length < 8) {
        form.setError('password', 'Password must be at least 8 characters.');
        return;
    }
    
    // Validate terms acceptance
    if (!form.terms) {
        form.setError('terms', 'You must agree to the Terms of Service and Privacy Policy.');
        return;
    }
    
    try {
        // Create user with Firebase using email/password
        const user = await createUserWithEmailPassword(form.email, form.password, form.name);
        
        // Get the ID token
        const token = await getIdToken();
        
        if (!token) {
            throw new Error('Failed to get authentication token');
        }

        // Send token and name to backend to create Laravel user record
        router.post(route('firebase.register'), { 
            token,
            name: form.name,
        }, {
            onSuccess: () => {
                // Redirect handled by backend
            },
            onError: (errors) => {
                form.setError('email', errors.firebase || errors.message || 'Registration failed');
                form.reset('password', 'password_confirmation');
            },
        });
    } catch (error: any) {
        // Handle Firebase errors
        if (error.message.includes('email-already-in-use')) {
            form.setError('email', 'An account with this email address already exists.');
        } else if (error.message.includes('weak-password')) {
            form.setError('password', 'Password is too weak. Please choose a stronger password.');
        } else if (error.message.includes('invalid-email')) {
            form.setError('email', 'Invalid email address.');
        } else {
            form.setError('email', error.message || 'Registration failed. Please try again.');
        }
        form.reset('password', 'password_confirmation');
    }
};

const handleOAuth = async (provider: 'google' | 'github' | 'microsoft') => {
    console.log('OAuth button clicked:', provider);
    oauthLoading.value = provider;
    oauthError.value = null;

    try {
        console.log('Starting Firebase OAuth...');
        // Sign in with Firebase OAuth
        const user = await signInWithOAuth(provider);
        console.log('Firebase OAuth successful, user:', user.email);
        
        // Get the ID token
        console.log('Getting ID token...');
        const token = await getIdToken();
        
        if (!token) {
            throw new Error('Failed to get authentication token');
        }
        console.log('ID token obtained, sending to backend...');

        // Send token to backend to create Laravel session
        router.post(route('firebase.verify'), { token }, {
            onSuccess: () => {
                console.log('Backend verification successful');
                // Redirect handled by backend
            },
            onError: (errors) => {
                console.error('Backend verification failed:', errors);
                oauthError.value = errors.firebase || errors.message || 'Authentication failed';
                oauthLoading.value = null;
            },
        });
    } catch (error: any) {
        console.error('OAuth error:', error);
        oauthError.value = error.message || 'Authentication failed. Please try again.';
        oauthLoading.value = null;
    }
};
</script>

<template>
    <SplitAuthLayout>
        <Head title="Register" />

        <template #title>Create your account</template>
        <template #subtitle>Get started with Teemops in minutes</template>
        <template #mobile-title>Create your account</template>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600 dark:text-green-400">
            {{ status }}
        </div>

        <div v-if="oauthError" class="mb-4 text-sm font-medium text-red-600 dark:text-red-400">
            {{ oauthError }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <InputLabel for="name" value="Full name" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-2 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="John Doe"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email address" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-2 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                    placeholder="you@example.com"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Password" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-2 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                    placeholder="••••••••"
                />

                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Must be at least 8 characters</p>
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Confirm password" />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-2 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="••••••••"
                />

                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <div class="flex items-start">
                <Checkbox name="terms" v-model:checked="form.terms" required />
                <label for="terms" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                    I agree to the
                    <a href="/terms" class="text-blue-600 hover:text-blue-500 dark:text-blue-400">Terms of Service</a>
                    and
                    <a href="/privacy" class="text-blue-600 hover:text-blue-500 dark:text-blue-400">Privacy Policy</a>
                </label>
            </div>

            <div>
                <PrimaryButton
                    id="register-submit"
                    class="w-full flex justify-center"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Create account
                </PrimaryButton>
            </div>
        </form>

        <!-- Divider -->
        <div class="mt-6">
            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300 dark:border-gray-600"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400">Or continue with</span>
                </div>
            </div>
        </div>

        <!-- OAuth Buttons -->
        <div class="mt-6 grid grid-cols-3 gap-3">
            <button
                type="button"
                @click="handleOAuth('google')"
                :disabled="oauthLoading !== null"
                class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                title="Sign up with Google"
            >
                <svg v-if="oauthLoading !== 'google'" class="h-5 w-5" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                <span v-else class="h-5 w-5 border-2 border-gray-300 border-t-gray-600 rounded-full animate-spin"></span>
            </button>
            <button
                type="button"
                @click="handleOAuth('github')"
                :disabled="oauthLoading !== null"
                class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                title="Sign up with GitHub"
            >
                <svg v-if="oauthLoading !== 'github'" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                </svg>
                <span v-else class="h-5 w-5 border-2 border-gray-300 border-t-gray-600 rounded-full animate-spin"></span>
            </button>
            <button
                type="button"
                @click="handleOAuth('microsoft')"
                :disabled="oauthLoading !== null"
                class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                title="Sign up with Microsoft"
            >
                <svg v-if="oauthLoading !== 'microsoft'" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M11.4 24H0V12.6h11.4V24zM24 24h-11.4V12.6H24V24zM11.4 11.4H0V0h11.4v11.4zM24 11.4h-11.4V0H24v11.4z"/>
                </svg>
                <span v-else class="h-5 w-5 border-2 border-gray-300 border-t-gray-600 rounded-full animate-spin"></span>
            </button>
        </div>

        <!-- Sign In Link -->
        <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
            Already have an account?
            <Link
                :href="route('login')"
                class="font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400"
            >
                Sign in
            </Link>
        </p>
    </SplitAuthLayout>
</template>
