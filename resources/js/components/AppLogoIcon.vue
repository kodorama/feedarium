<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img src="/favicon-96x96.png" alt="" aria-hidden="true" class="h-full w-full object-contain" :class="className" v-bind="$attrs" />
</template>
