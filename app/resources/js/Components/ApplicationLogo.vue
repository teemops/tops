<template>
    <img
        :src="logoSrc"
        alt="Teem Logo"
        class="h-9 w-auto block"
    />
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useDarkMode } from '@/composables/useDarkMode';

const { isDark } = useDarkMode();

const logoSrc = computed(() =>
    isDark.value ? '/images/brand/tops-logo-darkmode.png' : '/images/brand/tops-logo-lightmode.jpg'
);
</script>
