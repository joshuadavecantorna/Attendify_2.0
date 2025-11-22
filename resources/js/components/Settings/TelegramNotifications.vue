<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

interface TelegramStatus {
    linked: boolean;
    telegram_username: string | null;
    notifications_enabled: boolean;
    verification_code: string | null;
}

interface GenerateCodeResponse {
    success: boolean;
    verification_code?: string;
    bot_username?: string;
    bot_link?: string;
    linked?: boolean;
    message?: string;
}

const status = ref<TelegramStatus>({
    linked: false,
    telegram_username: null,
    notifications_enabled: false,
    verification_code: null,
});

const verificationCode = ref<string | null>(null);
const botLink = ref<string | null>(null);
const isLoading = ref(false);
const isGenerating = ref(false);
const error = ref<string | null>(null);
const success = ref<string | null>(null);

const isLinked = computed(() => status.value.linked);
const notificationsEnabled = computed(() => status.value.notifications_enabled);

const fetchStatus = async () => {
    isLoading.value = true;
    error.value = null;

    try {
        const response = await axios.get<TelegramStatus>('/api/telegram/status');
        status.value = response.data;
        verificationCode.value = response.data.verification_code;
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to fetch status';
    } finally {
        isLoading.value = false;
    }
};

const generateCode = async () => {
    isGenerating.value = true;
    error.value = null;
    success.value = null;

    try {
        const response = await axios.post<GenerateCodeResponse>('/api/telegram/generate-code');
        
        if (response.data.success) {
            verificationCode.value = response.data.verification_code || null;
            botLink.value = response.data.bot_link || null;
            status.value.verification_code = response.data.verification_code || null;
            success.value = 'Verification code generated! Click the button below to open Telegram.';
        } else {
            error.value = response.data.message || 'Failed to generate code';
        }
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to generate verification code';
    } finally {
        isGenerating.value = false;
    }
};

const unlinkAccount = async () => {
    if (!confirm('Are you sure you want to unlink your Telegram account? You will stop receiving notifications.')) {
        return;
    }

    isLoading.value = true;
    error.value = null;
    success.value = null;

    try {
        const response = await axios.post('/api/telegram/unlink');
        
        if (response.data.success) {
            success.value = 'Telegram account unlinked successfully';
            await fetchStatus();
            verificationCode.value = null;
            botLink.value = null;
        }
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to unlink account';
    } finally {
        isLoading.value = false;
    }
};

const toggleNotifications = async () => {
    isLoading.value = true;
    error.value = null;
    success.value = null;

    try {
        const response = await axios.post('/api/telegram/toggle-notifications');
        
        if (response.data.success) {
            status.value.notifications_enabled = response.data.notifications_enabled;
            const statusText = response.data.notifications_enabled ? 'enabled' : 'disabled';
            success.value = `Notifications ${statusText} successfully`;
        }
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to toggle notifications';
    } finally {
        isLoading.value = false;
    }
};

const openTelegramBot = () => {
    if (botLink.value) {
        window.open(botLink.value, '_blank');
    }
};

const copyCode = async () => {
    if (verificationCode.value) {
        try {
            await navigator.clipboard.writeText(verificationCode.value);
            success.value = 'Verification code copied to clipboard!';
            setTimeout(() => { success.value = null; }, 2000);
        } catch (err) {
            error.value = 'Failed to copy code';
        }
    }
};

onMounted(() => {
    fetchStatus();
});
</script>

<template>
    <div class="max-w-2xl mx-auto p-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center">
                    <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.295-.6.295-.002 0-.003 0-.005 0l.213-3.054 5.56-5.022c.24-.213-.054-.334-.373-.121L8.08 13.768l-2.88-.903c-.627-.198-.642-.627.13-.93l11.25-4.337c.524-.194.982.127.813.623z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Telegram Notifications</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Receive class reminders on Telegram</p>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading && !isLinked" class="text-center py-8">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
                <p class="mt-4 text-gray-600 dark:text-gray-400">Loading...</p>
            </div>

            <!-- Error Message -->
            <div v-if="error" class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <p class="text-red-800 dark:text-red-200">{{ error }}</p>
            </div>

            <!-- Success Message -->
            <div v-if="success" class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                <p class="text-green-800 dark:text-green-200">{{ success }}</p>
            </div>

            <!-- Not Linked State -->
            <div v-if="!isLoading && !isLinked">
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6 mb-6">
                    <h3 class="font-semibold text-blue-900 dark:text-blue-200 mb-2">📱 Setup Instructions</h3>
                    <ol class="list-decimal list-inside space-y-2 text-sm text-blue-800 dark:text-blue-300">
                        <li>Click "Generate Verification Code" below</li>
                        <li>Click "Open Telegram Bot" to start a chat</li>
                        <li>Send the verification code to the bot</li>
                        <li>You'll receive a confirmation message</li>
                        <li>Start receiving class reminders!</li>
                    </ol>
                </div>

                <!-- Generate Code Section -->
                <div v-if="!verificationCode" class="text-center">
                    <button
                        @click="generateCode"
                        :disabled="isGenerating"
                        class="px-6 py-3 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="isGenerating">Generating...</span>
                        <span v-else>Generate Verification Code</span>
                    </button>
                </div>

                <!-- Verification Code Display -->
                <div v-else class="space-y-4">
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-6 border-2 border-dashed border-gray-300 dark:border-gray-700">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Your Verification Code:</p>
                        <div class="flex items-center justify-between bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                            <code class="text-2xl font-mono font-bold text-blue-600 dark:text-blue-400">{{ verificationCode }}</code>
                            <button
                                @click="copyCode"
                                class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                title="Copy code"
                            >
                                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button
                        @click="openTelegramBot"
                        class="w-full px-6 py-3 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.295-.6.295-.002 0-.003 0-.005 0l.213-3.054 5.56-5.022c.24-.213-.054-.334-.373-.121L8.08 13.768l-2.88-.903c-.627-.198-.642-.627.13-.93l11.25-4.337c.524-.194.982.127.813.623z"/>
                        </svg>
                        Open Telegram Bot
                    </button>

                    <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                        Send the code above to the bot to complete setup
                    </p>
                </div>
            </div>

            <!-- Linked State -->
            <div v-else class="space-y-6">
                <!-- Connection Status -->
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-green-900 dark:text-green-200">Connected</p>
                            <p class="text-sm text-green-700 dark:text-green-300">
                                @{{ status.telegram_username || 'Unknown' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Notifications Toggle -->
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">Notifications</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Receive class reminders 30 minutes before
                        </p>
                    </div>
                    <button
                        @click="toggleNotifications"
                        :disabled="isLoading"
                        :class="[
                            'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
                            notificationsEnabled ? 'bg-blue-500' : 'bg-gray-300 dark:bg-gray-700'
                        ]"
                    >
                        <span
                            :class="[
                                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                                notificationsEnabled ? 'translate-x-6' : 'translate-x-1'
                            ]"
                        />
                    </button>
                </div>

                <!-- Unlink Button -->
                <button
                    @click="unlinkAccount"
                    :disabled="isLoading"
                    class="w-full px-4 py-2 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Unlink Telegram Account
                </button>

                <!-- Info Box -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <p class="text-sm text-blue-800 dark:text-blue-300">
                        💡 You'll receive notifications for all your enrolled classes 30 minutes before they start.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
