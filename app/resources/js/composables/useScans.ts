import { ref, computed } from 'vue';
import { useOrganizations } from './useOrganizations';

// Use the configured axios instance from bootstrap.ts
const axios = (window as any).axios;

export type Severity = 'critical' | 'high' | 'medium' | 'low';

export type SeverityCounts = Record<Severity, number>;

/**
 * One row of a grouped, severity-stacked breakdown — by service, or by finding type.
 * Insights (F-5/F-6) renders the same shape keyed on the organization.
 */
export interface BreakdownGroup {
    key: string;
    total: number;
    severities: SeverityCounts;
    weight: number;
}

/** A change that would clear several findings at once, ranked by severity. */
export interface FixFirstEntry {
    title: string;
    clears: number;
    weight: number;
    rules: string[];
    severities: SeverityCounts;
    topSeverity: Severity | null;
}

/**
 * What is open *now* for this scan's account — not what this scan personally saw. Since
 * durable findings the two are the same thing only for the latest scan, which is why this
 * is null on any older one.
 */
export interface ScanBreakdown {
    summary: { total: number; bySeverity: SeverityCounts };
    byService: BreakdownGroup[];
    byFindingType: BreakdownGroup[];
    fixFirst: FixFirstEntry[];
}

export interface Scan {
    id: string;
    awsAccountId: string;
    awsAccountName: string;
    status: 'pending' | 'running' | 'completed' | 'failed' | 'cancelled';
    scanTypes: string[];
    rulesets?: string[];
    /** Human labels for this scan's rulesets, already resolved server-side. */
    rulesetLabels?: string[];
    isPartial?: boolean;
    findingsCount: number;
    createdAt: string;
    startedAt?: string;
    completedAt?: string;
    errorMessage?: string;
    isLatestForAccount?: boolean;
    latestScanId?: string | null;
    breakdown?: ScanBreakdown | null;
}

export interface ScanType {
    value: string;
    label: string;
}

export interface ScanProfile {
    value: string;
    label: string;
    description: string;
    /** Services this profile has rules for, and can therefore be narrowed to. */
    services: string[];
    /** Rule count per service, so the modal can say what a selection will run. */
    serviceRuleCounts: Record<string, number>;
    /** Total rules across the profile's selectable services. */
    ruleCount: number;
}

