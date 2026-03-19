<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col md:flex-row bg-background">
        <!-- Left Side: Beautiful Blue Gradient -->
        <div class="hidden md:flex flex-1 flex-col justify-between bg-gradient-to-br from-blue-900 via-blue-700 to-blue-500 p-10 text-white relative overflow-hidden">
            <!-- Decorative elements -->
            <div class="absolute -left-20 -top-20 h-[500px] w-[500px] rounded-full bg-blue-500/30 blur-3xl"></div>
            <div class="absolute -right-20 -bottom-20 h-[500px] w-[500px] rounded-full bg-blue-300/20 blur-3xl"></div>
            
            <div class="relative z-10 flex items-center gap-3 font-bold text-2xl tracking-tight">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20 backdrop-blur-md shadow-lg border border-white/20">
                    <AppLogoIcon class="size-7 text-white" />
                </div>
                Distribusi App
            </div>

            <div class="relative z-10 space-y-6 max-w-lg mb-20 animate-in fade-in slide-in-from-bottom-8 duration-700">
                <h2 class="text-4xl md:text-5xl font-extrabold tracking-tight leading-tight">Welcome Back.</h2>
                <p class="text-blue-100 text-lg leading-relaxed">
                    Manage your analytics, track progress, and seamlessly handle all data distributions with elegance and ease.
                </p>
            </div>
            
            <div class="relative z-10 text-sm text-blue-200 font-medium">
                &copy; {{ new Date().getFullYear() }} Distribusi App. All rights reserved.
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="flex flex-1 items-center justify-center p-6 md:p-10 relative">
            <div class="absolute inset-0 bg-slate-50/50 dark:bg-slate-950/50 pointer-events-none"></div>
            
            <div class="w-full max-w-md relative z-10 animate-in fade-in slide-in-from-bottom-4 duration-500 delay-150">
                <div class="flex flex-col gap-8">
                    <div class="flex flex-col items-center gap-4 text-center md:hidden">
                        <Link
                            :href="home()"
                            class="flex flex-col items-center gap-3 font-medium"
                        >
                            <div
                                class="mb-1 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-600 shadow-xl shadow-blue-500/30"
                            >
                                <AppLogoIcon
                                    class="size-8 text-white"
                                />
                            </div>
                            <span class="text-xl font-bold tracking-tight">Distribusi App</span>
                        </Link>
                    </div>
                    
                    <div class="space-y-3 text-center md:text-left pt-4">
                        <h1 class="text-3xl font-bold tracking-tight text-foreground">{{ title }}</h1>
                        <p class="text-base text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                    
                    <div class="bg-card/80 shadow-2xl shadow-blue-900/5 ring-1 ring-border/50 rounded-3xl p-6 sm:p-10 backdrop-blur-xl">
                        <slot />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
