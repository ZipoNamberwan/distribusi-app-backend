<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as dataIndex } from '@/routes/data';
import { index as uploadIndex } from '@/routes/upload';
import { index as storeUpload } from '@/routes/upload/store';
import { index as downloadTemplate } from '@/routes/upload/template';
import type { BreadcrumbItem } from '@/types';
import StatusUploadComponent from '@/custom_components/StatusUploadComponent.vue';
import RawDataComponent from '@/custom_components/RawDataComponent.vue';

const page = usePage<{
    flash: {
        success?: string;
        error?: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Data',
        href: dataIndex().url,
    },
    {
        title: 'Upload Excel',
        href: uploadIndex().url,
    },
];

const form = useForm<{ target: 'input' | 'tabulation'; file: File | null }>({
    target: 'input',
    file: null,
});

const canSubmit = computed(() => {
    return form.file !== null && !form.processing;
});

type UploadChangeInfo = {
    file?: {
        originFileObj?: File;
        status?: string;
    };
    fileList?: Array<{ originFileObj?: File }>;
};

const onUploadChange = (info: UploadChangeInfo) => {
    if (!info.fileList || info.fileList.length === 0) {
        form.file = null;
        return;
    }

    form.file = info.fileList[0]?.originFileObj ?? null;
};

const onUploadRemove = () => {
    form.file = null;
    return true;
};

const submit = () => {
    form.post(storeUpload().url, {
        forceFormData: true,
        preserveScroll: true,
    });
};

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
                            <RawDataComponent />
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
                            <StatusUploadComponent />
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
                        <a-form layout="vertical">
                            <a-form-item label="File (.xlsx)" :validate-status="form.errors.file ? 'error' : undefined"
                                :help="form.errors.file">
                                <a-upload-dragger :before-upload="() => false" :max-count="1" :multiple="false"
                                    accept=".xlsx" @change="onUploadChange" @remove="onUploadRemove">
                                    <p class="ant-upload-text">Click or drag file to this area</p>
                                    <p class="ant-upload-hint">Only .xlsx files are supported.</p>
                                </a-upload-dragger>
                            </a-form-item>

                            <div class="flex flex-col gap-4">
                                <a-button type="primary" :disabled="!canSubmit" :loading="form.processing"
                                    @click="submit">
                                    Upload
                                </a-button>

                                <a-alert v-if="page.props.flash?.success" type="success" show-icon
                                    :message="page.props.flash.success" closable />

                                <a-alert v-if="page.props.flash?.error" type="error" show-icon
                                    :message="page.props.flash.error" closable />
                            </div>
                        </a-form>
                    </a-card>
                </a-col>
            </a-row>


        </div>
    </AppLayout>
</template>
