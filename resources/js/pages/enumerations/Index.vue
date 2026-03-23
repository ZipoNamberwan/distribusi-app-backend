<script setup lang="js">
import { ref, computed, onMounted, onUnmounted, h } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as enumerationPage } from '@/routes/enumeration/page';
import { index as enumerationIndex } from '@/routes/enumeration/data';
import { useAppearance } from '@/composables/useAppearance';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import EnumerationsMobile from '@/custom_components/mobile/EnumerationsMobile.vue';
import { DownloadOutlined, ShareAltOutlined } from '@ant-design/icons-vue';
import ExcelJS from 'exceljs';

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

const { resolvedAppearance } = useAppearance();
const THEME_COLORS = {
    light: ['#e6f4ff', '#f6ffed', '#fff7e6', '#f9f0ff', '#fff0f6'],
    dark: ['#112a45', '#16331e', '#362111', '#281b36', '#361b27']
};
const defaultBg = computed(() => resolvedAppearance.value === 'dark' ? '#1f1f1f' : '#f5f5f5');
const backgroundColors = computed(() => THEME_COLORS[resolvedAppearance.value === 'dark' ? 'dark' : 'light']);

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

const getValue = (record, parentKey, catId) => {
    if (parentKey === 'percentage') {
        const realization = record.realization?.[catId] ?? 0;
        const target = record.target?.[catId] ?? 0;
        return target > 0 ? (realization / target) * 100 : null;
    }

    return record[parentKey]?.[catId] ?? 0;
};

const getTotalValue = (record, parentKey) => {
    if (parentKey === 'percentage') {
        const totalRealization = props.categories.reduce(
            (sum, cat) => sum + (record.realization?.[cat.id] ?? 0),
            0
        );

        const totalTarget = props.categories.reduce(
            (sum, cat) => sum + (record.target?.[cat.id] ?? 0),
            0
        );

        return totalTarget > 0 ? (totalRealization / totalTarget) * 100 : null;
    }

    return props.categories.reduce(
        (sum, cat) => sum + (record[parentKey]?.[cat.id] ?? 0),
        0
    );
};

const enumerationColumns = computed(() =>
    parentColumn.map((parent, index) => {
        const bg = backgroundColors.value[index % backgroundColors.value.length] ?? defaultBg.value;
        const cell = () => ({ style: { background: bg } });

        const categoryColumns = props.categories.map((cat) => ({
            key: `${parent.key}_${cat.id}`,
            title: cat.name,
            align: 'center',
            customHeaderCell: cell,
            customCell: cell,
            sorter: (a, b) =>
                (getValue(a, parent.key, cat.id) ?? 0) -
                (getValue(b, parent.key, cat.id) ?? 0),
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
        }));

        const totalColumn = {
            key: `${parent.key}_total`,
            title: 'Total',
            align: 'center',
            customHeaderCell: cell,
            customCell: cell,
            sorter: (a, b) =>
                (getTotalValue(a, parent.key) ?? 0) -
                (getTotalValue(b, parent.key) ?? 0),
            customRender: ({ record }) => {
                if (parent.key === 'percentage') {
                    const totalRealization = props.categories.reduce(
                        (sum, cat) => sum + (record.realization?.[cat.id] ?? 0),
                        0
                    );

                    const totalTarget = props.categories.reduce(
                        (sum, cat) => sum + (record.target?.[cat.id] ?? 0),
                        0
                    );

                    const percentage = totalTarget > 0
                        ? (totalRealization / totalTarget) * 100
                        : null;

                    if (percentage === null) {
                        return h('span', '-');
                    }

                    const range = COLOR_RANGE.find(
                        r => percentage >= r.min && percentage < r.max
                    );

                    return h(
                        'span',
                        { class: `${range?.class ?? ''} px-1 rounded font-semibold` },
                        `${percentage.toFixed(2)}%`
                    );
                }

                const total = props.categories.reduce(
                    (sum, cat) => sum + (record[parent.key]?.[cat.id] ?? 0),
                    0
                );

                return h('span', { class: 'font-semibold' }, total);
            }
        };

        return {
            ...parent,
            customHeaderCell: cell,
            children: [
                ...categoryColumns,
                totalColumn
            ]
        };
    })
);

