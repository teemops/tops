import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useOrganizations } from './useOrganizations';

// Use the configured axios instance from bootstrap.ts
const axios = (window as any).axios;

export interface AwsAccount {
    id: string;
    name: string;
    awsAccountId?: string;
    status: 'pending' | 'completed' | 'error';
    lastScanAt?: string;
    createdAt: string;
    updatedAt?: string;
}

export interface InitAwsAccountResponse {
    accountId: string;
    uniqueId: string;
    externalId: string;
    cloudFormationUrl: string;
    status: string;
}

const accounts = ref<AwsAccount[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const pollingIntervals = ref<Map<string, number>>(new Map());

export function useAwsAccounts() {
    const { currentOrganization } = useOrganizations();

    const fetchAccounts = async (orgId?: string) => {
        const orgIdToUse = orgId || currentOrganization.value?.org_id;
        if (!orgIdToUse) {
            error.value = 'No organization selected';
            return;
        }

        loading.value = true;
        error.value = null;

        try {
            const response = await axios.get(`/api/organizations/${orgIdToUse}/aws-accounts`);
            accounts.value = response.data.accounts;
        } catch (err: any) {
            error.value = err.response?.data?.message || err.response?.data?.error || 'Failed to load AWS accounts';
            console.error('Error fetching AWS accounts:', err);
            accounts.value = [];
        } finally {
            loading.value = false;
        }
    };

    const initAccount = async (orgId?: string): Promise<InitAwsAccountResponse> => {
        const orgIdToUse = orgId || currentOrganization.value?.org_id;
        if (!orgIdToUse) {
            throw new Error('No organization selected');
        }

        loading.value = true;
        error.value = null;

        try {
            const response = await axios.post(`/api/organizations/${orgIdToUse}/aws-accounts/init`);
            const initData = response.data;
            
            // Add pending account to list
            accounts.value.unshift({
                id: initData.accountId,
                name: 'Pending AWS Account',
                status: 'pending',
                createdAt: new Date().toISOString(),
            });

            return initData;
        } catch (err: any) {
            error.value = err.response?.data?.message || err.response?.data?.error || 'Failed to initialize AWS account';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const createAccountManually = async (
        orgId: string,
        awsAccountId: string,
        iamRoleArn: string,
        name?: string
    ): Promise<AwsAccount> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.post(`/api/organizations/${orgId}/aws-accounts`, {
                aws_account_id: awsAccountId,
                iam_role_arn: iamRoleArn,
                name: name || `AWS Account ${awsAccountId}`,
            });

            const newAccount = response.data;
            accounts.value.unshift(newAccount);
            return newAccount;
        } catch (err: any) {
            error.value = err.response?.data?.message || err.response?.data?.error || 'Failed to create AWS account';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const updateAccount = async (accountId: string, name: string): Promise<AwsAccount> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.put(`/api/aws-accounts/${accountId}`, { name });
            const updatedAccount = response.data;

            const index = accounts.value.findIndex(acc => acc.id === accountId);
            if (index !== -1) {
                accounts.value[index] = { ...accounts.value[index], ...updatedAccount };
            }

            return updatedAccount;
        } catch (err: any) {
            error.value = err.response?.data?.message || err.response?.data?.error || 'Failed to update AWS account';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const deleteAccount = async (accountId: string): Promise<void> => {
        loading.value = true;
        error.value = null;

        try {
            await axios.delete(`/api/aws-accounts/${accountId}`);
            accounts.value = accounts.value.filter(acc => acc.id !== accountId);
        } catch (err: any) {
            error.value = err.response?.data?.message || err.response?.data?.error || 'Failed to delete AWS account';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const getAccount = async (accountId: string): Promise<AwsAccount> => {
        try {
            const response = await axios.get(`/api/aws-accounts/${accountId}`);
            return response.data;
        } catch (err: any) {
            throw new Error(err.response?.data?.message || err.response?.data?.error || 'Failed to fetch AWS account');
        }
    };

    const startPolling = (accountId: string, intervalMs: number = 5000) => {
        // Stop existing polling for this account if any
        stopPolling(accountId);

        const intervalId = window.setInterval(async () => {
            try {
                const account = await getAccount(accountId);
                
                // Update account in list
                const index = accounts.value.findIndex(acc => acc.id === accountId);
                if (index !== -1) {
                    accounts.value[index] = { ...accounts.value[index], ...account };
                }

                // Stop polling if account is no longer pending
                if (account.status !== 'pending') {
                    stopPolling(accountId);
                }
            } catch (err) {
                console.error('Error polling account status:', err);
                // Stop polling on error
                stopPolling(accountId);
            }
        }, intervalMs);

        pollingIntervals.value.set(accountId, intervalId);
    };

    const stopPolling = (accountId?: string) => {
        if (accountId) {
            // Stop polling for specific account
            const intervalId = pollingIntervals.value.get(accountId);
            if (intervalId !== undefined) {
                clearInterval(intervalId);
                pollingIntervals.value.delete(accountId);
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
        accounts: computed(() => accounts.value),
        loading: computed(() => loading.value),
        error: computed(() => error.value),
        fetchAccounts,
        initAccount,
        createAccountManually,
        updateAccount,
        deleteAccount,
        getAccount,
        startPolling,
        stopPolling,
    };
}
