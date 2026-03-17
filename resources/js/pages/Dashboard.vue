<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as dashboard } from '@/routes/data/dashboard';
import { Card, Statistic } from 'ant-design-vue';
import {
    EnvironmentOutlined,
    BarChartOutlined,
    WarningOutlined,
} from '@ant-design/icons-vue';
import { computed } from 'vue';

const breadcrumbs = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

// ✅ Props (JavaScript version)
const props = defineProps({
    period: { type: Object, default: null },
    tpk: { type: Number, default: 0 },
    enumeration: { type: Number, default: 0 },
    error: { type: Number, default: 0 },
});

// ================= SAFE PERIOD =================
const safePeriod = computed(() => {
    if (!props.period || !props.period.month || !props.period.year) {
        return 'No data';
    }
    return `${props.period.month.name} ${props.period.year.name}`;
});

// ================= SAFE DATA =================
const tpkData = computed(() => ({
    value: props.tpk ?? 0,
    period: safePeriod.value,
}));

const enumerationData = computed(() => ({
    value: props.enumeration ?? 0,
    period: safePeriod.value,
}));

const errorData = computed(() => ({
    value: props.error ?? 0,
    period: safePeriod.value,
}));

// Optional formatter (for UI display)
const formatValue = (val) => {
    return val === null || val === undefined ? '-' : val;
};

// Dummy bar data
const barData = [
    { label: 'Kecamatan A', value: 85 },
    { label: 'Kecamatan B', value: 72 },
    { label: 'Kecamatan C', value: 68 },
    { label: 'Kecamatan D', value: 61 },
    { label: 'Kecamatan E', value: 54 },
    { label: 'Kecamatan F', value: 49 },
    { label: 'Kecamatan G', value: 43 },
];

const maxBar = Math.max(...barData.map((d) => d.value));
</script>

<template>

    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 md:p-6 h-full overflow-x-auto">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                        Dashboard Meniko Jatim
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Gambaran umum pencacahan dan kualitas data Survei VHTS
                    </p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">

                <!-- TPK -->
                <Card class="!rounded-2xl shadow-sm border bg-white dark:bg-gray-800">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                            <BarChartOutlined class="text-blue-500" />
                        </div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            Prediksi TPK
                        </span>
                    </div>

                    <Statistic :value="formatValue(tpkData.value)" suffix="%"
                        :value-style="{ fontSize: '2rem', fontWeight: '700' }" />

                    <p class="mt-2 text-xs text-gray-400">
                        Periode: {{ tpkData.period }}
                    </p>
                </Card>

                <!-- Enumeration -->
                <Card class="!rounded-2xl shadow-sm border bg-white dark:bg-gray-800">
                    <div class="flex items-center gap-2 mb-3">
                        <div
                            class="w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center">
                            <EnvironmentOutlined class="text-violet-500" />
                        </div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            Progress Pencacahan
                        </span>
                    </div>

                    <Statistic :value="formatValue(enumerationData.value)" suffix="%"
                        :value-style="{ fontSize: '2rem', fontWeight: '700' }" />

                    <p class="mt-2 text-xs text-gray-400">
                        Periode: {{ enumerationData.period }}
                    </p>
                </Card>

                <!-- Error -->
                <Card class="!rounded-2xl shadow-sm border bg-white dark:bg-gray-800">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-9 h-9 rounded-xl bg-red-50 dark:bg-red-900/30 flex items-center justify-center">
                            <WarningOutlined class="text-red-500" />
                        </div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            Total Error
                        </span>
                    </div>

                    <Statistic :value="formatValue(errorData.value)"
                        :value-style="{ fontSize: '2rem', fontWeight: '700' }" />

                    <p class="mt-2 text-xs text-gray-400">
                        Periode: {{ errorData.period }}
                    </p>
                </Card>

            </div>

            <!-- Map Section -->
            <Card class="!rounded-2xl shadow-sm border bg-white dark:bg-gray-800">
                <template #title>
                    <div class="flex items-center gap-2">
                        <EnvironmentOutlined class="text-blue-500" />
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Persentase Pencacahan per Kabupaten/Kota
                        </span>
                    </div>
                </template>

                <div
                    class="h-72 md:h-96 flex items-center justify-center border-dashed border rounded-xl text-gray-400">
                    Komponen peta akan ditampilkan di sini
                </div>
            </Card>

        </div>
    </AppLayout>
</template>