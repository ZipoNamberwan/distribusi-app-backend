<script setup lang="js">
import { ref, computed } from 'vue';
import { usePagination } from 'vue-request';
import { ListChecks } from 'lucide-vue-next';
import axios from 'axios';
import { index as uploadStatus } from '@/routes/upload';
import Upload from '@/pages/data/Upload.vue';

const open = ref(false);

const columns = [
    {
        title: 'Name',
        dataIndex: 'name',
        sorter: true,
        width: '20%',
    },
    {
        title: 'Gender',
        dataIndex: 'gender',
        filters: [
            {
                text: 'Male',
                value: 'male',
            },
            {
                text: 'Female',
                value: 'female',
            },
        ],
        width: '20%',
    },
    {
        title: 'Email',
        dataIndex: 'email',
    },
];
const queryData = async params => {
    console.log('params:', params);
    const res = await axios.get('https://randomuser.me/api?noinfo', {
        params,
    });
    return res.data.results;
};
const {
    data: dataSource,
    run,
    loading,
    current,
    pageSize,
} = usePagination(queryData, {
    pagination: {
        currentKey: 'page',
        pageSizeKey: 'results',
    },
});
const pagination = computed(() => ({
    total: 200,
    current: current.value,
    pageSize: pageSize.value,
}));
const handleTableChange = (pag, filters, sorter) => {
    run({
        results: pag.pageSize,
        page: pag?.current,
        sortField: sorter.field,
        sortOrder: sorter.order,
        ...filters,
    });
};

</script>

<template>
    <a-button type="primary" @click="open = true">
        <template #icon>
            <ListChecks :size="16" />
        </template>
        Status
    </a-button>
    <a-modal v-model:open="open" title="Basic Modal">
        <a-table :columns="columns" :row-key="record => record.login.uuid" :data-source="dataSource"
            :pagination="pagination" :loading="loading" @change="handleTableChange">
            <template #bodyCell="{ column, text }">
                <template v-if="column.dataIndex === 'name'">{{ text.first }} {{ text.last }}</template>
            </template>
        </a-table>
    </a-modal>


</template>