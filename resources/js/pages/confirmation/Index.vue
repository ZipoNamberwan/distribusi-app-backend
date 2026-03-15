<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as confirmationPage } from '@/routes/user/page';
import { ref, computed, h } from 'vue';
import { usePagination } from 'vue-request';
import { index as confirmationDataIndex } from '@/routes/confirmation/data';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const breadcrumbs = [
    {
        title: 'Konfirmasi Error',
        href: confirmationPage().url,
    },
];

const props = defineProps({
    months: { type: Array, required: true, default: () => [] },
    years: { type: Array, required: true, default: () => [] },
    errorTypes: { type: Array, required: true, default: () => [] },
    regencies: { type: Array, required: true, default: () => [] },
    statuses: { type: Array, required: true, default: () => [] },
    initialPeriod: { type: Object, required: false, default: {} },
});

const lastParams = ref({});
const selectedMonth = ref(props.initialPeriod.month.id);
const selectedYear = ref(props.initialPeriod.year.id);
const selectedStatus = ref();
const selectedErrorType = ref();
const selectedRegency = ref([]);

const columns = [
    {
        title: 'Area',
        key: 'area',
        width: 200,
    },
    {
        title: 'Nama Komersial',
        key: 'nama',
        width: 250, // second longest
    },
    {
        title: 'Bulan',
        key: 'month',
        width: 100,
    },
    {
        title: 'Tahun',
        key: 'year',
        width: 100,
    },
    {
        title: 'Error Type',
        key: 'errorType',
        width: 150,
    },
    {
        title: 'Notes',
        key: 'notes',
        width: 350, // longest
    },
    {
        title: 'Status',
        key: 'status',
        width: 180,
    },
    {
        title: 'Sent / Approved By',
        key: 'confirmation',
        width: 220,
    },
    {
        title: 'Action',
        key: 'action',
        width: 120,
    },
];

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
    const { current, pageSize, sortField, sortOrder, ...filterQuery } = params;
    const size = Number(pageSize);
    const page = Number(current);

    const payload = await fetchJson(
        confirmationDataIndex.url({
            query: {
                start: Math.max(0, (page - 1) * size),
                length: size,
                sortField,
                sortOrder,
                ...filterQuery,
            },
        }),
    );

    return { list: payload.data, total: payload.total, period: payload.period };
};

const {
    data: dataSource,
    run,
    loading,
    current,
    pageSize,
    total,
} = usePagination(queryData, {
    defaultParams: [{
        current: 1, pageSize: 10,
        month: props.initialPeriod.month.id,
        year: props.initialPeriod.year.id
    }],
    pagination: {
        currentKey: 'current',
        pageSizeKey: 'pageSize',
    },
});

const pagination = computed(() => ({
    total: total.value,
    current: current.value,
    pageSize: pageSize.value,
    showSizeChanger: true,
    pageSizeOptions: ['10', '20', '50'],
}));

const handleTableChange = (pag, filters, sorter) => {
    const filterQuery = normalizeTableFilters(filters);

    run({
        ...lastParams.value,
        current: pag.current,
        pageSize: pag.pageSize,
        sortField: sorter.field ?? sorter.columnKey,
        sortOrder: sorter.order,
        ...filterQuery,
    });
};

const handleFilter = () => {
    const { month: _month,
        year: _year,
        status: _status,
        errorType: _errorType,
        regencies: _regencies, ...rest } = lastParams.value;

    run({
        ...rest,
        current: 1,
        ...(selectedMonth.value ? { month: selectedMonth.value } : {}),
        ...(selectedYear.value ? { year: selectedYear.value } : {}),
        ...(selectedStatus.value ? { status: selectedStatus.value } : {}),
        ...(selectedErrorType.value ? { errorType: selectedErrorType.value } : {}),
        ...(selectedRegency.value && selectedRegency.value.length > 0 ? { regencies: selectedRegency.value } : {}),
    });
};

function toTitleCase(str) {
    return str
        .toLowerCase()
        .replace(/\b\w/g, char => char.toUpperCase());
}

function getStatusInfo(status) {
    switch (status) {
        case 'not_confirmed':
            return { color: 'red', label: 'Belum Dikonfirmasi' };
        case 'confirmed':
            return { color: 'orange', label: 'Sudah Dikonfirmasi' };
        case 'approved':
            return { color: 'green', label: 'Approved' };
        case 'pending':
            return { color: 'blue', label: 'Pending' };
        case 'other':
            return { color: 'default', label: 'Status Lain' };
        default:
            return { color: 'default', label: '-' };
    }
}
</script>

