<script setup lang="ts">
import { onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
    }>(),
    {
        modelValue: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const input = ref<HTMLInputElement | null>(null);

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value?.focus();
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <input
        class="rounded-md border-gray-300 shadow-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
        :value="modelValue"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        ref="input"
    />
</template>
