<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useOrganizationMembers } from '@/composables/useOrganizationMembers';
import type { OrganizationMember } from '@/types/organization';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps<{
    modelValue: boolean;
    orgId: string;
    members: OrganizationMember[];
    currentUserId: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    transferred: [];
}>();

const { transferOwnership } = useOrganizationMembers();

const selectedUserId = ref<string>('');
const errors = ref<{ user_id?: string[] }>({});
const transferring = ref(false);

// Filter out current owner from selectable members
const selectableMembers = computed(() => {
    return props.members.filter(member => {
        // Exclude owner and current user
        return member.role !== 'owner' && member.user_id !== props.currentUserId;
    });
});

watch(() => props.modelValue, (newValue) => {
    if (newValue) {
        selectedUserId.value = '';
        errors.value = {};
    }
});

onMounted(() => {
    if (selectableMembers.value.length > 0) {
        selectedUserId.value = selectableMembers.value[0].user_id;
    }
});

const handleSubmit = async () => {
    errors.value = {};
    
    if (!selectedUserId.value) {
        errors.value = { user_id: ['Please select a new owner'] };
        return;
    }

    transferring.value = true;

    try {
        await transferOwnership(props.orgId, selectedUserId.value);
        emit('transferred');
        emit('update:modelValue', false);
        selectedUserId.value = '';
    } catch (err: any) {
        if (err.response?.data?.errors) {
            errors.value = err.response.data.errors;
        }
        // Error notification is handled by the composable
    } finally {
        transferring.value = false;
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
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                                    Transfer Ownership
                                </h3>
                                <div class="mt-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                        Transfer ownership of this organization to another team member. You will lose owner privileges and will need to be assigned a new role.
                                    </p>
                                    
                                    <div v-if="selectableMembers.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
                                        No other members available to transfer ownership to.
                                    </div>
                                    
                                    <div v-else>
                                        <InputLabel for="newOwner" value="Select new owner" />
                                        <select
                                            id="newOwner"
                                            v-model="selectedUserId"
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm"
                                            required
                                        >
                                            <option value="">-- Select a member --</option>
                                            <option
                                                v-for="member in selectableMembers"
                                                :key="member.user_id"
                                                :value="member.user_id"
                                            >
                                                {{ member.user.name }} ({{ member.user.email }}) - {{ member.role || 'No role' }}
                                            </option>
                                        </select>
                                        <InputError :message="errors.user_id?.[0]" class="mt-2" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <PrimaryButton
                            type="submit"
                            :disabled="transferring || selectableMembers.length === 0"
                            class="sm:ml-3 sm:w-auto sm:text-sm bg-red-600 hover:bg-red-700"
                        >
                            {{ transferring ? 'Transferring...' : 'Transfer Ownership' }}
                        </PrimaryButton>
                        <button
                            type="button"
                            @click="handleCancel"
                            :disabled="transferring"
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