const graphColumn = {
    title: 'Graph',
    key: 'graph',
    width: 180,
    align: 'left',

    sorter: (a, b) =>
        (getTotalValue(a, 'percentage') ?? 0) -
        (getTotalValue(b, 'percentage') ?? 0),

    customRender: ({ record }) => {
        const percent = getTotalValue(record, 'percentage');

        if (percent === null) {
            return h('span', '-');
        }

        const range = COLOR_RANGE.find(
            r => percent >= r.min && percent < r.max
        );

        const colorMap = {
            'bg-red-100 text-red-700': 'bg-red-500',
            'bg-yellow-100 text-yellow-700': 'bg-yellow-500',
            'bg-green-100 text-green-700': 'bg-green-500',
            'bg-emerald-100 text-emerald-700': 'bg-emerald-500'
        };

        const barColor = colorMap[range?.class] ?? 'bg-blue-500';

        const width = Math.min(percent, 150);

        return h('div', { class: 'flex items-center gap-2 w-full' }, [

            // BAR
            h('div', { class: 'flex-1 bg-gray-200 rounded h-4 overflow-hidden' }, [
                h('div', {
                    class: `${barColor} h-4`,
                    style: { width: `${width}%` }
                })
            ]),

            // FIXED WIDTH LABEL
            h(
                'span',
                { class: 'w-[55px] text-right text-xs tabular-nums' },
                `${percent.toFixed(2)}%`
            )
        ]);
    }
};

const regencyColumn = {
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
};

const columns = computed(() => [
    regencyColumn,
    ...enumerationColumns.value,
    graphColumn
]);

const mobileCardConfig = computed(() => ({
    header: (record) => ({
        title: record.regency?.name ?? '',
        subtitle: record.regency?.long_code ?? '',
    }),
    columns: ['B', 'NB', 'Total'], // B, NB, and Total column
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

            // Compute total for this parent column
            let totalValue;
            if (col.key === 'percentage') {
                const totalRealization = props.categories.reduce(
                    (sum, cat) => sum + (record.realization?.[cat.id] ?? 0),
                    0
                );
                const totalTarget = props.categories.reduce(
                    (sum, cat) => sum + (record.target?.[cat.id] ?? 0),
                    0
                );
                totalValue = totalTarget > 0
                    ? `${((totalRealization / totalTarget) * 100).toFixed(2)}%`
                    : '-';
            } else {
                totalValue = props.categories.reduce(
                    (sum, cat) => sum + (record[col.key]?.[cat.id] ?? 0),
                    0
                );
            }

            // Append total value to the items array
            items.push({ value: totalValue });

            sections.push({
                title: col.title,
                key: col.key,
                color: backgroundColors.value[index % backgroundColors.value.length] || defaultBg.value,
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

    const titleRow = [`Progress Pencacahan ${periodString}`];
    const headerRow1 = ['Kab/Kota'];
    const headerRow2 = [''];

    parentColumn.forEach(parent => {
        headerRow1.push(parent.title);
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
    parentColumn.forEach(parent => {
        const spanCount = props.categories.length + 1;
        if (spanCount > 1) {
            worksheet.mergeCells(2, currentCol, 2, currentCol + spanCount - 1);
        }
        currentCol += spanCount;
    });

    data.forEach(record => {
        const row = [`[${record.regency?.long_code}] ${toTitleCase(record.regency?.name || '')}`];

        parentColumn.forEach(parent => {
            let totalRealization = 0;
            let totalTarget = 0;
            let totalOther = 0;

            props.categories.forEach(cat => {
                if (parent.key === 'percentage') {
                    const realization = record.realization?.[cat.id] ?? 0;
                    const target = record.target?.[cat.id] ?? 0;
                    totalRealization += realization;
                    totalTarget += target;

                    const percentage = target > 0 ? (realization / target) * 100 : null;
                    if (percentage !== null) {
                        row.push(Number(percentage.toFixed(2)));
                    } else {
                        row.push('-');
                    }
                } else {
                    const val = record[parent.key]?.[cat.id] ?? 0;
                    totalOther += val;
                    row.push(val);
                }
            });

            if (parent.key === 'percentage') {
                const percentage = totalTarget > 0 ? (totalRealization / totalTarget) * 100 : null;
                if (percentage !== null) {
                    row.push(Number(percentage.toFixed(2)));
                } else {
                    row.push('-');
                }
            } else {
                row.push(totalOther);
            }
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
    link.download = `progress_pencacahan_${periodString.replace(/\s+/g, '_')}.xlsx`;

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

    <Head title="Progress Pencacahan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-2 sm:p-4">
            <Card>
                <CardHeader class="px-3 sm:px-4">
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
                            <a-button @click="handleDownload" size="small" :loading="downloadLoading" type="primary" title="Unduh"
                                shape="default">
                                <template #icon>
                                    <DownloadOutlined />
                                </template>
                            </a-button>
                        </div>
                    </div>

                </CardHeader>

                <CardContent class="p-0 sm:px-6 sm:pb-6">
                    <!-- Mobile Card View (visible only on mobile) -->
                    <div class="sm:hidden">
                        <EnumerationsMobile :data="filteredRows" :loading="loading" :card-config="mobileCardConfig"
                            empty-message="Tidak ada data" :colorRange="COLOR_RANGE"
                            :tableColumns="[regencyColumn, graphColumn]"/>
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