<template>

    <Head title="Konfirmasi Error" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-2 sm:p-4">
            <Card>
                <CardHeader class="px-3 py-4 sm:px-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="space-y-1">
                            <CardTitle class="text-lg sm:text-xl">
                                Konfirmasi Error
                            </CardTitle>
                        </div>
                    </div>
                    <div
                        class="mt-3 flex flex-col gap-2 sm:mt-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                            <a-select v-model:value="selectedMonth" placeholder="Semua Bulan" class="w-full sm:w-40"
                                @change="handleFilter">
                                <a-select-option v-for="m in props.months" :key="m.id" :value="m.id">
                                    {{ m.name }}
                                </a-select-option>
                            </a-select>

                            <a-select v-model:value="selectedYear" placeholder="Semua Tahun" class="w-full sm:w-32"
                                @change="handleFilter">
                                <a-select-option v-for="y in props.years" :key="y.id" :value="y.id">
                                    {{ y.name }}
                                </a-select-option>
                            </a-select>

                            <a-select v-model:value="selectedStatus" placeholder="Semua Status" class="w-full sm:w-47"
                                @change="handleFilter" allow-clear>
                                <a-select-option v-for="s in props.statuses" :key="s" :value="s">
                                    <a-tag :color="getStatusInfo(s).color">
                                        {{ getStatusInfo(s).label }}
                                    </a-tag> </a-select-option>
                            </a-select>

                            <a-select v-model:value="selectedErrorType" placeholder="Semua Tipe Error"
                                class="w-full sm:w-42" @change="handleFilter" allow-clear>
                                <a-select-option v-for="e in props.errorTypes" :key="e.id" :value="e.id">
                                    {{ e.column_name }}
                                </a-select-option>
                            </a-select>

                            <a-select max-tag-count="responsive" mode="multiple" v-model:value="selectedRegency"
                                placeholder="Semua Kabupaten/Kota" allow-clear class="w-full sm:w-50"
                                @change="handleFilter">
                                <a-select-option v-for="r in props.regencies" :key="r.id" :value="r.id">
                                    [{{ r.long_code }}] {{ toTitleCase(r.name) }}
                                </a-select-option>
                            </a-select>
                        </div>
                    </div>

                </CardHeader>

                <CardContent class="p-0 sm:px-6 sm:pb-6">
                    <a-table :columns="columns" :row-key="record => record.id" :data-source="dataSource?.list ?? []"
                        :pagination="pagination" :loading="loading" @change="handleTableChange" size="small"
                        :scroll="{ x: 1500 }">
                        <template #bodyCell="{ column, text, record }">
                            <!-- Area Column -->
                            <template v-if="column.key === 'area'">
                                <div>
                                    <!-- Highlight regency -->
                                    <div class="font-semibold">
                                        [{{ record.input.regency?.long_code ?? '-' }}]
                                        {{ toTitleCase(record.input.regency?.name ??
                                            '-') }}
                                    </div>
                                    <!-- Secondary info: full codes -->
                                    <div class="text-sm text-gray-500">
                                        {{ record.input.kode_prov ?? '-' }}
                                        {{ record.input.kode_kab ?? '-' }}
                                        {{ record.input.kode_kec ?? '-' }}
                                        {{ record.input.kode_desa ?? '-' }}
                                    </div>
                                </div>
                            </template>

                            <!-- Nama Komersial Column -->
                            <template v-else-if="column.key === 'nama'">
                                {{ record.input.nama_komersial ?? '-' }}
                            </template>

                            <!-- Month Column -->
                            <template v-else-if="column.key === 'month'">
                                {{ record.input.month?.name ?? '-' }}
                            </template>

                            <!-- Year Column -->
                            <template v-else-if="column.key === 'year'">
                                {{ record.input.year?.name ?? '-' }}
                            </template>

                            <!-- Sent / Approved Column -->
                            <template v-else-if="column.key === 'confirmation'">
                                <div>Sent: {{ record.sentBy?.name ?? '-' }}</div>
                                <div>Approved: {{ record.approvedBy?.name ?? '-' }}</div>
                            </template>

                            <!-- Notes Column -->
                            <template v-else-if="column.key === 'notes'">
                                {{ record.notes ?? '-' }}
                            </template>

                            <!-- Error Type Column -->
                            <template v-else-if="column.key === 'errorType'">
                                <a-tag color="blue" v-if="record.error_type">
                                    {{ record.error_type.column_name }}
                                </a-tag>
                                <a-tag color="default" v-else>-</a-tag>
                            </template>

                            <!-- Status Column -->
                            <template v-else-if="column.key === 'status'">
                                <a-tag v-if="record.status" :color="getStatusInfo(record.status).color">
                                    {{ getStatusInfo(record.status).label }}
                                </a-tag>
                                <a-tag v-else color="default">-</a-tag>
                            </template>

                            <!-- Action Column -->
                            <template v-else-if="column.key === 'action'">
                                <a-button type="primary" size="small" @click="onConfirm(record)">
                                    Confirm
                                </a-button>
                            </template>
                        </template>
                    </a-table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
