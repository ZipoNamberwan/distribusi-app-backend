<script setup>
import { ref, computed, onMounted, onUnmounted, h } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as predictionPage } from '@/routes/prediction/page';
import { index as predictionIndex } from '@/routes/prediction/data';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PredictionsMobile from '@/custom_components/mobile/PredictionsMobile.vue';
import { ArrowUpOutlined, ArrowDownOutlined } from '@ant-design/icons-vue';

const props = defineProps({
    months: { type: Array, required: true, default: () => [] },
    years: { type: Array, required: true, default: () => [] },
    categories: { type: Array, required: true, default: () => [] },
    regencies: { type: Array, required: true, default: () => [] },
    indicators: { type: Array, required: true, default: () => [] },
    initialPeriod: { type: Object, required: false, default: {} },
});

const rows = ref([]);
const filteredRows = ref([]);
const loading = ref(false);
const isSmallScreen = ref(typeof window !== 'undefined' ? window.innerWidth < 640 : false);
const selectedMonth = ref(props.initialPeriod.current.month.id);
const selectedYear = ref(props.initialPeriod.current.year.id);
const selectedRegency = ref([]);
const selectedPeriod = ref(props.initialPeriod);

const BACKGROUND_COLORS = ['#e6f4ff', '#f6ffed', '#fff7e6', '#f9f0ff', '#fff0f6',]

const onResize = () => { isSmallScreen.value = window.innerWidth < 640; };
onUnmounted(() => window.removeEventListener('resize', onResize));

const fetchData = async () => {
    loading.value = true;
    selectedRegency.value = [];

    try {
        const query = {};
        if (selectedMonth.value) query.month = selectedMonth.value;
        if (selectedYear.value) query.year = selectedYear.value;

        const result = await fetch(predictionIndex.url({ query }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        }).then((r) => r.json());

        rows.value = result.data ?? [];
        filteredRows.value = result.data ?? [];
        selectedPeriod.value = result.period ?? {};
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
        mobileTitle: 'MoM',
        key: 'mom',
        period: 'previousMonth',
    },
    {
        title: 'Pertumbuhan Year on Year',
        mobileTitle: 'YoY',
        key: 'yoy',
        period: 'sameMonthPreviousYear',
    },
];

const growthColumns = computed(() => {
    return growths.map((growth, index) => {
        const bg = BACKGROUND_COLORS[(index + 1) % BACKGROUND_COLORS.length] ?? '#f5f5f5';
        const cell = () => ({ style: { background: bg } });
        return {
            key: growth.key,
            title: () =>
                h('div', [
                    h('div', growth.title),
                    h(
                        'div',
                        `${selectedPeriod.value[growth.period].month?.name ?? ''} ${selectedPeriod.value[growth.period].year?.name ?? ''}`
                    ),
                ]),
            customHeaderCell: cell,
            children: props.categories.map((cat) => {
                const key = cat.id;
                return {
                    title: cat.name,
                    key,
                    customHeaderCell: cell,
                    customCell: cell,
                    align: 'center',
                    sorter: (a, b) => {
                        const aCurrent = a[growth.key]?.[key]?.['current'];
                        const aPrev = a[growth.key]?.[key]?.['prev'] ?? null;

                        const bCurrent = b[growth.key]?.[key]?.['current'];
                        const bPrev = b[growth.key]?.[key]?.['prev'] ?? null;

                        const aNull = aCurrent === null || aPrev === null;
                        const bNull = bCurrent === null || bPrev === null;

                        if (aNull && bNull) return 0;
                        if (aNull) return 1;   // a goes last
                        if (bNull) return -1;  // b goes last

                        const aGrowth = ((aCurrent - aPrev) / Math.abs(aPrev)) * 100;
                        const bGrowth = ((bCurrent - bPrev) / Math.abs(bPrev)) * 100;

                        return aGrowth - bGrowth;
                    },
                    customRender: ({ record }) => {
                        const currentValue = record[growth.key]?.[key]?.['current'];
                        const prevValue = record[growth.key]?.[key]?.['prev'] ?? null;
                        if (currentValue === null || prevValue === null) return h('span', '-');

                        const growthValue = ((currentValue - prevValue) / Math.abs(prevValue)) * 100;
                        const color = growthValue >= 0 ? 'green' : 'red';
                        const Icon = growthValue >= 0 ? ArrowUpOutlined : ArrowDownOutlined;

                        return h(
                            'span',
                            { style: { color, display: 'inline-flex', alignItems: 'center', gap: '4px' } },
                            [
                                h(Icon),
                                `${growthValue.toFixed(2)}%`,
                                h('span', { style: { color: '#999', marginLeft: '4px' } }, `(${prevValue})`)
                            ]
                        );
                    },
                };
            }),
        };
    })
}
);

