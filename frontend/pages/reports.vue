<template>
  <div>
    <div class="page-header">
      <h1 class="page-title">Reports & Insights</h1>
      <Button label="Generate Report" icon="pi pi-file" />
    </div>

    <div class="card">
      <DataTable :value="reportStore.reports" :loading="reportStore.loading">
        <Column field="scanId" header="Scan ID" />
        <Column field="accountName" header="Account" />
        <Column field="generatedAt" header="Generated">
          <template #body="{ data }">
            {{ new Date(data.generatedAt).toLocaleString() }}
          </template>
        </Column>
        <Column field="findings" header="Findings">
          <template #body="{ data }">
            Critical: {{ data.findings.critical }}, High: {{ data.findings.high }}
          </template>
        </Column>
        <Column header="Actions">
          <template #body="{ data }">
            <Button icon="pi pi-eye" text rounded @click="viewReport(data)" />
            <Button icon="pi pi-file" text rounded @click="downloadReport(data, 'json')" v-tooltip="'Download JSON'" />
            <Button icon="pi pi-download" text rounded @click="downloadReport(data, 'csv')" v-tooltip="'Download CSV'" />
          </template>
        </Column>
      </DataTable>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
});

const reportStore = useReportStore();
const organizationStore = useOrganizationStore();

onMounted(async () => {
  if (organizationStore.currentOrgId) {
    await reportStore.fetchReports(organizationStore.currentOrgId);
  }
});

const viewReport = (report: any) => {
  navigateTo(`/reports/${report.scanId}`);
};

const downloadReport = async (report: any, format: 'json' | 'csv') => {
  if (!organizationStore.currentOrgId) return;
  try {
    await reportStore.exportReport(organizationStore.currentOrgId, report.scanId, format);
  } catch (error) {
    console.error('Failed to export report:', error);
  }
};
</script>

