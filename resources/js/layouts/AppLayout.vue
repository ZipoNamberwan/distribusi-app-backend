<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { theme } from 'ant-design-vue'
import { computed, ref } from 'vue';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const isDark = ref(false)

const currentTheme = computed(() => ({
    algorithm: isDark.value
        ? theme.darkAlgorithm
        : theme.defaultAlgorithm
}))
</script>

<template>
    <a-config-provider :theme="currentTheme">
        <AppLayout :breadcrumbs="breadcrumbs">
            <slot />
        </AppLayout>
    </a-config-provider>

</template>
