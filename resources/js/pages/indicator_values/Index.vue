<script setup lang="js">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ref, computed, onMounted, h } from 'vue';
import { Head } from '@inertiajs/vue3';
import { index as dataIndex } from '@/routes/indicator/data';
import { index as tableIndex } from '@/routes/indicator/table';

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
    defaultMonth: { type: Number, required: false, default: null },
    defaultYear: { type: Number, required: false, default: null },
});

const INDICATORS = computed(() => props.indicators.map((i) => i.name));
const visibleIndicators = ref(props.indicators.map((i) => i.name));

const numSorter = (key) => (a, b) => (a[key] ?? -Infinity) - (b[key] ?? -Infinity);

const isSmallScreen = ref(window.innerWidth < 640);
if (typeof window !== 'undefined') {
    window.addEventListener('resize', () => {
        isSmallScreen.value = window.innerWidth < 640;
    });
}

const colWidth = computed(() => (isSmallScreen.value ? 40 : 90));

const bLabel = computed(() => (isSmallScreen.value ? 'B' : 'Bintang'));
const nbLabel = computed(() => (isSmallScreen.value ? 'NB' : 'Non Bintang'));
const tLabel = computed(() => (isSmallScreen.value ? 'T' : 'Total'));

// Light pastel backgrounds per indicator group
const INDICATOR_COLORS = {
    TPK: '#e6f4ff',
    RLMTA: '#f6ffed',
    RLMTN: '#fff7e6',
    GPR: '#f9f0ff',
    TPTT: '#fff0f6',
};

const makeChildren = (key, prefix) => {
    const bg = INDICATOR_COLORS[key];
    const cell = () => ({ style: { background: bg } });
    return [
        { title: bLabel.value, dataIndex: `${prefix}_bintang`, width: colWidth.value, align: 'center', sorter: numSorter(`${prefix}_bintang`), customHeaderCell: cell, customCell: cell },
        { title: nbLabel.value, dataIndex: `${prefix}_non_bintang`, width: colWidth.value, align: 'center', sorter: numSorter(`${prefix}_non_bintang`), customHeaderCell: cell, customCell: cell },
        { title: tLabel.value, dataIndex: `${prefix}_total`, width: colWidth.value, align: 'center', sorter: numSorter(`${prefix}_total`), customHeaderCell: cell, customCell: cell },
    ];
};

const allIndicatorColumns = computed(() =>
    INDICATORS.value.map((name) => ({
        key: name,
        title: name,
        customHeaderCell: () => ({ style: { background: INDICATOR_COLORS[name] ?? '#f5f5f5' } }),
        children: makeChildren(name, name.toLowerCase()),
    }))
);

const visibleColumns = computed(() => [
    {
        title: 'Kab/Kota',
        dataIndex: 'regency',
        sorter: (a, b) => (a.regency ?? '').localeCompare(b.regency ?? ''),
        fixed: 'left',
        width: isSmallScreen.value ? 40 : 200,
        ellipsis: true,
        customRender: ({ record }) =>
            isSmallScreen.value
                ? h('span', record.regency_long_code ?? '')
                : h('span', record.regency ?? ''),
    },
    ...allIndicatorColumns.value.filter((col) => visibleIndicators.value.includes(col.key)),
]);

const allSelected = computed(() => visibleIndicators.value.length === INDICATORS.value.length);
const isIndeterminate = computed(
    () => visibleIndicators.value.length > 0 && visibleIndicators.value.length < INDICATORS.value.length,
);

const toggleAll = (checked) => {
    visibleIndicators.value = checked ? [...INDICATORS.value] : [];
};

const toggleIndicator = (key, checked) => {
    if (checked) {
        visibleIndicators.value = INDICATORS.value.filter(
            (k) => visibleIndicators.value.includes(k) || k === key,
        );
    } else {
        visibleIndicators.value = visibleIndicators.value.filter((k) => k !== key);
    }
};

const dropdownOpen = ref(false);

const selectedMonth = ref(props.defaultMonth);
const selectedYear = ref(props.defaultYear);

const rows = ref([]);
const loading = ref(false);

const fetchData = async () => {
    loading.value = true;
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
        } else {
            rows.value = first.data;
        }
    } finally {
        loading.value = false;
    }
};

onMounted(fetchData);
</script>
<template>

    <Head title="Perhitungan Indikator Kab/Kota" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 sm:p-4">
            <Card>
                <CardHeader>
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="space-y-1">
                            <CardTitle>Perhitungan Indikator Kab/Kota</CardTitle>
                            <p class="text-sm text-muted-foreground">
                                Data agregat indikator perhotelan per kabupaten/kota.
                            </p>
                        </div>

                        <div class="shrink-0">
                            <a-dropdown :open="dropdownOpen" :trigger="['click']" placement="bottomRight"
                                @open-change="(val) => (dropdownOpen = val)">
                                <a-button type="primary" @click="dropdownOpen = !dropdownOpen">
                                    <template #icon><span>☰</span></template>
                                    Pilih Indikator
                                    <a-tag v-if="isIndeterminate" color="blue" class="ml-1 !py-0 !text-xs">
                                        {{ visibleIndicators.length }}/{{ INDICATORS.length }}
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
                                            <a-checkbox v-for="ind in INDICATORS" :key="ind"
                                                :checked="visibleIndicators.includes(ind)"
                                                @change="(e) => toggleIndicator(ind, e.target.checked)">
                                                {{ ind }}
                                            </a-checkbox>
                                        </div>
                                    </div>
                                </template>
                            </a-dropdown>
                        </div>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <a-select v-model:value="selectedMonth" placeholder="Semua Bulan" allow-clear class="w-40"
                                @change="fetchData">
                                <a-select-option v-for="m in props.months" :key="m.id" :value="m.id">
                                    {{ m.name }}
                                </a-select-option>
                            </a-select>

                            <a-select v-model:value="selectedYear" placeholder="Semua Tahun" allow-clear class="w-32"
                                @change="fetchData">
                                <a-select-option v-for="y in props.years" :key="y.id" :value="y.id">
                                    {{ y.name }}
                                </a-select-option>
                            </a-select>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="p-0 sm:px-6 sm:pb-6">
                    <div class="overflow-hidden sm:rounded-lg sm:border sm:border-border">
                        <a-table :scroll="{ x: 1500, y: 560 }" :columns="visibleColumns"
                            :row-key="record => record.regency_id" :data-source="rows" :loading="loading"
                            :pagination="false" size="small" bordered />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>