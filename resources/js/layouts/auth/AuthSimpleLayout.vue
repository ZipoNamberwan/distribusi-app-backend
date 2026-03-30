<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Moon, Sun } from "lucide-vue-next";
import { computed } from "vue";
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import BubbleAnimation from '@/components/BubbleAnimation.vue';
import { useAppearance } from "@/composables/useAppearance";
import { home } from '@/routes';

const { appearance, updateAppearance } = useAppearance();
const isDark = computed(() => appearance.value === "dark");

const toggleTheme = () => {
  updateAppearance(isDark.value ? "light" : "dark");
};

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
            <div class="absolute -left-20 -top-20 h-[500px] w-[500px] rounded-full bg-blue-500/30 blur-3xl z-0"></div>
            <div class="absolute -right-20 -bottom-20 h-[500px] w-[500px] rounded-full bg-blue-300/20 blur-3xl z-0"></div>
            <BubbleAnimation :count="12" class="z-0" />
            
            <div class="relative z-10 flex items-center gap-3 font-bold text-2xl tracking-tight">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20 backdrop-blur-md shadow-lg border border-white/20">
                    <AppLogoIcon class="size-12 text-white" />
                </div>
                MENIKO JATIM
            </div>

            <div class="relative z-10 space-y-6 max-w-lg mb-20 animate-in fade-in slide-in-from-bottom-8 duration-700">
                <h2 class="text-4xl md:text-5xl font-extrabold tracking-tight leading-tight">Selamat Datang di MENIKO JATIM</h2>
                <p class="text-blue-100 text-lg leading-relaxed">
                    Monitoring Kualitas Data Survei Penyedia Jasa Akomodasi Bulanan Jawa Timur.
                </p>
            </div>
            
            <div class="relative z-10 text-sm text-blue-200 font-medium">
                &copy; {{ new Date().getFullYear() }} MENIKO JATIM. All rights reserved.
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="flex flex-1 items-center justify-center p-6 md:p-10 relative">
            <div class="absolute inset-0 bg-slate-50/50 dark:bg-slate-950/50 pointer-events-none"></div>
            
            <!-- Header Bar (Logo + Theme Toggle) -->
            <div class="absolute top-6 left-6 right-6 md:top-8 md:left-8 md:right-8 z-20 flex items-center justify-between">
                <!-- Organization Logo and Text -->
                <div class="flex items-center gap-2">
                    <img src="/images/bps.svg" alt="BPS Logo" class="h-8 md:h-10 w-auto object-contain" />
                    <div class="flex flex-col justify-center text-foreground font-[Arial] italic font-bold leading-tight">
                        <span class="text-[10px] md:text-[12px] text-blue-900 dark:text-blue-100 uppercase">BADAN PUSAT STATISTIK</span>
                        <span class="text-[10px] md:text-[12px] text-blue-900 dark:text-blue-100 uppercase tracking-wider md:tracking-widest mt-0.5 md:mt-0">PROVINSI JAWA TIMUR</span>
                    </div>
                </div>

                <!-- Theme Toggle Button -->
                <button
                    class="p-2 rounded-full hover:bg-black/5 dark:hover:bg-white/10 focus:outline-none transition-colors"
                    @click="toggleTheme"
                    :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                >
                    <Sun
                    v-if="!isDark"
                    class="w-5 h-5 md:w-6 md:h-6 text-neutral-600 hover:text-neutral-900 transition-colors"
                    />
                    <Moon
                    v-else
                    class="w-5 h-5 md:w-6 md:h-6 text-neutral-400 hover:text-white transition-colors"
                    />
                </button>
            </div>

            <div class="w-full max-w-md relative z-10 animate-in fade-in slide-in-from-bottom-4 duration-500 delay-150">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col items-center gap-4 text-center md:hidden">
                        <Link
                            :href="home()"
                            class="flex flex-col items-center gap-3 font-medium"
                        >
                            <div
                                class="mb-1 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-600 shadow-xl shadow-blue-500/30"
                            >
                                <AppLogoIcon
                                    class="size-12 text-white"
                                />
                            </div>
                            <span class="text-xl font-bold tracking-tight">MENIKO JATIM</span>
                        </Link>
                    </div>
                    
                    <div class="space-y-3 text-center flex flex-col items-center pt-8">
                        <h1 class="text-3xl font-bold tracking-tight text-foreground text-center">{{ title }}</h1>
                        <p class="text-base text-muted-foreground text-center max-w-sm">
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
