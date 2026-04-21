<script setup lang="ts">
import { onMounted, ref } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import { useMfa } from '@/composables/useMfa';
import { useNotifications } from '@/composables/useNotifications';

const { showSuccess, showError } = useNotifications();
const {
  status,
  setupSecret,
  errorMessage,
  isConfigured,
  isEnabled,
  fetchStatus,
  generateSecret,
  verifyOtp,
  clearSetup,
} = useMfa();

const verifying = ref(false);
const generating = ref(false);
const verifyCode = ref('');
const showRemoveModal = ref(false);
const removePassword = ref('');
const removing = ref(false);

onMounted(() => {
  if (isConfigured.value) {
    fetchStatus();
  }
});

async function handleEnableMfa() {
  generating.value = true;
  try {
    const result = await generateSecret();
    if (result) {
      showSuccess('Scan the QR code with your authenticator app');
    }
  } finally {
    generating.value = false;
  }
}

async function handleVerifyOtp() {
  const code = verifyCode.value.replace(/\D/g, '');
  if (code.length !== 6) {
    showError('Please enter a 6-digit code');
    return;
  }

  verifying.value = true;
  try {
    const ok = await verifyOtp(code);
    if (ok) {
      showSuccess('MFA has been enabled successfully');
      verifyCode.value = '';
      window.dispatchEvent(new CustomEvent('mfa-status-changed', { detail: { enabled: true } }));
    } else if (errorMessage.value) {
      showError(errorMessage.value);
    }
  } finally {
    verifying.value = false;
  }
}

function handleCancelSetup() {
  clearSetup();
  verifyCode.value = '';
}

function handleRemoveMfa() {
  showRemoveModal.value = true;
  removePassword.value = '';
}

function closeRemoveModal() {
  showRemoveModal.value = false;
  removePassword.value = '';
}

async function confirmRemoveMfa() {
  // TODO: Backend endpoint for removing MFA - Teem API doesn't expose disable in OpenAPI
  showError('Remove MFA is not yet implemented. Contact support if you need to disable MFA.');
  closeRemoveModal();
}
</script>

<template>
  <section v-if="isConfigured">
    <header>
      <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
        Multi-Factor Authentication
      </h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Add an extra layer of security to your account with an authenticator app (Google Authenticator, Authy, etc.)
      </p>
    </header>

    <div class="mt-6 space-y-6">
      <!-- MFA disabled: Enable CTA -->
      <div
        v-if="status === 'disabled' || status === 'loading'"
        class="flex items-start gap-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4"
      >
        <div class="flex-shrink-0 w-12 h-12 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
          <svg class="h-6 w-6 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
        <div class="flex-1">
          <p v-if="status === 'loading'" class="text-sm text-gray-600 dark:text-gray-400">
            Checking MFA status...
          </p>
          <template v-else>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
              Use an authenticator app to generate one-time codes. Required for stronger account protection.
            </p>
            <PrimaryButton
              :disabled="generating"
              @click="handleEnableMfa"
            >
              {{ generating ? 'Generating...' : 'Enable MFA' }}
            </PrimaryButton>
          </template>
        </div>
      </div>

      <!-- MFA setup: QR code + verify -->
      <div
        v-else-if="status === 'setup-pending' && setupSecret"
        class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 space-y-6"
      >
        <h3 class="text-base font-medium text-gray-900 dark:text-gray-100">Set up authenticator app</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">
          Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)
        </p>

        <div class="flex flex-col sm:flex-row gap-6">
          <div class="flex-shrink-0 p-4 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 inline-flex items-center justify-center">
            <!-- QR code placeholder - in production use a QR library with setupSecret.otpauth_url -->
            <div class="w-40 h-40 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center text-gray-400 dark:text-gray-500 text-sm">
              QR Code
            </div>
          </div>
          <div class="flex-1">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Can't scan? Enter this code manually:</p>
            <code class="block p-3 bg-gray-100 dark:bg-gray-700 rounded text-sm font-mono text-gray-900 dark:text-white break-all">
              {{ setupSecret.base32 }}
            </code>
          </div>
        </div>

        <div>
          <InputLabel for="mfa-verify" value="Enter the 6-digit code from your app" />
          <div class="mt-2 flex gap-3">
            <TextInput
              id="mfa-verify"
              v-model="verifyCode"
              type="text"
              inputmode="numeric"
              maxlength="6"
              placeholder="000000"
              class="font-mono text-lg tracking-widest w-32"
              autocomplete="one-time-code"
              @keyup.enter="handleVerifyOtp"
            />
            <PrimaryButton
              :disabled="verifying || verifyCode.replace(/\D/g, '').length !== 6"
              @click="handleVerifyOtp"
            >
              {{ verifying ? 'Verifying...' : 'Verify & enable' }}
            </PrimaryButton>
          </div>
          <InputError class="mt-2" :message="errorMessage ?? undefined" />
        </div>

        <SecondaryButton @click="handleCancelSetup">
          Cancel
        </SecondaryButton>
      </div>

      <!-- MFA enabled: View device + Remove -->
      <div
        v-else-if="status === 'enabled'"
        class="flex items-center justify-between p-4 rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20"
      >
        <div class="flex items-center gap-3">
          <div class="flex-shrink-0 w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/40 flex items-center justify-center">
            <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="font-medium text-gray-900 dark:text-white">Authenticator app</p>
            <p class="text-sm text-gray-600 dark:text-gray-400">Your account is protected with MFA</p>
          </div>
        </div>
        <DangerButton @click="handleRemoveMfa">
          Remove MFA
        </DangerButton>
      </div>

      <!-- Error state -->
      <div
        v-else-if="status === 'error'"
        class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4"
      >
        <p class="text-sm text-red-700 dark:text-red-400">{{ errorMessage }}</p>
      </div>
    </div>

    <!-- Remove MFA modal -->
    <Modal :show="showRemoveModal" max-width="md" @close="closeRemoveModal">
      <div class="p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Remove multi-factor authentication?</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
          Your account will be less secure. This action may require backend support to complete.
        </p>
        <div class="mb-6">
          <InputLabel for="remove-password" value="Password" />
          <TextInput
            id="remove-password"
            v-model="removePassword"
            type="password"
            class="mt-1 block w-full"
            placeholder="Enter your password"
          />
        </div>
        <div class="flex gap-3 justify-end">
          <SecondaryButton @click="closeRemoveModal">Cancel</SecondaryButton>
          <DangerButton
            :disabled="removing || !removePassword"
            @click="confirmRemoveMfa"
          >
            {{ removing ? 'Removing...' : 'Remove MFA' }}
          </DangerButton>
        </div>
      </div>
    </Modal>
  </section>
</template>
