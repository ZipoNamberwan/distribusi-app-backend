<script setup lang="js">
import { ref, computed, onMounted, onUnmounted, h } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as enumerationPage } from '@/routes/enumeration/page';
import { index as enumerationIndex } from '@/routes/enumeration/data';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import EnumerationsMobile from '@/custom_components/mobile/EnumerationsMobile.vue';

const props = defineProps({
    months: { type: Array, required: true, default: () => [] },
    years: { type: Array, required: true, default: () => [] },
    categories: { type: Array, required: true, default: () => [] },
    regencies: { type: Array, required: true, default: () => [] },
    initialPeriod: { type: Object, required: false, default: {} },
});

const rows = ref([]);
const filteredRows = ref([]);

const loading = ref(false);
const isSmallScreen = ref(typeof window !== 'undefined' ? window.innerWidth < 640 : false);
const selectedMonth = ref(props.initialPeriod.month?.id ?? null);
const selectedYear = ref(props.initialPeriod.year?.id ?? null);
const selectedRegency = ref([]);
const selectedPeriod = ref(props.initialPeriod);

const BACKGROUND_COLORS = ['#e6f4ff', '#f6ffed', '#fff7e6', '#f9f0ff', '#fff0f6',]

const onResize = () => { isSmallScreen.value = window.innerWidth < 640; };
onUnmounted(() => window.removeEventListener('resize', onResize));

const fetchData = async () => {
    loading.value = true;
    selectedRegency.value = []; // reset regency filter when fetching new data
    try {
        const query = {};
        if (selectedMonth.value) query.month = selectedMonth.value;
        if (selectedYear.value) query.year = selectedYear.value;

        const result = await fetch(enumerationIndex.url({ query }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        }).then((r) => r.json());

        rows.value = result.data ?? [];
        filteredRows.value = result.data ?? [];
        selectedPeriod.value = result.period;
    } finally {
        loading.value = false;
    }
};

const breadcrumbs = [
    {
        title: 'Progress Pencacahan',
        href: enumerationPage().url,
    },
];

const parentColumn = [
    {
        title: 'Target',
        key: 'target',
        align: 'center',
    }, {
        title: 'Realisasi',
        key: 'realization',
        align: 'center',
    },
    {
        title: 'Persentase',
        key: 'percentage',
        align: 'center',
    }
]

const COLOR_RANGE = [
    { min: 0, max: 50, class: 'bg-red-100 text-red-700' },
    { min: 50, max: 80, class: 'bg-yellow-100 text-yellow-700' },
    { min: 80, max: 100, class: 'bg-green-100 text-green-700' },
    { min: 100, max: Infinity, class: 'bg-emerald-100 text-emerald-700' },
];

const enumerationColumns = computed(() =>
    parentColumn.map((parent, index) => {
        const bg = BACKGROUND_COLORS[index % BACKGROUND_COLORS.length] ?? '#f5f5f5';
        const cell = () => ({ style: { background: bg } });
        return {
            ...parent,
            customHeaderCell: cell,
            children: props.categories.map((cat) => ({
                key: `${parent.key}_${cat.id}`,
                title: cat.name,
                align: 'center',
                customHeaderCell: cell,
                customCell: cell,
                sorter: true,
                customRender: ({ record }) => {
                    if (parent.key === 'percentage') {
                        const realization = record.realization?.[cat.id] ?? 0;
                        const target = record.target?.[cat.id] ?? 0;
                        const percentage = target > 0 ? (realization / target) * 100 : null;
                        if (percentage === null) {
                            return h('span', '-');
                        }
                        const range = COLOR_RANGE.find(
                            r => percentage >= r.min && percentage < r.max
                        );
                        return h(
                            'span',
                            { class: `${range?.class ?? ''} px-1 rounded` },
                            `${percentage.toFixed(2)}%`
                        );
                    }
                    return h('span', record[parent.key]?.[cat.id] ?? '-');
                },
            }))
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
    ...enumerationColumns.value,
]);

const mobileCardConfig = computed(() => ({
    header: (record) => ({
        title: record.regency?.name ?? '',
        subtitle: record.regency?.long_code ?? '',
    }),
    columns: ['B', 'NB'],
    sections: (record) => {
        const sections = [];

        parentColumn.forEach((col, index) => {
            const items = props.categories.map((cat) => {
                if (col.key === 'percentage') {
                    const target = record.target?.[cat.id] ?? 0;
                    const realization = record.realization?.[cat.id] ?? 0;
                    const percentage = target > 0 ? (realization / target) * 100 : null;
                    return { value: percentage !== null ? `${percentage.toFixed(2)}%` : '-' };
                }
                return { value: record[col.key]?.[cat.id] ?? '-' };
            });

            sections.push({
                title: col.title,
                key: col.key,
                color: BACKGROUND_COLORS[index % BACKGROUND_COLORS.length] || '#f5f5f5',
                items,
            });
        });

        return sections;
    },
}));

onMounted(() => {
    window.addEventListener('resize', onResize);
    fetchData();
});

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

    <Head title="Progress Pencacahan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-2 sm:p-4">
            <Card>
                <CardHeader class="px-3 py-4 sm:px-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="space-y-1">
                            <CardTitle class="text-lg sm:text-xl">
                                Progress Pencacahan
                                <div
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 border border-blue-200 rounded">
                                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                        </path>
                                    </svg>
                                    <span class="text-sm font-semibold text-blue-700">
                                        {{ `${selectedPeriod.month.name} ${selectedPeriod.year.name}` }}
                                    </span>
                                </div>
                            </CardTitle>
                        </div>
                    </div>
                    <div
                        class="mt-3 flex flex-col gap-2 sm:mt-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                            <a-select v-model:value="selectedMonth" placeholder="Semua Bulan" class="w-full sm:w-40"
                                @change="fetchData">
                                <a-select-option v-for="m in props.months" :key="m.id" :value="m.id">
                                    {{ m.name }}
                                </a-select-option>
                            </a-select>

                            <a-select v-model:value="selectedYear" placeholder="Semua Tahun" class="w-full sm:w-32"
                                @change="fetchData">
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

                <CardContent class="p-0 sm:px-6 sm:pb-6">
                    <!-- Mobile Card View (visible only on mobile) -->
                    <div class="sm:hidden">
                        <EnumerationsMobile :data="filteredRows" :loading="loading" :card-config="mobileCardConfig"
                            empty-message="Tidak ada data" :colorRange="COLOR_RANGE" />
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