const indicatorColumns = computed(() => {
    return props.indicators.map((ind, index) => {
        const bg = BACKGROUND_COLORS[0] ?? '#f5f5f5';
        const cell = () => ({ style: { background: bg } });

        return {
            key: ind.id,
            title: () =>
                h('div', [
                    h('div', ind.short_name ?? ind.name),
                    h(
                        'div',
                        `${selectedPeriod.value.current.month?.name ?? ''} ${selectedPeriod.value.current.year?.name ?? ''}`
                    ),
                ]),
            customHeaderCell: cell,
            children: [
                ...ind.categories.map((cat) => {
                    const key = `${ind.id}_${cat.id}`;
                    return {
                        title: isSmallScreen.value ? (cat.short_name ?? cat.name) : (cat.name ?? cat.short_name),
                        customHeaderCell: cell,
                        customCell: cell,
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
}
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
                title: `${ind.short_name ?? ind.name} ${selectedPeriod.value.current.month?.name?.slice(0, 3) ?? ''} ${selectedPeriod.value.current.year?.name ?? ''}`,
                color: BACKGROUND_COLORS[0] ?? '#f5f5f5',
                key: ind.id,
                items,
            });
        });

        // GROWTHS
        growths.forEach((growth, index) => {
            const items = props.categories.map((cat) => {
                const key = cat.id;
                const current = record[growth.key]?.[key]?.current;
                const prev = record[growth.key]?.[key]?.prev ?? null;
                const value = current !== null && prev !== null ? (((current - prev) / Math.abs(prev)) * 100).toFixed(2) : '-';
                return { value, prev };
            });
            sections.push({
                title: `${growth.mobileTitle} ${selectedPeriod.value[growth.period].month?.name?.slice(0, 3) ?? ''} ${selectedPeriod.value[growth.period].year?.name ?? ''}`,
                color: BACKGROUND_COLORS[(index + 1) % BACKGROUND_COLORS.length] ?? '#f5f5f5',
                items,
                key: growth.key,
            });
        });
        return sections;
    },
    columns: ['B', 'NB'], // just for rendering the header once
}));

const filterRegency = () => {
    if (!selectedRegency.value.length) {
        filteredRows.value = rows.value;
        return;
    }
    filteredRows.value = rows.value.filter((r) => selectedRegency.value.includes(r.regency?.id));
};

function toTitleCase(str) {
    return str
        .toLowerCase()
        .replace(/\b\w/g, char => char.toUpperCase());
}
</script>

<template>

    <Head title="Prediksi TPK" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-2 sm:p-4">
            <Card>
                <CardHeader class="px-3 py-4 sm:px-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="space-y-1">
                            <CardTitle class="text-lg sm:text-xl">Prediksi TPK
                                <div
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 border border-blue-200 rounded">
                                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                        </path>
                                    </svg>
                                    <span class="text-sm font-semibold text-blue-700">
                                        {{ `${selectedPeriod.current.month.name} ${selectedPeriod.current.year.name}` }}
                                    </span>
                                </div>
                            </CardTitle>
                        </div>
                    </div>
                    <div
                        class="mt-3 flex flex-col gap-2 sm:mt-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                            <a-select v-model:value="selectedMonth" placeholder="Semua Bulan" @change="fetchData"
                                class="w-full sm:w-40">
                                <a-select-option v-for="m in props.months" :key="m.id" :value="m.id">
                                    {{ m.name }}
                                </a-select-option>
                            </a-select>

                            <a-select v-model:value="selectedYear" placeholder="Semua Tahun" @change="fetchData"
                                class="w-full sm:w-32">
                                <a-select-option v-for="y in props.years" :key="y.id" :value="y.id">
                                    {{ y.name }}
                                </a-select-option>
                            </a-select>
                            <a-select max-tag-count="responsive" mode="multiple" v-model:value="selectedRegency"
                                placeholder="Semua Kabupaten/Kota" allow-clear class="w-full sm:w-40"
                                @change="filterRegency()">
                                <a-select-option v-for="r in props.regencies" :key="r.id" :value="r.id">
                                    [{{ r.long_code }}] {{ toTitleCase(r.name) }}
                                </a-select-option>
                            </a-select>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="p-0 sm:px-4 sm:pb-6">
                    <!-- Mobile Card View (visible only on mobile) -->
                    <div class="sm:hidden">
                        <PredictionsMobile :data="filteredRows" :loading="loading" :card-config="mobileCardConfig"
                            empty-message="Tidak ada data" />
                    </div>

                    <!-- Desktop Table View (hidden on mobile, visible on sm and up) -->
                    <div class="hidden overflow-hidden sm:block sm:rounded-lg sm:border sm:border-border">
                        <a-table :scroll="{ x: 1200, y: '70vh' }" :columns="columns"
                            :row-key="(record) => record.regency.id" :data-source="filteredRows" :loading="loading"
                            :pagination="false" size="small" bordered />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>