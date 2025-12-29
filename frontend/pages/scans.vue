<template>
  <div>
    <div class="page-header">
      <h1 class="page-title">Scans</h1>
      <Button label="+ New Scan" icon="pi pi-plus" @click="showNewScanDialog = true" />
    </div>

    <div class="card">
      <div style="margin-bottom: 20px;">
        <Dropdown v-model="selectedAccount" :options="awsAccountStore.accounts" optionLabel="name" placeholder="All Accounts" class="mr-2" />
        <Dropdown v-model="selectedStatus" :options="statusOptions" placeholder="All Status" />
      </div>

      <DataTable :value="scanStore.scans" :loading="scanStore.loading">
        <Column field="id" header="Scan ID" />
        <Column header="Account">
          <template #body="{ data }">
            {{ data.awsAccount?.name || `AWS Account ${data.awsAccount?.awsAccountId}` }}
          </template>
        </Column>
        <Column field="status" header="Status">
          <template #body="{ data }">
            <Badge :value="data.status" :severity="getStatusSeverity(data.status)" />
          </template>
        </Column>
        <Column header="Started">
          <template #body="{ data }">
            {{ new Date(data.startedAt).toLocaleString() }}
          </template>
        </Column>
        <Column header="Results">
          <template #body="{ data }">
            {{ data.resultCount || 0 }} findings
          </template>
        </Column>
        <Column header="Actions">
          <template #body="{ data }">
            <Button icon="pi pi-eye" text rounded @click="viewScan(data)" />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- New Scan Dialog -->
    <Dialog v-model:visible="showNewScanDialog" modal header="New Scan" :style="{ width: '500px' }">
      <div class="p-field">
        <label for="scanAccount">AWS Account</label>
        <Dropdown id="scanAccount" v-model="scanAccount" :options="awsAccountStore.accounts" optionLabel="name" class="w-full" />
      </div>
      <div class="p-field">
        <label>Scan Type</label>
        <Dropdown v-model="scanType" :options="['full', 'quick', 'custom']" class="w-full" />
      </div>
      <div class="p-field" v-if="scanType === 'custom'">
        <label>Services to Scan</label>
        <div style="margin-top: 8px;">
          <Checkbox v-model="servicesScanned" inputId="s3" value="S3" />
          <label for="s3" style="margin-left: 8px; margin-right: 16px;">S3</label>
          <Checkbox v-model="servicesScanned" inputId="iam" value="IAM" />
          <label for="iam" style="margin-left: 8px; margin-right: 16px;">IAM</label>
          <Checkbox v-model="servicesScanned" inputId="ec2" value="EC2" />
          <label for="ec2" style="margin-left: 8px; margin-right: 16px;">EC2</label>
          <Checkbox v-model="servicesScanned" inputId="rds" value="RDS" />
          <label for="rds" style="margin-left: 8px;">RDS</label>
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" text @click="showNewScanDialog = false" />
        <Button label="Start Scan" @click="startScan" :loading="scanStore.loading" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
});

const scanStore = useScanStore();
const awsAccountStore = useAwsAccountStore();
const organizationStore = useOrganizationStore();
const showNewScanDialog = ref(false);
const selectedAccount = ref(null);
const selectedStatus = ref(null);
const scanAccount = ref(null);
const scanType = ref('full');
const servicesScanned = ref(['S3', 'IAM', 'EC2', 'RDS']);

const statusOptions = [
  { label: 'All Status', value: null },
  { label: 'Pending', value: 'PENDING' },
  { label: 'Running', value: 'RUNNING' },
  { label: 'Completed', value: 'COMPLETED' },
  { label: 'Failed', value: 'FAILED' },
];

onMounted(async () => {
  if (organizationStore.currentOrgId) {
    await awsAccountStore.fetchAccounts(organizationStore.currentOrgId);
    await scanStore.fetchScans(organizationStore.currentOrgId);
  }
});

watch([selectedAccount, selectedStatus], async () => {
  if (organizationStore.currentOrgId) {
    await scanStore.fetchScans(organizationStore.currentOrgId, {
      accountId: selectedAccount.value?.id,
      status: selectedStatus.value,
    });
  }
});

const startScan = async () => {
  if (!scanAccount.value || !organizationStore.currentOrgId) return;
  
  try {
    await scanStore.createScan(
      organizationStore.currentOrgId,
      scanAccount.value.id,
      scanType.value,
      servicesScanned.value
    );
    showNewScanDialog.value = false;
    await scanStore.fetchScans(organizationStore.currentOrgId);
  } catch (error) {
    console.error('Failed to start scan:', error);
  }
};

const viewScan = (scan: any) => {
  navigateTo(`/scans/${scan.id}`);
};

const getStatusSeverity = (status: string) => {
  switch (status) {
    case 'COMPLETED': return 'success';
    case 'RUNNING': return 'info';
    case 'PENDING': return 'warning';
    case 'FAILED': return 'danger';
    default: return 'secondary';
  }
};
</script>

