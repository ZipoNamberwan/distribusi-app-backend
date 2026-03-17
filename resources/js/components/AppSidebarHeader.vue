<script setup lang="ts">

import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { LogOut, Moon, Sun, User } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';

// Theme state (sync with AppLayout)
const isDark = ref(false);
const toggleTheme = () => {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
    // Optionally emit or use a global store for theme
};

const page = usePage();
const user = computed(() => page.props.auth.user);
const handleLogout = () => {
    router.flushAll();
};

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2 flex-1">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="flex items-center gap-2">
            <!-- Theme Toggle Button -->
            <!-- <button
                class="p-2 rounded hover:bg-sidebar-accent focus:outline-none"
                @click="toggleTheme"
                :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
            >
                <Sun v-if="!isDark" class="w-5 h-5" />
                <Moon v-else class="w-5 h-5" />
            </button> -->
            <!-- User/Logout Dropdown -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button class="p-2 rounded hover:bg-sidebar-accent focus:outline-none flex items-center" aria-label="User menu">
                        <User class="w-5 h-5" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="min-w-40">
                    <div class="px-3 py-2">
                        <div class="font-medium text-sm">{{ user?.name }}</div>
                        <div class="text-xs text-muted-foreground truncate">{{ user?.email }}</div>
                    </div>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <button @click="handleLogout" class="flex items-center w-full text-left">
                            <LogOut class="mr-2 w-4 h-4" />
                            Log out
                        </button>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
