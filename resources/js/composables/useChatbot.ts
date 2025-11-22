import { reactive, ref, computed } from 'vue';
import type { ChatMessage, ChatState, ChatResponse } from '@/types/chatbot';
import axios from 'axios';

const state = reactive<ChatState>({
    isOpen: false,
    messages: [],
    isLoading: false,
    error: null,
});

export function useChatbot() {
    const generateId = () => `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;

    const openChat = () => {
        state.isOpen = true;
        state.error = null;
    };

    const closeChat = () => {
        state.isOpen = false;
    };

    const toggleChat = () => {
        state.isOpen = !state.isOpen;
    };

    const addMessage = (role: 'user' | 'assistant', content: string) => {
        const message: ChatMessage = {
            id: generateId(),
            role,
            content,
            timestamp: new Date(),
        };
        state.messages.push(message);
        return message;
    };

    const sendMessage = async (content: string) => {
        if (!content.trim() || state.isLoading) return;

        // Add user message
        addMessage('user', content);
        state.isLoading = true;
        state.error = null;

        // Add temporary loading message
        const loadingMessage: ChatMessage = {
            id: generateId(),
            role: 'assistant',
            content: '',
            timestamp: new Date(),
            isLoading: true,
        };
        state.messages.push(loadingMessage);

        try {
            // Build conversation history (last 10 messages for context)
            const conversationHistory = state.messages
                .slice(-10)
                .map(msg => ({
                    role: msg.role,
                    content: msg.content
                }));

            const response = await axios.post<ChatResponse>('/api/chatbot/query', {
                message: content,
                conversation_history: conversationHistory,
            }, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                withCredentials: true,
            });

            // Remove loading message
            const loadingIndex = state.messages.findIndex(m => m.id === loadingMessage.id);
            if (loadingIndex !== -1) {
                state.messages.splice(loadingIndex, 1);
            }

            // Add AI response
            if (response.data.success) {
                addMessage('assistant', response.data.response);
            } else {
                throw new Error(response.data.error || 'Failed to get response');
            }
        } catch (error: any) {
            // Remove loading message
            const loadingIndex = state.messages.findIndex(m => m.id === loadingMessage.id);
            if (loadingIndex !== -1) {
                state.messages.splice(loadingIndex, 1);
            }

            const errorMessage = error.response?.data?.error || error.message || 'Failed to send message. Please try again.';
            state.error = errorMessage;
            addMessage('assistant', `Sorry, I encountered an error: ${errorMessage}`);
        } finally {
            state.isLoading = false;
        }
    };

    const clearHistory = () => {
        state.messages = [];
        state.error = null;
    };

    return {
        // State (as refs)
        isOpen: computed(() => state.isOpen),
        messages: computed(() => state.messages),
        isLoading: computed(() => state.isLoading),
        error: computed(() => state.error),

        // Actions
        openChat,
        closeChat,
        toggleChat,
        sendMessage,
        clearHistory,
    };
}
