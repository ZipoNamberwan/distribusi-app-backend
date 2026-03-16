<script setup>
import { usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    data: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    emptyMessage: { type: String, default: 'Tidak ada data' },
    pagination: { type: Object, default: () => ({}) },
    selectedRowKeys: { type: Array, default: () => [] },
});

const emit = defineEmits(['confirm', 'approve', 'page-change', 'update:selectedRowKeys']);

const page = usePage();
const roles = page.props.auth.roles;

function toTitleCase(str) {
    if (!str) return '-';
    return str.toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
}

function getStatusInfo(status) {
    switch (status) {
        case 'not_confirmed': return { color: 'red', label: 'Belum Dikonfirmasi' };
        case 'confirmed': return { color: 'orange', label: 'Sudah Dikonfirmasi' };
        case 'approved': return { color: 'green', label: 'Approved' };
        case 'rejected': return { color: 'volcano', label: 'Rejected' };
        case 'pending': return { color: 'blue', label: 'Pending' };
        default: return { color: 'default', label: '-' };
    }
}

function canConfirm(record) {
    return roles.includes('adminkab') && record.status !== 'approved';
}

function canApprove(record) {
    return (
        roles.includes('adminprov') &&
        ['confirmed', 'approved', 'rejected'].includes(record.status)
    );
}

function isSelectable(record) {
    return ['approved', 'confirmed', 'rejected'].includes(record.status);
}

const selectableIds = computed(() =>
    (props.data ?? []).filter(isSelectable).map(r => r.id)
);

const allSelected = computed(() =>
    selectableIds.value.length > 0 &&
    selectableIds.value.every(id => props.selectedRowKeys.includes(id))
);

const indeterminate = computed(() =>
    selectableIds.value.some(id => props.selectedRowKeys.includes(id)) && !allSelected.value
);

function toggleSelectAll(e) {
    if (e.target.checked) {
        const merged = [...new Set([...props.selectedRowKeys, ...selectableIds.value])];
        emit('update:selectedRowKeys', merged);
    } else {
        const removed = props.selectedRowKeys.filter(id => !selectableIds.value.includes(id));
        emit('update:selectedRowKeys', removed);
    }
}

function toggleRecord(record, e) {
    if (!isSelectable(record)) return;
    const keys = [...props.selectedRowKeys];
    if (e.target.checked) {
        if (!keys.includes(record.id)) keys.push(record.id);
    } else {
        const idx = keys.indexOf(record.id);
        if (idx !== -1) keys.splice(idx, 1);
    }
    emit('update:selectedRowKeys', keys);
}

const approveOpen = ref({});
const rejectOpen = ref({});

function setApproveOpen(id, val) { approveOpen.value = { ...approveOpen.value, [id]: val }; }
function setRejectOpen(id, val) { rejectOpen.value = { ...rejectOpen.value, [id]: val }; }
</script>

