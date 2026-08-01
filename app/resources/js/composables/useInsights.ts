import { ref } from 'vue';
import { useOrganizations } from './useOrganizations';
import type { ScanBreakdown } from './useScans';

const axios = (window as any).axios;

export interface InsightPoint {
    date: string;
    new: number;
    resolved: number;
}

export interface InsightSummary {
    totalFindings: number;
    previousTotalFindings: number;
    /** Percent change vs. the preceding window; null when there is no baseline to compare against. */
    findingsChangePercent: number | null;
    openFindings: number;
    criticalOpen: number;
    remediationRate: number;
    /** Null until at least one framework has actually been evaluated. */
    averageCompliance: number | null;
    scanCount: number;
}

export interface ComplianceFramework {
    key: string;
    label: string;
    description: string;
    totalRules: number;
    failingRules: number;
    score: number | null;
    evaluated: boolean;
}

export interface InsightItem {
    title: string;
    detail: string;
    severity: 'positive' | 'warning' | 'danger';
}

export interface TopService {
    service: string;
    count: number;
}

export interface InsightsResponse {
    period: string;
    /** Trend bucket size the API chose for this period: day (30d), week (90d), month (1y). */
    granularity: 'day' | 'week' | 'month';
    summary: InsightSummary;
    severityDistribution: {
        critical: number;
        high: number;
        medium: number;
        low: number;
    };
    compliance: {
        average: number | null;
        frameworks: ComplianceFramework[];
    };
    trend: InsightPoint[];
    topServices: TopService[];
    /**
     * Current state across all scans — the one part of this payload that is not scoped to
     * the selected period, so a service reads the same number here as on Scan detail.
     */
    breakdown: ScanBreakdown;
    keyInsights: InsightItem[];
}

const insights = ref<InsightsResponse | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

export function useInsights() {
    const { currentOrganization } = useOrganizations();

    const fetchInsights = async (period = '30d') => {
        const orgId = currentOrganization.value?.org_id;
        if (!orgId) {
            error.value = 'No organization selected';
            return;
        }

        loading.value = true;
        error.value = null;

        try {
            const response = await axios.get(`/api/organizations/${orgId}/insights`, {
                params: { period },
            });
            insights.value = response.data;
        } catch (err: any) {
            error.value = err.response?.data?.error ?? 'Failed to load insights';
            insights.value = null;
        } finally {
            loading.value = false;
        }
    };

    return {
        insights,
        loading,
        error,
        fetchInsights,
    };
}
