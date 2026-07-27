<script setup lang="ts">
/**
 * Login page: email/password or OAuth, then verification step.
 * Flow: Login → backend returns mfa_required | email_otp_required → user completes OTP → session created.
 * - MFA enabled: show authenticator code first, with "Email me a code instead" fallback.
 * - MFA not enabled: show email OTP only ("Send code to my email").
 */
import Checkbox from '@/Components/Checkbox.vue';
import SplitAuthLayout from '@/Layouts/SplitAuthLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { signInWithOAuth, signInWithEmailPassword } from '@/composables/useFirebase';
import { useFirebaseAuthEnabled } from '@/composables/useFeatures';
import { computed, ref, onMounted } from 'vue';
import axios from 'axios';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const page = usePage();
const firebaseAuthEnabled = useFirebaseAuthEnabled();
const authError = computed(() => (page.props as { errors?: Record<string, string> }).errors?.firebase);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const oauthLoading = ref<string | null>(null);
const oauthError = ref<string | null>(null);

// Verification step (after password/OAuth success)
const verificationToken = ref<string | null>(null);
/** 'mfa' = TOTP with email fallback; 'email' = email OTP only */
const verificationMode = ref<'mfa' | 'email'>('mfa');
/** When true, user chose email OTP (either from MFA fallback or email-only mode) */
const useEmailOtp = ref(false);
const otpCode = ref('');
const otpError = ref<string | null>(null);
const otpProcessing = ref(false);
const emailOtpSent = ref(false);
const maskedEmail = ref('');
const emailOtpResendCooldown = ref(0);

const isVerificationStep = computed(() => !!verificationToken.value);
const showAuthenticatorFirst = computed(
    () => isVerificationStep.value && verificationMode.value === 'mfa' && !useEmailOtp.value
);
const showEmailOtpForm = computed(
    () => isVerificationStep.value && (useEmailOtp.value || verificationMode.value === 'email')
);
const canShowAuthenticatorLink = computed(
    () => verificationMode.value === 'mfa' && useEmailOtp.value
);

function setVerificationRequired(token: string, mfaRequired: boolean) {
    verificationToken.value = token;
    verificationMode.value = mfaRequired ? 'mfa' : 'email';
    useEmailOtp.value = !mfaRequired;
    otpCode.value = '';
    otpError.value = null;
    emailOtpSent.value = false;
}

function clearVerification() {
    verificationToken.value = null;
    verificationMode.value = 'mfa';
    useEmailOtp.value = false;
    otpCode.value = '';
    otpError.value = null;
    emailOtpSent.value = false;
}

function setFormError(message: string) {
    form.setError('email', message);
    form.reset('password');
}

/** Call backend verify with Firebase token; returns true if verification step is required. */
async function verifyToken(token: string): Promise<{ needVerification: boolean; mfaRequired?: boolean }> {
    const res = await axios.post(route('firebase.verify'), { token }, {
        maxRedirects: 0,
        validateStatus: () => true,
    });
    if (res.status === 200 && res.data?.mfa_required) {
        return { needVerification: true, mfaRequired: true };
    }
    if (res.status === 200 && res.data?.email_otp_required) {
        return { needVerification: true, mfaRequired: false };
    }
    if (res.status === 302 && res.headers?.location) {
        window.location.href = res.headers.location;
        return { needVerification: false };
    }
    const errData = (res?.data || {}) as Record<string, unknown>;
    const msg = (errData?.errors as Record<string, string>)?.firebase
        ?? (errData?.message as string)
        ?? 'Authentication failed';
    throw new Error(msg);
}

async function submitPassword() {
    form.clearErrors();
    oauthError.value = null;

    if (!firebaseAuthEnabled.value) {
        form.post(route('login'));
        return;
    }

    try {
        const user = await signInWithEmailPassword(form.email, form.password);
        const token = await user.getIdToken(true);
        if (!token) throw new Error('Failed to get authentication token');

        const { needVerification, mfaRequired } = await verifyToken(token);
        if (needVerification) setVerificationRequired(token, mfaRequired ?? false);
    } catch (err: unknown) {
        setFormError(err instanceof Error ? err.message : 'Authentication failed. Please try again.');
    }
}

function switchToEmailOtp() {
    useEmailOtp.value = true;
    otpCode.value = '';
    otpError.value = null;
    if (!emailOtpSent.value) requestEmailOtp();
}

function switchToAuthenticator() {
    useEmailOtp.value = false;
    otpCode.value = '';
    otpError.value = null;
    emailOtpSent.value = false;
}

