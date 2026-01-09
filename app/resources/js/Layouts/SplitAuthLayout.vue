<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex">
        <!-- Left Side: Branding -->
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-gray-900 to-gray-800 items-center justify-center p-12">
            <div class="max-w-md">
                <div class="mb-8">
                    <Link href="/">
                        <div class="h-12 w-auto mb-6 flex items-center">
                            <span class="text-2xl font-bold text-white">Teemops</span>
                        </div>
                    </Link>
                    <h1 class="text-4xl font-bold text-white mb-4">Simplify Cloud</h1>
                    <p class="text-xl text-gray-300">Cloud security made simple for busy CTOs</p>
                </div>
                <div class="space-y-4 text-gray-400">
                    <div class="flex items-start">
                        <svg class="h-6 w-6 mr-3 mt-0.5 text-orange-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <p class="font-medium text-white">Automated Security Scanning</p>
                            <p class="text-sm">Find misconfigurations in minutes, not days</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <svg class="h-6 w-6 mr-3 mt-0.5 text-orange-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <div>
                            <p class="font-medium text-white">One-Click Remediation</p>
                            <p class="text-sm">Fix issues with actionable playbooks</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <svg class="h-6 w-6 mr-3 mt-0.5 text-orange-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <div>
                            <p class="font-medium text-white">Enterprise-Grade Security</p>
                            <p class="text-sm">Built for compliance and peace of mind</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Form Content -->
        <div class="flex-1 flex items-center justify-center p-8 lg:p-12 bg-gray-50 dark:bg-gray-900">
            <div class="w-full max-w-md">
                <!-- Mobile Logo -->
                <div class="lg:hidden mb-8 text-center">
                    <Link href="/">
                        <div class="h-10 w-auto mx-auto mb-4 flex items-center justify-center">
                            <span class="text-xl font-bold text-gray-900 dark:text-white">Teemops</span>
                        </div>
                    </Link>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        <slot name="mobile-title">Welcome</slot>
                    </h1>
                </div>

                <!-- Desktop Heading -->
                <div class="hidden lg:block mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        <slot name="title">Welcome</slot>
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400">
                        <slot name="subtitle">Sign in to your account to continue</slot>
                    </p>
                </div>

                <!-- Form Card -->
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-8 shadow-sm">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

