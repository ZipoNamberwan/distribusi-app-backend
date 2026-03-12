<script setup>
import { ref, computed, onMounted, onUnmounted, h } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as predictionPage } from '@/routes/prediction/page';
import { index as predictionIndex } from '@/routes/prediction/data';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PredictionsMobile from '@/custom_components/mobile/PredictionsMobile.vue';

const props = defineProps({
    months: { type: Array, required: true, default: () => [] },
    years: { type: Array, required: true, default: () => [] },
    categories: { type: Array, required: true, default: () => [] },
    indicators: { type: Array, required: true, default: () => [] },
    defaultMonth: { type: Number, required: false, default: null },
    defaultYear: { type: Number, required: false, default: null },
});


const rows = ref([]);
const loading = ref(false);
const period = ref({});

const isSmallScreen = ref(typeof window !== 'undefined' ? window.innerWidth < 640 : false);
const selectedMonth = ref(props.defaultMonth);
const selectedYear = ref(props.defaultYear);

const onResize = () => { isSmallScreen.value = window.innerWidth < 640; };
onUnmounted(() => window.removeEventListener('resize', onResize));

const fetchData = async () => {
    loading.value = true;
    try {
        const query = {};
        if (selectedMonth.value) query.month = selectedMonth.value;
        if (selectedYear.value) query.year = selectedYear.value;

        const result = await fetch(predictionIndex.url({ query }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        }).then((r) => r.json());

        rows.value = result.data ?? [];
        period.value = result.period ?? {};
        console.table(period.value)
    } finally {
        loading.value = false;
    }
};

const breadcrumbs = [
    {
        title: 'Prediksi TPK',
        href: predictionPage().url,
    },
];

const growths = [
    {
        title: 'Pertumbuhan Month to Month',
        key: 'mom',
    },
    {
        title: 'Pertumbuhan Year on Year',
        key: 'yoy',
    },
];

const growthColumns = computed(() =>
    growths.map((growth) => {
        return {
            key: growth.key,
            title: growth.title,
            children: props.categories.map((cat) => {
                const key = cat.id;
                return {
                    title: cat.name,
                    key,
                    align: 'center',
                    sorter: (a, b) => {
                        const av = a.values?.[key];
                        const bv = b.values?.[key];
                        return (av ?? 0) - (bv ?? 0);
                    },
                    customRender: ({ record }) => {
                        const currentValue = record[growth.key]?.[key]?.['current'];
                        const prevValue = record[growth.key]?.[key]?.['prev'] ?? null;
                        if (currentValue === null || prevValue === null) return h('span', '-');
                        const growthValue = ((currentValue - prevValue) / Math.abs(prevValue)) * 100;
                        return h('span', `${growthValue.toFixed(2)}`);
                    },
                };
            }),
        };
    })
);

const indicatorColumns = computed(() =>
    props.indicators.map((ind) => {
        return {
            key: ind.id,
            title: `${ind.short_name ?? ind.name} ${period.value.month?.name ?? ''} ${period.value.year?.name ?? ''}`,
            children: [
                ...ind.categories.map((cat) => {
                    const key = `${ind.id}_${cat.id}`;
                    return {
                        title: isSmallScreen.value ? (cat.short_name ?? cat.name) : (cat.name ?? cat.short_name),
                        key,
                        align: 'center',
                        sorter: (a, b) => {
                            const av = a.values?.[key];
                            const bv = b.values?.[key];
                            const an = av?.den > 0 ? (ind.scale_factor * av.num) / av.den : -Infinity;
                            const bn = bv?.den > 0 ? (ind.scale_factor * bv.num) / bv.den : -Infinity;
                            return an - bn;
                        },
                        customRender: ({ record }) => {
                            const val = record.values?.[key];
                            if (!val || !val.den) return h('span', '-');
                            return h('span', ((val.num / val.den) * ind.scale_factor).toFixed(2));
                        },
                    };
                }),
            ],
        };
    })
);

const columns = computed(() => [
    {
        title: 'Kab/Kota',
        key: 'regency',
        sorter: (a, b) => (a.regency?.long_code ?? '').localeCompare(b.regency?.long_code ?? ''),
        fixed: 'left',
        width: isSmallScreen.value ? 40 : 200,
        ellipsis: true,
        customRender: ({ record }) =>
            isSmallScreen.value
                ? h('span', record.regency?.long_code ?? '')
                : h('span', `[${record.regency?.long_code}] ${record.regency?.name}`),
    },
    ...indicatorColumns.value,
    ...growthColumns.value,
]);

onMounted(() => {
    window.addEventListener('resize', onResize);
    fetchData();
});

const mobileCardConfig = computed(() => ({
    header: (record) => ({
        title: record.regency?.name ?? '',
        subtitle: record.regency?.long_code ?? '',
    }),
    sections: (record) => {
        const sections = [];

        // INDICATORS
        props.indicators.forEach((ind) => {
            const items = ind.categories.map((cat) => {
                const key = `${ind.id}_${cat.id}`;
                const val = record.values?.[key];
                return { value: val?.den > 0 ? ((val.num / val.den) * ind.scale_factor).toFixed(2) : '-' };
            });
            sections.push({
                title: `${ind.short_name ?? ind.name} ${period.value.month?.name ?? ''} ${period.value.year?.name ?? ''}`,
                color: '#e6f4ff',
                items,
            });
        });

        // GROWTHS
        growths.forEach((growth) => {
            const items = props.categories.map((cat) => {
                const key = cat.id;
                const current = record[growth.key]?.[key]?.current;
                const prev = record[growth.key]?.[key]?.prev ?? null;
                const value = current !== null && prev !== null ? (((current - prev) / Math.abs(prev)) * 100).toFixed(2) : '-';
                return { value };
            });
            sections.push({
                title: growth.title,
                color: '#fff7e6',
                items,
            });
        });

        return sections;
    },
    columns: ['B', 'NB'], // just for rendering the header once
}));
</script>

<template>

    <Head title="Prediksi TPK" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-2 sm:p-4">
            <Card>
                <CardHeader class="px-3 py-4 sm:px-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="space-y-1">
                            <CardTitle class="text-lg sm:text-xl">Prediksi TPK</CardTitle>
                        </div>
                    </div>
                    <div
                        class="mt-3 flex flex-col gap-2 sm:mt-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                            <a-select v-model:value="selectedMonth" placeholder="Semua Bulan" allow-clear
                                @change="fetchData" class="w-full sm:w-40">
                                <a-select-option v-for="m in props.months" :key="m.id" :value="m.id">
                                    {{ m.name }}
                                </a-select-option>
                            </a-select>

                            <a-select v-model:value="selectedYear" placeholder="Semua Tahun" allow-clear
                                @change="fetchData" class="w-full sm:w-32">
                                <a-select-option v-for="y in props.years" :key="y.id" :value="y.id">
                                    {{ y.name }}
                                </a-select-option>
                            </a-select>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="p-0 sm:px-4 sm:pb-6">
                    <!-- Mobile Card View (visible only on mobile) -->
                    <div class="sm:hidden">
                        <PredictionsMobile :data="rows" :loading="loading" :card-config="mobileCardConfig"
                            empty-message="Tidak ada data" />
                    </div>

                    <!-- Desktop Table View (hidden on mobile, visible on sm and up) -->
                    <div class="hidden overflow-hidden sm:block sm:rounded-lg sm:border sm:border-border">
                        <a-table :scroll="{ x: 1200, y: '70vh' }" :columns="columns"
                            :row-key="(record) => record.regency.id" :data-source="rows" :loading="loading"
                            :pagination="false" size="small" bordered />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>