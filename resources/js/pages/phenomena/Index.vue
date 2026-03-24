<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as phenomenaPage } from '@/routes/user/page';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ref, computed, onMounted, onUnmounted, h } from 'vue';
import { index as dataIndex } from '@/routes/phenomena/data';
import { SearchOutlined } from '@ant-design/icons-vue';
import PhenomenaMobile from '@/custom_components/mobile/PhenomenaMobile.vue';
import PhenomenaForm from '@/custom_components/PhenomenaForm.vue';
import { message } from 'ant-design-vue'

const breadcrumbs = [
    {
        title: 'Konfirmasi Fenomena',
        href: phenomenaPage().url,
    },
];

const page = usePage();
const roles = page.props.auth.roles;
const userRegency = page.props.auth.user.regency_id;

const props = defineProps({
    months: { type: Array, required: true, default: () => [] },
    years: { type: Array, required: true, default: () => [] },
    indicators: { type: Array, required: true, default: () => [] },
    regencies: { type: Array, required: true, default: () => [] },
    initialPeriod: { type: Object, required: false, default: {} },
});

const selectedMonth = ref(props.initialPeriod.month.id);
const selectedYear = ref(props.initialPeriod.year.id);
const selectedRegency = ref([]);
const selectedPeriod = ref(props.initialPeriod);
const searchInput = ref();
const searchKeyword = ref(null);
const statusFilter = ref(null);

const rows = ref([]);
const filteredRows = ref([]);
const loading = ref(false);

const phenomenaForm = ref({});
const openFormDialog = ref(false);

const columns = computed(() => {
    const baseColumns = [
        {
            title: 'Kab/Kota',
            key: 'regency',
            width: 150,
            sorter: (a, b) => (a.regency?.long_code ?? '').localeCompare(b.regency?.long_code ?? ''),
        },
        {
            title: 'Periode',
            key: 'period',
            width: 100,
        },
        {
            title: 'Deskripsi',
            key: 'description',
            width: 350,
            customFilterDropdown: true,
            onFilter: (value, record) => record.phenomena?.description.toString().toLowerCase().includes(value.toLowerCase()),
            onFilterDropdownOpenChange: visible => {
                if (visible) {
                    setTimeout(() => searchInput.value.focus(), 100);
                }
            }
        }
    ];

    if (!roles.includes('adminprov')) {
        baseColumns.push({
            title: 'Aksi',
            key: 'action',
            width: 100,
            fixed: 'right',
        });
    }

    return baseColumns;
});

const fetchData = async () => {
    loading.value = true;
    selectedRegency.value = []; // reset regency filter when fetching new data
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
            filteredRows.value = full.data;
        } else {
            rows.value = first.data;
            filteredRows.value = first.data;
        }
        selectedPeriod.value = first.period;
    } finally {
        loading.value = false;
    }
};

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

function toTitleCase(str) {
    return str
        .toLowerCase()
        .replace(/\b\w/g, char => char.toUpperCase());
}

const handleSearch = (selectedKeys, confirm, dataIndex) => {
    confirm();
};

const handleReset = clearFilters => {
    clearFilters({ confirm: true });
};

const onSearch = () => {
    // filter the rows based on searchKeyword
    if (!searchKeyword.value) {
        filteredRows.value = rows.value;
        return;
    }
    const keyword = searchKeyword.value.toLowerCase();
    filteredRows.value = rows.value.filter(row => {
        const regencyName = row.regency ? `[${row.regency.long_code}] ${row.regency.name}`.toLowerCase() : '';
        const period = row.period ? `${row.period.month.name} ${row.period.year.name}`.toLowerCase() : '';
        const description = row.phenomena?.description ? row.phenomena.description.toLowerCase() : '';
        return regencyName.includes(keyword) || period.includes(keyword) || description.includes(keyword);
    });
};

const handleEdit = (record) => {
    phenomenaForm.value = record;
    openFormDialog.value = true;
};

const handleSaved = () => {
    openFormDialog.value = false   // close dialog
    message.success('Fenomena berhasil disimpan', 7) // show message

    fetchData();
}

const handleStatusFilter = () => {
    if (!statusFilter.value) {
        filteredRows.value = rows.value;
        return;
    }
    // filter rows based on statusFilter, if value is done then filter that description is not null, if not_done then filter that description is null
    if (statusFilter.value === 'done') {
        filteredRows.value = rows.value.filter((r) => r.phenomena !== null);
    } else {
        filteredRows.value = rows.value.filter((r) => r.phenomena === null);
    }
};

onMounted(() => {
    // window.addEventListener('resize', onResize);
    fetchData();
});

// onUnmounted(() => window.removeEventListener('resize', onResize));
</script>

