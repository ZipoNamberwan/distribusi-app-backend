<script setup lang="js">
import { SearchOutlined, ClearOutlined } from '@ant-design/icons-vue';
import { useMediaQuery } from '@vueuse/core';
import moment from 'moment';
import { ref, computed, watch, h } from 'vue';
import { usePagination } from 'vue-request';
import { useAppearance } from '@/composables/useAppearance';
import { index as rawDataIndex } from '@/routes/data/raw';
import { download as downloadRawData } from '@/routes/data/download';
import RawDataDownloadDialog from '@/custom_components/RawDataDownloadDialog.vue';
import { message } from 'ant-design-vue';
import { router, usePage } from '@inertiajs/vue3';

const isMobile = useMediaQuery('(max-width: 767px)');

const lastParams = ref({});
const searchInput = ref();
const tableKey = ref(0);
const selectedMonth = ref(null);
const selectedYear = ref(null);
const viewColumns = [
    { label: 'Input Data', value: 'input' },
    { label: 'Tabulasi', value: 'tabulation' }
];
const selectedView = ref(viewColumns.map(v => v.value));
const errorFilter = ref(null);

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
    open: {
        type: Boolean,
        required: true,
        default: () => false
    },
});

const { resolvedAppearance } = useAppearance();
const THEME_COLORS = {
    light: ['#e6f4ff', '#f6ffed', '#fff7e6', '#f9f0ff', '#fff0f6'],
    dark: ['#112a45', '#16331e', '#362111', '#281b36', '#361b27']
};
const defaultBg = computed(() => resolvedAppearance.value === 'dark' ? '#1f1f1f' : '#f5f5f5');
const backgroundColors = computed(() => THEME_COLORS[resolvedAppearance.value === 'dark' ? 'dark' : 'light']);

function getBgColor(index) {
    return { background: backgroundColors.value[index] || defaultBg.value };
}

const filteredColumns = computed(() => {
    return columns.filter(col => {
        if (!col.group) return true
        return selectedView.value.includes(col.group)
    }).map(col => {
        if (col.dataIndex === 'nama_komersial' || col.key === 'regency') {
            return {
                ...col,
                fixed: isMobile.value ? false : 'left'
            }
        }
        return col
    })
})

function makeColumn(group, col) {
    const groupColorIndex = {
        input: 0,
        tabulation: 1,
    };

    const colorIndex = groupColorIndex[group];

    const column = {
        ...col,
        group,
        customCell: () => ({ style: getBgColor(colorIndex) }),
        customHeaderCell: () => ({ style: getBgColor(colorIndex) }),
    };

    // auto highlight error columns
    if (col.dataIndex?.startsWith('error_') || col.dataIndex === 'jumlah_error') {
        column.customRender = ({ text }) => {
            const value = Number(text ?? 0);

            return h(
                'span',
                {
                    class: value > 0 ? 'bg-red-100 text-red-700 px-1 rounded' : ''
                },
                value
            );
        };
    }

    return column;
}

const textFilter = {
    customFilterDropdown: true,
    onFilterDropdownOpenChange: visible => {
        if (visible) {
            setTimeout(() => searchInput.value.focus(), 100);
        }
    }
};

const inputColumns = [
    ['Status Kunjungan', 'status_kunjungan', 130],
    ['Jenis Akomodasi', 'jenis_akomodasi', 130],
    ['Kelas Akomodasi', 'kelas_akomodasi', 130],
    ['Room', 'room', 80],
    ['Bed', 'bed', 80],
    ['Room Yesterday', 'room_yesterday', 130],
    ['Room In', 'room_in', 90],
    ['Room Out', 'room_out', 90],
    ['WNA Yesterday', 'wna_yesterday', 120],
    ['WNI Yesterday', 'wni_yesterday', 120],
    ['WNA In', 'wna_in', 80],
    ['WNI In', 'wni_in', 80],
    ['WNA Out', 'wna_out', 80],
    ['WNI Out', 'wni_out', 80],
    ['Room/Day', 'room_per_day', 90],
    ['Bed/Day', 'bed_per_day', 80],
    ['Day', 'day', 60],
];

const tabulationColumns = [
    ['MKTS', 'mkts'],
    ['MKTJ', 'mktj'],
    ['TPK', 'tpk'],
    ['MTA', 'mta'],
    ['TA', 'ta'],
    ['MTNUS', 'mtnus'],
    ['TNUS', 'tnus'],
    ['RLMTA', 'rlmta'],
    ['RLMTNUS', 'rlmtnus'],
    ['MTGAB', 'mtgab'],
    ['TGAB', 'tgab'],
    ['RLMTGAB', 'rlmtgab'],
    ['GPR', 'gpr'],
    ['TPTT', 'tptt'],
    ['Jumlah Hari', 'jumlah_hari', 110],
    ['Error TPK', 'error_tpk'],
    ['Error RLMTA', 'error_rlmta', 110],
    ['Error RLMTNUS', 'error_rlmtnus', 120],
    ['Error GPR', 'error_gpr'],
    ['Error TPTT', 'error_tptt', 100],
    ['Error Hari', 'error_hari', 100],
    ['Jumlah Error', 'jumlah_error', 110],
];

