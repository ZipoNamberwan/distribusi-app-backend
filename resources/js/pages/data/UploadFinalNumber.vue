<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3';
import StatusUploadComponent from '@/custom_components/StatusUploadComponent.vue';
import { store as storeUpload } from '@/routes/final/upload';
import { index as downloadTemplate } from '@/routes/final/template';
import FinalNumberDataComponent from '@/custom_components/FinalNumberDataComponent.vue';

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
    statuses: {
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

const finalDataOpen = ref(false);
const statusOpen = ref(false);
const formRef = ref(null)
const form = useForm({
    year: props.defaultYear,
    month: props.defaultMonth,
    file: null,
});

const fileList = computed({
    get() {
        return form.file ? [form.file] : []
    },
    set(files) {
        form.file = files?.[0] ?? null
    }
})

const onUploadChange = (info) => {
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
    formRef.value
        .validate()
        .then(() => {
            form.post(storeUpload().url, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    formRef.value.resetFields();
                },
            });
        })
        .catch(() => {
            // console.log('client validation failed')
        })
};

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
</script>

<template>
    <a-row :gutter="[16, 16]">
        <a-col :xs="24" :sm="12">
            <a-card size="small">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="font-semibold">Lihat Angka Final</p>
                        <p class="text-muted-foreground text-sm">Lihat angka final yang sudah terupload
                        </p>
                    </div>
                    <a-button @click="finalDataOpen = true">
                        Lihat
                    </a-button>
                    <a-modal style="top: 20px" v-model:open="finalDataOpen" title="Angka Final Terupload" :footer="null"
                        width="60%">

                        <FinalNumberDataComponent :open="finalDataOpen" :regencies="props.regencies"
                            :categories="props.categories" :months="props.months" :years="props.years" />
                    </a-modal>
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
                    <div>
                        <a-button type="primary" @click="statusOpen = true">
                            Status Upload
                        </a-button>
                        <a-modal style="top: 20px" v-model:open="statusOpen" title="Status Upload" :footer="null"
                            width="70%">
                            <StatusUploadComponent type="final" :open="statusOpen" :statuses="props.statuses" />
                        </a-modal>
                    </div>
                </div>
            </a-card>
        </a-col>
        <a-col :xs="24" :sm="24">
            <a-card title="Upload Angka Final">
                <a-typography-paragraph type="secondary" :style="{ marginTop: '-4px' }">
                    Upload angka final pada form berikut. Pastikan file yang diunggah memiliki format
                    .xlsx dan sesuai dengan template yang disediakan.
                    Template file dapat diunduh pada tautan berikut:
                    <a :href="downloadTemplate().url" target="_blank" class="text-blue-600 underline">
                        Download Template
                    </a>
                </a-typography-paragraph>
                <a-form layout="vertical" :label-col="{ span: 3 }" :wrapper-col="{ span: 6 }" :model="form"
                    :rules="rules" ref="formRef">
                    <a-form-item name="month" label="Bulan" :validate-status="form.errors.month ? 'error' : undefined"
                        :help="form.errors.month">
                        <a-select v-model:value="form.month" placeholder="Pilih Bulan" allow-clear>
                            <a-select-option v-for="month in props.months" :key="month.id" :value="month.id">
                                {{ month.name }}
                            </a-select-option>
                        </a-select>
                    </a-form-item>

                    <a-form-item name="year" label="Tahun" :validate-status="form.errors.year ? 'error' : undefined"
                        :help="form.errors.year">
                        <a-select v-model:value="form.year" placeholder="Pilih Tahun" allow-clear>
                            <a-select-option v-for="year in props.years" :key="year.id" :value="year.id">
                                {{ year.name }}
                            </a-select-option>
                        </a-select>
                    </a-form-item>

                    <a-form-item name="file" label="File (.xlsx)" :wrapper-col="{ span: 6 }"
                        :validate-status="form.errors.file ? 'error' : undefined" :help="form.errors.file">
                        <a-upload-dragger :before-upload="() => false" :max-count="1" :multiple="false" accept=".xlsx"
                            v-model:fileList="fileList" @change="onUploadChange" @remove="onUploadRemove">
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

                            <!-- <a-alert v-if="page.props.flash?.success" type="success" show-icon
                        :message="page.props.flash.success" closable />
                    <a-alert v-if="page.props.flash?.error" type="error" show-icon :message="page.props.flash.error"
                        closable /> -->
                        </div>
                    </a-form-item>
                </a-form>
            </a-card>
        </a-col>
    </a-row>
</template>