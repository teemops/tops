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
                  :value="account.status.toUpperCase()" 
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
            <Button 
              label="Scan" 
              icon="pi pi-search" 
              @click="startScan(account)"
              :disabled="account.status !== 'active'"
            />
            <Button 
              icon="pi pi-pencil" 
              text 
              rounded 
              @click="editAccount(account)"
              :disabled="account.status === 'pending'"
              v-tooltip.top="'Edit Account Name'"
            />
            <Button 
              icon="pi pi-trash" 
              text 
              rounded 
              severity="danger" 
              @click="deleteAccount(account)"
              :disabled="account.status === 'pending'"
              v-tooltip.top="'Delete Account'"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Account Dialog -->
    <Dialog v-model:visible="showEditDialog" modal header="Edit AWS Account Name" :style="{ width: '500px' }">
      <div class="p-field">
        <label for="editAccountName">Account Name</label>
        <InputText 
          id="editAccountName" 
          v-model="editAccountName" 
          placeholder="Enter account name" 
          class="w-full"
          :maxlength="255"
        />
        <small class="text-gray-500">Enter a name to identify this AWS account (1-255 characters)</small>
      </div>
      <template #footer>
        <Button label="Cancel" text @click="closeEditDialog" />
        <Button 
          label="Save" 
          @click="saveAccountEdit" 
          :loading="editLoading"
          :disabled="!editAccountName || editAccountName.trim().length === 0"
        />
      </template>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <Dialog v-model:visible="showDeleteDialog" modal header="Delete AWS Account" :style="{ width: '500px' }">
      <div v-if="accountToDelete">
        <Message severity="warn" :closable="false" class="mb-4">
          <div class="font-bold mb-2">Warning: This action is irreversible</div>
          <div>All data including scans, and relevant AWS account information will be removed.</div>
        </Message>
        
        <div class="mb-3">
          <p class="font-semibold">You are about to delete:</p>
          <p class="text-lg">{{ accountToDelete.name || `AWS Account ${accountToDelete.awsAccountId}` }}</p>
          <p class="text-sm text-gray-600 mt-1">AWS Account ID: {{ accountToDelete.awsAccountId }}</p>
        </div>

        <div class="p-field">
          <label for="deleteAccountNumber" class="font-semibold">
            Enter AWS Account Number to confirm deletion:
          </label>
          <InputText 
            id="deleteAccountNumber" 
            v-model="deleteAccountNumber" 
            placeholder="123456789012" 
            class="w-full"
            maxlength="12"
            @input="validateDeleteAccountNumber"
          />
          <small class="text-gray-500">
            Type <strong>{{ accountToDelete.awsAccountId }}</strong> to confirm
          </small>
          <Message 
            v-if="deleteAccountNumber && !isDeleteAccountNumberValid" 
            severity="error" 
            :closable="false" 
            class="mt-2"
          >
            The entered account number does not match.
          </Message>
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" text @click="closeDeleteDialog" />
        <Button 
          label="Delete Account" 
          severity="danger"
          @click="confirmDeleteAccount" 
          :loading="deleteLoading"
          :disabled="!isDeleteAccountNumberValid"
        />
      </template>
    </Dialog>

    <!-- Add Account Dialog -->
    <Dialog v-model:visible="showAddDialog" modal header="Add AWS Account" :style="{ width: '600px' }">
      <div v-if="step === 1">
        <p>To securely access your AWS account, we need to create an IAM role using CloudFormation.</p>
        <ol style="margin: 15px 0; padding-left: 20px;">
          <li>Click "Open AWS Console" below</li>
          <li>Complete the CloudFormation stack in the new window</li>
          <li>Return here - the account will be added automatically</li>
          <li>If it doesn't appear, use "Enter Details Manually"</li>
        </ol>
        <div v-if="pendingAccountId" class="mb-3">
          <Message severity="info" :closable="false">
            Waiting for CloudFormation completion... Status will update automatically.
          </Message>
        </div>
        <Button 
          label="Open AWS Console" 
          @click="openCloudFormation"
          :loading="initLoading"
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
          <label for="awsAccountId">AWS Account ID (12 digits)</label>
          <InputText 
            id="awsAccountId" 
            v-model="manualAccountId" 
            placeholder="123456789012" 
            class="w-full"
            maxlength="12"
          />
        </div>
        <div class="p-field">
          <label for="iamRoleArn">IAM Role ARN</label>
          <InputText 
            id="iamRoleArn" 
            v-model="manualRoleArn" 
            placeholder="arn:aws:iam::123456789012:role/TeemOps" 
            class="w-full"
          />
        </div>
        <div class="p-field">
          <label for="accountName">Account Name (optional)</label>
          <InputText 
            id="accountName" 
            v-model="accountName" 
            placeholder="Production AWS" 
            class="w-full"
          />
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" text @click="closeDialog" />
        <Button 
          v-if="step === 2" 
          label="Add Account" 
          @click="addAccountManually" 
          :loading="loading"
          :disabled="!manualAccountId || !manualRoleArn"
        />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import type { AwsAccount } from '~/stores/aws-accounts';

definePageMeta({
  middleware: 'auth',
});

const organizationStore = useOrganizationStore();
const awsAccountStore = useAwsAccountStore();
const showAddDialog = ref(false);
const showEditDialog = ref(false);
const showDeleteDialog = ref(false);
const step = ref(1);
const loading = ref(false);
const initLoading = ref(false);
const editLoading = ref(false);
const deleteLoading = ref(false);
const cloudFormationUrl = ref('');
const pendingAccountId = ref<string | null>(null);
const manualAccountId = ref('');
const manualRoleArn = ref('');
const accountName = ref('');
const editAccountId = ref<string | null>(null);
const editAccountName = ref('');
const accountToDelete = ref<AwsAccount | null>(null);
const deleteAccountNumber = ref('');
const isDeleteAccountNumberValid = ref(false);
let pollingInterval: NodeJS.Timeout | null = null;

