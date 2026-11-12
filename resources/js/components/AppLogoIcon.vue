<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

interface Props {
    className?: HTMLAttributes['class'];
}

defineOptions({ inheritAttrs: false });
defineProps<Props>();
</script>

<template>
    <span :class="className" v-bind="$attrs">
        <img src="/images/SirkulaFood-mark.png" alt="" class="size-full object-contain dark:hidden" />
        <img src="/images/SirkulaFood-mark-dark.png" alt="" class="hidden size-full object-contain dark:block" />
    </span>
</template>
