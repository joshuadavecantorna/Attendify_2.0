<script setup lang="ts">
import type { ChatMessage } from '@/types/chatbot';
import { computed } from 'vue';

const props = defineProps<{
    message: ChatMessage;
}>();

const isUser = computed(() => props.message.role === 'user');
const formattedTime = computed(() => {
    const date = new Date(props.message.timestamp);
    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
});
</script>

<template>
    <div :class="[
        'flex mb-4 animate-in fade-in slide-in-from-bottom-2 duration-300',
        isUser ? 'justify-end' : 'justify-start'
    ]">
        <div :class="[
            'max-w-[80%] rounded-2xl px-4 py-3 backdrop-blur-md border shadow-sm transition-all',
            isUser 
                ? 'bg-blue-500/15 border-blue-500/20 dark:bg-blue-500/25 dark:border-blue-500/30' 
                : 'bg-purple-500/15 border-purple-500/20 dark:bg-purple-500/25 dark:border-purple-500/30'
        ]">
            <!-- Loading State -->
            <div v-if="message.isLoading" class="flex items-center gap-1 py-1">
                <div class="w-2 h-2 bg-purple-500 rounded-full animate-bounce [animation-delay:-0.3s]"></div>
                <div class="w-2 h-2 bg-purple-500 rounded-full animate-bounce [animation-delay:-0.15s]"></div>
                <div class="w-2 h-2 bg-purple-500 rounded-full animate-bounce"></div>
            </div>

            <!-- Message Content -->
            <div v-else>
                <p class="text-sm text-gray-800 dark:text-gray-100 whitespace-pre-wrap break-words">
                    {{ message.content }}
                </p>
                <p :class="[
                    'text-xs mt-1.5',
                    isUser ? 'text-blue-600 dark:text-blue-400' : 'text-purple-600 dark:text-purple-400'
                ]">
                    {{ formattedTime }}
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes slide-in-from-bottom {
    from {
        transform: translateY(10px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.animate-in {
    animation: slide-in-from-bottom 0.3s ease-out;
}
</style>