<template>

    <Head title="Konfirmasi Fenomena" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-2 sm:p-4">
            <Card>
                <CardHeader class="px-3 sm:px-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="space-y-1">
                            <CardTitle class="text-lg sm:text-xl">
                                Fenomena terkait VHTS
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
                            <p class="text-xs text-muted-foreground sm:text-sm">
                                List fenomena menurut kabupaten/kota
                            </p>
                        </div>
                    </div>
                    <a-row :gutter="[8, 8]" class="px-0">
                        <a-col :xs="24" :sm="12" :md="8" :lg="5">
                            <a-select v-model:value="selectedMonth" placeholder="Semua Bulan" class="w-full"
                                @change="fetchData">
                                <a-select-option v-for="m in props.months" :key="m.id" :value="m.id">
                                    {{ m.name }}
                                </a-select-option>
                            </a-select>
                        </a-col>
                        <a-col :xs="24" :sm="12" :md="8" :lg="5">
                            <a-select v-model:value="selectedYear" placeholder="Semua Tahun" class="w-full"
                                @change="fetchData">
                                <a-select-option v-for="y in props.years" :key="y.id" :value="y.id">
                                    {{ y.name }}
                                </a-select-option>
                            </a-select>
                        </a-col>
                        <a-col :xs="24" :sm="12" :md="8" :lg="5">
                            <a-select max-tag-count="responsive" mode="multiple" v-model:value="selectedRegency"
                                placeholder="Semua Kabupaten/Kota" allow-clear class="w-full"
                                @change="filterRegencyFromData()" :filter-option="filterRegency">
                                <a-select-option v-for="r in props.regencies" :key="r.id" :value="r.id">
                                    [{{ r.long_code }}] {{ toTitleCase(r.name) }}
                                </a-select-option>
                            </a-select>
                        </a-col>
                        <a-col :xs="24" :sm="12" :md="8" :lg="5">
                            <a-input @change="onSearch" allow-clear v-model:value="searchKeyword" placeholder="Cari..."
                                class="w-full" />
                        </a-col>
                        <a-col :xs="24" :sm="24" :md="12" :lg="12">
                            <a-radio-group v-model:value="statusFilter" button-style="solid"
                                @change="handleStatusFilter">
                                <a-radio-button :value="null">Semua</a-radio-button>
                                <a-radio-button value="done">Sudah</a-radio-button>
                                <a-radio-button value="not_done">Belum</a-radio-button>
                            </a-radio-group>
                        </a-col>
                    </a-row>
                </CardHeader>

                <CardContent class="p-0 sm:px-4 sm:pb-6">
                    <!-- Mobile Card View (visible only on mobile) -->
                    <div class="sm:hidden">
                        <PhenomenaMobile :data="filteredRows" :loading="loading" empty-message="Tidak ada data"
                            @edit="handleEdit" />
                    </div>

                    <!-- Desktop Table View (hidden on mobile, visible on sm and up) -->
                    <div class="hidden overflow-hidden sm:block sm:rounded-lg sm:border sm:border-border">
                        <a-table :scroll="{ x: '70vw', y: '70vh' }" :columns="columns" :row-key="record => record.id"
                            :data-source="filteredRows" :loading="loading" :pagination="false" size="small" bordered
                            tableLayout="fixed">

                            <template #bodyCell="{ column, record }">

                                <!-- Regency Column -->
                                <template v-if="column.key === 'regency'">
                                    [{{ record.regency.long_code }}] {{ toTitleCase(record.regency.name) }}
                                </template>

                                <!-- Period Column -->
                                <template v-else-if="column.key === 'period'">
                                    {{ record.period.month.name }} {{ record.period.year.name }}
                                </template>

                                <!-- Description Column -->
                                <template v-else-if="column.key === 'description'">
                                    <span v-if="record.phenomena">
                                        {{ record.phenomena.description }}
                                    </span>
                                    <a-tag v-else color="red">Belum Diisi</a-tag>
                                </template>

                                <!-- Action Column -->
                                <template v-else-if="column.key === 'action'">
                                    <a-space size="small">
                                        <a-button
                                            v-if="userRegency === record.regency_id || userRegency === record.regency?.id"
                                            type="primary" size="small" @click="handleEdit(record)">
                                            Edit
                                        </a-button>
                                    </a-space>
                                </template>
                            </template>
                            <template #customFilterIcon="{ filtered }">
                                <search-outlined :style="{ color: filtered ? '#108ee9' : undefined }" />
                            </template>
                            <template
                                #customFilterDropdown="{ setSelectedKeys, selectedKeys, confirm, clearFilters, column }">
                                <div style="padding: 8px">
                                    <a-input ref="searchInput" :placeholder="`Search ${column.dataIndex}`"
                                        :value="selectedKeys[0]"
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
                        </a-table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <a-modal v-model:open="openFormDialog" :title="'Konfirmasi Fenomena'" :footer="null">
            <PhenomenaForm :key="phenomenaForm?.id ?? 'create'" :phenomena="phenomenaForm" @saved="handleSaved" />
        </a-modal>
    </AppLayout>
</template>
