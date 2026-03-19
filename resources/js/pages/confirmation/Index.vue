<script setup>
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { message } from 'ant-design-vue'
import AppLayout from '@/layouts/AppLayout.vue';
import { index as confirmationPage } from '@/routes/user/page';
import { ref, computed, watch, h } from 'vue';
import { usePagination } from 'vue-request';
import { index as confirmationDataIndex } from '@/routes/confirmation/data';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { store as confirmationStore } from '@/routes/confirmation/confirm';
import { store as approveStore } from '@/routes/confirmation/approve';
import { HomeOutlined, EnvironmentOutlined, TableOutlined, InfoCircleOutlined } from '@ant-design/icons-vue';
import ConfirmationsMobile from '@/custom_components/mobile/ConfirmationsMobile.vue';
import debounce from 'lodash/debounce'

const breadcrumbs = [
    {
        title: 'Konfirmasi Error',
        href: confirmationPage().url,
    },
];

const page = usePage();
const props = defineProps({
    months: { type: Array, required: true, default: () => [] },
    years: { type: Array, required: true, default: () => [] },
    errorTypes: { type: Array, required: true, default: () => [] },
    regencies: { type: Array, required: true, default: () => [] },
    statuses: { type: Array, required: true, default: () => [] },
    initialPeriod: { type: Object, required: false, default: {} },
});

const roles = page.props.auth.roles;

const lastParams = ref({});
const selectedMonth = ref(props.initialPeriod.month.id);
const selectedYear = ref(props.initialPeriod.year.id);
const selectedStatus = ref();
const selectedErrorType = ref();
const selectedRegency = ref([]);
const selectedConfirmation = ref({});
const searchKeyword = ref(null);
const isFormDialogOpen = ref(false);
const formKey = ref(0);
const formRef = ref(null);
const form = useForm({
    id: null,
    notes: null,
    record: null,
});
const selectedRowKeys = ref([])

const rowSelection = computed(() => ({
    selectedRowKeys: selectedRowKeys.value,
    preserveSelectedRowKeys: true,
    onChange: (keys) => {
        selectedRowKeys.value = keys
    },
    getCheckboxProps: (record) => ({
        disabled: record.status !== 'approved' && record.status !== 'confirmed' && record.status !== 'rejected',
    })
}))

const rules = {
    notes: [
        { required: true, message: 'Catatan masih kosong', trigger: 'change' },
    ],
}

const columns = [
    {
        title: 'Nama Komersial',
        key: 'nama',
        width: 150, // second longest
        fixed: 'left',
    },
    {
        title: 'Wilayah',
        key: 'area',
        width: 120,
    },
    {
        title: 'Status',
        key: 'status',
        width: 180,
    },
    {
        title: 'Periode',
        key: 'period',
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
        title: 'Sent / Approved By',
        key: 'confirmation',
        width: 220,
    },
    {
        title: 'Action',
        key: 'action',
        width: 170,
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

const onSearch = () => {
    const { search: _search, ...rest } = lastParams.value;

    run({
        ...rest,
        current: 1,
        ...(searchKeyword.value ? { search: searchKeyword.value } : {}),
    });
};
const debouncedSearch = debounce(onSearch, 500)

const handleRefresh = () => run({ ...lastParams.value });

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
        case 'rejected':
            return { color: 'volcano', label: 'Rejected' };
        case 'pending':
            return { color: 'blue', label: 'Pending' };
        case 'other':
            return { color: 'default', label: 'Status Lain' };
        default:
            return { color: 'default', label: '-' };
    }
}

const onConfirm = ((record) => {
    isFormDialogOpen.value = true
    selectedConfirmation.value = record
    form.notes = record.notes
    form.id = record.id
    form.record = record
})

const submit = () => {
    formRef.value
        .validate()
        .then(() => {
            form.post(confirmationStore().url, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    formRef.value.resetFields();
                    handleRefresh();
                    isFormDialogOpen.value = false;
                },
            });
        })
        .catch(() => {
            // console.log('client validation failed')
        })
};

const filterRegency = (input, option) => {
    const regency = props.regencies.find(r => r.id === option.value)
    if (!regency) return false
    const label = `[${regency.long_code}] ${toTitleCase(regency.name)}`.toLowerCase()
    return label.includes(input.toLowerCase())
}

const handleApprove = (ids, status) => {
    return new Promise((resolve, reject) => {
        router.post(approveStore().url,
            {
                ids: ids,
                status: status
            }, {
            preserveScroll: true,

            onSuccess: () => {
                selectedRowKeys.value = []
                handleRefresh()
                resolve()
            },

            onError: (errors) => {
                const firstError = Object.values(errors)[0]

                if (firstError) {
                    message.error(firstError)
                } else {
                    message.error('Terjadi kesalahan')
                }

                reject(errors)
            }
        })
    })
}

