<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/icon.webp"
        alt="App Logo"
        :class="['object-contain', className]"
        v-bind="$attrs"
    />
</template>
