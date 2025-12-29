<template>
  <div>
    <div class="page-header">
      <h1 class="page-title">Dashboard</h1>
      <div>
        <Button label="New Scan" icon="pi pi-search" @click="navigateTo('/scans?action=new')" />
        <Button label="Add AWS Account" icon="pi pi-plus" class="ml-2" @click="navigateTo('/aws-accounts?action=add')" />
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Accounts</div>
        <div class="stat-value">{{ awsAccountStore.accounts.length }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Active Scans</div>
        <div class="stat-value">0</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Critical Findings</div>
        <div class="stat-value">0</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Compliance Score</div>
        <div class="stat-value">--</div>
      </div>
    </div>

    <!-- Recent Scans -->
    <div class="card">
      <div class="card-header">Recent Scans</div>
      <div class="card-content">
        <p style="color: #999; text-align: center; padding: 40px;">
          No scans yet. <NuxtLink to="/scans" style="color: var(--primary-color); text-decoration: none;">Start your first scan</NuxtLink>
        </p>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
      <div class="card-header">Quick Actions</div>
      <div class="card-content" style="display: flex; gap: 10px; flex-wrap: wrap;">
        <Button label="Add AWS Account" icon="pi pi-cloud" @click="navigateTo('/aws-accounts?action=add')" />
        <Button label="View Organizations" icon="pi pi-building" @click="navigateTo('/organizations')" />
        <Button label="View Reports" icon="pi pi-file" @click="navigateTo('/reports')" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
});

const authStore = useAuthStore();
const organizationStore = useOrganizationStore();
const awsAccountStore = useAwsAccountStore();

onMounted(async () => {
  if (organizationStore.currentOrgId) {
    await awsAccountStore.fetchAccounts(organizationStore.currentOrgId);
  }
});
</script>
