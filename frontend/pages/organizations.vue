<template>
  <div>
    <div class="page-header">
      <h1 class="page-title">Organizations</h1>
      <Button label="+ Add Organization" icon="pi pi-plus" @click="showAddDialog = true" />
    </div>

    <div v-if="organizationStore.loading" style="text-align: center; padding: 40px;">
      <ProgressBar mode="indeterminate" style="height: 6px;" />
    </div>

    <div v-else-if="organizationStore.organizations.length === 0" class="card">
      <div style="text-align: center; padding: 40px; color: #999;">
        <p>No organizations yet. Create your first organization to get started.</p>
        <Button label="Create Organization" class="mt-3" @click="showAddDialog = true" />
      </div>
    </div>

    <div v-else class="card" v-for="org in organizationStore.organizations" :key="org.id">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <div class="card-header">
            {{ org.name }}
            <Badge v-if="org.isDefault" value="Default" severity="info" class="ml-2" />
          </div>
          <div class="card-content">
            Created: {{ new Date(org.createdAt).toLocaleDateString() }}
          </div>
        </div>
        <div class="card-actions">
          <Button 
            icon="pi pi-pencil" 
            text 
            rounded 
            @click="editOrganization(org)"
            v-tooltip="'Edit'"
          />
          <Button 
            icon="pi pi-trash" 
            text 
            rounded 
            severity="danger"
            @click="deleteOrganization(org)"
            v-tooltip="'Delete'"
            :disabled="org.isDefault"
          />
        </div>
      </div>
    </div>

    <!-- Add Organization Dialog -->
    <Dialog v-model:visible="showAddDialog" modal header="Add Organization" :style="{ width: '400px' }">
      <div class="p-field">
        <label for="orgName">Organization Name</label>
        <InputText id="orgName" v-model="newOrgName" placeholder="My Organization" class="w-full" />
      </div>
      <template #footer>
        <Button label="Cancel" text @click="showAddDialog = false" />
        <Button label="Create" @click="createOrganization" :loading="creating" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
});

const organizationStore = useOrganizationStore();
const showAddDialog = ref(false);
const newOrgName = ref('');
const creating = ref(false);

onMounted(async () => {
  await organizationStore.fetchOrganizations();
});

const createOrganization = async () => {
  if (!newOrgName.value.trim()) return;
  
  creating.value = true;
  try {
    await organizationStore.createOrganization(newOrgName.value);
    showAddDialog.value = false;
    newOrgName.value = '';
  } catch (error) {
    console.error('Failed to create organization:', error);
  } finally {
    creating.value = false;
  }
};

const editOrganization = (org: any) => {
  // TODO: Implement edit
  console.log('Edit organization:', org);
};

const deleteOrganization = async (org: any) => {
  if (confirm(`Are you sure you want to delete "${org.name}"?`)) {
    // TODO: Implement delete
    console.log('Delete organization:', org);
  }
};
</script>

