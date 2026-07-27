<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue';
import { useScans } from '@/composables/useScans';
import { useAwsAccounts } from '@/composables/useAwsAccounts';
import { useOrganizations } from '@/composables/useOrganizations';
import { useNotifications } from '@/composables/useNotifications';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps<{
    modelValue: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    created: [];
}>();

const { createScanFromProfiles, scanProfiles, fetchScanProfiles } = useScans();
const { accounts } = useAwsAccounts();
const { currentOrganization } = useOrganizations();
const { showSuccess, showError } = useNotifications();

const selectedAwsAccountId = ref('');
const selectedScanProfiles = ref<string[]>([]);
const creating = ref(false);
const errors = ref<{ aws_account_id?: string[]; scan_profiles?: string[] }>({});

const availableAccounts = computed(() => {
    return accounts.value.filter(acc => acc.status === 'completed');
});

// Fetch scan profiles (groups) on component mount
onMounted(() => {
    fetchScanProfiles();
});

watch(() => props.modelValue, (newValue) => {
    if (newValue) {
        selectedAwsAccountId.value = '';
        selectedScanProfiles.value = [];
        errors.value = {};
    }
});

const handleSubmit = async () => {
    if (!currentOrganization.value?.org_id) {
        showError('No organization selected');
        return;
    }

    if (!selectedAwsAccountId.value) {
        errors.value = { aws_account_id: ['Please select an AWS account'] };
        return;
    }

    if (selectedScanProfiles.value.length === 0) {
        errors.value = { scan_profiles: ['Please select at least one scan group'] };
        return;
    }

    creating.value = true;
    errors.value = {};

    try {
        await createScanFromProfiles(
            currentOrganization.value.org_id,
            selectedAwsAccountId.value,
            selectedScanProfiles.value
        );
        
        showSuccess('Scan started successfully');
        emit('created');
        emit('update:modelValue', false);
    } catch (err: any) {
        if (err.response?.data?.errors) {
            errors.value = err.response.data.errors;
        } else {
            showError(err.response?.data?.message || err.response?.data?.error || 'Failed to create scan');
        }
    } finally {
        creating.value = false;
    }
};

const handleCancel = () => {
    emit('update:modelValue', false);
};
</script>

<template>
    <div
        v-if="modelValue"
        class="fixed z-10 inset-0 overflow-y-auto"
        @click.self="handleCancel"
    >
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form @submit.prevent="handleSubmit">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                                    Start New Scan
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                                    Select an AWS account and scan group to begin a security scan.
                                </p>

                                <div class="space-y-4">
                                    <div>
                                        <InputLabel for="aws_account" value="AWS Account" />
                                        <select
                                            id="aws_account"
                                            v-model="selectedAwsAccountId"
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-white text-sm"
                                            required
                                        >
                                            <option value="">Select an AWS account</option>
                                            <option
                                                v-for="account in availableAccounts"
                                                :key="account.id"
                                                :value="account.id"
                                            >
                                                {{ account.name }} ({{ account.awsAccountId }})
                                            </option>
                                        </select>
                                        <InputError :message="errors.aws_account_id?.[0]" class="mt-2" />
                                        <p v-if="availableAccounts.length === 0" class="mt-1 text-xs text-yellow-600 dark:text-yellow-400">
                                            No active AWS accounts available. Please add an AWS account first.
                                        </p>
                                    </div>

                                    <div>
                                        <InputLabel value="Scan Groups" />
                                        <div class="mt-2 space-y-2">
                                            <label
                                                v-for="profile in scanProfiles"
                                                :key="profile.value"
                                                class="flex items-start"
                                            >
                                                <input
                                                    type="checkbox"
                                                    v-model="selectedScanProfiles"
                                                    :value="profile.value"
                                                    class="mt-0.5 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                                />
                                                <span class="ml-2">
                                                    <span class="block text-sm text-gray-700 dark:text-gray-300">{{ profile.label }}</span>
                                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ profile.description }}</span>
                                                </span>
                                            </label>
                                        </div>
                                        <InputError :message="errors.scan_profiles?.[0]" class="mt-2" />
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Select one or more scan groups. EC2 and RDS checks are processed per region.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <PrimaryButton
                            type="submit"
                            :disabled="creating || availableAccounts.length === 0 || selectedScanProfiles.length === 0"
                            class="sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            {{ creating ? 'Starting...' : 'Start Scan' }}
                        </PrimaryButton>
                        <button
                            type="button"
                            @click="handleCancel"
                            :disabled="creating"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