async function requestEmailOtp() {
    if (!verificationToken.value) return;
    otpProcessing.value = true;
    otpError.value = null;
    try {
        const res = await axios.post(route('firebase.request-email-otp'), {
            token: verificationToken.value,
        });
        maskedEmail.value = res.data.masked_email ?? '';
        emailOtpSent.value = true;
        emailOtpResendCooldown.value = 60;
        const interval = setInterval(() => {
            emailOtpResendCooldown.value--;
            if (emailOtpResendCooldown.value <= 0) clearInterval(interval);
        }, 1000);
    } catch {
        otpError.value = 'Failed to send code. Please try again.';
    } finally {
        otpProcessing.value = false;
    }
}

function submitOtp() {
    if (!verificationToken.value) return;
    const digits = otpCode.value.replace(/\D/g, '');
    if (digits.length !== 6) {
        otpError.value = 'Please enter a 6-digit code';
        return;
    }
    otpProcessing.value = true;
    otpError.value = null;
    router.post(route('firebase.verify-mfa'), {
        token: verificationToken.value,
        otp: digits,
        use_email_otp: useEmailOtp.value,
    }, {
        preserveState: false,
        onError: (errors) => {
            otpError.value = errors.otp ?? 'Invalid or expired code. Please try again.';
            otpProcessing.value = false;
        },
        onFinish: () => {
            otpProcessing.value = false;
        },
    });
}

async function handleOAuth(provider: 'google' | 'github' | 'microsoft') {
    oauthLoading.value = provider;
    oauthError.value = null;
    try {
        const user = await signInWithOAuth(provider);
        const token = await user.getIdToken(true);
        if (!token) throw new Error('Failed to get authentication token');

        const { needVerification, mfaRequired } = await verifyToken(token);
        if (needVerification) setVerificationRequired(token, mfaRequired ?? false);
        else oauthError.value = null;
    } catch (err: unknown) {
        oauthError.value = err instanceof Error ? err.message : 'Authentication failed. Please try again.';
    } finally {
        oauthLoading.value = null;
    }
}

const verificationSubtitle = computed(() => {
    if (!isVerificationStep.value) return '';
    if (showEmailOtpForm.value && emailOtpSent.value && maskedEmail.value) {
        return `We've sent a 6-digit code to ${maskedEmail.value}`;
    }
    if (showAuthenticatorFirst.value) {
        return 'Enter the 6-digit code from your authenticator app';
    }
    return "We'll send a 6-digit code to your email address.";
});

// E2E: show MFA/verification step when ?e2e_mfa=1 so Playwright can test the form without Firebase
onMounted(() => {
    if (typeof window !== 'undefined' && window.location.search.includes('e2e_mfa=1')) {
        setVerificationRequired('e2e-test-token', true);
    }
});
</script>

