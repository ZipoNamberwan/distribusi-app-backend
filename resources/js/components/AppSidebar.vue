<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, BookOpen, Database, FolderGit2, LayoutGrid, Upload, ChartBar, Rainbow } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as dataIndex } from '@/routes/data';
import { index as dataUpload } from '@/routes/upload';
import { index as indicatorValues } from '@/routes/indicator/table';
import { index as errorSummaries } from '@/routes/error_summaries/page';
import { index as enumerationPage } from '@/routes/enumeration/page';
import { index as predictionPage } from '@/routes/prediction/page';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Data',
        href: dataIndex(),
        icon: Database,
    },
    {
        title: 'Upload Data',
        href: dataUpload(),
        icon: Upload,
    },
    {
        title: 'Indikator',
        href: indicatorValues(),
        icon: LayoutGrid,
    },
    {
        title: 'Prediksi TPK',
        href: predictionPage(),
        icon: Rainbow,
    },
    {
        title: 'Rekap Error',
        href: errorSummaries(),
        icon: AlertTriangle,
    },
    {
        title: 'Progress Pencacahan',
        href: enumerationPage(),
        icon: ChartBar,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
