<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NotificationContainer from '@/Components/NotificationContainer.vue';
import OrganizationSelector from '@/Components/OrganizationSelector.vue';
import DarkModeToggle from '@/Components/DarkModeToggle.vue';
import { useOrganizations } from '@/composables/useOrganizations';
import { useOrganizationPermissions } from '@/composables/useOrganizationPermissions';
import { useDarkMode } from '@/composables/useDarkMode';

const showingNavigationDropdown = ref(false);
const { fetchOrganizations, initCurrentOrganization } = useOrganizations();
const { canViewAwsAccounts, canViewScans, canViewFindings } = useOrganizationPermissions();
useDarkMode(); // Initialize dark mode

onMounted(async () => {
    // Initialize organization context on app load
    try {
        await fetchOrganizations();
        initCurrentOrganization();
    } catch (error) {
        // Don't block page rendering if organization fetch fails
        console.error('Failed to initialize organizations:', error);
    }
    
    // If no organizations exist after loading, the OrganizationSelector will handle prompting
    // to create one via its watcher
});

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div class="min-h-screen flex bg-gray-50 dark:bg-gray-900">
        <!-- Sidebar -->
        <aside class="hidden lg:flex lg:w-64 lg:flex-col lg:fixed lg:inset-y-0">
            <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700">
                <div class="flex items-center flex-shrink-0 px-4 mb-8">
                    <Link :href="route('dashboard')" class="flex items-center">
                        <span class="text-xl font-bold text-gray-900 dark:text-white">Teemops</span>
                    </Link>
                </div>
                <nav class="flex-1 px-3 space-y-1">
                    <Link
                        :href="route('dashboard')"
                        :class="[
                            route().current('dashboard')
                                ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400'
                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                            'group flex items-center px-3 py-2 text-sm font-medium rounded-md'
                        ]"
                    >
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        Dashboard
                    </Link>
                    <Link
                        :href="route('organizations.index')"
                        :class="[
                            route().current('organizations.*')
                                ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400'
                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                            'group flex items-center px-3 py-2 text-sm font-medium rounded-md'
                        ]"
                    >
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        Organizations
                    </Link>
                    <Link
                        v-if="canViewAwsAccounts"
                        :href="route('aws-accounts.index')"
                        :class="[
                            route().current('aws-accounts.*')
                                ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400'
                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                            'group flex items-center px-3 py-2 text-sm font-medium rounded-md'
                        ]"
                    >
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        AWS Accounts
                    </Link>
                    <Link
                        v-if="canViewScans"
                        :href="route('scans.index')"
                        :class="[
                            route().current('scans.*')
                                ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400'
                                : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                            'group flex items-center px-3 py-2 text-sm font-medium rounded-md'
                        ]"
                    >
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Scans
                    </Link>
                    <Link
                        v-if="canViewFindings"
                        href="#"
                        :class="[
                            'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 group flex items-center px-3 py-2 text-sm font-medium rounded-md'
                        ]"
                    >
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Findings
                    </Link>
                    <Link
                        v-if="canViewFindings"
                        href="#"
                        class="text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 group flex items-center px-3 py-2 text-sm font-medium rounded-md"
                    >
                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Insights
                    </Link>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="lg:pl-64 flex flex-col flex-1">
            <!-- Top Bar -->
            <header class="sticky top-0 z-10 flex-shrink-0 flex h-16 bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="flex-1 px-4 flex justify-between items-center">
                    <div class="flex-1 flex">
                        <div class="flex items-center">
                            <OrganizationSelector />
                        </div>
                    </div>
                    <div class="ml-4 flex items-center md:ml-6 space-x-4">
                        <DarkModeToggle />
                        <button class="bg-white dark:bg-gray-700 p-1 rounded-full text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                        </button>
                        <div class="relative">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <button class="flex items-center text-sm focus:outline-none">
                                        <span class="sr-only">Open user menu</span>
                                        <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center">
                                            <span class="text-white text-sm font-medium">JD</span>
                                        </div>
                                    </button>
                                </template>

                                <template #content>
                                    <DropdownLink :href="route('profile.edit')"> Profile </DropdownLink>
                                    <button
                                        @click="logout"
                                        class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-800 dark:focus:bg-gray-800"
                                    >
                                        Log Out
                                    </button>
                                </template>
                            </Dropdown>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 bg-gray-50 dark:bg-gray-900">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                        <slot />
                    </div>
                </div>
            </main>
        </div>

        <!-- Notifications -->
        <NotificationContainer />
    </div>
</template>

