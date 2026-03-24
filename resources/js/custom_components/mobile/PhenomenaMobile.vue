<script setup>
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    data: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    emptyMessage: { type: String, default: 'Tidak ada data' },
});

const emit = defineEmits(['edit']);

const page = usePage();
const roles = page.props.auth.roles;
const userRegency = page.props.auth.user.regency_id;

function toTitleCase(str) {
    if (!str) return '-';
    return str.toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
}
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
        <div v-if="!loading && (!data || data.length === 0)" class="flex flex-col items-center justify-center py-16 gap-2 text-gray-400">
            <svg class="w-10 h-10 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <a-typography-text type="secondary">{{ emptyMessage }}</a-typography-text>
        </div>

        <!-- Card list -->
        <div v-if="!loading && data && data.length > 0" class="flex flex-col gap-2 p-3">
            <a-card v-for="record in data" :key="record.id" size="small" :body-style="{ padding: 0 }">
                
                <!-- Name + regency -->
                <div class="px-3 pt-3 pb-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <a-typography-text strong class="block leading-snug">
                                [{{ record.regency?.long_code ?? '-' }}] {{ toTitleCase(record.regency?.name) }}
                            </a-typography-text>
                            <a-typography-text type="secondary" class="mt-0.5 !text-xs">
                                {{ record.period?.month?.name ?? '-' }} {{ record.period?.year?.name ?? '-' }}
                            </a-typography-text>
                        </div>
                    </div>
                </div>

                <!-- Notes / Description -->
                <div class="mx-3 mb-2 px-2.5 py-2 rounded bg-amber-50 border border-amber-100">
                    <a-typography-text strong class="block !text-xs !text-amber-700 mb-0.5">Deskripsi</a-typography-text>
                    <a-typography-paragraph v-if="record.phenomena" class="!mb-0 !text-xs !text-amber-900 line-clamp-3">
                        {{ record.phenomena.description }}
                    </a-typography-paragraph>
                    <a-tag v-else color="red" class="!m-0 !text-xs">Belum Diisi</a-tag>
                </div>

                <!-- Action Column -->
                <div v-if="!roles.includes('adminprov') && (userRegency === record.regency_id || userRegency === record.regency?.id)" class="px-3 pb-3 pt-2">
                    <div class="flex gap-2">
                        <a-button type="primary" size="small" block @click="emit('edit', record)">
                            Edit
                        </a-button>
                    </div>
                </div>

            </a-card>
        </div>
    </div>
</template>