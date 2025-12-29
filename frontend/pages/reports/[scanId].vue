<template>
  <div>
    <div class="page-header">
      <div class="page-title">Scan Report #{{ scanId }}</div>
      <div>
        <Button label="Download PDF" icon="pi pi-file" class="mr-2" disabled />
        <Button label="Download CSV" icon="pi pi-download" class="mr-2" @click="downloadCsv" />
        <Button label="Download JSON" icon="pi pi-file" @click="downloadJson" />
      </div>
    </div>

    <div v-if="reportStore.loading" style="text-align: center; padding: 40px;">
      <ProgressBar mode="indeterminate" style="height: 6px;" />
    </div>

    <div v-else-if="reportStore.currentReport">
      <!-- Executive Summary -->
      <div class="card">
        <div class="card-header">Executive Summary</div>
        <div class="card-content">
          <div class="stats-grid">
            <div class="stat-card">
              <div class="stat-label">Total Findings</div>
              <div class="stat-value">{{ reportStore.currentReport.statistics.total }}</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Critical</div>
              <div class="stat-value" style="color: #f44336;">{{ reportStore.currentReport.statistics.critical }}</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">High</div>
              <div class="stat-value" style="color: #ff9800;">{{ reportStore.currentReport.statistics.high }}</div>
            </div>
            <div class="stat-card">
              <div class="stat-label">Compliance Score</div>
              <div class="stat-value">{{ reportStore.currentReport.complianceScore }}%</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Findings by Service -->
      <div class="card">
        <div class="card-header">Findings by Service</div>
        <div class="card-content">
          <div v-for="(count, service) in reportStore.currentReport.statistics.byService" :key="service" style="margin-bottom: 10px;">
            <strong>{{ service }}:</strong> {{ count }} findings
          </div>
        </div>
      </div>

      <!-- Critical Findings -->
      <div class="card">
        <div class="card-header">Critical Findings</div>
        <div class="card-content">
          <div v-for="finding in criticalFindings" :key="finding.id" class="finding-item">
            <div style="display: flex; justify-content: space-between; align-items: start;">
              <div>
                <Badge :value="finding.severity" severity="danger" class="mr-2" />
                <strong>{{ finding.findingType }}</strong>
                <div style="margin-top: 8px; color: #666;">{{ finding.description }}</div>
                <div v-if="finding.resourceArn" style="margin-top: 4px; font-size: 12px; color: #999;">
                  Resource: {{ finding.resourceArn }}
                </div>
                <div v-if="finding.remediation" style="margin-top: 8px; padding: 8px; background: #f5f5f5; border-radius: 4px;">
                  <strong>Remediation:</strong> {{ finding.remediation }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- All Findings -->
      <div class="card">
        <div class="card-header">All Findings</div>
        <div class="card-content">
          <DataTable :value="reportStore.currentReport.scan.results" :paginator="true" :rows="20">
            <Column field="service" header="Service" />
            <Column field="severity" header="Severity">
              <template #body="{ data }">
                <Badge :value="data.severity" :severity="getSeverityColor(data.severity)" />
              </template>
            </Column>
            <Column field="findingType" header="Finding Type" />
            <Column field="description" header="Description" />
            <Column field="remediation" header="Remediation" />
          </DataTable>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
});

const route = useRoute();
const scanId = route.params.scanId as string;
const reportStore = useReportStore();
const organizationStore = useOrganizationStore();

const criticalFindings = computed(() => {
  if (!reportStore.currentReport) return [];
  return reportStore.currentReport.scan.results.filter((r: any) => r.severity === 'CRITICAL');
});

onMounted(async () => {
  if (organizationStore.currentOrgId) {
    await reportStore.getReport(organizationStore.currentOrgId, scanId);
  }
});

const downloadCsv = async () => {
  if (!organizationStore.currentOrgId) return;
  await reportStore.exportReport(organizationStore.currentOrgId, scanId, 'csv');
};

const downloadJson = async () => {
  if (!organizationStore.currentOrgId) return;
  await reportStore.exportReport(organizationStore.currentOrgId, scanId, 'json');
};

const getSeverityColor = (severity: string) => {
  switch (severity) {
    case 'CRITICAL': return 'danger';
    case 'HIGH': return 'warning';
    case 'MEDIUM': return 'info';
    case 'LOW': return 'secondary';
    default: return 'secondary';
  }
};
</script>

<style scoped>
.finding-item {
  padding: 15px;
  border-left: 4px solid #f44336;
  background: #fff3f3;
  margin: 10px 0;
  border-radius: 4px;
}
</style>

