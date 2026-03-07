<script setup lang="js">
import { ref, watch, computed } from 'vue';
import { usePagination } from 'vue-request';
import { index as rawDataIndex } from '@/routes/data/raw';
import moment from 'moment';
import { SearchOutlined, ClearOutlined } from '@ant-design/icons-vue';


const open = ref(false);
const lastParams = ref({});
const searchInput = ref();
const tableKey = ref(0);
const selectedMonth = ref(null);
const selectedYear = ref(null);

const props = defineProps({
    regencies: {
        type: Array,
        required: false,
        default: () => []
    },
    months: {
        type: Array,
        required: false,
        default: () => []
    },
    years: {
        type: Array,
        required: false,
        default: () => []
    },
});

const columns = [
    { title: 'Periode', key: 'periode', width: 100, fixed: 'left' },
    {
        title: 'Kabupaten', key: 'regency', width: 140, fixed: 'left',
        filters: (props.regencies ?? []).map(s => ({ text: `${s.long_code} ${s.name}`, value: s.id })),
    },
    {
        title: 'Nama Komersial', dataIndex: 'nama_komersial', width: 200,
        customFilterDropdown: true,
        onFilterDropdownOpenChange: visible => {
            if (visible) {
                setTimeout(() => {
                    searchInput.value.focus();
                }, 100);
            }
        },
    },
    {
        title: 'Alamat', dataIndex: 'alamat', width: 250,
        customFilterDropdown: true,
        onFilterDropdownOpenChange: visible => {
            if (visible) setTimeout(() => searchInput.value.focus(), 100);
        },
    },
    {
        title: 'Kode Kab', dataIndex: 'kode_kab', width: 90,
        customFilterDropdown: true,
        onFilterDropdownOpenChange: visible => {
            if (visible) setTimeout(() => searchInput.value.focus(), 100);
        },
    },
    {
        title: 'Kode Kec', dataIndex: 'kode_kec', width: 90,
        customFilterDropdown: true,
        onFilterDropdownOpenChange: visible => {
            if (visible) setTimeout(() => searchInput.value.focus(), 100);
        },
    },
    {
        title: 'Kode Desa', dataIndex: 'kode_desa', width: 100,
        customFilterDropdown: true,
        onFilterDropdownOpenChange: visible => {
            if (visible) setTimeout(() => searchInput.value.focus(), 100);
        },
    },
    { title: 'Status Kunjungan', dataIndex: 'status_kunjungan', width: 130, sorter: true },
    { title: 'Jenis Akomodasi', dataIndex: 'jenis_akomodasi', width: 130, sorter: true },
    { title: 'Kelas Akomodasi', dataIndex: 'kelas_akomodasi', width: 130, sorter: true },
    { title: 'Room', dataIndex: 'room', width: 80, sorter: true },
    { title: 'Bed', dataIndex: 'bed', width: 80, sorter: true },
    { title: 'Room Yesterday', dataIndex: 'room_yesterday', width: 130, sorter: true },
    { title: 'Room In', dataIndex: 'room_in', width: 90, sorter: true },
    { title: 'Room Out', dataIndex: 'room_out', width: 90, sorter: true },
    { title: 'WNA Yesterday', dataIndex: 'wna_yesterday', width: 120, sorter: true },
    { title: 'WNI Yesterday', dataIndex: 'wni_yesterday', width: 120, sorter: true },
    { title: 'WNA In', dataIndex: 'wna_in', width: 80, sorter: true },
    { title: 'WNI In', dataIndex: 'wni_in', width: 80, sorter: true },
    { title: 'WNA Out', dataIndex: 'wna_out', width: 80, sorter: true },
    { title: 'WNI Out', dataIndex: 'wni_out', width: 80, sorter: true },
    { title: 'Room/Day', dataIndex: 'room_per_day', width: 90, sorter: true },
    { title: 'Bed/Day', dataIndex: 'bed_per_day', width: 80, sorter: true },
    { title: 'Day', dataIndex: 'day', width: 60, sorter: true },
    {
        title: 'Status', dataIndex: 'status', width: 220,
        customFilterDropdown: true,
        onFilterDropdownOpenChange: visible => {
            if (visible) setTimeout(() => searchInput.value.focus(), 100);
        },
    },
    { title: 'Dibuat', key: 'created_at', dataIndex: 'created_at', width: 150, sorter: true },
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
    const { current = 1, pageSize = 10, sortField, sortOrder, ...filterQuery } = params;
    const size = Number(pageSize);
    const page = Number(current);

    const payload = await fetchJson(
        rawDataIndex.url({
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
    current,
    pageSize,
    total,
} = usePagination(queryData, {
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
        current: pag.current,
        pageSize: pag.pageSize,
        sortField: sorter.field,
        sortOrder: sorter.order,
        ...filterQuery,
    });
};

const handleRefresh = () => run({ ...lastParams.value });

const handlePeriodFilter = () => {
    const { month: _month, year: _year, ...rest } = lastParams.value;
    run({
        ...rest,
        current: 1,
        ...(selectedMonth.value ? { month: selectedMonth.value } : {}),
        ...(selectedYear.value ? { year: selectedYear.value } : {}),
    });
};

watch(open, (isOpen) => {
    if (isOpen) {
        tableKey.value++;
        selectedMonth.value = null;
        selectedYear.value = null;
        run();
    }
});

const handleResetAll = () => {
    tableKey.value++;
    selectedMonth.value = null;
    selectedYear.value = null;
    run();
};

const handleSearch = (selectedKeys, confirm, dataIndex) => {
    confirm();
};
const handleReset = clearFilters => {
    clearFilters({ confirm: true });
};

</script>

<template>
    <div>
        <a-button @click="open = true">
            Lihat
        </a-button>
        <a-modal style="top: 20px" v-model:open="open" title="Raw Data Terupload" :footer="null" width="90%">

            <div class="flex flex-wrap items-center gap-3 mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                <span class="text-sm font-medium text-gray-500 shrink-0">Filter Periode</span>
                <a-select v-model:value="selectedMonth" placeholder="Semua Bulan" allow-clear class="w-44"
                    @change="handlePeriodFilter">
                    <a-select-option v-for="month in props.months" :key="month.id" :value="month.id">
                        {{ month.name }}
                    </a-select-option>
                </a-select>
                <a-select v-model:value="selectedYear" placeholder="Semua Tahun" allow-clear class="w-44"
                    @change="handlePeriodFilter">
                    <a-select-option v-for="year in props.years" :key="year.id" :value="year.id">
                        {{ year.name }}
                    </a-select-option>
                </a-select>
                <a-tooltip title="Reset Semua Filter">
                    <a-button @click="handleResetAll">
                        <template #icon>
                            <ClearOutlined />
                        </template>
                    </a-button>
                </a-tooltip>
            </div>

            <a-table :key="tableKey" :scroll="{ x: 1600, y: 600 }" :columns="columns" :row-key="record => record.id"
                :data-source="dataSource?.list ?? []" :pagination="pagination" :loading="loading"
                @change="handleTableChange" size="small">
                <template #bodyCell="{ column, text, record }">
                    <template v-if="column.key === 'periode'">
                        <span>{{ record.month?.name }} {{ record.year?.name }}</span>
                    </template>
                    <template v-if="column.key === 'month'">
                        <span>{{ record.month?.name }}</span>
                    </template>
                    <template v-else-if="column.key === 'year'">
                        <span>{{ record.year?.name }}</span>
                    </template>
                    <template v-else-if="column.key === 'regency'">
                        <span>[{{ record.regency?.long_code }}] {{ record.regency?.name }}</span>
                    </template>
                    <template v-else-if="column.dataIndex === 'kode_kab'">
                        <span>{{ record.regency?.short_code }}</span>
                    </template>
                    <template v-else-if="column.key === 'created_at'">
                        <span :title="moment(text).format('DD MMM YYYY HH:mm:ss')">{{ moment(text).fromNow() }}</span>
                    </template>
                </template>

                <template #customFilterDropdown="{ setSelectedKeys, selectedKeys, confirm, clearFilters, column }">
                    <div style="padding: 8px">
                        <a-input ref="searchInput" :placeholder="`Search ${column.dataIndex}`" :value="selectedKeys[0]"
                            style="width: 188px; margin-bottom: 8px; display: block"
                            @change="e => setSelectedKeys(e.target.value ? [e.target.value] : [])"
                            @pressEnter="handleSearch(selectedKeys, confirm, column.dataIndex)" />
                        <a-button type="primary" size="small" style="width: 90px; margin-right: 8px"
                            @click="handleSearch(selectedKeys, confirm, column.dataIndex)">
                            <template #icon>
                                <SearchOutlined />
                            </template>
                            Search
                        </a-button>
                        <a-button size="small" style="width: 90px" @click="handleReset(clearFilters)">
                            Reset
                        </a-button>
                    </div>
                </template>
                <template #customFilterIcon="{ filtered }">
                    <search-outlined :style="{ color: filtered ? '#108ee9' : undefined }" />
                </template>
            </a-table>
        </a-modal>
    </div>
</template>