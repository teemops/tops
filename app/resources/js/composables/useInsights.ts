import { ref } from 'vue';
import { useOrganizations } from './useOrganizations';

const axios = (window as any).axios;

export interface InsightPoint {
    date: string;
    new: number;
    resolved: number;
}

export interface InsightSummary {
    totalFindings: number;
    openFindings: number;
    criticalOpen: number;
    remediationRate: number;
    averageCompliance: number;
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
    summary: InsightSummary;
    severityDistribution: {
        critical: number;
        high: number;
        medium: number;
        low: number;
    };
    trend: InsightPoint[];
    topServices: TopService[];
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
