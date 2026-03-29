<script setup lang="js">
import { DownloadOutlined, ShareAltOutlined } from '@ant-design/icons-vue';
import { Head } from '@inertiajs/vue3';
import ExcelJS from 'exceljs';
import { ref, computed, onMounted, onUnmounted, h } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAppearance } from '@/composables/useAppearance';
import ErrorSummariesMobile from '@/custom_components/mobile/ErrorSummariesMobile.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as errorSummariesData } from '@/routes/error_summaries/data';
import { index as errorSummariesPage } from '@/routes/error_summaries/page';

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
    regencies: { type: Array, required: true, default: () => [] },
    initialPeriod: { type: Object, required: false, default: {} },
});

const isSmallScreen = ref(typeof window !== 'undefined' ? window.innerWidth < 640 : false);
const onResize = () => { isSmallScreen.value = window.innerWidth < 640; };
onUnmounted(() => window.removeEventListener('resize', onResize));

const colWidth = computed(() => (isSmallScreen.value ? 40 : 90));

const { resolvedAppearance } = useAppearance();
const THEME_COLORS = {
    light: ['#e6f4ff', '#f6ffed', '#fff7e6', '#f9f0ff', '#fff0f6'],
    dark: ['#112a45', '#16331e', '#362111', '#281b36', '#361b27']
};
const defaultBg = computed(() => resolvedAppearance.value === 'dark' ? '#1f1f1f' : '#f5f5f5');
const backgroundColors = computed(() => THEME_COLORS[resolvedAppearance.value === 'dark' ? 'dark' : 'light']);

