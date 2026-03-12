<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as phenomenaPage } from '@/routes/user/page';

const breadcrumbs = [
    {
        title: 'Konfirmasi Fenomena',
        href: phenomenaPage().url,
    },
];

</script>

<template>

    <Head title="Konfirmasi Fenomena" />

    <AppLayout :breadcrumbs="breadcrumbs">
    </AppLayout>
</template>
