<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue'
import {store as storeUser} from '@/routes/user/page';

const props = defineProps({
    user: {
        type: Object,
        required: true,
        default: () => { }
    },
    regencies: {
        type: Array,
        required: true,
        default: () => []
    },
    roles: {
        type: Array,
        required: true,
        default: () => []
    },
});

const emit = defineEmits(['saved'])

const form = useForm({
    email: props.user?.email,
    name: props.user?.name,
    regency: props.user?.regency_id,
    role: props.user?.roles?.length > 0 ? props.user.roles[0].name : null,
});

const formRef = ref(null)

const rules = {
    email: [
        { required: true, message: 'Email masih kosong', trigger: 'change' },
    ],
    name: [
        { required: true, message: 'Nama masih kosong', trigger: 'change' },
    ],
    regency: [
        { required: true, message: 'Regency masih kosong', trigger: 'change' },
    ],
    role: [
        { required: true, message: 'Role masih kosong', trigger: 'change' },
    ],
}

const submit = () => {
    formRef.value
        .validate()
        .then(() => {
            form.post(storeUser().url, {
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
</script>

<template>
    <a-form layout="horizontal" :label-col="{ span: 3 }" :wrapper-col="{ span: 12 }" :model="form" :rules="rules"
        ref="formRef">
        <a-form-item name="email" label="Email" :validate-status="form.errors.email ? 'error' : undefined"
            :help="form.errors.email">
            <a-input v-model:value="form.email" placeholder="Email" />
        </a-form-item>

        <a-form-item name="name" label="Nama" :validate-status="form.errors.name ? 'error' : undefined"
            :help="form.errors.name">
            <a-input v-model:value="form.name" placeholder="name" />
        </a-form-item>

        <a-form-item name="regency" label="Kabupaten" :validate-status="form.errors.regency ? 'error' : undefined"
            :help="form.errors.regency">
            <a-select v-model:value="form.regency" placeholder="Pilih Kabupaten" allow-clear>
                <a-select-option v-for="regency in props.regencies" :key="regency.id" :value="regency.id">
                    [{{ regency.long_code }}] {{ regency.name }}
                </a-select-option>
            </a-select>
        </a-form-item>

        <a-form-item name="role" label="Role" :validate-status="form.errors.role ? 'error' : undefined"
            :help="form.errors.role">
            <a-select v-model:value="form.role" placeholder="Pilih Role" allow-clear>
                <a-select-option v-for="role in props.roles" :key="role.uuid" :value="role.name">
                    {{ role.name }}
                </a-select-option>
            </a-select>
        </a-form-item>
        <a-form-item :wrapper-col="{ offset: 3, span: 12 }">
            <div class="flex flex-col gap-3">
                <div>
                    <a-button type="primary" :loading="form.processing" @click="submit">
                        Simpan
                    </a-button>
                </div>

                <!-- <a-alert v-if="page.props.flash?.success" type="success" show-icon :message="page.props.flash.success"
                    closable />
                <a-alert v-if="page.props.flash?.error" type="error" show-icon :message="page.props.flash.error"
                    closable /> -->
            </div>
        </a-form-item>
    </a-form>
</template>