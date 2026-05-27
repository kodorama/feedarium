<script setup lang="ts">
import { onMounted, ref } from 'vue';
import Tooltip from './Tooltip.vue';
import TooltipContent from './TooltipContent.vue';
import TooltipProvider from './TooltipProvider.vue';
import TooltipTrigger from './TooltipTrigger.vue';

interface Props {
    title: string;
    side?: 'top' | 'right' | 'bottom' | 'left';
}

const props = withDefaults(defineProps<Props>(), {
    side: 'bottom',
});

/**
 * Only show tooltip on devices that support fine pointer hover.
 * This prevents sticky/annoying tooltips on touch/mobile devices.
 */
const isPointerFine = ref(false);

onMounted(() => {
    isPointerFine.value = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
});
</script>

<template>
    <!-- Desktop/fine-pointer: wrap with reka-ui tooltip (instant, delayDuration=0 in TooltipProvider) -->
    <template v-if="isPointerFine">
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger as-child>
                    <slot />
                </TooltipTrigger>
                <TooltipContent :side="props.side" role="tooltip">
                    {{ props.title }}
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </template>

    <!-- Mobile/touch/coarse-pointer: render slot directly, no tooltip wrapper -->
    <template v-else>
        <slot />
    </template>
</template>

