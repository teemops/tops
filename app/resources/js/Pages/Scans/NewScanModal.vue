<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue';
import { useScans, type ScanProfile } from '@/composables/useScans';
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
// Services explicitly ticked, per profile. A profile absent from this map — the default —
// means "everything this profile covers", which is the behaviour that existed before
// per-service selection and must stay the zero-click path.
const selectedServices = ref<Record<string, string[]>>({});
const expandedProfiles = ref<string[]>([]);
const creating = ref(false);
const errors = ref<{ aws_account_id?: string[]; scan_profiles?: string[]; scan_types?: string[] }>({});

const isExpanded = (profile: string) => expandedProfiles.value.includes(profile);

const toggleExpanded = (profile: string) => {
    expandedProfiles.value = isExpanded(profile)
        ? expandedProfiles.value.filter(p => p !== profile)
        : [...expandedProfiles.value, profile];
};

const isProfileSelected = (profile: string) => selectedScanProfiles.value.includes(profile);

/** Services ticked for a profile — all of them unless the user narrowed it. */
const servicesFor = (profile: ScanProfile): string[] =>
    selectedServices.value[profile.value] ?? profile.services;

const isServiceSelected = (profile: ScanProfile, service: string) =>
    isProfileSelected(profile.value) && servicesFor(profile).includes(service);

/** Ticked some but not all of a profile's services. */
const isPartial = (profile: ScanProfile) =>
    isProfileSelected(profile.value) &&
    servicesFor(profile).length > 0 &&
    servicesFor(profile).length < profile.services.length;

const toggleProfile = (profile: ScanProfile) => {
    if (isProfileSelected(profile.value)) {
        selectedScanProfiles.value = selectedScanProfiles.value.filter(p => p !== profile.value);
        delete selectedServices.value[profile.value];
    } else {
        selectedScanProfiles.value = [...selectedScanProfiles.value, profile.value];
    }
};

const toggleService = (profile: ScanProfile, service: string) => {
    // Ticking a service implies the profile is selected.
    if (!isProfileSelected(profile.value)) {
        selectedScanProfiles.value = [...selectedScanProfiles.value, profile.value];
        selectedServices.value = { ...selectedServices.value, [profile.value]: [service] };
        return;
    }

    const current = servicesFor(profile);
    const next = current.includes(service)
        ? current.filter(s => s !== service)
        : [...current, service];

    if (next.length === 0) {
        // Unticking the last service deselects the profile rather than submitting a
        // selection that cannot scan anything.
        selectedScanProfiles.value = selectedScanProfiles.value.filter(p => p !== profile.value);
        delete selectedServices.value[profile.value];
        return;
    }

    selectedServices.value = { ...selectedServices.value, [profile.value]: next };
};

/** The services to send, or undefined when nothing was narrowed. */
const narrowedServices = computed<string[] | undefined>(() => {
    const selected = scanProfiles.value.filter(p => isProfileSelected(p.value));
    const narrowed = selected.some(p => selectedServices.value[p.value] !== undefined);
    if (!narrowed) {
        return undefined;
    }

    return [...new Set(selected.flatMap(p => servicesFor(p)))];
});

/** "2 of 11 services · 12 rules" — what will actually run. */
const selectionSummary = computed(() => {
    const selected = scanProfiles.value.filter(p => isProfileSelected(p.value));
    if (selected.length === 0) {
        return null;
    }

    const services = new Set<string>();
    const available = new Set<string>();
    let rules = 0;

    for (const profile of selected) {
        profile.services.forEach(s => available.add(s));
        for (const service of servicesFor(profile)) {
            services.add(service);
            rules += profile.serviceRuleCounts?.[service] ?? 0;
        }
    }

    return {
        services: services.size,
        available: available.size,
        rules,
        groups: selected.map(p => p.label).join(' + '),
    };
});

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
        selectedServices.value = {};
        expandedProfiles.value = [];
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
            selectedScanProfiles.value,
            narrowedServices.value
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
                                        <InputLabel value="What to scan" />
                                        <div class="mt-2 rounded-md border border-gray-200 dark:border-gray-700 divide-y divide-gray-200 dark:divide-gray-700">
                                            <div v-for="profile in scanProfiles" :key="profile.value">
                                                <div class="flex items-center px-3 py-2.5">
                                                    <button
                                                        v-if="profile.services.length"
                                                        type="button"
                                                        @click="toggleExpanded(profile.value)"
                                                        :aria-expanded="isExpanded(profile.value)"
                                                        :aria-label="`${isExpanded(profile.value) ? 'Collapse' : 'Expand'} ${profile.label} services`"
                                                        class="mr-1.5 flex h-5 w-5 items-center justify-center rounded text-gray-400 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:hover:text-gray-200"
                                                    >
                                                        <span class="text-[10px]">{{ isExpanded(profile.value) ? '▼' : '▶' }}</span>
                                                    </button>
                                                    <span v-else class="mr-1.5 w-5"></span>

                                                    <input
                                                        type="checkbox"
                                                        :id="`profile-${profile.value}`"
                                                        :checked="isProfileSelected(profile.value)"
                                                        :indeterminate.prop="isPartial(profile)"
                                                        @change="toggleProfile(profile)"
                                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                                    />
                                                    <label :for="`profile-${profile.value}`" class="ml-2 flex-1 cursor-pointer">
                                                        <span class="block text-sm text-gray-700 dark:text-gray-300">{{ profile.label }}</span>
                                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ profile.description }}</span>
                                                    </label>
                                                    <span class="ml-2 whitespace-nowrap text-xs text-gray-400 dark:text-gray-500">
                                                        {{ profile.services.length }} services · {{ profile.ruleCount }} rules
                                                    </span>
                                                </div>

                                                <div v-if="isExpanded(profile.value)" class="bg-gray-50 dark:bg-gray-900/40 px-3 pb-2.5 pt-0.5">
                                                    <label
                                                        v-for="service in profile.services"
                                                        :key="service"
                                                        class="flex items-center py-1 pl-8 cursor-pointer"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            :checked="isServiceSelected(profile, service)"
                                                            @change="toggleService(profile, service)"
                                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                                        />
                                                        <span class="ml-2 flex-1 text-sm text-gray-700 dark:text-gray-300 uppercase">{{ service }}</span>
                                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ profile.serviceRuleCounts?.[service] ?? 0 }}</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <InputError :message="errors.scan_profiles?.[0]" class="mt-2" />
                                        <InputError :message="errors.scan_types?.[0]" class="mt-2" />

                                        <p v-if="selectionSummary" class="mt-2 rounded-md bg-gray-50 dark:bg-gray-900/40 px-3 py-2 text-xs text-gray-600 dark:text-gray-400">
                                            Scanning
                                            <span class="font-medium text-gray-900 dark:text-gray-200">{{ selectionSummary.services }} of {{ selectionSummary.available }} services</span>
                                            against
                                            <span class="font-medium text-gray-900 dark:text-gray-200">{{ selectionSummary.groups }}</span>
                                            — {{ selectionSummary.rules }} rules.
                                        </p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Pick a scan group, or expand it to scan only certain services. EC2 and RDS checks are processed per region.
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
