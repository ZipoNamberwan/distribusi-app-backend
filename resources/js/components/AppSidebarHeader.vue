<script setup lang="ts">
import { usePage, router } from "@inertiajs/vue3";
import { LogOut, Moon, Sun, User, Download } from "lucide-vue-next";
import { ref, computed } from "vue";
import Breadcrumbs from "@/components/Breadcrumbs.vue";
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
} from "@/components/ui/dropdown-menu";
import { SidebarTrigger } from "@/components/ui/sidebar";
import { useAppearance } from "@/composables/useAppearance";
import { usePWAInstall } from "@/composables/usePWAInstall";
import { logout } from "@/routes";
import { index as logoutIndex } from "@/routes/sso/logout";
import type { BreadcrumbItem } from "@/types";

// Theme state (handled globally now)
const { appearance, updateAppearance } = useAppearance();
const isDark = computed(() => appearance.value === "dark");

const toggleTheme = () => {
  updateAppearance(isDark.value ? "light" : "dark");
};

const { isInstallable, promptInstall } = usePWAInstall();

const page = usePage();
const user = computed(() => page.props.auth.user);

const handleLogout = () => {
  window.location.href = logoutIndex().url; // Redirect to SSO logout route
};

withDefaults(
  defineProps<{
    breadcrumbs?: BreadcrumbItem[];
  }>(),
  {
    breadcrumbs: () => [],
  }
);
</script>

<template>
  <header
    class="sticky top-0 z-10 flex h-16 shrink-0 items-center gap-2 border-b border-border/50 bg-background/80 backdrop-blur-md px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
  >
    <div class="flex items-center gap-2 flex-1">
      <SidebarTrigger class="-ml-1" />
      <template v-if="breadcrumbs && breadcrumbs.length > 0">
        <Breadcrumbs :breadcrumbs="breadcrumbs" />
      </template>
    </div>
    <div class="flex items-center gap-2">
      <!-- Install App Button -->
      <button
        v-if="isInstallable"
        @click="promptInstall"
        class="flex items-center gap-1.5 px-3 py-1.5 mr-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors"
        aria-label="Install App"
      >
        <Download class="w-4 h-4" />
        <span class="hidden md:inline">Install App</span>
      </button>
      
      <!-- Theme Toggle Button -->
      <button
        class="p-2 mr-2 rounded-full hover:bg-sidebar-accent focus:outline-none transition-colors"
        @click="toggleTheme"
        :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
      >
        <Sun
          v-if="!isDark"
          class="w-5 h-5 text-neutral-600 hover:text-neutral-900 transition-colors"
        />
        <Moon
          v-else
          class="w-5 h-5 text-neutral-400 hover:text-white transition-colors"
        />
      </button>
      <!-- User/Logout Dropdown -->
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <button
            class="p-2 rounded hover:bg-sidebar-accent focus:outline-none flex items-center"
            aria-label="User menu"
          >
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
