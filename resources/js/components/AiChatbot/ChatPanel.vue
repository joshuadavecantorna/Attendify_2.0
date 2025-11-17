<script setup lang="ts">
import { ref, computed, nextTick, watch } from 'vue';
import { useChatbot } from '@/composables/useChatbot';
import ChatMessage from './ChatMessage.vue';
import SuggestedQueries from './SuggestedQueries.vue';

const { isOpen, messages, isLoading, closeChat, sendMessage, clearHistory } = useChatbot();

const messageInput = ref('');
const messagesContainer = ref<HTMLElement | null>(null);

const hasMessages = computed(() => messages.value.length > 0);

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
};

watch(messages, () => {
    scrollToBottom();
}, { deep: true });

const handleSend = async () => {
    const message = messageInput.value.trim();
    if (!message || isLoading.value) return;

    messageInput.value = '';
    await sendMessage(message);
};

const handleSuggestedQuery = async (query: string) => {
    messageInput.value = query;
    await handleSend();
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        handleSend();
    }
};

const handleClose = () => {
    closeChat();
};

const handleClearHistory = () => {
    if (confirm('Are you sure you want to clear the chat history?')) {
        clearHistory();
    }
};
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-4 opacity-0 scale-95"
        enter-to-class="translate-y-0 opacity-100 scale-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100 scale-100"
        leave-to-class="translate-y-4 opacity-0 scale-95"
    >
        <div
            v-if="isOpen"
            class="fixed bottom-6 right-6 w-[420px] h-[650px] max-md:w-[calc(100vw-2rem)] max-md:h-[75vh] flex flex-col backdrop-blur-xl bg-white/15 dark:bg-gray-900/15 border border-white/15 dark:border-white/10 rounded-3xl shadow-2xl shadow-black/10 overflow-hidden z-40"
        >
            <!-- Header -->
            <div class="flex items-center justify-between p-4 backdrop-blur-lg bg-gradient-to-r from-blue-500/2 to-purple-500/2 border-b border-white/10">
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center shadow-lg">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-500 border-2 border-white dark:border-gray-900 rounded-full"></div>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">AI Assistant</h3>
                        <p class="text-xs text-green-600 dark:text-green-400">Online</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-if="hasMessages"
                        @click="handleClearHistory"
                        class="p-2 rounded-lg hover:bg-white/50 dark:hover:bg-gray-800/50 transition-colors"
                        title="Clear history"
                    >
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                    <button
                        @click="handleClose"
                        class="p-2 rounded-lg hover:bg-red-500/10 hover:text-red-600 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Messages Container -->
            <div
                ref="messagesContainer"
                class="flex-1 overflow-y-auto p-4 space-y-2 scroll-smooth"
                style="scrollbar-width: thin; scrollbar-color: rgba(139, 92, 246, 0.3) transparent;"
            >
                <!-- Welcome Message -->
                <div v-if="!hasMessages" class="text-center py-8">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center shadow-lg">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                        Welcome to AI Assistant
                    </h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6 px-4">
                        Ask me anything about attendance, students, or classes. I'm here to help!
                    </p>
                    <SuggestedQueries @select="handleSuggestedQuery" />
                </div>

                <!-- Messages -->
                <ChatMessage
                    v-for="message in messages"
                    :key="message.id"
                    :message="message"
                />
            </div>

            <!-- Input Area -->
            <div class="p-4 backdrop-blur-lg bg-white/50 dark:bg-gray-800/50 border-t border-white/10">
                <div class="flex items-end gap-2">
                    <div class="flex-1 relative">
                        <textarea
                            v-model="messageInput"
                            @keydown="handleKeydown"
                            :disabled="isLoading"
                            placeholder="Ask me anything..."
                            rows="1"
                            class="w-full px-4 py-3 pr-12 rounded-2xl backdrop-blur-lg bg-white/60 dark:bg-gray-700/60 border border-white/30 dark:border-gray-600/30 focus:ring-2 focus:ring-blue-500/50 focus:border-transparent transition-all resize-none text-sm text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 disabled:opacity-50 disabled:cursor-not-allowed"
                            style="max-height: 120px; scrollbar-width: thin;"
                        />
                    </div>
                    <button
                        @click="handleSend"
                        :disabled="!messageInput.trim() || isLoading"
                        class="p-3 rounded-2xl bg-gradient-to-br from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 disabled:from-gray-400 disabled:to-gray-500 disabled:cursor-not-allowed shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-200"
                    >
                        <svg v-if="!isLoading" class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        <svg v-else class="w-5 h-5 text-white animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </button>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 text-center">
                    Press Enter to send, Shift+Enter for new line
                </p>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
/* Custom scrollbar for webkit browsers */
::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background: transparent;
}

::-webkit-scrollbar-thumb {
    background: rgba(139, 92, 246, 0.3);
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(139, 92, 246, 0.5);
}
</style>
