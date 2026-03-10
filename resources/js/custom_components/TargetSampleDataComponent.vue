<script setup>
import { h, ref, watch, computed } from 'vue';
import { usePagination } from 'vue-request';
import { index as sampleTargetDataIndex } from '@/routes/sample/data';

const props = defineProps({
    categories: { type: Array, required: true, default: () => [] },
    months: { type: Array, required: true, default: () => [] },
    years: { type: Array, required: true, default: () => [] },
    regencies: { type: Array, required: true, default: () => [] },
    open: {
        type: Boolean, required: true, default: () => false
    },
});

const lastParams = ref({});
const open = computed(() => (props.open));

const baseColumns = [
    {
        title: 'Kabupaten/Kota',
        key: 'regency',
        width: '15%',
        customRender: ({ record }) => {
            return h('span', `[${record.regency?.['long_code']}] ${record.regency?.['name']}`);
        },
        filters: (props.regencies ?? []).map(s => ({ text: `${s.long_code} ${s.name}`, value: s.id })),
    },
    {
        title: 'Default',
        dataIndex: 'is_default',
        width: '10%',
        align: 'center',
        customRender: ({ record }) => {
            if (record.is_default === 1) {
                return h('span', `Ya`);
            } else if (record.is_default === 0) { return h('span', `Bukan`); }

            return h('span', `-`);
        },
        filters: [
            { text: 'Ya', value: 1 },
            { text: 'Bukan', value: 0 },
        ],
    },
    {
        title: 'Bulan',
        key: 'month',
        width: '10%',
        align: 'center',
        customRender: ({ record }) => {
            if (record.month) {
                return h('span', `${record.month?.['name']}`);
            }
            return '-'
        },
        filters: (props.months ?? []).map(m => ({ text: `${m.name}`, value: m.id })),
    },
    {
        title: 'Tahun',
        key: 'year',
        width: '10%',
        align: 'center',
        customRender: ({ record }) => {
            return h('span', `${record.year?.['name']}`);
        },
        filters: (props.years ?? []).map(y => ({ text: `${y.name}`, value: y.id })),
    },
];

const categoryColumns = props.categories.map(category => ({
    title: category.name,
    key: String(category.id),
    width: '15%',
    align: 'center',
    customRender: ({ record }) => {
        return h('span', record[category.id]);
    },
    sorter: true
}));

const columns = [...baseColumns, ...categoryColumns];

const fetchJson = async (url) => {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`Failed to fetch: ${response.status}`);
    }

    return response.json();
};

const normalizeTableFilters = (filters) => {
    const normalized = {};

    Object.entries(filters ?? {}).forEach(([key, value]) => {
        if (!Array.isArray(value) || value.length === 0) {
            return;
        }

        normalized[key] = value
            .filter((v) => v !== null && v !== undefined)
            .map((v) => String(v));
    });

    return normalized;
};

const queryData = async (params = {}) => {
    lastParams.value = params;
    const { current = 1, pageSize = 10, sortField, sortOrder, ...filterQuery } = params;
    const size = Number(pageSize);
    const page = Number(current);

    const payload = await fetchJson(
        sampleTargetDataIndex.url({
            query: {
                start: Math.max(0, (page - 1) * size),
                length: size,
                sortField,
                sortOrder,
                ...filterQuery,
            },
        }),
    );

    return { list: payload.data, total: payload.total };
};

const {
    data: dataSource,
    run,
    loading,
} = usePagination(queryData);

const handleTableChange = (pag, filters, sorter) => {
    const filterQuery = normalizeTableFilters(filters);

    run({
        current: pag.current,
        pageSize: pag.pageSize,
        sortField: sorter.field ?? sorter.columnKey,
        sortOrder: sorter.order,
        ...filterQuery,
    });
};


watch(open, (isOpen) => {
    console.log('Open changed:', isOpen);
    if (isOpen) {
        run();
    }
});
</script>
<template>
    <a-table :scroll="{ y: 400 }" :columns="columns" :row-key="record => record.id"
        :data-source="dataSource?.list ?? []" :pagination="false" :loading="loading" @change="handleTableChange"
        size="small">
    </a-table>
</template>