<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as confirmationPage } from '@/routes/user/page';

const breadcrumbs = [
    {
        title: 'Manajemen user',
        href: confirmationPage().url,
    },
];

</script>

<template>

    <Head title="Manajemen user" />

    <AppLayout :breadcrumbs="breadcrumbs">
    </AppLayout>
</template>
