<template>
  <div class="auth-container">
    <Card class="auth-card">
      <template #title>
        <div class="text-center">
          <h1 class="text-2xl font-bold mb-2">🔒 Cloud Security</h1>
          <h2 class="text-xl">Sign In to Your Account</h2>
        </div>
      </template>
      <template #content>
        <form @submit.prevent="handleLogin" class="auth-form">
          <div class="p-field">
            <label for="email">Email Address</label>
            <InputText
              id="email"
              v-model="email"
              type="email"
              placeholder="you@example.com"
              class="w-full"
              required
            />
          </div>

          <div class="p-field">
            <label for="password">Password</label>
            <InputPassword
              id="password"
              v-model="password"
              placeholder="••••••••"
              class="w-full"
              :feedback="false"
              toggleMask
              required
            />
          </div>

          <div class="p-field-checkbox" style="margin-bottom: 1.5rem;">
            <Checkbox v-model="rememberMe" inputId="remember" />
            <label for="remember" style="margin-left: 0.5rem;">Remember me</label>
          </div>

          <Message v-if="error" severity="error" :closable="false" class="mb-4">
            {{ error }}
          </Message>

          <Button
            type="submit"
            label="Sign In"
            class="w-full mb-3"
            :loading="loading"
          />

          <div class="auth-divider">
            <span>────────── OR ──────────</span>
          </div>

          <div class="oauth-buttons">
            <Button
              label="Google"
              icon="pi pi-google"
              class="w-full mb-2"
              outlined
              @click="handleGoogleSignIn"
              :loading="loading"
            />
            <Button
              label="Microsoft"
              icon="pi pi-microsoft"
              class="w-full mb-2"
              outlined
              @click="handleMicrosoftSignIn"
              :loading="loading"
            />
          </div>

          <div class="auth-links">
            <NuxtLink to="/forgot-password">Forgot Password?</NuxtLink>
            <div style="margin-top: 12px;">
              Don't have an account? <NuxtLink to="/register">Sign Up</NuxtLink>
            </div>
          </div>
        </form>
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
  layout: false,
});

const authStore = useAuthStore();
const email = ref('');
const password = ref('');
const rememberMe = ref(false);
const loading = ref(false);
const error = ref<string | null>(null);

const handleLogin = async () => {
  loading.value = true;
  error.value = null;
  
  try {
    await authStore.signIn(email.value, password.value);
    await navigateTo('/dashboard');
  } catch (err: any) {
    error.value = err.message || 'Failed to sign in';
  } finally {
    loading.value = false;
  }
};

const handleGoogleSignIn = async () => {
  loading.value = true;
  error.value = null;
  
  try {
    await authStore.signInWithGoogle();
    await navigateTo('/dashboard');
  } catch (err: any) {
    error.value = err.message || 'Failed to sign in with Google';
  } finally {
    loading.value = false;
  }
};

const handleMicrosoftSignIn = async () => {
  error.value = 'Microsoft sign-in coming soon';
};
</script>