const columns = [
    {
        title: 'Nama Komersial', dataIndex: 'nama_komersial', width: 200, ...textFilter,
        fixed: 'left',
        width: 150
    },
    {
        title: 'Kabupaten',
        key: 'regency',
        width: 130,
        fixed: 'left',
        filters: (props.regencies ?? []).map(s => ({
            text: `${s.long_code} ${s.name}`,
            value: s.id
        })),
        sorter: true,
    },
    { title: 'Alamat', dataIndex: 'alamat', width: 250, ...textFilter },
    { title: 'Periode', key: 'periode', width: 100 },
    { title: 'Kode Kab', dataIndex: 'kode_kab', width: 90, ...textFilter },
    { title: 'Kode Kec', dataIndex: 'kode_kec', width: 90, ...textFilter },
    { title: 'Kode Desa', dataIndex: 'kode_desa', width: 100, ...textFilter },

    // input columns
    ...inputColumns.map(([title, dataIndex, width = 90]) =>
        makeColumn('input', {
            title,
            dataIndex,
            width,
            sorter: true
        })
    ),

    // status column
    makeColumn('input', {
        title: 'Status',
        dataIndex: 'status',
        width: 220,
        ...textFilter
    }),

    // tabulation columns
    ...tabulationColumns.map(([title, dataIndex, width = 90]) =>
        makeColumn('tabulation', {
            title,
            dataIndex,
            width,
            sorter: true
        })
    ),

    { title: 'Dibuat', key: 'created_at', dataIndex: 'created_at', width: 150, sorter: true }
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

const open = computed(() => (props.open));

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

// const handleRefresh = () => run({ ...lastParams.value });

const handleFilter = () => {
    const { month: _month, year: _year, error_filter: _error, ...rest } = lastParams.value;

    run({
        ...rest,
        current: 1,
        ...(selectedMonth.value ? { month: selectedMonth.value } : {}),
        ...(selectedYear.value ? { year: selectedYear.value } : {}),
        ...(errorFilter.value ? { error_filter: errorFilter.value } : {}),
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
    errorFilter.value = null;
    run();
};

const handleSearch = (selectedKeys, confirm, dataIndex) => {
    confirm();
};
const handleReset = clearFilters => {
    clearFilters({ confirm: true });
};



const downloadStatusOpen = ref(false);

const downloading = ref(false);

const submitDownload = () => router.post(downloadRawData().url, {
    month: selectedMonth.value,
    year: selectedYear.value,
}, {
    onStart: () => { downloading.value = true; },
    onFinish: () => { downloading.value = false; },
});

const page = usePage();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash.success) {
            message.success(flash.success, 15);
        }
        if (flash.error) {
            message.error(flash.error, 15);
        }
    },
    { immediate: true },
);
</script>

<template>

    <a-card title="Raw Data dan Tabulasi">
        <template #extra>
            <a-flex gap="small" horizontal>
                <a-button @click="downloadStatusOpen = true">Status</a-button>
                <a-button type="primary" :loading="downloading" @click="submitDownload">Download</a-button>
            </a-flex>
        </template>
        <div class="flex flex-wrap items-center gap-3 mb-4 p-3 rounded-lg border">
            <!-- <span class="text-sm font-medium text-gray-500 shrink-0">Filter Periode</span> -->
            <a-select v-model:value="selectedMonth" placeholder="Semua Bulan" class="w-44" @change="handleFilter"
                allow-clear>
                <a-select-option v-for="month in props.months" :key="month.id" :value="month.id">
                    {{ month.name }}
                </a-select-option>
            </a-select>
            <a-select v-model:value="selectedYear" placeholder="Semua Tahun" class="w-44" @change="handleFilter"
                allow-clear>
                <a-select-option v-for="year in props.years" :key="year.id" :value="year.id">
                    {{ year.name }}
                </a-select-option>
            </a-select>
            <a-radio-group v-model:value="errorFilter" button-style="solid" @change="handleFilter">
                <a-radio-button :value="null">Semua</a-radio-button>
                <a-radio-button value="has_error">Ada Error</a-radio-button>
                <a-radio-button value="no_error">Tanpa Error</a-radio-button>
            </a-radio-group>
            <a-select max-tag-count="responsive" mode="multiple" v-model:value="selectedView"
                placeholder="Pilih View Kolom" class="w-44">
                <a-select-option v-for="view in viewColumns" :key="view.value" :value="view.value">
                    {{ view.label }}
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

        <a-table :key="tableKey" :scroll="{ x: 1600, y: 600 }" :columns="filteredColumns" :row-key="record => record.id"
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
    </a-card>

    <a-modal style="top: 20px" v-model:open="downloadStatusOpen" title="Status Download Raw Data" :footer="null" width="90%">
        <RawDataDownloadDialog :open="downloadStatusOpen" />
    </a-modal>
</template>