<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { message } from 'ant-design-vue'
import { ref } from 'vue'
import { store as storePhenomena } from '@/routes/phenomena/store'

const props = defineProps({
    phenomena: {
        type: Object,
        required: true,
        default: () => { }
    },});
const page = usePage();
const emit = defineEmits(['saved'])

const form = useForm({
    id: props.phenomena?.phenomena?.id ?? null,
    month_id: props.phenomena?.period?.month?.id ?? null,
    year_id: props.phenomena?.period?.year?.id ?? null,
    regency_id: props.phenomena?.regency?.id ?? null,
    description: props.phenomena?.phenomena?.description,
});

const formRef = ref(null)

const rules = {
    description: [
        { required: true, message: 'Deskripsi masih kosong', trigger: 'change' },
    ],
}

const submit = () => {
    formRef.value
        .validate()
        .then(() => {
            form.post(storePhenomena().url, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    const flash = page.props.flash

                    if (flash?.error) {
                        message.error(flash.error, 7)
                        return
                    }

                    form.reset()
                    formRef.value.resetFields()

                    emit('saved') 
                },
            });
        })
        .catch(() => {
            // console.log('client validation failed')
        })
};
</script>

<template>
    <div class="mb-2">
        <div class="text-base font-medium text-gray-900 dark:text-white">
            {{ props.phenomena?.period?.month?.name || '-' }} {{ props.phenomena?.period?.year?.name || '-' }}
        </div>
    </div>

    <a-form layout="vertical" :model="form" :rules="rules" ref="formRef">
        
        <a-form-item name="description" label="Deskripsi Fenomena" :validate-status="form.errors.description ? 'error' : undefined"
            :help="form.errors.description">
            <a-textarea v-model:value="form.description" placeholder="Masukkan deskripsi fenomena..." :rows="6" />
        </a-form-item>

        <a-form-item class="mb-0">
            <div class="flex flex-col gap-3">
                <div class="flex justify-end">
                    <a-button type="primary" :loading="form.processing" @click="submit">
                        Simpan
                    </a-button>
                </div>

                <a-alert v-if="page.props.flash?.error" type="error" show-icon :message="page.props.flash.error"
                    closable />
            </div>
        </a-form-item>

    </a-form>
</template>