<template>
    <div>
        <!-- Loading skeleton -->
        <div v-if="loading" class="flex flex-col gap-2 p-3">
            <div v-for="i in 4" :key="i" class="rounded-lg border border-gray-100 bg-white p-3 animate-pulse">
                <div class="flex items-center justify-between mb-2">
                    <div class="h-3.5 bg-gray-200 rounded w-2/5" />
                    <div class="h-4 w-24 bg-gray-100 rounded-full" />
                </div>
                <div class="space-y-1.5">
                    <div class="h-3 bg-gray-100 rounded w-3/4" />
                    <div class="h-3 bg-gray-100 rounded w-1/2" />
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="!loading && (!data || data.length === 0)"
            class="flex flex-col items-center justify-center py-16 gap-2 text-gray-400">
            <svg class="w-10 h-10 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <a-typography-text type="secondary">{{ emptyMessage }}</a-typography-text>
        </div>

        <!-- Card list -->
        <div v-if="!loading && data && data.length > 0" class="flex flex-col gap-2 p-3">

            <!-- Select all bar (adminprov only) -->
            <div v-if="roles.includes('adminprov')"
                class="flex items-center justify-between px-3 py-2 rounded-lg bg-gray-50 border border-gray-100">
                <a-checkbox :checked="allSelected" :indeterminate="indeterminate" @change="toggleSelectAll">
                    <a-typography-text class="!text-xs">
                        Pilih semua
                        <template v-if="selectedRowKeys.length > 0">
                            ({{ selectedRowKeys.length }} dipilih)
                        </template>
                    </a-typography-text>
                </a-checkbox>
            </div>

            <a-card v-for="record in data" :key="record.id" size="small" :body-style="{ padding: 0 }">

                <!-- Name + regency -->
                <div class="px-3 pt-3 pb-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <a-typography-text strong class="block leading-snug">
                                {{ record.input?.nama_komersial ?? '-' }}
                            </a-typography-text>
                            <a-typography-text type="secondary" class="mt-0.5 !text-xs">
                                [{{ record.input?.regency?.long_code ?? '-' }}]
                                {{ toTitleCase(record.input?.regency?.name) }}
                            </a-typography-text>

                            <a-typography-text type="secondary" code class="mt-0.5 !text-xs">
                                {{ [record.input?.kode_kab, record.input?.kode_kec,
                                record.input?.kode_des].filter(Boolean).join('') || '-' }}
                            </a-typography-text>
                        </div>

                        <!-- Checkbox (adminprov only, selectable records only) -->
                        <a-checkbox v-if="roles.includes('adminprov') && isSelectable(record)"
                            :checked="selectedRowKeys.includes(record.id)" @change="toggleRecord(record, $event)"
                            class="shrink-0 mt-0.5" />
                    </div>
                </div>

                <!-- Meta: period · error type -->
                <div class="px-3 pb-2 flex flex-wrap gap-x-2 gap-y-0.5">
                    <a-typography-text type="secondary" class="!text-xs">
                        {{ record.input?.month?.name ?? '-' }} {{ record.input?.year?.name ?? '-' }}
                    </a-typography-text>
                    <a-tag v-if="record.error_type" color="blue" class="!text-xs">
                        {{ record.error_type.column_name }}
                    </a-tag>
                </div>

                <!-- Notes -->
                <div v-if="record.notes" class="mx-3 mb-2 px-2.5 py-2 rounded bg-amber-50 border border-amber-100">
                    <a-typography-text strong class="block !text-xs !text-amber-700 mb-0.5">Catatan</a-typography-text>
                    <a-typography-paragraph class="!mb-0 !text-xs !text-amber-900 line-clamp-3">
                        {{ record.notes }}
                    </a-typography-paragraph>
                </div>

                <!-- Confirmed / Approved by -->
                <div v-if="record.sent_by || record.approved_by" class="px-3 pb-2 flex flex-wrap gap-x-4 gap-y-0.5">
                    <a-typography-text v-if="record.sent_by" type="secondary" class="!text-xs">
                        Confirmed: {{ record.sent_by.name }}
                    </a-typography-text>
                    <a-typography-text v-if="record.approved_by" type="secondary" class="!text-xs">
                        Approved: {{ record.approved_by.name }}
                    </a-typography-text>
                </div>

                <!-- Status + Actions -->
                <div v-if="canConfirm(record) || canApprove(record)"
                    class="px-3 pb-3 pt-2 border-t border-gray-100 space-y-2">
                    <div>
                        <a-tag :color="getStatusInfo(record.status).color" class="!m-0 !text-xs">
                            {{ getStatusInfo(record.status).label }}
                        </a-tag>
                    </div>
                    <div class="flex gap-2">
                        <template v-if="canConfirm(record)">
                            <a-button type="primary" size="small" block @click="emit('confirm', record)">
                                Confirm
                            </a-button>
                        </template>

                        <template v-if="canApprove(record)">
                            <a-popconfirm title="Approve konfirmasi ini?" ok-text="Ya, approve" cancel-text="Batal"
                                @confirm="emit('approve', [record.id], 'approved')" :open="approveOpen[record.id]"
                                @update:open="setApproveOpen(record.id, $event)">
                                <a-button type="primary" size="small" ghost style="flex:1">Approve</a-button>
                            </a-popconfirm>

                            <a-popconfirm title="Reject konfirmasi ini?" ok-text="Ya, reject" ok-type="danger"
                                cancel-text="Batal" @confirm="emit('approve', [record.id], 'rejected')"
                                :open="rejectOpen[record.id]" @update:open="setRejectOpen(record.id, $event)">
                                <a-button type="primary" size="small" danger ghost style="flex:1">Reject</a-button>
                            </a-popconfirm>
                        </template>
                    </div>
                </div>

                <!-- Status only (no actions) -->
                <div v-else class="px-3 pb-3 pt-2 border-t border-gray-100">
                    <a-tag :color="getStatusInfo(record.status).color" class="!m-0 !text-xs">
                        {{ getStatusInfo(record.status).label }}
                    </a-tag>
                </div>
            </a-card>
        </div>

        <!-- Pagination -->
        <div v-if="!loading && data && data.length > 0 && pagination?.total" class="flex justify-center px-3 pb-4 pt-2">
            <a-pagination size="small" :current="pagination.current" :page-size="pagination.pageSize"
                :total="pagination.total" :show-size-changer="false" :show-quick-jumper="false"
                @change="(page, pageSize) => emit('page-change', { current: page, pageSize })" />
        </div>
    </div>
</template>