<template>
    <SplitAuthLayout>
        <Head :title="isVerificationStep ? 'Verify identity' : 'Log in'" />

        <template #title>{{ isVerificationStep ? 'Verify your identity' : 'Welcome back' }}</template>
        <template #subtitle>
            {{ isVerificationStep ? verificationSubtitle : 'Sign in to your account to continue' }}
        </template>
        <template #mobile-title>{{ isVerificationStep ? 'Verify your identity' : 'Welcome back' }}</template>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600 dark:text-green-400">
            {{ status }}
        </div>
        <div v-if="oauthError" class="mb-4 text-sm font-medium text-red-600 dark:text-red-400">
            {{ oauthError }}
        </div>
        <div v-if="authError" class="mb-4 text-sm font-medium text-red-600 dark:text-red-400">
            {{ authError }}
        </div>

        <!-- Verification step: authenticator or email OTP -->
        <template v-if="isVerificationStep">
            <!-- Authenticator (MFA only, before switching to email) -->
            <div v-if="showAuthenticatorFirst" class="space-y-6">
                <div class="flex justify-center">
                    <div class="w-14 h-14 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <svg class="h-7 w-7 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center">
                    Open your authenticator app (Google Authenticator, Authy, etc.) and enter the code shown for Teemops.
                </p>
                <form @submit.prevent="submitOtp" class="space-y-6">
                    <div>
                        <InputLabel for="otp" value="Verification code" />
                        <TextInput
                            id="otp"
                            v-model="otpCode"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            placeholder="000000"
                            class="mt-2 block w-full text-center text-xl font-mono tracking-widest"
                        />
                        <InputError class="mt-2" :message="otpError ?? undefined" />
                    </div>
                    <PrimaryButton
                        type="submit"
                        class="w-full flex justify-center"
                        :class="{ 'opacity-25': otpProcessing }"
                        :disabled="otpProcessing || otpCode.replace(/\D/g, '').length !== 6"
                    >
                        {{ otpProcessing ? 'Verifying...' : 'Verify & sign in' }}
                    </PrimaryButton>
                </form>
                <div class="pt-6 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center">Don't have your authenticator?</p>
                    <button
                        type="button"
                        @click="switchToEmailOtp"
                        class="w-full mt-2 text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400"
                    >
                        Email me a code instead
                    </button>
                </div>
            </div>

            <!-- Email OTP: request code or enter code -->
            <div v-else class="space-y-6">
                <template v-if="emailOtpSent">
                    <form @submit.prevent="submitOtp" class="space-y-6">
                        <div>
                            <InputLabel for="email-otp" value="Verification code" />
                            <TextInput
                                id="email-otp"
                                v-model="otpCode"
                                type="text"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                maxlength="6"
                                placeholder="000000"
                                class="mt-2 block w-full text-center text-xl font-mono tracking-widest"
                            />
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Code expires in 10 minutes</p>
                            <InputError class="mt-2" :message="otpError ?? undefined" />
                        </div>
                        <PrimaryButton
                            type="submit"
                            class="w-full flex justify-center"
                            :class="{ 'opacity-25': otpProcessing }"
                            :disabled="otpProcessing || otpCode.replace(/\D/g, '').length !== 6"
                        >
                            {{ otpProcessing ? 'Verifying...' : 'Verify & sign in' }}
                        </PrimaryButton>
                    </form>
                </template>
                <div v-else class="space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        We'll send a 6-digit code to your email address.
                    </p>
                    <PrimaryButton
                        @click="requestEmailOtp"
                        class="w-full flex justify-center"
                        :disabled="otpProcessing"
                    >
                        {{ otpProcessing ? 'Sending...' : 'Send code to my email' }}
                    </PrimaryButton>
                </div>
                <div class="pt-6 border-t border-gray-200 dark:border-gray-700 space-y-2">
                    <button
                        v-if="emailOtpSent && emailOtpResendCooldown > 0"
                        type="button"
                        disabled
                        class="w-full text-sm text-gray-500 dark:text-gray-400"
                    >
                        Resend code in {{ emailOtpResendCooldown }}s
                    </button>
                    <button
                        v-else-if="emailOtpSent"
                        type="button"
                        @click="requestEmailOtp"
                        class="w-full text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400"
                    >
                        Resend code
                    </button>
                    <button
                        v-if="canShowAuthenticatorLink"
                        type="button"
                        @click="switchToAuthenticator"
                        class="w-full text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400"
                    >
                        Use authenticator app instead
                    </button>
                </div>
            </div>

            <button
                type="button"
                @click="clearVerification"
                class="w-full text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
            >
                ← Use different account
            </button>
        </template>

        <!-- Login form -->
        <form v-else @submit.prevent="submitPassword" class="space-y-6">
            <div>
                <InputLabel for="email" value="Email address" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-2 block w-full"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="you@example.com"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>
            <div>
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-2 block w-full"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <label for="remember" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                        Remember me
                    </label>
                </div>
                <div class="text-sm">
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400"
                    >
                        Forgot password?
                    </Link>
                </div>
            </div>
            <PrimaryButton
                type="submit"
                class="w-full flex justify-center"
                :class="{ 'opacity-25': form.processing }"
                :disabled="form.processing"
            >
                Sign in
            </PrimaryButton>
        </form>

        <!-- OAuth (only when Firebase auth is enabled) -->
        <template v-if="!isVerificationStep && firebaseAuthEnabled">
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
            <div class="mt-6 grid grid-cols-3 gap-3">
                <button
                    type="button"
                    @click="handleOAuth('google')"
                    :disabled="oauthLoading !== null"
                    class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Sign in with Google"
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
                    title="Sign in with GitHub"
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
                    title="Sign in with Microsoft"
                >
                    <svg v-if="oauthLoading !== 'microsoft'" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M11.4 24H0V12.6h11.4V24zM24 24h-11.4V12.6H24V24zM11.4 11.4H0V0h11.4v11.4zM24 11.4h-11.4V0H24v11.4z"/>
                    </svg>
                    <span v-else class="h-5 w-5 border-2 border-gray-300 border-t-gray-600 rounded-full animate-spin"></span>
                </button>
            </div>
        </template>

        <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
            Don't have an account?
            <Link :href="route('register')" class="font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400">
                Sign up
            </Link>
        </p>
    </SplitAuthLayout>
</template>
