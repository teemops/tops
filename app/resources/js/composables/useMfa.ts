import { ref, computed } from 'vue';
import { getIdToken, waitForAuthState } from './useFirebase';

const MFA_API_BASE = (import.meta.env.VITE_MFA_AUTH_API || '').replace(/\/$/, '');

export type MfaStatus = 'loading' | 'enabled' | 'disabled' | 'setup-pending' | 'error';

export interface GenerateResult {
  success: boolean;
  base32?: string;
  otpauth_url?: string;
  message?: string;
}

export interface VerifyResult {
  success: boolean;
  message?: string;
}

export interface ValidateResult {
  success: boolean;
  message?: string;
}

async function mfaFetch<T>(
  path: string,
  options: RequestInit & { token: string }
): Promise<T> {
  const { token, ...init } = options;
  const url = `${MFA_API_BASE}${path}`;
  const res = await fetch(url, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
      ...init.headers,
    },
  });

  const data = await res.json().catch(() => ({}));

  if (!res.ok) {
    const err = new Error((data as { error?: string }).error || `MFA API error: ${res.status}`);
    (err as Error & { status?: number }).status = res.status;
    throw err;
  }

  return data as T;
}

export function useMfa() {
  const status = ref<MfaStatus>('loading');
  const setupSecret = ref<GenerateResult | null>(null);
  const errorMessage = ref<string | null>(null);

  const isConfigured = computed(() => !!MFA_API_BASE);
  const isEnabled = computed(() => status.value === 'enabled');
  const isDisabled = computed(() => status.value === 'disabled' || status.value === 'setup-pending');
  const showAlert = computed(() => isConfigured.value && isDisabled.value && status.value !== 'loading');

  async function fetchStatus(): Promise<void> {
    if (!MFA_API_BASE) {
      status.value = 'error';
      errorMessage.value = 'MFA API not configured (VITE_MFA_AUTH_API)';
      return;
    }

    status.value = 'loading';
    errorMessage.value = null;

    try {
      await waitForAuthState();
      const token = await getIdToken();
      if (!token) {
        status.value = 'error';
        errorMessage.value = 'Not authenticated';
        return;
      }

      const result = await mfaFetch<GenerateResult>('/api/generate', {
        method: 'GET',
        token,
      });

      if (result.success && result.base32) {
        status.value = 'setup-pending';
        setupSecret.value = result;
      } else if (!result.success && result.message?.toLowerCase().includes('already verified')) {
        status.value = 'enabled';
        setupSecret.value = null;
      } else {
        status.value = 'disabled';
        setupSecret.value = null;
      }
    } catch (err: unknown) {
      status.value = 'error';
      errorMessage.value = err instanceof Error ? err.message : 'Failed to fetch MFA status';
      setupSecret.value = null;
    }
  }

  async function generateSecret(): Promise<GenerateResult | null> {
    if (!MFA_API_BASE) {
      errorMessage.value = 'MFA API not configured';
      return null;
    }

    errorMessage.value = null;

    try {
      await waitForAuthState();
      const token = await getIdToken();
      if (!token) {
        errorMessage.value = 'Not authenticated';
        return null;
      }

      const result = await mfaFetch<GenerateResult>('/api/generate', {
        method: 'GET',
        token,
      });

      if (result.success && result.base32) {
        status.value = 'setup-pending';
        setupSecret.value = result;
        return result;
      }

      if (!result.success && result.message?.toLowerCase().includes('already verified')) {
        status.value = 'enabled';
        setupSecret.value = null;
        errorMessage.value = 'MFA is already enabled';
        return null;
      }

      errorMessage.value = result.message || 'Failed to generate secret';
      return null;
    } catch (err: unknown) {
      errorMessage.value = err instanceof Error ? err.message : 'Failed to generate secret';
      return null;
    }
  }

  async function verifyOtp(otp: string): Promise<boolean> {
    if (!MFA_API_BASE) {
      errorMessage.value = 'MFA API not configured';
      return false;
    }

    errorMessage.value = null;

    try {
      await waitForAuthState();
      const token = await getIdToken();
      if (!token) {
        errorMessage.value = 'Not authenticated';
        return false;
      }

      const result = await mfaFetch<VerifyResult>('/api/verify', {
        method: 'POST',
        body: JSON.stringify({ otp: otp.trim() }),
        token,
      });

      if (result.success) {
        status.value = 'enabled';
        setupSecret.value = null;
        return true;
      }

      errorMessage.value = result.message || 'Verification failed';
      return false;
    } catch (err: unknown) {
      errorMessage.value = err instanceof Error ? err.message : 'Verification failed';
      return false;
    }
  }

  async function validateOtp(otp: string): Promise<boolean> {
    if (!MFA_API_BASE) return false;

    try {
      await waitForAuthState();
      const token = await getIdToken();
      if (!token) return false;

      const result = await mfaFetch<ValidateResult>('/api/validate', {
        method: 'POST',
        body: JSON.stringify({ otp: otp.trim() }),
        token,
      });

      return !!result.success;
    } catch {
      return false;
    }
  }

  function clearSetup(): void {
    setupSecret.value = null;
    if (status.value === 'setup-pending') {
      status.value = 'disabled';
    }
  }

  return {
    status,
    setupSecret,
    errorMessage,
    isConfigured,
    isEnabled,
    isDisabled,
    showAlert,
    fetchStatus,
    generateSecret,
    verifyOtp,
    validateOtp,
    clearSetup,
  };
}
