<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';
import { usePage } from '@inertiajs/vue3';
import FloatingChatButton from '@/components/AiChatbot/FloatingChatButton.vue';
import ChatPanel from '@/components/AiChatbot/ChatPanel.vue';

interface Props {
    variant?: 'header' | 'sidebar';
}

defineProps<Props>();

const isOpen = usePage().props.sidebarOpen;
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <slot />
        
        <!-- AI Chatbot -->
        <FloatingChatButton />
        <ChatPanel />
    </div>
    <SidebarProvider v-else :default-open="isOpen">
        <slot />
        
        <!-- AI Chatbot -->
        <FloatingChatButton />
        <ChatPanel />
    </SidebarProvider>
</template>
