<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePagination } from 'vue-request';
import { ref, computed } from 'vue';
import { index as userPage } from '@/routes/user/page';
import { index as userDataIndex } from '@/routes/user/data';
import { index as userDelete } from '@/routes/user/delete';
import UserForm from '@/custom_components/UserForm.vue';
import { message } from 'ant-design-vue'

const props = defineProps({
    regencies: {
        type: Array,
        required: false,
        default: () => []
    },
    roles: {
        type: Array,
        required: false,
        default: () => []
    },
});

const breadcrumbs = [
    {
        title: 'Manajemen user',
        href: userPage().url,
    },
];

const lastParams = ref({});
const searchInput = ref();
const userForm = ref({});
const openFormDialog = ref(false);

const columns = [
    {
        title: 'Nama', dataIndex: 'name', width: 180, customFilterDropdown: true,
        onFilterDropdownOpenChange: visible => {
            if (visible) setTimeout(() => searchInput.value.focus(), 100);
        },
        sorter: true,

    },
    {
        title: 'Email', dataIndex: 'email', width: 220, customFilterDropdown: true,
        onFilterDropdownOpenChange: visible => {
            if (visible) setTimeout(() => searchInput.value.focus(), 100);
        },
        sorter: true,

    },
    {
        title: 'Regency',
        dataIndex: 'regency',
        width: 200,
        filters: (props.regencies ?? []).map(s => ({ text: `${s.long_code} ${s.name}`, value: s.id })),
        sorter: true,
        customRender: ({ record }) => `[${record.regency?.long_code}] ${record.regency?.name}` || '-',
    },
    {
        title: 'Roles',
        dataIndex: 'roles',
        width: 180,
        filters: (props.roles ?? []).map(s => ({ text: s.name, value: s.name })),
        sorter: true,
        customRender: ({ record }) =>
            Array.isArray(record.roles) && record.roles.length
                ? record.roles.map(role => role.name).join(', ')
                : '-',
    },
    {
        title: 'Aksi',
        key: 'action',
        width: 140,
        align: 'center',
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
        userDataIndex.url({
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
    defaultParams: [{ current: 1, pageSize: 20 }],
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
        sortField: sorter.field ?? sorter.columnKey,
        sortOrder: sorter.order,
        ...filterQuery,
    });
};

const handleEdit = (record) => {
    userForm.value = record;
    openFormDialog.value = true;
};

const handleDelete = (record) => {
    return new Promise((resolve, reject) => {
        router.delete(userDelete(record.id).url, {
            preserveScroll: true,

            onSuccess: () => {
                message.success('User berhasil dihapus')
                handleRefresh()
                resolve()
            },

            onError: () => {
                message.error('Gagal menghapus user')
                reject()
            }
        })
    })
}

const handleSearch = (selectedKeys, confirm, dataIndex) => {
    confirm();
};
const handleReset = clearFilters => {
    clearFilters({ confirm: true });
};
const openForm = (rec) => {
    userForm.value = rec;
    openFormDialog.value = true;
};
const handleRefresh = () => run({ ...lastParams.value });

const handleSaved = () => {
    openFormDialog.value = false   // close dialog
    message.success('User berhasil disimpan', 7) // show message

    handleRefresh()
}
</script>

<template>

    <Head title="Manajemen user" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 sm:p-4">
            <a-card title="Manajemen User">
                <template #extra><a-button @click="openForm({})" type="primary">Tambah</a-button></template>
                <a-table :columns="columns" :row-key="record => record.id" :data-source="dataSource?.list ?? []"
                    :pagination="pagination" :loading="loading" @change="handleTableChange" size="small"
                    :scroll="{ x: 900 }">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'action'">
                            <a-space size="small">
                                <a-button type="primary" size="small" @click="handleEdit(record)">
                                    Edit
                                </a-button>

                                <a-popconfirm title="Hapus user ini?" description="Tindakan ini tidak dapat dibatalkan."
                                    ok-text="Oke" cancel-text="Batal" ok-type="primary" placement="topRight"
                                    @confirm="handleDelete(record)">
                                    <a-button danger size="small">
                                        Hapus
                                    </a-button>
                                </a-popconfirm>
                            </a-space>
                        </template>
                    </template>

                    <template #customFilterDropdown="{ setSelectedKeys, selectedKeys, confirm, clearFilters, column }">
                        <div style="padding: 8px">
                            <a-input ref="searchInput" :placeholder="`Search ${column.dataIndex}`"
                                :value="selectedKeys[0]" style="width: 188px; margin-bottom: 8px; display: block"
                                @change="e => setSelectedKeys(e.target.value ? [e.target.value] : [])"
                                @pressEnter="handleSearch(selectedKeys, confirm, column.dataIndex)" />
                            <a-button type="primary" size="small" style="width: 90px; margin-right: 8px"
                                @click="handleSearch(selectedKeys, confirm, column.dataIndex)">
                                Search
                            </a-button>
                            <a-button size="small" style="width: 90px" @click="handleReset(clearFilters)">
                                Reset
                            </a-button>
                        </div>
                    </template>
                </a-table>
            </a-card>
        </div>

        <a-modal v-model:open="openFormDialog" :title="Object.keys(userForm).length === 0 ? 'Tambah User' : 'Edit User'"
            :footer="null">
            <UserForm :key="userForm?.id ?? 'create'" :user="userForm" :regencies="props.regencies" :roles="props.roles"
                @saved="handleSaved" />
        </a-modal>
    </AppLayout>
</template>