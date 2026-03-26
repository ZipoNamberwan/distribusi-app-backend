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
import { computed, onMounted } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { index as indexMap } from "@/routes/data/map";

const breadcrumbs = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

const props = defineProps({
    period: { type: Object, default: null },
    selectedRegency: { type: Object, default: null },
    tpk: { type: Number, default: 0 },
    enumeration: { type: Number, default: 0 },
    error: { type: [String, Number], default: 0 },
    mapData: { type: Object, default: () => ({}) }
});

const safePeriod = computed(() => {
    if (!props.period || !props.period.month || !props.period.year) {
        return 'No data';
    }
    return `${props.period.month.name} ${props.period.year.name}`;
});

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

const formatValue = (val) => {
    return val === null || val === undefined ? '-' : val;
};

const getColor = (value) => {
    if (value >= 100) return '#16a34a'; // green
    if (value >= 80) return '#f97316'; // orange
    if (value >= 40) return '#eab308'; // yellow
    return '#dc2626';                   // red
};

onMounted(async () => {
    const mapEl = document.getElementById('map');
    if (!mapEl) return;

    const map = L.map('map').setView([-7.5, 112.5], 8);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    let geojson;
    try {
        const res = await fetch(indexMap().url);
        if (!res.ok) return;
        geojson = await res.json();
    } catch (e) {
        console.error('[MAP] Fetch error:', e);
        return;
    }

    let geoLayer;

    geoLayer = L.geoJSON(geojson, {
        style: (feature) => {
            const id = String(feature.properties.regency);
            const value = props.mapData?.[id]?.value ?? null;

            return {
                fillColor: value !== null ? getColor(value) : '#e5e7eb',
                weight: 1,
                color: '#fff',
                fillOpacity: 0.9
            };
        },

        onEachFeature: (feature, layer) => {
            const id = String(feature.properties.regency);
            const item = props.mapData?.[id];

            if (item) {
                layer.bindTooltip(
                    `<strong>[${item.regency?.long_code}] ${item.regency?.name ?? id}</strong><br/>${item.value ?? 0}%`,
                    { sticky: true }
                );
            } else {
                layer.bindTooltip(`No data`, { sticky: true });
            }

            layer.on({
                mouseover: (e) => {
                    e.target.setStyle({ weight: 2, fillOpacity: 0.9 });
                },
                mouseout: (e) => {
                    geoLayer.resetStyle(e.target);
                }
            });
        }
    }).addTo(map);

    const bounds = geoLayer.getBounds();
    if (bounds.isValid()) {
        map.fitBounds(bounds);
    }
});
</script>

<template>

    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-3 p-4 md:p-6 h-full overflow-x-auto">

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

            <!-- Regency Info Badge -->
            <div
                class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 w-fit">
                <EnvironmentOutlined class="text-blue-500 text-xs" />
                <span class="text-xs font-semibold text-blue-700 dark:text-blue-300">{{props.selectedRegency?.name}}</span>
                <span class="text-xs text-blue-300 dark:text-blue-600">·</span>
                <span class="text-xs text-blue-500 dark:text-blue-400">{{props.selectedRegency?.long_code}}</span>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">

                <!-- TPK -->
                <Card class="!rounded-2xl shadow-sm border bg-white dark:bg-gray-800">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                            <BarChartOutlined class="text-blue-500" />
                        </div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Prediksi TPK</span>
                    </div>
                    <Statistic :value="formatValue(tpkData.value)" suffix=""
                        :value-style="{ fontSize: '2rem', fontWeight: '700' }" />
                    <p class="mt-2 text-xs text-gray-400">Periode: {{ tpkData.period }}</p>
                </Card>

                <!-- Enumeration -->
                <Card class="!rounded-2xl shadow-sm border bg-white dark:bg-gray-800">
                    <div class="flex items-center gap-2 mb-3">
                        <div
                            class="w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center">
                            <EnvironmentOutlined class="text-violet-500" />
                        </div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Progress Pencacahan</span>
                    </div>
                    <Statistic :value="formatValue(enumerationData.value)" suffix="%"
                        :value-style="{ fontSize: '2rem', fontWeight: '700' }" />
                    <p class="mt-2 text-xs text-gray-400">Periode: {{ enumerationData.period }}</p>
                </Card>

                <!-- Error -->
                <Card class="!rounded-2xl shadow-sm border bg-white dark:bg-gray-800">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-9 h-9 rounded-xl bg-red-50 dark:bg-red-900/30 flex items-center justify-center">
                            <WarningOutlined class="text-red-500" />
                        </div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Total Error</span>
                    </div>
                    <Statistic :value="formatValue(errorData.value)"
                        :value-style="{ fontSize: '2rem', fontWeight: '700' }" />
                    <p class="mt-2 text-xs text-gray-400">Periode: {{ errorData.period }}</p>
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

                <div id="map" class="h-72 md:h-96 rounded-xl"></div>

                <!-- Legend -->
                <div class="mt-4 flex flex-wrap gap-3">
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm inline-block" style="background:#16a34a"></span>
                        <span class="text-xs text-gray-600 dark:text-gray-400">≥ 100%</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm inline-block" style="background:#f97316"></span>
                        <span class="text-xs text-gray-600 dark:text-gray-400">80% – 99%</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm inline-block" style="background:#eab308"></span>
                        <span class="text-xs text-gray-600 dark:text-gray-400">40% – 79%</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm inline-block" style="background:#dc2626"></span>
                        <span class="text-xs text-gray-600 dark:text-gray-400">&lt; 40%</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm inline-block" style="background:#e5e7eb"></span>
                        <span class="text-xs text-gray-600 dark:text-gray-400">No data</span>
                    </div>
                </div>
            </Card>

        </div>
    </AppLayout>
</template>