<script setup lang="js">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ref, computed, onMounted, onUnmounted, h } from 'vue';
import { Head } from '@inertiajs/vue3';
import { index as dataIndex } from '@/routes/indicator/data';
import { index as tableIndex } from '@/routes/indicator/table';
import IndicatorValuesMobile from '@/custom_components/mobile/IndicatorValuesMobile.vue';

const breadcrumbs = [
    {
        title: 'Indikator',
        href: tableIndex.url(),
    },
];

const props = defineProps({
    months: { type: Array, required: true, default: () => [] },
    years: { type: Array, required: true, default: () => [] },
    indicators: { type: Array, required: true, default: () => [] },
    regencies: { type: Array, required: true, default: () => [] },
    initialPeriod: { type: Object, required: false, default: {} },
});

const visibleIndicators = ref(props.indicators.map((i) => i.id));

const isSmallScreen = ref(typeof window !== 'undefined' ? window.innerWidth < 640 : false);

const onResize = () => {
    isSmallScreen.value = window.innerWidth < 640;
};

onMounted(() => {
    window.addEventListener('resize', onResize);
    fetchData();
});

onUnmounted(() => window.removeEventListener('resize', onResize));

const colWidth = computed(() => (isSmallScreen.value ? 40 : 90));

const BACKGROUND_COLORS = ['#e6f4ff', '#f6ffed', '#fff7e6', '#f9f0ff', '#fff0f6',]

// =====================================================
// MOBILE CARD CONFIGURATION
// =====================================================
const mobileCardConfig = computed(() => ({
    // Define how to extract header information from each record
    header: (record) => ({
        title: record.regency?.name || '',
        subtitle: record.regency?.long_code || '',
    }),

    // Define sections to display - each section represents an indicator
    sections: props.indicators
        .filter((ind) => visibleIndicators.value.includes(ind.id))
        .map((ind, index) => ({
            title: ind.name,
            color: BACKGROUND_COLORS[index % BACKGROUND_COLORS.length] || '#f5f5f5',
            // Function to dynamically generate items for this section
            items: (record) => {
                const items = [];

                // Add category items
                ind.categories.forEach((cat) => {
                    const key = `${ind.id}_${cat.id}`;
                    const val = record.values?.[key];
                    const displayValue = val && val.den
                        ? ((val.num / val.den) * ind.scale_factor).toFixed(2)
                        : '-';

                    items.push({
                        label: cat.short_name || cat.name,
                        value: displayValue,
                    });
                });

                // Add total item
                let totalNum = 0, totalDen = 0;
                ind.categories.forEach((cat) => {
                    const v = record.values?.[`${ind.id}_${cat.id}`];
                    if (v?.den > 0) {
                        totalNum += v.num;
                        totalDen += v.den;
                    }
                });
                const totalValue = totalDen
                    ? ((totalNum / totalDen) * ind.scale_factor).toFixed(2)
                    : '-';

                items.push({
                    label: 'Total',
                    value: totalValue,
                });

                return items;
            },
        })),

    // columnHeaders: [...props.categories.map((cat) => cat.name), "Total"]
    columnHeaders: ['', 'B', 'NB', 'Total'], // default headers if no indicators are available
}));

