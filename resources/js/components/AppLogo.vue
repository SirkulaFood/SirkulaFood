<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

interface Props {
    class?: HTMLAttributes['class'];
}

const props = defineProps<Props>();
</script>

<template>
    <span :class="['flex min-w-0 flex-1 items-center gap-2', props.class]">
        <img src="/images/SirkulaFood-mark.png" alt="" class="size-8 shrink-0 object-contain dark:hidden" />
        <img src="/images/SirkulaFood-mark-dark.png" alt="" class="hidden size-8 shrink-0 object-contain dark:block" />
        <span class="ml-1 grid min-w-0 flex-1 text-left text-sm">
            <span class="mb-0.5 truncate font-semibold leading-none">SirkulaFood</span>
            <span class="truncate text-xs text-muted-foreground">Selamatkan pangan, jaga nilainya</span>
        </span>
    </span>
</template>
