<script setup lang="js">
import { h } from 'vue';

const props = defineProps({
    data: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    cardConfig: { type: Object, required: true }, // { header: fn, sections: fn }
    emptyMessage: { type: String, default: 'Tidak ada data' },
});

const getHeaderInfo = (record) => props.cardConfig.header(record);

const getSections = (record) => props.cardConfig.sections(record); // expect array of { title, color, items: [{value}] }

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
                            :style="{ background: section.color }">

                            <!-- Section title -->
                            <div class="text-[11px] font-semibold" :style="{ color: getTextColor(section.color) }">
                                {{ section.title }}
                            </div>

                            <!-- Section values -->
                            <div v-for="(item, iIndex) in section.items" :key="iIndex"
                                class="text-center text-[11px] font-semibold tabular-nums">
                                {{ item.value ?? '-' }}
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