<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

type NavGroup = {
    label: string
    items: NavItem[]
}

type SidebarEntry = NavItem | NavGroup

defineProps<{
    sidebarItems: SidebarEntry[];
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <template v-for="entry in sidebarItems" :key="entry.label ?? entry.title">
        <SidebarGroup v-if="'items' in entry" class="px-2 py-0">
            <SidebarGroupLabel>{{ entry.label }}</SidebarGroupLabel>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in entry.items" :key="item.title">
                    <SidebarMenuButton as-child :is-active="isCurrentUrl(item.href)" :tooltip="item.title">
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>

        <!-- SINGLE ITEM -->
        <SidebarMenuItem class="px-2 py-0" v-else>
            <SidebarMenuButton as-child :is-active="isCurrentUrl(entry.href)" :tooltip="entry.title">
                <Link :href="entry.href">
                    <component v-if="entry.icon" :is="entry.icon" />
                    <span>{{ entry.title }}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </template>

</template>
