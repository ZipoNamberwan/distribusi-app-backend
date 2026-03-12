<script setup lang="js">
import { Typography } from 'ant-design-vue';

const props = defineProps({
    data: {
        type: Array,
        required: true,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    cardConfig: {
        type: Object,
        required: true,
        validator: (config) => {
            return config.header && typeof config.header === 'function' &&
                Array.isArray(config.sections);
        },
    },
    emptyMessage: {
        type: String,
        default: 'Tidak ada data',
    },
});

const getHeaderInfo = (record) => {
    return props.cardConfig.header(record);
};

const getSections = (record) => {
    return props.cardConfig.sections.map((section) => ({
        title: section.title,
        color: section.color || '#f5f5f5',
        items: typeof section.items === 'function'
            ? section.items(record)
            : section.items.map((item) => ({
                label: item.label,
                value: typeof item.value === 'function'
                    ? item.value(record)
                    : item.value,
            })),
    }));
};

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
    <div class="mobile-card-view">

        <a-spin :spinning="loading">

            <!-- Empty state -->
            <a-empty v-if="data.length === 0 && !loading" :description="emptyMessage" />

            <div v-else class="flex flex-col gap-3 pb-4">

                <!-- CARD -->
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

                    <!-- COLUMN HEADER -->
                    <div class="grid grid-cols-4 text-center text-[11px] text-gray-500 font-semibold mb-1">

                        <div></div>
                        <div>B</div>
                        <div>NB</div>
                        <div>Total</div>

                    </div>

                    <!-- SECTIONS -->
                    <div class="flex flex-col">

                        <div v-for="(section, sIndex) in getSections(record)" :key="sIndex"
                            class="grid grid-cols-4 items-center px-2 py-1 rounded-sm"
                            :style="{ background: section.color }">

                            <div class="text-[11px] font-semibold" :style="{ color: getTextColor(section.color) }">
                                {{ section.title }}
                            </div>

                            <div v-for="(item, iIndex) in section.items" :key="iIndex"
                                class="text-center text-[11px] font-semibold tabular-nums">
                                {{ item.value }}
                            </div>

                        </div>

                    </div>

                </a-card>

            </div>

        </a-spin>

    </div>
</template>

<style scoped>
.mobile-card-view {
    display: block;
}

@media (min-width:640px) {
    .mobile-card-view {
        display: none;
    }
}
</style>