<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue';
import { index as dataIndex } from '@/routes/data';
import { index as uploadIndex } from '@/routes/upload';
import UploadInput from './UploadInput.vue';
import UploadTargetSample from './UploadTargetSample.vue';
import UploadFinalNumber from './UploadFinalNumber.vue';
const props = defineProps({
    months: {
        type: Array,
        required: true,
        default: () => []
    },
    years: {
        type: Array,
        required: true,
        default: () => []
    },
    statuses: {
        type: Array,
        required: true,
        default: () => []
    },
    regencies: {
        type: Array,
        required: true,
        default: () => []
    },
    categories: {
        type: Array,
        required: true,
        default: () => []
    },
    defaultMonth: {
        type: Number,
        required: false,
        default: null,
    },
    defaultYear: {
        type: Number,
        required: false,
        default: null,
    },
});

const activeKey = ref('1');

const breadcrumbs = [
    {
        title: 'Data',
        href: dataIndex().url,
    },
    {
        title: 'Upload Data',
        href: uploadIndex().url,
    },
];

</script>

<template>

    <Head title="Upload Input Data" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <a-tabs v-model:activeKey="activeKey">
                <a-tab-pane key="1" tab="Upload Input Data">
                    <UploadInput :months="props.months" :years="props.years" :statuses="props.statuses"
                        :regencies="props.regencies" :defaultMonth="props.defaultMonth"
                        :defaultYear="props.defaultYear" />
                </a-tab-pane>
                <a-tab-pane key="2" tab="Upload Target Sampel">
                    <UploadTargetSample :months="props.months" :years="props.years" :statuses="props.statuses"
                        :regencies="props.regencies" :categories="props.categories" :defaultMonth="props.defaultMonth"
                        :defaultYear="props.defaultYear" />
                </a-tab-pane>
                <a-tab-pane key="3" tab="Upload Angka Final">
                    <UploadFinalNumber :months="props.months" :years="props.years" :regencies="props.regencies"
                        :categories="props.categories" :defaultMonth="props.defaultMonth"
                        :defaultYear="props.defaultYear" :statuses="props.statuses" />
                </a-tab-pane>
            </a-tabs>
        </div>
    </AppLayout>
</template>