// =====================================================
// TABLE CONFIGURATION (DESKTOP)
// =====================================================
const allIndicatorColumns = computed(() =>
    props.indicators.map((ind, index) => {
        const bg = BACKGROUND_COLORS[index % BACKGROUND_COLORS.length] ?? '#f5f5f5';
        const cell = () => ({ style: { background: bg } });
        return {
            key: ind.id,
            title: ind.short_name ?? ind.name,
            customHeaderCell: cell,
            children: [
                ...ind.categories.map((cat) => {
                    const key = `${ind.id}_${cat.id}`;
                    return {
                        title: isSmallScreen.value ? (cat.short_name ?? cat.name) : (cat.name ?? cat.short_name),
                        key,
                        width: colWidth.value,
                        align: 'center',
                        customHeaderCell: cell,
                        customCell: cell,
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
                {
                    title: isSmallScreen.value ? 'T' : 'Total',
                    key: `${ind.id}_total`,
                    width: colWidth.value,
                    align: 'center',
                    customHeaderCell: () => ({ style: { background: bg } }),
                    customCell: () => ({ style: { background: bg } }),
                    sorter: (a, b) => {
                        const calcTotal = (record) => {
                            let num = 0, den = 0;
                            for (const cat of ind.categories) {
                                const v = record.values?.[`${ind.id}_${cat.id}`];
                                if (v?.den > 0) { num += v.num; den += v.den; }
                            }
                            return den > 0 ? (ind.scale_factor * num) / den : -Infinity;
                        };
                        return calcTotal(a) - calcTotal(b);
                    },
                    customRender: ({ record }) => {
                        let num = 0, den = 0;
                        for (const cat of ind.categories) {
                            const v = record.values?.[`${ind.id}_${cat.id}`];
                            if (v?.den > 0) { num += v.num; den += v.den; }
                        }
                        if (!den) return h('span', '-');
                        return h('span', ((num / den) * ind.scale_factor).toFixed(2));
                    },
                },
            ],
        };
    })
);

const visibleColumns = computed(() => [
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
                : h('span', `[${record.regency?.long_code}] ${toTitleCase(record.regency?.name ?? '')}`),
    },
    ...allIndicatorColumns.value.filter((col) => visibleIndicators.value.includes(col.key)),
]);

const scrollX = computed(() => {
    const regencyWidth = isSmallScreen.value ? 40 : 200;

    const categoriesCount =
        (props.indicators[0]?.categories?.length ?? 2) + 1;

    const calculatedWidth =
        regencyWidth +
        visibleIndicators.value.length *
        categoriesCount *
        colWidth.value;

    // force minimum width so Kab/Kota doesn't stretch
    const minWidth = isSmallScreen.value ? 600 : 1000;

    return Math.max(calculatedWidth, minWidth);
});

const allSelected = computed(() => visibleIndicators.value.length === props.indicators.length);
const isIndeterminate = computed(
    () => visibleIndicators.value.length > 0 && visibleIndicators.value.length < props.indicators.length,
);

const toggleAll = (checked) => {
    visibleIndicators.value = checked ? props.indicators.map((i) => i.id) : [];
};

const toggleIndicator = (id, checked) => {
    if (checked) {
        visibleIndicators.value = props.indicators
            .filter((i) => visibleIndicators.value.includes(i.id) || i.id === id)
            .map((i) => i.id);
    } else {
        visibleIndicators.value = visibleIndicators.value.filter((i) => i !== id);
    }
};

const dropdownOpen = ref(false);

const selectedMonth = ref(props.initialPeriod.month.id);
const selectedYear = ref(props.initialPeriod.year.id);
const selectedRegency = ref([]);
const selectedPeriod = ref(props.initialPeriod);

const rows = ref([]);
const filteredRows = ref([]);
const loading = ref(false);

const fetchData = async () => {
    loading.value = true;
    selectedRegency.value = []; // reset regency filter when fetching new data
    try {
        const query = { start: 0, length: 10000 };
        if (selectedMonth.value) query.month = selectedMonth.value;
        if (selectedYear.value) query.year = selectedYear.value;


        const first = await fetch(dataIndex.url({ query }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        }).then((r) => r.json());

        const total = first.total ?? first.data.length;

        if (first.data.length < total) {
            const full = await fetch(dataIndex.url({ query: { ...query, length: total } }), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            }).then((r) => r.json());
            rows.value = full.data;
            filteredRows.value = full.data;
        } else {
            rows.value = first.data;
            filteredRows.value = first.data;
        }
        selectedPeriod.value = first.period;
    } finally {
        loading.value = false;
    }
};

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

    <Head title="Perhitungan Indikator Kab/Kota" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-2 sm:p-4">
            <Card>
                <CardHeader class="px-3 py-4 sm:px-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="space-y-1">
                            <CardTitle class="text-lg sm:text-xl">
                                Perhitungan Indikator Kab/Kota
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
                            <p class="text-xs text-muted-foreground sm:text-sm">
                                Data agregat indikator perhotelan per kabupaten/kota.
                            </p>
                            <!-- Colored Badge Style with Icon -->

                        </div>

                        <div class="w-full shrink-0 sm:w-auto">
                            <a-dropdown :open="dropdownOpen" :trigger="['click']" placement="bottomRight"
                                @open-change="(val) => (dropdownOpen = val)">
                                <a-button type="primary" size="small" class="w-full sm:w-auto"
                                    @click="dropdownOpen = !dropdownOpen">
                                    <template #icon><span>☰</span></template>
                                    Pilih Indikator
                                    <a-tag v-if="isIndeterminate" color="blue" class="ml-1 !py-0 !text-xs">
                                        {{ visibleIndicators.length }}/{{ props.indicators.length }}
                                    </a-tag>
                                </a-button>
                                <template #overlay>
                                    <div @click.stop
                                        class="flex min-w-[180px] flex-col rounded-md border border-border bg-white shadow-lg dark:bg-zinc-900">
                                        <div class="border-b border-border px-3 py-2">
                                            <a-checkbox :checked="allSelected" :indeterminate="isIndeterminate"
                                                @change="(e) => toggleAll(e.target.checked)">
                                                <span class="font-semibold">Pilih Semua</span>
                                            </a-checkbox>
                                        </div>
                                        <div class="flex flex-col gap-1 px-3 py-2">
                                            <a-checkbox v-for="ind in props.indicators" :key="ind.id"
                                                :checked="visibleIndicators.includes(ind.id)"
                                                @change="(e) => toggleIndicator(ind.id, e.target.checked)">
                                                {{ ind.name }}
                                            </a-checkbox>
                                        </div>
                                    </div>
                                </template>
                            </a-dropdown>
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

                <CardContent class="p-0 sm:px-4 sm:pb-6">
                    <!-- Mobile Card View (visible only on mobile) -->
                    <div class="sm:hidden">
                        <IndicatorValuesMobile :data="filteredRows" :loading="loading" :card-config="mobileCardConfig"
                            empty-message="Tidak ada data" />
                    </div>

                    <!-- Desktop Table View (hidden on mobile, visible on sm and up) -->
                    <div class="hidden overflow-hidden sm:block sm:rounded-lg sm:border sm:border-border">
                        <a-table :scroll="{ x: '70vw', y: '70vh' }" :columns="visibleColumns"
                            :row-key="record => record.regency.id" :data-source="filteredRows" :loading="loading"
                            :pagination="false" size="small" bordered tableLayout="fixed" />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>