<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';
import { index as dataIndex, upload as uploadPage } from '@/routes/data';
import { store as storeUpload } from '@/routes/data/upload';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Data',
        href: dataIndex().url,
    },
    {
        title: 'Upload Excel',
        href: uploadPage().url,
    },
];

const form = useForm<{ target: 'input' | 'tabulation'; file: File | null }>({
    target: 'input',
    file: null,
});

const canSubmit = computed(() => {
    return form.file !== null && !form.processing;
});

const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement | null;
    const file = target?.files?.[0] ?? null;

    form.file = file;
};

const submit = () => {
    form.post(storeUpload().url, {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Upload Excel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 rounded-xl p-4">
            <Card>
                <CardHeader>
                    <CardTitle>Upload Excel Data</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Upload a .xlsx or .csv file and import it into the
                        database. Rows are considered present only when Column C
                        is not empty.
                    </p>
                </CardHeader>

                <CardContent class="space-y-6">
                    <div class="space-y-2">
                        <Label for="target">Target table</Label>
                        <select
                            id="target"
                            v-model="form.target"
                            class="border-input dark:bg-input/30 placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground h-9 w-full rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] md:text-sm"
                        >
                            <option value="input">INPUT</option>
                            <option value="tabulation">TABULASI</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.target" />
                    </div>

                    <div class="space-y-2">
                        <Label for="file">File (.xlsx or .csv)</Label>
                        <input
                            id="file"
                            type="file"
                            accept=".xlsx,.csv"
                            class="border-input dark:bg-input/30 placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground file:text-foreground h-9 w-full rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] md:text-sm"
                            @change="onFileChange"
                        />
                        <InputError class="mt-2" :message="form.errors.file" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="!canSubmit" @click="submit">
                            <span v-if="form.processing">Uploading…</span>
                            <span v-else>Upload</span>
                        </Button>

                        <p
                            v-show="form.recentlySuccessful"
                            class="text-sm text-muted-foreground"
                        >
                            Imported.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
