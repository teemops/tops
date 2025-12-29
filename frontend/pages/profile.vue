<template>
  <div>
    <div class="page-header">
      <h1 class="page-title">Profile</h1>
    </div>

    <div class="card" v-if="userStore.profile">
      <div class="card-header">User Information</div>
      <div class="card-content">
        <form @submit.prevent="handleUpdate" class="auth-form">
          <div class="p-field">
            <label for="name">Full Name</label>
            <InputText
              id="name"
              v-model="name"
              placeholder="John Doe"
              class="w-full"
            />
          </div>

          <div class="p-field">
            <label for="email">Email Address</label>
            <InputText
              id="email"
              v-model="email"
              type="email"
              disabled
              class="w-full"
            />
          </div>

          <div class="p-field">
            <label>Account Created</label>
            <InputText
              :value="new Date(userStore.profile.createdAt).toLocaleString()"
              disabled
              class="w-full"
            />
          </div>

          <Message v-if="userStore.error" severity="error" :closable="false" class="mb-4">
            {{ userStore.error }}
          </Message>

          <Message v-if="successMessage" severity="success" :closable="true" @close="successMessage = null" class="mb-4">
            {{ successMessage }}
          </Message>

          <Button
            type="submit"
            label="Update Profile"
            :loading="userStore.loading"
          />
        </form>
      </div>
    </div>

    <div v-else-if="userStore.loading" style="text-align: center; padding: 40px;">
      <ProgressBar mode="indeterminate" style="height: 6px;" />
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
});

const userStore = useUserStore();
const name = ref('');
const email = ref('');
const successMessage = ref<string | null>(null);

onMounted(async () => {
  await userStore.fetchProfile();
  if (userStore.profile) {
    name.value = userStore.profile.name || '';
    email.value = userStore.profile.email || '';
  }
});

const handleUpdate = async () => {
  try {
    await userStore.updateProfile(name.value);
    successMessage.value = 'Profile updated successfully';
    setTimeout(() => {
      successMessage.value = null;
    }, 3000);
  } catch (error) {
    // Error handled by store
  }
};
</script>

