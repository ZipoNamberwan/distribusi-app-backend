<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle, BookOpen, Database, FolderGit2, LayoutGrid, Upload, ChartBar, Rainbow, User, Bolt, Columns4 } from 'lucide-vue-next';
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
import { index as confirmationPage } from '@/routes/confirmation/page';
import { index as dataIndex } from '@/routes/data';
import { index as dashboard } from '@/routes/data/dashboard';
import { index as enumerationPage } from '@/routes/enumeration/page';
import { index as errorSummaries } from '@/routes/error_summaries/page';
import { index as indicatorValues } from '@/routes/indicator/table';
import { index as phenomenaPage } from '@/routes/phenomena/page';
import { index as predictionPage } from '@/routes/prediction/page';
import { index as dataUpload } from '@/routes/upload';
import { index as userPage } from '@/routes/user/page';

const page = usePage();
const roles = page.props.auth.roles;

const isAdminProv = roles.includes('adminprov');

const sidebarItems = [
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
    {
        label: 'Konfirmasi',
        items: [
            {
                title: 'Konfirmasi Error',
                href: confirmationPage(),
                icon: Bolt,
            },
            {
                title: 'Fenomena',
                href: phenomenaPage(),
                icon: Columns4,
            },
        ],
    },
    ...(isAdminProv ? [
        {
            label: 'Admin',
            items: [
                {
                    title: 'Upload Data',
                    href: dataUpload(),
                    icon: Upload,
                },
                {
                    title: 'Manajemen User',
                    href: userPage(),
                    icon: User,
                },
            ],
        },
    ] : []),
];

const footerNavItems = [
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
            <NavMain :sidebarItems="sidebarItems" />
        </SidebarContent>

        <SidebarFooter>
            <!-- <NavFooter :items="footerNavItems" /> -->
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>