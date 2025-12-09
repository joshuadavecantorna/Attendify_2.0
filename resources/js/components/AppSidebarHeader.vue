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
        class="flex flex-wrap items-start sm:items-center gap-3 border-b border-sidebar-border/70 px-3 sm:px-4 py-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-auto"
    >
        <SidebarTrigger class="-ml-1" />
        <div v-if="breadcrumbs && breadcrumbs.length > 0" class="min-w-0 flex-1">
            <Breadcrumbs :breadcrumbs="breadcrumbs" />
        </div>
    </header>
</template>
