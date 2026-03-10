<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type SheetRow = Record<string, string | null> & {
    __row?: string;
};

const props = defineProps<{
    headers: string[];
    rows: SheetRow[];
    dataTypeOptions: string[];
    filters: {
        data_type: string | null;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Data',
        href: '/data',
    },
];

const ALL_VALUE = '__all__';

const selectedType = ref(props.filters.data_type ?? ALL_VALUE);

watch(
    () => props.filters.data_type,
    (value) => {
        selectedType.value = value ?? ALL_VALUE;
    },
);

const typeLabel = computed(() => {
    return selectedType.value === ALL_VALUE ? 'All' : selectedType.value;
});

const onTypeChange = (value: unknown) => {
    if (value == null) {
        selectedType.value = ALL_VALUE;
    } else if (
        typeof value === 'string' ||
        typeof value === 'number' ||
        typeof value === 'bigint'
    ) {
        selectedType.value = value.toString();
    } else {
        selectedType.value = ALL_VALUE;
    }

    router.get(
        '/data',
        {
            data_type: selectedType.value === ALL_VALUE ? null : selectedType.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const hasDataTypeFilter = computed(() => props.dataTypeOptions.length > 0);

const columns = computed(() => {
    return props.headers.map((header) => ({
        title: header,
        dataIndex: header,
        key: header,
    }));
});

const pagination = {
    pageSize: 20,
    showSizeChanger: true,
    pageSizeOptions: ['10', '20', '50', '100'],
};

const rowKey = (record: SheetRow, index: number) => {
    return record.__row ?? String(index);
};
</script>

<template>
    <Head title="Data" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between gap-4"
                >
                    <div class="space-y-1">
                        <CardTitle>Data</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Data loaded from Google Sheets.
                        </p>
                    </div>

                    <div v-if="hasDataTypeFilter" class="flex items-center gap-3">
                        <span class="text-sm text-muted-foreground">
                            Filter
                        </span>

                        <Select
                            :model-value="selectedType"
                            @update:model-value="onTypeChange"
                        >
                            <SelectTrigger class="w-[220px]">
                                <SelectValue :placeholder="typeLabel" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="ALL_VALUE">All</SelectItem>
                                <SelectItem
                                    v-for="option in dataTypeOptions"
                                    :key="option"
                                    :value="option"
                                >
                                    {{ option }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </CardHeader>

                <CardContent class="pt-0">
                    <div class="overflow-hidden rounded-lg border border-border">
                        <a-table
                            :columns="columns"
                            :data-source="rows"
                            :pagination="pagination"
                            :row-key="rowKey"
                            :scroll="{ x: 'max-content' }"
                            bordered
                            size="middle"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