onMounted(async () => {
  if (organizationStore.currentOrgId) {
    await awsAccountStore.fetchAccounts(organizationStore.currentOrgId);
  }
});

onUnmounted(() => {
  if (pollingInterval) {
    clearInterval(pollingInterval);
  }
});

const closeDialog = () => {
  showAddDialog.value = false;
  step.value = 1;
  cloudFormationUrl.value = '';
  pendingAccountId.value = null;
  manualAccountId.value = '';
  manualRoleArn.value = '';
  accountName.value = '';
  if (pollingInterval) {
    clearInterval(pollingInterval);
    pollingInterval = null;
  }
};

const initAddAccount = async () => {
  if (!organizationStore.currentOrgId) {
    alert('Please select an organization first');
    return;
  }
  
  initLoading.value = true;
  try {
    const response = await awsAccountStore.initAccount(organizationStore.currentOrgId);
    cloudFormationUrl.value = response.cloudFormationUrl;
    pendingAccountId.value = response.accountId;
    showAddDialog.value = true;
    step.value = 1;
  } catch (error: any) {
    console.error('Failed to init account:', error);
    alert(error.message || 'Failed to initialize AWS account');
  } finally {
    initLoading.value = false;
  }
};

const openCloudFormation = () => {
  if (cloudFormationUrl.value) {
    window.open(cloudFormationUrl.value, '_blank');
    // Start polling for account status
    startPolling();
  }
};

const startPolling = () => {
  if (!pendingAccountId.value || !organizationStore.currentOrgId) return;
  
  // Poll every 3 seconds for up to 2 minutes
  let attempts = 0;
  const maxAttempts = 40; // 2 minutes
  
  pollingInterval = setInterval(async () => {
    attempts++;
    
    if (attempts > maxAttempts) {
      if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
      }
      return;
    }
    
    try {
      // Refresh accounts list
      await awsAccountStore.fetchAccounts(organizationStore.currentOrgId!);
      
      // Check if account status changed from pending
      const account = awsAccountStore.accounts.find((acc: AwsAccount) => acc.id === pendingAccountId.value);
      if (account && account.status !== 'pending') {
        // Account is now active or error
        if (pollingInterval) {
          clearInterval(pollingInterval);
          pollingInterval = null;
        }
        
        if (account.status === 'active') {
          closeDialog();
        }
      }
    } catch (error) {
      console.error('Error polling account status:', error);
    }
  }, 3000);
};

const addAccountManually = async () => {
  if (!manualAccountId.value.trim() || !manualRoleArn.value.trim()) {
    alert('Please enter AWS Account ID and IAM Role ARN');
    return;
  }
  
  if (!organizationStore.currentOrgId) {
    alert('Please select an organization first');
    return;
  }
  
  loading.value = true;
  try {
    await awsAccountStore.createAccount(
      organizationStore.currentOrgId,
      manualAccountId.value.trim(),
      manualRoleArn.value.trim(),
      accountName.value.trim() || undefined
    );
    closeDialog();
  } catch (error: any) {
    console.error('Failed to add account:', error);
    alert(error.message || 'Failed to add AWS account');
  } finally {
    loading.value = false;
  }
};

const startScan = (account: AwsAccount) => {
  navigateTo(`/scans?accountId=${account.id}`);
};

const editAccount = (account: AwsAccount) => {
  editAccountId.value = account.id;
  editAccountName.value = account.name || '';
  showEditDialog.value = true;
};

const closeEditDialog = () => {
  showEditDialog.value = false;
  editAccountId.value = null;
  editAccountName.value = '';
};

const saveAccountEdit = async () => {
  if (!editAccountId.value || !editAccountName.value.trim()) {
    return;
  }

  editLoading.value = true;
  try {
    await awsAccountStore.updateAccount(editAccountId.value, editAccountName.value.trim());
    closeEditDialog();
  } catch (error: any) {
    console.error('Failed to update account:', error);
    alert(error.message || 'Failed to update AWS account name');
  } finally {
    editLoading.value = false;
  }
};

const deleteAccount = (account: AwsAccount) => {
  accountToDelete.value = account;
  deleteAccountNumber.value = '';
  isDeleteAccountNumberValid.value = false;
  showDeleteDialog.value = true;
};

const validateDeleteAccountNumber = () => {
  if (accountToDelete.value) {
    isDeleteAccountNumberValid.value = deleteAccountNumber.value === accountToDelete.value.awsAccountId;
  }
};

const closeDeleteDialog = () => {
  showDeleteDialog.value = false;
  accountToDelete.value = null;
  deleteAccountNumber.value = '';
  isDeleteAccountNumberValid.value = false;
};

const confirmDeleteAccount = async () => {
  if (!accountToDelete.value || !isDeleteAccountNumberValid.value) {
    return;
  }

  deleteLoading.value = true;
  try {
    await awsAccountStore.deleteAccount(accountToDelete.value.id);
    closeDeleteDialog();
  } catch (error: any) {
    console.error('Failed to delete account:', error);
    alert(error.message || 'Failed to delete AWS account');
  } finally {
    deleteLoading.value = false;
  }
};

const getStatusSeverity = (status: string) => {
  switch (status.toLowerCase()) {
    case 'active': return 'success';
    case 'pending': return 'warning';
    case 'error': return 'danger';
    default: return 'secondary';
  }
};
</script>
