<script setup lang="ts">
import { computed } from 'vue';
import { useDarkMode } from '@/composables/useDarkMode';

/**
 * The TOPS logo.
 *
 * Pass `variant="reversed"` for surfaces that are dark whatever the theme is,
 * like the branding panel in SplitAuthLayout. Everything else gets `auto`,
 * which follows the theme.
 *
 * No flash on load: bootstrap.ts sets the `dark` class before the app renders,
 * and useDarkMode's `isDark` is module-scope and read from localStorage
 * synchronously, so the first paint already has the right variant.
 */
const props = withDefaults(
    defineProps<{
        variant?: 'auto' | 'reversed';
    }>(),
    { variant: 'auto' },
);

const { isDark } = useDarkMode();

const src = computed(() =>
    props.variant === 'reversed' || isDark.value
        ? '/images/brand/tops-logo-reversed.png'
        : '/images/brand/tops-logo.png',
);
</script>

<template>
    <!-- Both files share one canvas (see design/brand/build-app-logos.py), so
         swapping between them cannot shift the layout. Sizing is the caller's:
         set a height and leave the width auto. -->
    <img :src="src" alt="TOPS" />
</template>
