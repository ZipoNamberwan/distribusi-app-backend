<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import StatusUpload from '@/custom_components/StatusUpload.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as dataIndex } from '@/routes/data';
import { index as uploadIndex } from '@/routes/upload';
import { index as storeUpload } from '@/routes/upload/store';
import { index as downloadTemplate } from '@/routes/upload/template';
import type { BreadcrumbItem } from '@/types';

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
            <a-card title="Upload Excel Data">
                <template #extra>
                    <StatusUpload />
                </template>
                <a-typography-paragraph type="secondary" :style="{ marginTop: '-4px' }">
                    Upload input data pada form berikut. Pastikan file yang diunggah memiliki format .xlsx atau .csv dan
                    sesuai dengan template yang disediakan.
                    Template file dapat diunduh pada tautan berikut:
                    <a :href="downloadTemplate().url" target="_blank" class="text-blue-600 underline">
                        Download Template
                    </a>
                </a-typography-paragraph>

                <a-row>
                    <a-col :xs="24" :md="12" :lg="8">
                        <a-form layout="vertical">
                            <a-form-item label="Target table"
                                :validate-status="form.errors.target ? 'error' : undefined" :help="form.errors.target">
                                <a-select v-model:value="form.target" style="width: 100%">
                                    <a-select-option value="input">INPUT</a-select-option>
                                    <a-select-option value="tabulation">TABULASI</a-select-option>
                                </a-select>
                            </a-form-item>

                            <a-form-item label="File (.xlsx or .csv)"
                                :validate-status="form.errors.file ? 'error' : undefined" :help="form.errors.file">
                                <a-upload-dragger :before-upload="() => false" :max-count="1" :multiple="false"
                                    accept=".xlsx,.csv" @change="onUploadChange" @remove="onUploadRemove">
                                    <p class="ant-upload-text">Click or drag file to this area</p>
                                    <p class="ant-upload-hint">Only .xlsx and .csv files are supported.</p>
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
                    </a-col>
                </a-row>
            </a-card>
        </div>
    </AppLayout>
</template>