const scans = ref<Scan[]>([]);
const scanTypes = ref<ScanType[]>([]);
const scanProfiles = ref<ScanProfile[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const pollingIntervals = ref<Map<string, number>>(new Map());
const pagination = ref({
    total: 0,
    limit: 20,
    offset: 0,
    currentPage: 1,
});

export function useScans() {
    const { currentOrganization } = useOrganizations();

    const fetchScanTypes = async () => {
        try {
            const response = await axios.get('/api/scan-types');
            scanTypes.value = response.data.scanTypes;
        } catch (err: any) {
            console.error('Error fetching scan types:', err);
            // Fallback to default scan types if API fails
            scanTypes.value = [
                { value: 'ec2', label: 'EC2' },
                { value: 'iam', label: 'IAM' },
                { value: 's3', label: 'S3' },
                { value: 'rds', label: 'RDS' },
            ];
        }
    };

    const fetchScanProfiles = async () => {
        try {
            const response = await axios.get('/api/scan-profiles');
            scanProfiles.value = response.data.scanProfiles;
        } catch (err: any) {
            console.error('Error fetching scan profiles:', err);
            // Fallback to the Basic profile if the API fails. The service list is
            // deliberately empty rather than hardcoded: the server derives it from the
            // tasks.json definitions, and a stale copy here would quietly mislead about
            // what a scan covers. The backend expands the profile either way.
            scanProfiles.value = [
                {
                    value: 'basic',
                    label: 'Basic',
                    description: 'Core security checks across all supported services',
                    services: [],
                    serviceRuleCounts: {},
                    ruleCount: 0,
                },
            ];
        }
    };

    const fetchScans = async (orgId?: string, filters?: {
        awsAccountId?: string;
        status?: string;
        scanType?: string;
        sortBy?: string;
        sortOrder?: 'asc' | 'desc';
        limit?: number;
        offset?: number;
    }) => {
        const orgIdToUse = orgId || currentOrganization.value?.org_id;
        if (!orgIdToUse) {
            error.value = 'No organization selected';
            return;
        }

        loading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams();
            if (filters?.awsAccountId) params.append('aws_account_id', filters.awsAccountId);
            if (filters?.status) params.append('status', filters.status);
            if (filters?.scanType) params.append('scan_type', filters.scanType);
            if (filters?.sortBy) params.append('sort_by', filters.sortBy);
            if (filters?.sortOrder) params.append('sort_order', filters.sortOrder);
            if (filters?.limit) params.append('limit', filters.limit.toString());
            if (filters?.offset) params.append('offset', filters.offset.toString());

            const queryString = params.toString();
            const url = `/api/organizations/${orgIdToUse}/scans${queryString ? `?${queryString}` : ''}`;
            const response = await axios.get(url);
            scans.value = response.data.scans;
            
            // Update pagination info
            pagination.value = {
                total: response.data.total || 0,
                limit: response.data.limit || 20,
                offset: response.data.offset || 0,
                currentPage: Math.floor((response.data.offset || 0) / (response.data.limit || 20)) + 1,
            };
        } catch (err: any) {
            error.value = err.response?.data?.message || err.response?.data?.error || 'Failed to load scans';
            console.error('Error fetching scans:', err);
            scans.value = [];
        } finally {
            loading.value = false;
        }
    };

    const createScan = async (
        orgId: string,
        awsAccountId: string,
        scanTypes: string[]
    ): Promise<Scan> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.post(`/api/organizations/${orgId}/scans`, {
                aws_account_id: awsAccountId,
                scan_types: scanTypes,
            });

            const newScan = response.data;
            scans.value.unshift(newScan);
            return newScan;
        } catch (err: any) {
            error.value = err.response?.data?.message || err.response?.data?.error || 'Failed to create scan';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Start a scan from one or more profiles. `services`, when given, narrows the scan to
     * that subset of the profiles' services — the server rejects any that the chosen
     * profiles have no rules for. Omit it to scan everything the profiles cover.
     */
    const createScanFromProfiles = async (
        orgId: string,
        awsAccountId: string,
        profiles: string[],
        services?: string[]
    ): Promise<Scan> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.post(`/api/organizations/${orgId}/scans`, {
                aws_account_id: awsAccountId,
                scan_profiles: profiles,
                ...(services && services.length ? { scan_types: services } : {}),
            });

            const newScan = response.data;
            scans.value.unshift(newScan);
            return newScan;
        } catch (err: any) {
            error.value = err.response?.data?.message || err.response?.data?.error || 'Failed to create scan';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const getScan = async (scanId: string): Promise<Scan> => {
        try {
            const response = await axios.get(`/api/scans/${scanId}`);
            return response.data;
        } catch (err: any) {
            throw new Error(err.response?.data?.message || err.response?.data?.error || 'Failed to fetch scan');
        }
    };

    const cancelScan = async (scanId: string): Promise<Scan> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.post(`/api/scans/${scanId}/cancel`);
            const updatedScan = response.data;

            // Update scan in list
            const index = scans.value.findIndex(s => s.id === scanId);
            if (index !== -1) {
                scans.value[index] = { ...scans.value[index], ...updatedScan };
            }

            return updatedScan;
        } catch (err: any) {
            error.value = err.response?.data?.message || err.response?.data?.error || 'Failed to cancel scan';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const startPolling = (scanId: string, intervalMs: number = 5000) => {
        // Stop existing polling for this scan if any
        stopPolling(scanId);

        const intervalId = window.setInterval(async () => {
            try {
                const scan = await getScan(scanId);
                
                // Update scan in list
                const index = scans.value.findIndex(s => s.id === scanId);
                if (index !== -1) {
                    scans.value[index] = { ...scans.value[index], ...scan };
                }

                // Stop polling if scan is no longer pending or running
                if (!['pending', 'running'].includes(scan.status)) {
                    stopPolling(scanId);
                }
            } catch (err) {
                console.error('Error polling scan status:', err);
                // Stop polling on error
                stopPolling(scanId);
            }
        }, intervalMs);

        pollingIntervals.value.set(scanId, intervalId);
    };

    const stopPolling = (scanId?: string) => {
        if (scanId) {
            // Stop polling for specific scan
            const intervalId = pollingIntervals.value.get(scanId);
            if (intervalId !== undefined) {
                clearInterval(intervalId);
                pollingIntervals.value.delete(scanId);
            }
        } else {
            // Stop all polling
            pollingIntervals.value.forEach((intervalId) => {
                clearInterval(intervalId);
            });
            pollingIntervals.value.clear();
        }
    };

    return {
        scans: computed(() => scans.value),
        scanTypes: computed(() => scanTypes.value),
        scanProfiles: computed(() => scanProfiles.value),
        pagination: computed(() => pagination.value),
        loading: computed(() => loading.value),
        error: computed(() => error.value),
        fetchScanTypes,
        fetchScanProfiles,
        fetchScans,
        createScan,
        createScanFromProfiles,
        getScan,
        cancelScan,
        startPolling,
        stopPolling,
    };
}