const errorColumns = computed(() =>
    props.errors.map((err, index) => {
        const bg = backgroundColors.value[index % backgroundColors.value.length] ?? defaultBg.value;
        const cell = () => ({ style: { background: bg } });
        return {
            key: err.id,
            title: err.name,
            align: 'center',
            customHeaderCell: cell,
            children: [
                ...props.categories.map((cat) => {
                    const key = `${err.id}_${cat.id}`;
                    return {
                        title: isSmallScreen.value ? (cat.short_name ?? cat.name) : (cat.name ?? cat.short_name),
                        key,
                        width: colWidth.value,
                        align: 'center',
                        customHeaderCell: cell,
                        customCell: cell,
                        sorter: (a, b) => (a.values?.[key] ?? 0) - (b.values?.[key] ?? 0),
                        customRender: ({ record }) => {
                            const value = record.values?.[key] ?? 0;

                            return h('span', { class: value > 0 ? 'bg-red-100 text-red-700 px-1 rounded' : '' }, value);
                        },
                    };
                }),
                {
                    title: isSmallScreen.value ? 'T' : 'Total',
                    key: `${err.id}_total`,
                    width: colWidth.value,
                    align: 'center',
                    customHeaderCell: cell,
                    customCell: cell,
                    sorter: (a, b) => {
                        const sum = (record) => props.categories.reduce((acc, cat) => acc + (record.values?.[`${err.id}_${cat.id}`] ?? 0), 0);
                        return sum(a) - sum(b);
                    },
                    customRender: ({ record }) => {
                        const total = props.categories.reduce((acc, cat) => acc + (record.values?.[`${err.id}_${cat.id}`] ?? 0), 0);
                        return h('span', { class: total > 0 ? 'bg-red-100 text-red-700 px-1 rounded' : '' }, total);
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
        const query = {};
        if (selectedMonth.value) query.month = selectedMonth.value;
        if (selectedYear.value) query.year = selectedYear.value;

        const result = await fetch(errorSummariesData.url({ query }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        }).then((r) => r.json());

        rows.value = result.data ?? [];
        filteredRows.value = rows.value;
        selectedPeriod.value = result.period;
    } finally {
        loading.value = false;
    }
};

const mobileCardConfig = computed(() => ({
    header: (record) => ({
        title: record.regency?.name ?? '',
        subtitle: record.regency?.long_code ?? '',
    }),
    sections: (record) => {
        const sections = [];

        // Errors per category + total
        props.errors.forEach((err, index) => {
            const bg = backgroundColors.value[index % backgroundColors.value.length] ?? defaultBg.value;
            const items = props.categories.map((cat) => {
                const key = `${err.id}_${cat.id}`;
                return { value: record.values?.[key] ?? 0 };
            });
            // Add total column
            const total = props.categories.reduce((acc, cat) => acc + (record.values?.[`${err.id}_${cat.id}`] ?? 0), 0);
            items.push({ value: total });

            sections.push({
                title: err.name,
                color: bg,
                items,
            });
        });

        return sections;
    },
    columns: ['B', 'NB', 'T'], // adjust if your categories are fixed
}));

onMounted(() => {
    window.addEventListener('resize', onResize);
    fetchData();
});

const filterRegency = (input, option) => {
    const regency = props.regencies.find(r => r.id === option.value)
    if (!regency) return false
    const label = `[${regency.long_code}] ${toTitleCase(regency.name)}`.toLowerCase()
    return label.includes(input.toLowerCase())
}

const filterRegencyFromData = () => {
    if (!selectedRegency.value.length) {
        filteredRows.value = rows.value;
        return;
    }
    filteredRows.value = rows.value.filter((r) => selectedRegency.value.includes(r.regency?.id));
};

const downloadLoading = ref(false);

const downloadExcel = async (data) => {
    const periodString = selectedPeriod.value?.month ? `${selectedPeriod.value.month.name} ${selectedPeriod.value.year.name}` : '';

    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Data');

    const titleRow = [`Rekap Error ${periodString}`];
    const headerRow1 = ['Kab/Kota'];
    const headerRow2 = [''];

    props.errors.forEach(err => {
        headerRow1.push(err.name);
        for (let i = 0; i < props.categories.length; i++) {
            headerRow1.push('');
        }
        props.categories.forEach(cat => {
            headerRow2.push(cat.name || cat.short_name);
        });
        headerRow2.push('Total');
    });

    worksheet.addRow(titleRow);
    worksheet.addRow(headerRow1);
    worksheet.addRow(headerRow2);

    const totalCols = headerRow2.length;

    worksheet.mergeCells(1, 1, 1, totalCols);
    worksheet.mergeCells(2, 1, 3, 1);

    let currentCol = 2;
    props.errors.forEach(err => {
        const spanCount = props.categories.length + 1;
        if (spanCount > 1) {
            worksheet.mergeCells(2, currentCol, 2, currentCol + spanCount - 1);
        }
        currentCol += spanCount;
    });

    data.forEach(record => {
        const row = [`[${record.regency?.long_code}] ${toTitleCase(record.regency?.name || '')}`];

        props.errors.forEach(err => {
            let errorTotal = 0;
            props.categories.forEach(cat => {
                const key = `${err.id}_${cat.id}`;
                const val = record.values?.[key] ?? 0;
                row.push(val);
                errorTotal += val;
            });
            row.push(errorTotal);
        });

        worksheet.addRow(row);
    });

    for (let R = 1; R <= 3; ++R) {
        worksheet.getRow(R).eachCell((cell) => {
            cell.font = { bold: true, size: R === 1 ? 14 : 11 };
            cell.alignment = { horizontal: 'center', vertical: 'middle' };
        });
    }

    worksheet.getColumn(1).width = 25;
    for (let c = 2; c <= totalCols; c++) {
        worksheet.getColumn(c).width = 12;
    }

    const buffer = await workbook.xlsx.writeBuffer();

    const blob = new Blob([buffer], {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });

    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = `rekap_error_${periodString.replace(/\s+/g, '_')}.xlsx`;

    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
};

const handleDownload = async () => {
    downloadLoading.value = true;
    try {
        await downloadExcel(filteredRows.value);
    } finally {
        downloadLoading.value = false;
    }
};

function toTitleCase(str) {
    return str
        .toLowerCase()
        .replace(/\b\w/g, char => char.toUpperCase());
}
</script>

<template>

    <Head title="Rekap Error" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-2 sm:p-4">
            <Card>
                <CardHeader class="px-3 sm:px-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="space-y-1">
                            <CardTitle class="text-lg sm:text-xl">
                                Rekap Error
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
                                @change="filterRegencyFromData()" :filter-option="filterRegency">
                                <a-select-option v-for="r in props.regencies" :key="r.id" :value="r.id">
                                    [{{ r.long_code }}] {{ toTitleCase(r.name) }}
                                </a-select-option>
                            </a-select>
                        </div>
                        <div class="shrink-0 mt-0 flex items-center gap-2">
                            <!-- <a-button size="small" type="default" title="Bagikan" shape="default">
                                <template #icon>
                                    <ShareAltOutlined />
                                </template>
</a-button> -->
                            <a-button @click="handleDownload" size="small" :loading="downloadLoading" type="primary"
                                title="Unduh" shape="default">
                                <template #icon>
                                    <DownloadOutlined />
                                </template>
                            </a-button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="p-0 sm:px-4 sm:pb-6">
                    <!-- Mobile Card View (visible only on mobile) -->
                    <div class="sm:hidden">
                        <ErrorSummariesMobile :data="filteredRows" :loading="loading" :card-config="mobileCardConfig"
                            empty-message="Tidak ada data" />
                    </div>

                    <!-- Desktop Table View (hidden on mobile, visible on sm and up) -->
                    <div class="hidden overflow-hidden sm:block sm:rounded-lg sm:border sm:border-border">
                        <a-table :scroll="{ x: scrollX, y: '70vh' }" :columns="columns"
                            :row-key="(record) => record.regency.id" :data-source="filteredRows" :loading="loading"
                            :pagination="false" size="small" bordered />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>