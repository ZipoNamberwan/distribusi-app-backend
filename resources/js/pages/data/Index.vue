<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import RawDataComponent from '@/custom_components/RawDataComponent.vue';
import { index as dataPage } from '@/routes/data';

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

const breadcrumbs = [
    {
        title: 'Raw Data',
        href: dataPage().url,
    },
];

</script>

<template>

    <Head title="Raw Data" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 sm:p-4">
            <Card>
                <CardHeader>
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="space-y-1">
                            <CardTitle>Raw Data</CardTitle>
                        </div>
                    </div>
                </CardHeader>

                <CardContent class="p-0 sm:px-6 sm:pb-6">
                    <RawDataComponent :regencies="props.regencies" :months="props.months" :years="props.years" />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>