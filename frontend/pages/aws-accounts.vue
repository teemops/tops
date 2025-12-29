<template>
  <div>
    <div class="page-header">
      <h1 class="page-title">AWS Accounts</h1>
      <Button 
        label="+ Add Account" 
        icon="pi pi-plus" 
        @click="initAddAccount"
        :disabled="!organizationStore.currentOrgId"
      />
    </div>

    <Message v-if="!organizationStore.currentOrgId" severity="warn" :closable="false" class="mb-4">
      Please select an organization to view AWS accounts.
    </Message>

    <div v-if="awsAccountStore.loading" style="text-align: center; padding: 40px;">
      <ProgressBar mode="indeterminate" style="height: 6px;" />
    </div>

    <div v-else-if="awsAccountStore.accounts.length === 0" class="card">
      <div style="text-align: center; padding: 40px; color: #999;">
        <p>No AWS accounts yet. Add your first AWS account to start scanning.</p>
        <Button label="Add AWS Account" class="mt-3" @click="initAddAccount" />
      </div>
    </div>

    <div v-else>
      <div class="card" v-for="account in awsAccountStore.accounts" :key="account.id">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div class="card-header">
              {{ account.name || `AWS Account ${account.awsAccountId}` }}
            </div>
            <div class="card-content">
              <div>Account ID: {{ account.awsAccountId }}</div>
              <div style="margin-top: 8px;">
                Status: 
                <Badge 
                  :value="account.status" 
                  :severity="getStatusSeverity(account.status)"
                  class="ml-2"
                />
                <span v-if="account.lastScanAt" class="ml-3">
                  Last Scan: {{ new Date(account.lastScanAt).toLocaleString() }}
                </span>
              </div>
            </div>
          </div>
          <div class="card-actions">
            <Button label="Scan" icon="pi pi-search" @click="startScan(account)" />
            <Button icon="pi pi-trash" text rounded severity="danger" @click="deleteAccount(account)" />
          </div>
        </div>
      </div>
    </div>

    <!-- Add Account Dialog -->
    <Dialog v-model:visible="showAddDialog" modal header="Add AWS Account" :style="{ width: '600px' }">
      <div v-if="step === 1">
        <p>To securely access your AWS account, we need to create an IAM role using CloudFormation.</p>
        <ol style="margin: 15px 0; padding-left: 20px;">
          <li>Click "Open AWS Console" below</li>
          <li>Complete the CloudFormation stack</li>
          <li>The account will be added automatically</li>
        </ol>
        <Button 
          label="Open AWS Console" 
          @click="openCloudFormation"
          :loading="loading"
          class="w-full mb-2"
        />
        <Button 
          label="Enter Details Manually" 
          text
          @click="step = 2"
          class="w-full"
        />
      </div>
      <div v-else>
        <div class="p-field">
          <label for="awsAccountId">AWS Account ID</label>
          <InputText id="awsAccountId" v-model="manualAccountId" placeholder="123456789012" class="w-full" />
        </div>
        <div class="p-field">
          <label for="iamRoleArn">IAM Role ARN</label>
          <InputText id="iamRoleArn" v-model="manualRoleArn" placeholder="arn:aws:iam::123456789012:role/..." class="w-full" />
        </div>
        <div class="p-field">
          <label for="accountName">Account Name (optional)</label>
          <InputText id="accountName" v-model="accountName" placeholder="Production AWS" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" text @click="showAddDialog = false; step = 1" />
        <Button v-if="step === 2" label="Add Account" @click="addAccountManually" :loading="loading" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
});

const organizationStore = useOrganizationStore();
const awsAccountStore = useAwsAccountStore();
const showAddDialog = ref(false);
const step = ref(1);
const loading = ref(false);
const cloudFormationUrl = ref('');
const manualAccountId = ref('');
const manualRoleArn = ref('');
const accountName = ref('');

onMounted(async () => {
  if (organizationStore.currentOrgId) {
    await awsAccountStore.fetchAccounts(organizationStore.currentOrgId);
  }
});

const initAddAccount = async () => {
  if (!organizationStore.currentOrgId) {
    alert('Please select an organization first');
    return;
  }
  
  loading.value = true;
  try {
    const response = await awsAccountStore.initAccount(organizationStore.currentOrgId);
    cloudFormationUrl.value = response.cloudFormationUrl;
    showAddDialog.value = true;
    step.value = 1;
  } catch (error) {
    console.error('Failed to init account:', error);
  } finally {
    loading.value = false;
  }
};

const openCloudFormation = () => {
  if (cloudFormationUrl.value) {
    window.open(cloudFormationUrl.value, '_blank');
    // Poll for account status
    pollAccountStatus();
  }
};

const pollAccountStatus = async () => {
  // TODO: Implement polling
  console.log('Polling for account status...');
};

const addAccountManually = async () => {
  // TODO: Implement manual account addition
  console.log('Add account manually');
};

const startScan = (account: any) => {
  navigateTo(`/scans?accountId=${account.id}`);
};

const deleteAccount = async (account: any) => {
  if (confirm(`Are you sure you want to remove "${account.name || account.awsAccountId}"?`)) {
    // TODO: Implement delete
    console.log('Delete account:', account);
  }
};

const getStatusSeverity = (status: string) => {
  switch (status) {
    case 'ACTIVE': return 'success';
    case 'PENDING': return 'warning';
    case 'ERROR': return 'danger';
    default: return 'secondary';
  }
};
</script>

