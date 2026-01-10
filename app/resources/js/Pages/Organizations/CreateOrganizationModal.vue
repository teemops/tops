<script setup lang="ts">
import { ref, watch } from 'vue';
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

const { createOrganization, switchOrganization } = useOrganizations();
const { showSuccess, showError } = useNotifications();

const name = ref('');
const errors = ref<{ name?: string[] }>({});
const creating = ref(false);

watch(() => props.modelValue, (newValue) => {
    if (newValue) {
        name.value = '';
        errors.value = {};
    }
});

const handleSubmit = async () => {
    errors.value = {};
    creating.value = true;

    try {
        const org = await createOrganization(name.value);
        showSuccess(`Organization "${org.name}" created successfully`);
        emit('created');
        emit('update:modelValue', false);
        
        // Switch to the new organization
        await switchOrganization(org.org_id);
    } catch (err: any) {
        if (err.response?.data?.errors) {
            errors.value = err.response.data.errors;
        } else {
            showError(err.response?.data?.message || 'Failed to create organization');
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
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                                    Create Organization
                                </h3>
                                <div class="mt-4">
                                    <InputLabel for="name" value="Organization name" />
                                    <TextInput
                                        id="name"
                                        v-model="name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        required
                                        autofocus
                                        placeholder="My Organization"
                                    />
                                    <InputError :message="errors.name?.[0]" class="mt-2" />
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        Choose a name to identify this organization. You can change it later.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <PrimaryButton
                            type="submit"
                            :disabled="creating"
                            class="sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            {{ creating ? 'Creating...' : 'Create' }}
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