watch(isFormDialogOpen, (isOpen) => {
    if (isOpen) {
        formKey.value++;
    }
});

watch(
    () => page.props.flash,
    (flash) => {
        if (flash.success) {
            message.success(flash.success, 5)
        }

        if (flash.error) {
            message.error(flash.error, 5)
        }
    },
    { immediate: true }
);
</script>

<template>

    <Head title="Konfirmasi Error" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-2 sm:p-4">
            <Card>
                <CardHeader class="px-3 sm:px-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="space-y-1">
                            <CardTitle class="text-lg sm:text-xl">
                                Konfirmasi Error
                            </CardTitle>
                        </div>
                    </div>
                    <a-row class="px-2">
                        <a-col :span="24">
                            <a-input @change="debouncedSearch" allow-clear v-model:value="searchKeyword"
                                placeholder="Cari..." />
                        </a-col>
                    </a-row>
                    <a-row :gutter="[8, 8]" class="px-2">
                        <a-col :xs="24" :sm="12" :md="8" :lg="5">
                            <a-select v-model:value="selectedMonth" placeholder="Semua Bulan" class="w-full"
                                @change="handleFilter">
                                <a-select-option v-for="m in props.months" :key="m.id" :value="m.id">
                                    {{ m.name }}
                                </a-select-option>
                            </a-select>
                        </a-col>
                        <a-col :xs="24" :sm="12" :md="8" :lg="4">
                            <a-select v-model:value="selectedYear" placeholder="Semua Tahun" class="w-full"
                                @change="handleFilter">
                                <a-select-option v-for="y in props.years" :key="y.id" :value="y.id">
                                    {{ y.name }}
                                </a-select-option>
                            </a-select>
                        </a-col>
                        <a-col :xs="24" :sm="12" :md="8" :lg="5">
                            <a-select v-model:value="selectedStatus" placeholder="Semua Status" class="w-full"
                                @change="handleFilter" allow-clear>
                                <a-select-option v-for="s in props.statuses" :key="s" :value="s">
                                    <a-tag :color="getStatusInfo(s).color">
                                        {{ getStatusInfo(s).label }}
                                    </a-tag> </a-select-option>
                            </a-select>
                        </a-col>
                        <a-col :xs="24" :sm="12" :md="8" :lg="5">
                            <a-select v-model:value="selectedErrorType" placeholder="Semua Tipe Error" class="w-full"
                                @change="handleFilter" allow-clear>
                                <a-select-option v-for="e in props.errorTypes" :key="e.id" :value="e.id">
                                    {{ e.column_name }}
                                </a-select-option>
                            </a-select>
                        </a-col>
                        <a-col :xs="24" :sm="12" :md="8" :lg="5">
                            <a-select max-tag-count="responsive" mode="multiple" v-model:value="selectedRegency"
                                placeholder="Semua Kabupaten/Kota" allow-clear class="w-full" @change="handleFilter"
                                :filter-option="filterRegency">
                                <a-select-option v-for="r in props.regencies" :key="r.id" :value="r.id">
                                    [{{ r.long_code }}] {{ toTitleCase(r.name) }}
                                </a-select-option>
                            </a-select>
                        </a-col>
                    </a-row>


                </CardHeader>

                <CardContent class="p-0 sm:px-6 sm:pb-6">

                    <a-row :gutter="[8, 8]" class="px-4 sm:px-2 mb-3" v-if="roles.includes('adminprov')">
                        <a-col span="24">
                            <div class="flex gap-2 mt-2 sm:mt-0">
                                <a-popconfirm :disabled="!selectedRowKeys.length" title="Approve semua?"
                                    ok-text="Ya, approve semua" cancel-text="Batal"
                                    @confirm="handleApprove(selectedRowKeys, 'approved')">
                                    <a-button type="primary" :disabled="!selectedRowKeys.length">
                                        Approve All
                                    </a-button>
                                </a-popconfirm>
                                <a-popconfirm :disabled="!selectedRowKeys.length" title="Reject semua?"
                                    ok-text="Ya, reject semua" ok-type="danger" cancel-text="Batal"
                                    @confirm="handleApprove(selectedRowKeys, 'rejected')">
                                    <a-button type="primary" danger :disabled="!selectedRowKeys.length">
                                        Reject All
                                    </a-button>
                                </a-popconfirm>
                            </div>
                        </a-col>
                    </a-row>
                    <!-- Mobile Card View (visible only on mobile) -->
                    <div class="sm:hidden">
                        <ConfirmationsMobile :data="dataSource?.list ?? []" :loading="loading" :pagination="pagination"
                            v-model:selectedRowKeys="selectedRowKeys" empty-message="Tidak ada data"
                            @confirm="onConfirm" @approve="handleApprove"
                            @page-change="({ current, pageSize }) => handleTableChange({ current, pageSize }, {}, {})" />
                    </div>

                    <!-- Desktop Table View (hidden on mobile, visible on sm and up) -->
                    <div class="hidden overflow-hidden sm:block sm:rounded-lg sm:border sm:border-border">

                        <a-table :row-selection="roles.includes('adminprov') ? rowSelection : null" :columns="columns"
                            :row-key="record => record.id" :data-source="dataSource?.list ?? []"
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

                                <!-- Period Column -->
                                <template v-else-if="column.key === 'period'">
                                    {{ record.input.month?.name ?? '-' }} {{ record.input.year?.name ?? '-' }}

                                </template>

                                <!-- Sent / Approved Column -->
                                <template v-else-if="column.key === 'confirmation'">
                                    <span>Confirmed: {{ record.sent_by?.name ?? '-' }}</span>
                                    <br />
                                    <span>Approved: {{ record.approved_by?.name ?? '-' }}</span>
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

                                    <!-- adminkab: show Confirm only if not yet approved -->
                                    <template v-if="roles.includes('adminkab') && record.status !== 'approved'">
                                        <a-button type="primary" size="small" @click="onConfirm(record)">
                                            Confirm
                                        </a-button>
                                    </template>

                                    <!-- adminprov: Approve / Reject with popconfirm -->
                                    <template v-else-if="roles.includes('adminprov') &&
                                        ['confirmed', 'approved', 'rejected'].includes(record.status)">
                                        <a-space>
                                            <a-popconfirm :title="`Approve?`" ok-text="Ya, approve" cancel-text="Batal"
                                                @confirm="handleApprove([record.id], 'approved')">
                                                <a-button type="primary" size="small" ghost>
                                                    Approve
                                                </a-button>
                                            </a-popconfirm>

                                            <a-popconfirm :title="`Reject?`" ok-text="Ya, reject" cancel-text="Batal"
                                                ok-type="danger" @confirm="handleApprove([record.id], 'rejected')">
                                                <a-button type="primary" size="small" danger ghost>
                                                    Reject
                                                </a-button>
                                            </a-popconfirm>
                                        </a-space>
                                    </template>
                                </template>
                            </template>
                        </a-table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <a-modal v-model:open="isFormDialogOpen" :footer="null" title="Tindak Lanjut Error" :width="520">
            <div class="flex flex-col gap-5 pt-4">

                <!-- Record info (Grid layout) -->
                <div class="grid grid-cols-2 gap-4 p-4 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-800">
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">
                            <HomeOutlined />
                            <span>Komersial</span>
                        </div>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ form.record?.input?.nama_komersial ?? '-' }}
                        </span>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">
                            <EnvironmentOutlined />
                            <span>Wilayah</span>
                        </div>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            [{{ form.record?.input?.regency?.long_code ?? '-' }}] {{ form.record?.input?.regency?.name ?? '-' }}
                        </span>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">
                            <TableOutlined />
                            <span>Kode Valid</span>
                        </div>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100 font-mono bg-white dark:bg-gray-900 px-2 py-0.5 rounded shadow-sm self-start border border-gray-200 dark:border-gray-700">
                            {{ [form.record?.input?.kode_kab, form.record?.input?.kode_kec, form.record?.input?.kode_des].filter(Boolean).join('') || '-' }}
                        </span>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center gap-1.5 text-xs text-red-500 dark:text-red-400 uppercase tracking-wider font-semibold">
                            <InfoCircleOutlined />
                            <span>Error Flag</span>
                        </div>
                        <div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400 border border-red-200 dark:border-red-500/30">
                                {{ form.record?.error_type?.column_name ?? '-' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <a-form :key="formKey" layout="vertical" :model="form" :rules="rules" ref="formRef">
                    <a-form-item name="notes" :validate-status="form.errors.notes ? 'error' : undefined" :help="form.errors.notes" class="mb-0">
                        <template #label>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Catatan Penyelesaian</span>
                        </template>
                        <a-textarea v-model:value="form.notes" placeholder="Berikan alasan atau detail penyelesaian..." :auto-size="{ minRows: 4, maxRows: 8 }" class="!rounded-lg" />
                    </a-form-item>
                </a-form>

                <!-- Actions -->
                <div class="flex justify-end gap-3 pt-3 mt-1 border-t border-gray-100 dark:border-gray-800">
                    <a-button @click="isFormDialogOpen = false" class="!rounded-lg">Kembali</a-button>
                    <a-button type="primary" :loading="form.processing" @click="submit" class="!rounded-lg shadow-md shadow-blue-500/20 px-6">Simpan Konfirmasi</a-button>
                </div>

            </div>
        </a-modal>
    </AppLayout>
</template>
