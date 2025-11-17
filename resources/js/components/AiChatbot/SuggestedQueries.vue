<script setup lang="ts">
import type { SuggestedQuery } from '@/types/chatbot';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const emit = defineEmits<{
    select: [query: string];
}>();

const page = usePage();
const userRole = computed(() => page.props.auth?.user?.role || 'student');

const suggestions = computed<SuggestedQuery[]>(() => {
    const baseQueries: SuggestedQuery[] = [
        { icon: '📊', text: "Today's attendance", query: 'How many students were absent today?' },
        { icon: '📅', text: 'Weekly summary', query: 'Show me attendance summary for this week' },
    ];

    if (userRole.value === 'student') {
        return [
            { icon: '👤', text: 'My attendance', query: 'Show my attendance record for this month' },
            { icon: '📝', text: 'My absences', query: 'How many times have I been absent this month?' },
            { icon: '⏰', text: 'Late arrivals', query: 'How many times was I late this month?' },
            { icon: '📋', text: 'Excuse requests', query: 'Do I have any pending excuse requests?' },
        ];
    }

    if (userRole.value === 'teacher') {
        return [
            ...baseQueries,
            { icon: '👥', text: 'Class attendance', query: 'How many students were absent today?' },
            { icon: '⚠️', text: 'Student absences', query: 'How many times was [student name] absent this month?' },
            { icon: '🔍', text: 'Attendance rate', query: "What is [student name]'s attendance rate?" },
        ];
    }

    // Admin queries
    return [
        ...baseQueries,
        { icon: '📈', text: 'Overall rate', query: "What's the overall attendance rate this month?" },
        { icon: '🏆', text: 'Best class', query: 'Which class has the best attendance?' },
        { icon: '📉', text: 'Most absences', query: 'Which class has the most absences?' },
        { icon: '📨', text: 'Excuse requests', query: 'Show me all pending excuse requests' },
    ];
});

const handleSelect = (query: string) => {
    emit('select', query);
};
</script>

<template>
    <div class="p-4 space-y-3">
        <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">
            Suggested questions:
        </p>
        <div class="grid grid-cols-1 gap-2">
            <button
                v-for="suggestion in suggestions"
                :key="suggestion.query"
                @click="handleSelect(suggestion.query)"
                class="group flex items-center gap-3 p-3 rounded-xl backdrop-blur-md bg-white/50 dark:bg-gray-800/50 border border-gray-200/50 dark:border-gray-700/50 hover:bg-white/80 dark:hover:bg-gray-800/80 hover:scale-[1.02] hover:shadow-md transition-all duration-200 text-left"
            >
                <span class="text-2xl">{{ suggestion.icon }}</span>
                <span class="text-sm text-gray-700 dark:text-gray-200 font-medium group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                    {{ suggestion.text }}
                </span>
                <svg class="w-4 h-4 ml-auto text-gray-400 group-hover:text-blue-500 group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>
    </div>
</template>
