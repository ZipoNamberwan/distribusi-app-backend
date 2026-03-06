<script setup lang="js">
import { ref} from 'vue';

const open = ref(false);

</script>

<template>
    <div>
        <a-button @click="open = true">
            Lihat
        </a-button>
        <a-modal v-model:open="open" title="Raw Data Terupload" :footer="null">
            
        </a-modal>
    </div>
</template>