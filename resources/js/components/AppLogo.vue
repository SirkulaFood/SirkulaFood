<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

interface Props {
    class?: HTMLAttributes['class'];
}

const props = defineProps<Props>();
</script>

<template>
    <span :class="['flex min-w-0 flex-1 items-center gap-2', props.class]">
        <img src="/images/SirkulaFood-logo-light.png" alt="SirkulaFood" class="h-7 w-auto max-w-[190px] object-contain dark:hidden group-data-[collapsible=icon]:hidden" />
        <img src="/images/SirkulaFood-logo-dark.png" alt="SirkulaFood" class="hidden h-7 w-auto max-w-[190px] object-contain dark:block group-data-[collapsible=icon]:hidden" />
        <img src="/images/SirkulaFood-mark.png" alt="SirkulaFood" class="hidden size-8 shrink-0 object-contain group-data-[collapsible=icon]:block dark:group-data-[collapsible=icon]:hidden" />
        <img src="/images/SirkulaFood-mark-dark.png" alt="SirkulaFood" class="hidden size-8 shrink-0 object-contain dark:group-data-[collapsible=icon]:block" />
    </span>
</template>
