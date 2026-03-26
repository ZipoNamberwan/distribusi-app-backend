<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { Button } from "@/components/ui/button";

defineProps<{
    error: string;
    description?: string;
}>();

const handleBackToLogin = () => {
    window.location.href = '/login';
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center p-4 bg-gray-50 dark:bg-gray-950">
        <Head :title="error" />

        <div class="max-w-md w-full bg-white dark:bg-gray-900 rounded-2xl shadow-xl p-8 text-center border border-gray-100 dark:border-gray-800 transition-all">
            <div class="w-16 h-16 bg-red-50 dark:bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg
                    class="w-8 h-8 text-red-500 dark:text-red-400"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                    />
                </svg>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-3 tracking-tight">
                {{ error }}
            </h1>

            <p v-if="description" class="text-sm text-gray-500 dark:text-gray-400 mb-8 leading-relaxed">
                {{ description }}
            </p>

            <Button
                type="button"
                class="w-full h-11 text-base font-semibold shadow-md transition-all rounded-lg"
                @click="handleBackToLogin"
            >
                Kembali ke Login
            </Button>
        </div>
    </div>
</template>
