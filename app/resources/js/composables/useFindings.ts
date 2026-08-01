import { ref } from 'vue';
import { useOrganizations } from './useOrganizations';

const axios = (window as any).axios;

export interface FindingSummary {
    total: number;
    bySeverity: {
        critical: number;
        high: number;
        medium: number;
        low: number;
    };
}

export interface Finding {
    id: string;
    scanId: string;
    findingType: string;
    title: string;
    description: string;
    severity: 'critical' | 'high' | 'medium' | 'low';
    service: string;
    resourceId: string;
    resourceType: string;
    status: string;
    remediation: string | null;
    createdAt: string;
    awsAccountId?: string;
    awsAccountName?: string;
}

export interface Recommendation {
    name: string;
    recommendation: string;
    impact: string;
    links: string[];
    description: string;
    steps: string[];
    rules: string[];
}

/**
 * One service pill: how many findings you would get by selecting it, given whatever other
 * filters are already active. Computed server-side — the list is paginated, so counting the
 * loaded page would undercount.
 */
export interface ServiceFacet {
    service: string;
    count: number;
}

export interface FindingsIndexResponse {
    summary: FindingSummary;
    findings: Finding[];
    serviceFacets: ServiceFacet[];
    recommendationsMap: Record<string, Recommendation>;
    total: number;
    limit: number;
    offset: number;
}

export interface FindingByTypeResponse {
    findingType: string;
    title: string;
    description: string;
    recommendation: Recommendation | null;
    findings: Array<{
        id: string;
        scanId: string;
        title: string;
        description: string;
        severity: string;
        service: string;
        resourceId: string;
        resourceType: string;
        status: string;
        createdAt: string;
        awsAccountId?: string;
        awsAccountName?: string;
    }>;
    total: number;
}

const findings = ref<Finding[]>([]);
const summary = ref<FindingSummary | null>(null);
const serviceFacets = ref<ServiceFacet[]>([]);
const recommendationsMap = ref<Record<string, Recommendation>>({});
const loading = ref(false);
const error = ref<string | null>(null);
const pagination = ref({ total: 0, limit: 50, offset: 0 });

export function useFindings() {
    const { currentOrganization } = useOrganizations();

    const fetchFindings = async (filters?: {
        awsAccountId?: string;
        findingType?: string;
        service?: string;
        status?: string;
        limit?: number;
        offset?: number;
    }) => {
        const orgId = currentOrganization.value?.org_id;
        if (!orgId) {
            error.value = 'No organization selected';
            return;
        }

        loading.value = true;
        error.value = null;
        try {
            const params = new URLSearchParams();
            if (filters?.awsAccountId) params.set('aws_account_id', filters.awsAccountId);
            if (filters?.findingType) params.set('finding_type', filters.findingType);
            if (filters?.service) params.set('service', filters.service);
            if (filters?.status) params.set('status', filters.status);
            if (filters?.limit) params.set('limit', String(filters.limit));
            if (filters?.offset) params.set('offset', String(filters.offset));

            const url = `/api/organizations/${orgId}/findings${params.toString() ? `?${params}` : ''}`;
            const response = await axios.get(url);
            const data: FindingsIndexResponse = response.data;

            summary.value = data.summary;
            findings.value = data.findings;
            serviceFacets.value = data.serviceFacets ?? [];
            recommendationsMap.value = data.recommendationsMap ?? {};
            pagination.value = {
                total: data.total,
                limit: data.limit,
                offset: data.offset,
            };
        } catch (err: any) {
            error.value = err.response?.data?.error ?? 'Failed to load findings';
            findings.value = [];
            summary.value = null;
            serviceFacets.value = [];
        } finally {
            loading.value = false;
        }
    };

    const fetchFindingById = async (findingId: string) => {
        loading.value = true;
        error.value = null;
        try {
            const response = await axios.get(`/api/results/${findingId}`);
            return response.data;
        } catch (err: any) {
            error.value = err.response?.data?.error ?? 'Failed to load finding';
            return null;
        } finally {
            loading.value = false;
        }
    };

    const updateFindingStatus = async (findingId: string, status: 'open' | 'resolved' | 'ignored') => {
        try {
            await axios.put(`/api/results/${findingId}`, { status });
        } catch (err: any) {
            throw new Error(err.response?.data?.error ?? 'Failed to update status');
        }
    };

    const fetchFindingsByType = async (findingType: string): Promise<FindingByTypeResponse | null> => {
        const orgId = currentOrganization.value?.org_id;
        if (!orgId) {
            error.value = 'No organization selected';
            return null;
        }

        loading.value = true;
        error.value = null;
        try {
            const response = await axios.get(`/api/organizations/${orgId}/findings/by-type/${encodeURIComponent(findingType)}`);
            return response.data;
        } catch (err: any) {
            error.value = err.response?.data?.error ?? 'Failed to load findings';
            return null;
        } finally {
            loading.value = false;
        }
    };

    const fetchRecommendations = async () => {
        try {
            const response = await axios.get('/api/recommendations');
            return response.data;
        } catch (err: any) {
            console.error('Error fetching recommendations:', err);
            return { recommendations: [] };
        }
    };

    return {
        findings,
        summary,
        serviceFacets,
        recommendationsMap,
        loading,
        error,
        pagination,
        fetchFindings,
        fetchFindingById,
        updateFindingStatus,
        fetchFindingsByType,
        fetchRecommendations,
    };
}
