import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { PageProps } from '@/types';

export function useFirebaseAuthEnabled() {
    const page = usePage<PageProps>();

    return computed(() => page.props.features?.firebase_auth ?? true);
}
