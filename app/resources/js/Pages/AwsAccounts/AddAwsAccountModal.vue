<script setup lang="ts">
import { ref, watch, computed } from 'vue';
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

const { initAccount, createAccountManually } = useAwsAccounts();
const { currentOrganization } = useOrganizations();
const { showSuccess, showError } = useNotifications();

const step = ref<'cloudformation' | 'manual'>('cloudformation');
const cloudFormationUrl = ref<string | null>(null);
const pendingAccountId = ref<string | null>(null);
const initializing = ref(false);
const creating = ref(false);

// Manual entry form
const awsAccountId = ref('');
const iamRoleArn = ref('');
const accountName = ref('');
const errors = ref<{ aws_account_id?: string[]; iam_role_arn?: string[]; name?: string[] }>({});

watch(() => props.modelValue, (newValue) => {
    if (newValue) {
        step.value = 'cloudformation';
        cloudFormationUrl.value = null;
        pendingAccountId.value = null;
        awsAccountId.value = '';
        iamRoleArn.value = '';
        accountName.value = '';
        errors.value = {};
    }
});

const handleInitCloudFormation = async () => {
    if (!currentOrganization.value?.org_id) {
        showError('No organization selected');
        return;
    }

    initializing.value = true;
    errors.value = {};

    try {
        const response = await initAccount();
        cloudFormationUrl.value = response.cloudFormationUrl;
        pendingAccountId.value = response.accountId;
        
        // Open CloudFormation URL in new window
        window.open(response.cloudFormationUrl, '_blank');
        
        showSuccess('CloudFormation setup initiated. Complete the stack in AWS Console.');
    } catch (err: any) {
        if (err.response?.data?.errors) {
            errors.value = err.response.data.errors;
        } else {
            showError(err.response?.data?.message || err.response?.data?.error || 'Failed to initialize AWS account');
        }
    } finally {
        initializing.value = false;
    }
};

const handleManualEntry = () => {
    step.value = 'manual';
    cloudFormationUrl.value = null;
};

const handleBackToCloudFormation = () => {
    step.value = 'cloudformation';
    errors.value = {};
};

const handleSubmitManual = async () => {
    if (!currentOrganization.value?.org_id) {
        showError('No organization selected');
        return;
    }

    creating.value = true;
    errors.value = {};

    try {
        await createAccountManually(
            currentOrganization.value.org_id,
            awsAccountId.value.trim(),
            iamRoleArn.value.trim(),
            accountName.value.trim() || undefined
        );
        
        showSuccess('AWS account added successfully');
        emit('created');
        emit('update:modelValue', false);
    } catch (err: any) {
        if (err.response?.data?.errors) {
            errors.value = err.response.data.errors;
        } else {
            showError(err.response?.data?.message || err.response?.data?.error || 'Failed to create AWS account');
        }
    } finally {
        creating.value = false;
    }
};

const handleCancel = () => {
    emit('update:modelValue', false);
};

const canSubmitManual = computed(() => {
    return awsAccountId.value.trim().length === 12 && 
           iamRoleArn.value.trim().startsWith('arn:aws:iam::');
});
</script>

<template>
    <div
        v-if="modelValue"
        class="fixed z-10 inset-0 overflow-y-auto"
        @click.self="handleCancel"
    >
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <!-- CloudFormation Step -->
                <div v-if="step === 'cloudformation'">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                                    Add AWS Account
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                                    To securely access your AWS account, we need to create an IAM role using CloudFormation.
                                </p>

                                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                                    <h4 class="text-sm font-medium text-blue-900 dark:text-blue-200 mb-2">Instructions:</h4>
                                    <ol class="list-decimal list-inside text-sm text-blue-800 dark:text-blue-300 space-y-1">
                                        <li>Click "Open AWS Console" below</li>
                                        <li>Complete the CloudFormation stack in your AWS Console</li>
                                        <li>The account will be added automatically</li>
                                    </ol>
                                </div>

                                <div v-if="cloudFormationUrl" class="mb-4">
                                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                            <strong>Status:</strong> 🟡 Waiting for CloudFormation completion...
                                        </p>
                                        <p class="text-xs text-yellow-700 dark:text-yellow-300 mt-2">
                                            The CloudFormation stack should open in a new window. Complete it to finish adding your account.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <PrimaryButton
                            v-if="!cloudFormationUrl"
                            @click="handleInitCloudFormation"
                            :disabled="initializing"
                            class="sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            {{ initializing ? 'Initializing...' : 'Open AWS Console' }}
                        </PrimaryButton>
                        <button
                            v-if="cloudFormationUrl"
                            @click="handleCancel"
                            class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Close
                        </button>
                        <button
                            v-if="!cloudFormationUrl"
                            type="button"
                            @click="handleManualEntry"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Enter Details Manually
                        </button>
                        <button
                            v-if="!cloudFormationUrl"
                            type="button"
                            @click="handleCancel"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Cancel
                        </button>
                    </div>
                </div>

                <!-- Manual Entry Step -->
                <form v-else @submit.prevent="handleSubmitManual">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                                    Enter AWS Account Details
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                                    If you've already created the IAM role via CloudFormation, enter the details below.
                                </p>

                                <div class="space-y-4">
                                    <div>
                                        <InputLabel for="aws_account_id" value="AWS Account ID" />
                                        <TextInput
                                            id="aws_account_id"
                                            v-model="awsAccountId"
                                            type="text"
                                            class="mt-1 block w-full"
                                            placeholder="123456789012"
                                            required
                                            pattern="[0-9]{12}"
                                            maxlength="12"
                                        />
                                        <InputError :message="errors.aws_account_id?.[0]" class="mt-2" />
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            12-digit AWS account ID
                                        </p>
                                    </div>

                                    <div>
                                        <InputLabel for="iam_role_arn" value="IAM Role ARN" />
                                        <TextInput
                                            id="iam_role_arn"
                                            v-model="iamRoleArn"
                                            type="text"
                                            class="mt-1 block w-full"
                                            placeholder="arn:aws:iam::123456789012:role/TeemOps"
                                            required
                                        />
                                        <InputError :message="errors.iam_role_arn?.[0]" class="mt-2" />
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            The IAM Role ARN created by CloudFormation
                                        </p>
                                    </div>

                                    <div>
                                        <InputLabel for="account_name" value="Account Name (optional)" />
                                        <TextInput
                                            id="account_name"
                                            v-model="accountName"
                                            type="text"
                                            class="mt-1 block w-full"
                                            placeholder="Production AWS"
                                        />
                                        <InputError :message="errors.name?.[0]" class="mt-2" />
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            A friendly name to identify this account
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <PrimaryButton
                            type="submit"
                            :disabled="creating || !canSubmitManual"
                            class="sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            {{ creating ? 'Adding...' : 'Add Account' }}
                        </PrimaryButton>
                        <button
                            type="button"
                            @click="handleBackToCloudFormation"
                            :disabled="creating"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                        >
                            Back
                        </button>
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
