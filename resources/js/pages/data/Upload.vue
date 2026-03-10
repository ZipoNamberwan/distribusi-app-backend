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
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded mb-4 flex items-center gap-3">
                        <span class="text-blue-500 text-2xl"><i class="fa fa-file-upload"></i></span>
                        <div>
                            <span class="font-bold text-blue-700 text-lg">Input Data</span>
                            <p class="text-sm text-blue-700 mt-1">Upload Input Data/Raw Data</p>
                        </div>
                    </div>
                    <UploadInput :months="props.months" :years="props.years" :statuses="props.statuses"
                        :regencies="props.regencies" :defaultMonth="props.defaultMonth"
                        :defaultYear="props.defaultYear" />
                </a-tab-pane>
                <a-tab-pane key="2" tab="Upload Target Sampel">
                    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded mb-4 flex items-center gap-3">
                        <span class="text-yellow-500 text-2xl"><i class="fa fa-bullseye"></i></span>
                        <div>
                            <span class="font-bold text-yellow-700 text-lg">Target Sampel</span>
                            <p class="text-sm text-yellow-700 mt-1">Upload Target Sampel per Kabupaten/Kota</p>
                        </div>
                    </div>
                    <UploadTargetSample :months="props.months" :years="props.years" :statuses="props.statuses"
                        :regencies="props.regencies" :categories="props.categories" :defaultMonth="props.defaultMonth"
                        :defaultYear="props.defaultYear" />
                </a-tab-pane>
                <a-tab-pane key="3" tab="Upload Angka Final">
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded mb-4 flex items-center gap-3">
                        <span class="text-green-500 text-2xl"><i class="fa fa-check-circle"></i></span>
                        <div>
                            <span class="font-bold text-green-700 text-lg">Angka Final</span>
                            <p class="text-sm text-green-700 mt-1">Upload Angka Final TPK per Kabupaten/Kota</p>
                        </div>
                    </div>
                    <UploadFinalNumber :months="props.months" :years="props.years" :regencies="props.regencies"
                        :categories="props.categories" :defaultMonth="props.defaultMonth"
                        :defaultYear="props.defaultYear" :statuses="props.statuses" />
                </a-tab-pane>
            </a-tabs>
        </div>
    </AppLayout>
</template>
