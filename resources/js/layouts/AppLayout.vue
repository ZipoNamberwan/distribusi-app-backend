<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { theme } from 'ant-design-vue';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { resolvedAppearance } = useAppearance();

const currentTheme = computed(() => ({
    algorithm: resolvedAppearance.value === 'dark'
        ? theme.darkAlgorithm
        : theme.defaultAlgorithm,
    token: {
        colorPrimary: '#2563eb', // Beautiful blue primary
        borderRadius: 8,
    }
}));
</script>

<template>
    <a-config-provider :theme="currentTheme">
        <AppLayout :breadcrumbs="breadcrumbs">
            <slot />
        </AppLayout>
    </a-config-provider>

</template>
