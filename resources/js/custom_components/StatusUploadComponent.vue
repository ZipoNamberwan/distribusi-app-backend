<script setup lang="js">
import moment from 'moment';
import { ref, computed, watch } from 'vue';
import { usePagination } from 'vue-request';
import { index as statusIndex } from '@/routes/data/status';
import { download as downloadFile } from '@/routes/upload/file';
import { usePage } from '@inertiajs/vue3';
import { DownloadOutlined } from '@ant-design/icons-vue';
import { message } from 'ant-design-vue';

const page = usePage();
const lastParams = ref({});

const props = defineProps({
    statuses: {
        type: Array,
        required: true,
        default: () => []
    },
    open: {
        type: Boolean,
        required: false,
        default: () => false
    },
    type: {
        type: String,
        required: true,
        default: () => false
    },
});

const statusMap = computed(() =>
    Object.fromEntries((props.statuses ?? []).map(s => [s.value, s]))
);

const open = computed(() => (props.open));

const columns = [
    {
        title: 'Status',
        dataIndex: 'status',
        sorter: true,
        width: '15%',
        filters: (props.statuses ?? []).map(s => ({ text: s.title, value: s.value })),
    },
    {
        title: 'Periode',
        key: 'periode',
        width: '15%',
        // sorter: true,
    },
    {
        title: 'File',
        dataIndex: 'filename',
        width: "30%",
    },
    {
        title: 'Pesan',
        dataIndex: 'user_message',
        width: "40%",
    },
    {
        title: 'Waktu',
        dataIndex: 'created_at',
        sorter: true,
        width: '20%',
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
    const { current = 1, pageSize = 10, sortField, sortOrder, ...filterQuery } = params;
    const size = Number(pageSize);
    const page = Number(current);

    const payload = await fetchJson(
        statusIndex.url(
            props.type, {
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

watch(open, (isOpen) => {
    if (isOpen) {
        run();
    }
});

const downloadingIds = ref([]);
const handleDownload = async (record) => {
    downloadingIds.value.push(record.id);
    try {
        const response = await fetch(downloadFile.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': page.props.csrf_token,
            },
            body: JSON.stringify({ uploadId: record.id }),
        });

        if (!response.ok) {
            throw new Error(`Gagal mengunduh file (${response.status}: ${response.statusText})`);
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', record.filename);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (e) {
        message.error(e.message || 'Terjadi kesalahan saat mengunduh file');
    } finally {
        downloadingIds.value = downloadingIds.value.filter(id => id !== record.id);
    }
};

</script>

<template>
    <div class="flex mb-3">
        <a-button type="primary" :loading="loading" @click="handleRefresh">
            Refresh
        </a-button>
    </div>
    <a-table :scroll="{ x: 500, y: '60vh' }" :columns="columns" :row-key="record => record.id"
        :data-source="dataSource?.list ?? []" :pagination="pagination" :loading="loading" @change="handleTableChange"
        size="small">
        <template #bodyCell="{ column, text, record }">
            <template v-if="column.dataIndex === 'status'">
                <a-tag :color="statusMap[text]?.color ?? 'default'">
                    {{ statusMap[text]?.title ?? text }}
                </a-tag>
            </template>
            <template v-if="column.key === 'periode'">
                <span>{{ record.month?.name }} {{ record.year?.name }}</span>
            </template>
            <template v-else-if="column.dataIndex === 'filename'">
                <div class="flex items-center gap-1">
                    <span>{{ text }}</span>
                    <a-button 
                        v-if="text" 
                        type="text" 
                        size="small" 
                        title="Unduh"
                        :loading="downloadingIds.includes(record.id)"
                        @click="handleDownload(record)"
                    >
                        <template #icon>
                            <DownloadOutlined />
                        </template>
                    </a-button>
                </div>
            </template>
            <template v-else-if="column.dataIndex === 'message'">
                <span :title="text">{{ text ? text.slice(0, 150) + (text.length > 150 ? '…' : '') : '' }}</span>
            </template>
            <template v-else-if="column.dataIndex === 'created_at'">
                <span :title="moment(text).format('DD MMM YYYY HH:mm:ss')">{{ moment(text).fromNow() }}</span>
            </template>
        </template>
    </a-table>
</template>