<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();

// Check if dark mode is enabled
const page = usePage();
const isDark = computed(() => {
    if (typeof window !== 'undefined') {
        return document.documentElement.classList.contains('dark');
    }
    return false;
});
</script>

<template>
    <img 
        :src="isDark ? '/dark.png' : '/light.png'" 
        :class="className" 
        v-bind="$attrs"
        alt="Attendify Logo"
    />
</template>
