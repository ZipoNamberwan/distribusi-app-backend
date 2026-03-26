<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps({
    count: {
        type: Number,
        default: 15,
    },
});

const bubbles = computed(() => {
    return Array.from({ length: props.count }).map((_, i) => {
        const size = Math.random() * 60 + 20; // 20px to 80px
        const left = Math.random() * 100; // 0% to 100%
        const animationDuration = Math.random() * 10 + 10; // 10s to 20s
        const animationDelay = Math.random() * 10; // 0s to 10s
        const opacity = Math.random() * 0.15 + 0.05; // 0.05 to 0.20

        return {
            id: i,
            style: {
                width: `${size}px`,
                height: `${size}px`,
                left: `${left}%`,
                animationDuration: `${animationDuration}s`,
                animationDelay: `${animationDelay}s`,
                backgroundColor: `rgba(255, 255, 255, ${opacity})`,
            },
        };
    });
});
</script>

<template>
    <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
        <div
            v-for="bubble in bubbles"
            :key="bubble.id"
            class="bubble"
            :style="bubble.style"
        ></div>
    </div>
</template>

<style scoped>
.bubble {
    position: absolute;
    bottom: -100px;
    border-radius: 50%;
    animation: float linear infinite;
    backdrop-filter: blur(2px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

@keyframes float {
    0% {
        transform: translateY(0) scale(1) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: translateY(-1200px) scale(1.5) rotate(360deg);
        opacity: 0;
    }
}
</style>
