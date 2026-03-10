<script setup lang="js">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ref, computed, onMounted, onUnmounted, h } from 'vue';
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

const visibleIndicators = ref(props.indicators.map((i) => i.id));

const isSmallScreen = ref(typeof window !== 'undefined' ? window.innerWidth < 640 : false);

const onResize = () => {
    isSmallScreen.value = window.innerWidth < 640;
};

onUnmounted(() => window.removeEventListener('resize', onResize));

const colWidth = computed(() => (isSmallScreen.value ? 40 : 90));

const INDICATOR_COLORS = {
    TPK: '#e6f4ff',
    RLMTA: '#f6ffed',
    RLMTN: '#fff7e6',
    GPR: '#f9f0ff',
    TPTT: '#fff0f6',
};

const allIndicatorColumns = computed(() =>
    props.indicators.map((ind) => {
        const bg = INDICATOR_COLORS[ind.name] ?? '#f5f5f5';
        return {
            key: ind.id,
            title: ind.short_name ?? ind.name,
            customHeaderCell: () => ({ style: { background: bg } }),
            children: [
                ...ind.categories.map((cat) => {
                    const key = `${ind.id}_${cat.id}`;
                    const cell = () => ({ style: { background: bg } });
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
                : h('span', `[${record.regency?.long_code}] ${record.regency?.name}`),
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

onMounted(() => {
    window.addEventListener('resize', onResize);
    fetchData();
});
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
                        <a-table :scroll="{ x: scrollX, y: '70vh' }" :columns="visibleColumns"
                            :row-key="record => record.regency.id" :data-source="rows" :loading="loading"
                            :pagination="false" size="small" bordered tableLayout="fixed" />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>