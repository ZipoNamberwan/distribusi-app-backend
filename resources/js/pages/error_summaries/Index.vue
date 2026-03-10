<script setup lang="js">
import { ref, computed, onMounted, onUnmounted, h } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as errorSummariesPage } from '@/routes/error_summaries/page';
import { index as errorSummariesData } from '@/routes/error_summaries/data';

const breadcrumbs = [
    {
        title: 'Rekap Error',
        href: errorSummariesPage().url,
    },
];

const props = defineProps({
    months: { type: Array, required: true, default: () => [] },
    years: { type: Array, required: true, default: () => [] },
    categories: { type: Array, required: true, default: () => [] },
    errors: { type: Array, required: true, default: () => [] },
    defaultMonth: { type: Number, required: false, default: null },
    defaultYear: { type: Number, required: false, default: null },
});

const isSmallScreen = ref(typeof window !== 'undefined' ? window.innerWidth < 640 : false);
const onResize = () => { isSmallScreen.value = window.innerWidth < 640; };
onUnmounted(() => window.removeEventListener('resize', onResize));

const colWidth = computed(() => (isSmallScreen.value ? 40 : 90));

const ERROR_COLORS = {
    Hotel: '#fff1f0',
    Indikator: '#fff7e6',
};

const errorColumns = computed(() =>
    props.errors.map((err) => {
        const bg = ERROR_COLORS[err.code] ?? '#f5f5f5';
        return {
            key: err.id,
            title: err.name,
            align: 'center',
            customHeaderCell: () => ({ style: { background: bg } }),
            children: [
                ...props.categories.map((cat) => {
                    const key = `${err.id}_${cat.id}`;
                    const cell = () => ({ style: { background: bg } });
                    return {
                        title: isSmallScreen.value ? (cat.short_name ?? cat.name) : (cat.name ?? cat.short_name),
                        key,
                        width: colWidth.value,
                        align: 'center',
                        customHeaderCell: cell,
                        customCell: cell,
                        sorter: (a, b) => (a.values?.[key] ?? 0) - (b.values?.[key] ?? 0),
                        customRender: ({ record }) => h('span', record.values?.[key] ?? 0),
                    };
                }),
                {
                    title: isSmallScreen.value ? 'T' : 'Total',
                    key: `${err.id}_total`,
                    width: colWidth.value,
                    align: 'center',
                    customHeaderCell: () => ({ style: { background: bg } }),
                    customCell: () => ({ style: { background: bg } }),
                    sorter: (a, b) => {
                        const sum = (record) => props.categories.reduce((acc, cat) => acc + (record.values?.[`${err.id}_${cat.id}`] ?? 0), 0);
                        return sum(a) - sum(b);
                    },
                    customRender: ({ record }) => {
                        const total = props.categories.reduce((acc, cat) => acc + (record.values?.[`${err.id}_${cat.id}`] ?? 0), 0);
                        return h('span', total);
                    },
                },
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
    ...errorColumns.value,
]);

const scrollX = computed(() => {
    const noWidth = isSmallScreen.value ? 30 : 50;
    const regencyWidth = isSmallScreen.value ? 40 : 200;
    const colsPerError = (props.categories.length ?? 2) + 1; // +1 for Total
    return noWidth + regencyWidth + props.errors.length * colsPerError * colWidth.value;
});

const selectedMonth = ref(props.defaultMonth);
const selectedYear = ref(props.defaultYear);

const rows = ref([]);
const loading = ref(false);

const fetchData = async () => {
    loading.value = true;
    try {
        const query = {};
        if (selectedMonth.value) query.month = selectedMonth.value;
        if (selectedYear.value) query.year = selectedYear.value;

        const result = await fetch(errorSummariesData.url({ query }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        }).then((r) => r.json());

        rows.value = result.data ?? [];
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
    <Head title="Rekap Error" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 sm:p-4">
            <Card>
                <CardHeader>
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="space-y-1">
                            <CardTitle>Rekap Error Survei VHTS Kab/Kota</CardTitle>
                            <p class="text-sm text-muted-foreground">
                                Rekap jumlah error hotel dan indikator per kabupaten/kota.
                            </p>
                        </div>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
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
                </CardHeader>

                <CardContent class="p-0 sm:px-6 sm:pb-6">
                    <div class="overflow-hidden sm:rounded-lg sm:border sm:border-border">
                        <a-table
                            :scroll="{ x: scrollX, y: '70vh' }"
                            :columns="columns"
                            :row-key="(record) => record.regency.id"
                            :data-source="rows"
                            :loading="loading"
                            :pagination="false"
                            size="small"
                            bordered
                        />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
kjklj