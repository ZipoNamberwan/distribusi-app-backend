<script setup lang="js">
import { ref, computed } from 'vue';

const activeKey = ref('table');

const props = defineProps({
    data: { type: Array, default: () => [] },
    tableColumns: { type: Array, default: () => [] },
    colorRange: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    cardConfig: { type: Object, required: true }, // { header: fn, sections: fn, columns: array of headers }
    emptyMessage: { type: String, default: 'Tidak ada data' },
});

// Modify columns: remove fixed/width and set proportional widths
const modifiedColumns = computed(() => {
    if (!props.tableColumns || props.tableColumns.length < 2) return props.tableColumns;

    return props.tableColumns.map((col, index) => {
        const newCol = { ...col };
        delete newCol.fixed;

        // Use width directly instead of customCell style
        newCol.width = index === 0 ? '30%' : '70%';

        // Keep customCell only if you need other cell styling
        newCol.customHeaderCell = () => ({
            style: {
                width: index === 0 ? '30%' : '70%',
                minWidth: '0',
            }
        });

        newCol.customCell = () => ({
            style: {
                width: index === 0 ? '30%' : '70%',
                minWidth: '0',
            }
        });

        return newCol;
    });
});

const getHeaderInfo = (record) => props.cardConfig.header(record);

const getSections = (record) => props.cardConfig.sections(record); // should return array of { title, color, items: [{value}] }

const getTextColor = (bgColor) => {
    const hex = bgColor.replace('#', '');
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);
    const brightness = (r * 299 + g * 587 + b * 114) / 1000;
    return brightness > 128 ? '#333' : '#fff';
};

</script>

<template>

    <a-tabs v-model:activeKey="activeKey" :tab-bar-style="{ paddingLeft: '0.75rem', paddingRight: '0.75rem' }">
        <a-tab-pane key="table" tab="Grafik">
            <a-table class="w-full table-fixed" :scroll="{ x: '100%' }"  :columns="modifiedColumns"
                :row-key="(record) => record.regency.id" :data-source="data" :loading="loading" :pagination="false"
                size="small" bordered />
        </a-tab-pane>
        <a-tab-pane key="card" tab="Card">

            <a-spin :spinning="loading">
                <a-empty v-if="data.length === 0 && !loading" :description="emptyMessage" />

                <div v-else class="flex flex-col gap-3 pb-4">

                    <a-card v-for="(record, index) in data" :key="index" size="small" :bordered="true">

                        <!-- HEADER -->
                        <div class="mb-2">
                            <a-typography-text type="secondary" class="text-[11px]">
                                {{ getHeaderInfo(record).subtitle }}
                            </a-typography-text>
                            <br />
                            <a-typography-title :level="5" class="!m-0">
                                {{ getHeaderInfo(record).title }}
                            </a-typography-title>
                        </div>

                        <a-divider class="!my-2" />

                        <!-- COLUMN HEADER ONCE -->
                        <div
                            class="grid grid-cols-[repeat(auto-fit,_minmax(40px,_1fr))] text-center text-[11px] text-gray-500 font-semibold mb-1 px-2">
                            <div></div>
                            <div v-for="col in props.cardConfig.columns" :key="col">{{ col }}</div>
                        </div>

                        <!-- SECTIONS -->
                        <div class="flex flex-col">
                            <div v-for="(section, sIndex) in getSections(record)" :key="sIndex"
                                class="grid grid-cols-[repeat(auto-fit,_minmax(40px,_1fr))] items-center px-2 py-1 rounded-sm"
                                :style="{ background: section.color || '#f5f5f5' }">

                                <!-- Section title -->
                                <div class="text-[11px] font-semibold"
                                    :style="{ color: getTextColor(section.color || '#f5f5f5') }">
                                    {{ section.title }}
                                </div>

                                <!-- Section values -->
                                <div v-for="(item, iIndex) in section.items" :key="iIndex"
                                    class="text-center text-[11px] font-semibold tabular-nums">
                                    <span>
                                        {{ item.value ?? '-' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a-card>
                </div>
            </a-spin>
        </a-tab-pane>
    </a-tabs>

</template>
