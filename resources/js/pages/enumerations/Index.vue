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
    defaultMonth: { type: Number, required: false, default: null },
    defaultYear: { type: Number, required: false, default: null },
});

const rows = ref([]);
const loading = ref(false);
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

        const result = await fetch(enumerationIndex.url({ query }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        }).then((r) => r.json());

        rows.value = result.data ?? [];
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

const enumerationColumns = computed(() =>
    parentColumn.map((parent) => ({
        ...parent,
        children: props.categories.map((cat) => ({
            key: `${parent.key}_${cat.id}`,
            title: cat.name,
            align: 'center',
            sorter: true,
            customRender: ({ record }) => {
                if (parent.key === 'percentage') {
                    const realization = record.realization?.[cat.id] ?? 0;
                    const target = record.target?.[cat.id] ?? 0;
                    const percentage = target > 0 ? (realization / target) * 100 : null;
                    return h(
                        'span',
                        percentage === null ? '-' : `${percentage.toFixed(2)}%`
                    );
                }
                return h('span', record[parent.key]?.[cat.id] ?? '-');
            },
        }))
    }))
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
    columns: ['Target', 'Realisasi', 'Persentase'],
    sections: (record) => {
        const sections = [];

        ['target', 'realization', 'percentage'].forEach((key) => {
            const items = props.categories.map((cat) => {
                if (key === 'percentage') {
                    const target = record.target?.[cat.id] ?? 0;
                    const realization = record.realization?.[cat.id] ?? 0;
                    const percentage = target > 0 ? (realization / target) * 100 : null;
                    return { value: percentage !== null ? `${percentage.toFixed(2)}%` : '-' };
                }
                return { value: record[key]?.[cat.id] ?? '-' };
            });

            sections.push({
                title: key === 'target' ? 'Target' : key === 'realization' ? 'Realisasi' : 'Persentase',
                color: key === 'percentage' ? '#e6f7ff' : '#fffbe6',
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

</script>

<template>

    <Head title="Progress Pencacahan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-2 sm:p-4">
            <Card>
                <CardHeader class="px-3 py-4 sm:px-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="space-y-1">
                            <CardTitle class="text-lg sm:text-xl">Progress Pencacahan</CardTitle>
                        </div>
                    </div>
                    <div
                        class="mt-3 flex flex-col gap-2 sm:mt-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
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
                    <!-- Mobile Card View (visible only on mobile) -->
                    <div class="sm:hidden">
                        <EnumerationsMobile :data="rows" :loading="loading" :card-config="mobileCardConfig"
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