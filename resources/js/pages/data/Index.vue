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

type Row = {
    id: number;
    code: string;
    name: string;
    value: string;
    data_type: string;
};

const props = defineProps<{
    rows: Row[];
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
                            Dummy data from controller.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
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
                        <table class="w-full text-sm">
                            <thead class="bg-muted">
                                <tr class="text-left">
                                    <th class="px-3 py-2 font-medium">ID</th>
                                    <th class="px-3 py-2 font-medium">Code</th>
                                    <th class="px-3 py-2 font-medium">Name</th>
                                    <th class="px-3 py-2 font-medium">Value</th>
                                    <th class="px-3 py-2 font-medium">
                                        Data type
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in rows"
                                    :key="`${row.id}-${row.code}-${row.data_type}`"
                                    class="border-t border-border"
                                >
                                    <td class="px-3 py-2 align-top">
                                        {{ row.id }}
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        {{ row.code }}
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        {{ row.name }}
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        {{ row.value }}
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        {{ row.data_type }}
                                    </td>
                                </tr>

                                <tr v-if="rows.length === 0">
                                    <td
                                        colspan="5"
                                        class="px-3 py-10 text-center text-sm text-muted-foreground"
                                    >
                                        No data found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
