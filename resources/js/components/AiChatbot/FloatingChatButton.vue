<script setup lang="ts">
import { useChatbot } from '@/composables/useChatbot';

const { isOpen, toggleChat } = useChatbot();
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-2 opacity-0 scale-90"
        enter-to-class="translate-y-0 opacity-100 scale-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100 scale-100"
        leave-to-class="translate-y-2 opacity-0 scale-90"
    >
        <button
            v-if="!isOpen"
            @click="toggleChat"
            class="group fixed bottom-6 right-6 w-16 h-16 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 shadow-lg hover:shadow-2xl hover:scale-110 transition-all duration-300 flex items-center justify-center z-50"
            aria-label="Open AI Chat Assistant"
        >
            <!-- AI Icon with Animation -->
            <div class="relative">
                <!-- Sparkle Icon -->
                <svg class="w-7 h-7 text-white group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                
                <!-- Pulse Ring -->
                <span class="absolute inset-0 rounded-full bg-white/30 animate-ping"></span>
            </div>

            <!-- Notification Badge (Optional) -->
            <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center border-2 border-white shadow-lg opacity-0 group-hover:opacity-100 transition-opacity">
                AI
            </span>

            <!-- Glow Effect -->
            <div class="absolute inset-0 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 opacity-0 group-hover:opacity-50 blur-xl transition-opacity duration-300"></div>
        </button>
    </Transition>
</template>

<style scoped>
@keyframes pulse-ring {
    0%, 100% {
        transform: scale(1);
        opacity: 0.5;
    }
    50% {
        transform: scale(1.1);
        opacity: 0;
    }
}

.animate-ping {
    animation: pulse-ring 2s cubic-bezier(0, 0, 0.2, 1) infinite;
}

/* Hover tooltip */
button:hover::after {
    content: 'AI Assistant';
    position: absolute;
    bottom: 100%;
    right: 0;
    margin-bottom: 8px;
    padding: 6px 12px;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    font-size: 12px;
    font-weight: 500;
    border-radius: 8px;
    white-space: nowrap;
    opacity: 0;
    animation: fadeIn 0.2s ease-out 0.5s forwards;
    pointer-events: none;
}

@keyframes fadeIn {
    to {
        opacity: 1;
    }
}
</style>
