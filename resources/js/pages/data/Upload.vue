<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue'
import { message } from 'ant-design-vue'
import AppLayout from '@/layouts/AppLayout.vue';
import { index as dataIndex } from '@/routes/data';
import { index as uploadIndex } from '@/routes/upload';
import { index as storeUpload } from '@/routes/upload/store';
import { index as downloadTemplate } from '@/routes/upload/template';
import StatusUploadComponent from '@/custom_components/StatusUploadComponent.vue';
import RawDataComponent from '@/custom_components/RawDataComponent.vue';

const page = usePage();

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

const breadcrumbs = [
    {
        title: 'Data',
        href: dataIndex().url,
    },
    {
        title: 'Upload Excel',
        href: uploadIndex().url,
    },
];

const form = useForm({
    target: 'input',
    year: props.defaultYear,
    month: props.defaultMonth,
    file: null,
});

const formRef = ref(null)

const rules = {
    month: [
        { required: true, message: 'Bulan masih kosong', trigger: 'change' },
    ],
    year: [
        { required: true, message: 'Tahun masih kosong', trigger: 'change' },
    ],
    file: [
        { required: true, message: 'File masih kosong', trigger: 'change' },
    ],
}

const fileList = ref([]);

const onUploadChange = (info) => {
    fileList.value = info.fileList;
    if (!info.fileList || info.fileList.length === 0) {
        form.file = null;
        return;
    }

    form.file = info.fileList[0]?.originFileObj ?? null;
};

const onUploadRemove = () => {
    form.file = null;
    fileList.value = [];
    return true;
};

const submit = () => {
    formRef.value
        .validate()
        .then(() => {
            form.post(storeUpload().url, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    form.month = props.defaultMonth;
                    form.year = props.defaultYear;
                    fileList.value = [];
                    formRef.value.resetFields();
                },
            });
        })
        .catch(() => {
            // console.log('client validation failed')
        })
};

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
)
</script>

<template>

    <Head title="Upload Input Data" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <a-row :gutter="[16, 16]">
                <a-col :xs="24" :sm="12">
                    <a-card size="small">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="font-semibold">Raw Data</p>
                                <p class="text-muted-foreground text-sm">Lihat raw data yang sudah terupload</p>
                            </div>
                            <RawDataComponent :regencies="props.regencies" :months="props.months"
                                :years="props.years" />
                        </div>
                    </a-card>
                </a-col>
                <a-col :xs="24" :sm="12">
                    <a-card size="small">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="font-semibold">Status Upload</p>
                                <p class="text-muted-foreground text-sm">Pantau riwayat upload file</p>
                            </div>
                            <StatusUploadComponent :statuses="props.statuses" />
                        </div>
                    </a-card>
                </a-col>
                <a-col :xs="24" :sm="24">
                    <a-card title="Upload Excel Data">
                        <a-typography-paragraph type="secondary" :style="{ marginTop: '-4px' }">
                            Upload input data pada form berikut. Pastikan file yang diunggah memiliki format .xlsx dan
                            sesuai dengan template yang disediakan.
                            Template file dapat diunduh pada tautan berikut:
                            <a :href="downloadTemplate().url" target="_blank" class="text-blue-600 underline">
                                Download Template
                            </a>
                        </a-typography-paragraph>
                        <a-form layout="vertical" :label-col="{ span: 3 }" :wrapper-col="{ span: 6 }" :model="form"
                            :rules="rules" ref="formRef">
                            <a-form-item name="month" label="Bulan"
                                :validate-status="form.errors.month ? 'error' : undefined" :help="form.errors.month">
                                <a-select v-model:value="form.month" placeholder="Pilih Bulan" allow-clear>
                                    <a-select-option v-for="month in props.months" :key="month.id" :value="month.id">
                                        {{ month.name }}
                                    </a-select-option>
                                </a-select>
                            </a-form-item>

                            <a-form-item name="year" label="Tahun"
                                :validate-status="form.errors.year ? 'error' : undefined" :help="form.errors.year">
                                <a-select v-model:value="form.year" placeholder="Pilih Tahun" allow-clear>
                                    <a-select-option v-for="year in props.years" :key="year.id" :value="year.id">
                                        {{ year.name }}
                                    </a-select-option>
                                </a-select>
                            </a-form-item>

                            <a-form-item name="file" label="File (.xlsx)" :wrapper-col="{ span: 6 }"
                                :validate-status="form.errors.file ? 'error' : undefined" :help="form.errors.file">
                                <a-upload-dragger :before-upload="() => false" :max-count="1" :multiple="false"
                                    accept=".xlsx" v-model:fileList="fileList" @change="onUploadChange" @remove="onUploadRemove">
                                    <p class="ant-upload-text">Click or drag file to this area</p>
                                    <p class="ant-upload-hint">Only .xlsx files are supported.</p>
                                </a-upload-dragger>
                            </a-form-item>

                            <a-form-item :wrapper-col="{ offset: 0, span: 12 }">
                                <div class="flex flex-col gap-3">
                                    <div>
                                        <a-button type="primary" :loading="form.processing" @click="submit">
                                            Upload
                                        </a-button>
                                    </div>

                                    <a-alert v-if="page.props.flash?.success" type="success" show-icon
                                        :message="page.props.flash.success" closable />
                                    <a-alert v-if="page.props.flash?.error" type="error" show-icon
                                        :message="page.props.flash.error" closable />
                                </div>
                            </a-form-item>
                        </a-form>
                    </a-card>
                </a-col>
            </a-row>


        </div>
    </AppLayout>
</template>
