<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex items-center gap-2 border-b border-sidebar-border/70 px-3 sm:px-4 py-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-auto overflow-x-auto"
    >
        <SidebarTrigger class="-ml-1 flex-shrink-0" />
        <div v-if="breadcrumbs && breadcrumbs.length > 0" class="min-w-0 flex-1 overflow-x-auto">
            <Breadcrumbs :breadcrumbs="breadcrumbs" />
        </div>
    </header>
</template>
