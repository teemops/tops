<template>
  <div>
    <div class="page-header">
      <h1 class="page-title">Insights & Analytics</h1>
      <Dropdown v-model="timeRange" :options="timeRangeOptions" />
    </div>

    <div v-if="insightsStore.loading" style="text-align: center; padding: 40px;">
      <ProgressBar mode="indeterminate" style="height: 6px;" />
    </div>

    <div v-else-if="insightsStore.insights">
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Total Findings</div>
          <div class="stat-value">{{ insightsStore.insights.totalFindings }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Avg Scan Time</div>
          <div class="stat-value">{{ insightsStore.insights.avgScanTime }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Compliance Score</div>
          <div class="stat-value">{{ insightsStore.insights.complianceScore }}%</div>
        </div>
      </div>

    <div class="card">
      <div class="card-header">Findings Trend</div>
      <div class="card-content" style="height: 300px; display: flex; align-items: center; justify-content: center; color: #999;">
        Chart placeholder - Findings over time
      </div>
    </div>

    <div class="card">
      <div class="card-header">Findings by Service</div>
      <div class="card-content" style="height: 300px; display: flex; align-items: center; justify-content: center; color: #999;">
        Chart placeholder - Service breakdown
      </div>
    </div>

      <div class="card">
        <div class="card-header">Compliance Status</div>
        <div class="card-content">
          <div style="margin-bottom: 10px;">
            <strong>CIS Benchmarks:</strong> {{ insightsStore.insights.complianceScores.cis }}%
          </div>
          <div style="margin-bottom: 10px;">
            <strong>SOC 2:</strong> {{ insightsStore.insights.complianceScores.soc2 }}%
          </div>
          <div>
            <strong>PCI-DSS:</strong> {{ insightsStore.insights.complianceScores.pci }}%
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">Top Risk Areas</div>
        <div class="card-content">
          <ol style="padding-left: 20px;">
            <li v-for="(risk, index) in insightsStore.insights.topRiskAreas" :key="index">
              {{ risk }}
            </li>
            <li v-if="insightsStore.insights.topRiskAreas.length === 0">No risk areas identified</li>
          </ol>
        </div>
      </div>
    </div>
    <div v-else class="card">
      <div class="card-content" style="text-align: center; padding: 40px; color: #999;">
        No insights available yet. Run some scans to see analytics.
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
});

const insightsStore = useInsightsStore();
const organizationStore = useOrganizationStore();
const timeRange = ref('30days');
const timeRangeOptions = [
  { label: 'Last 7 Days', value: '7days' },
  { label: 'Last 30 Days', value: '30days' },
  { label: 'Last 90 Days', value: '90days' },
];

onMounted(async () => {
  if (organizationStore.currentOrgId) {
    await insightsStore.fetchInsights(organizationStore.currentOrgId, timeRange.value);
  }
});

watch(timeRange, async () => {
  if (organizationStore.currentOrgId) {
    await insightsStore.fetchInsights(organizationStore.currentOrgId, timeRange.value);
  }
});
</script>

