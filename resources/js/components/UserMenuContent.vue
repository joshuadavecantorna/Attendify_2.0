<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import type { User } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Settings } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    user: User;
}

const props = defineProps<Props>();

const settingsUrl = computed(() => {
    const role = props.user.role?.toLowerCase();
    if (role === 'teacher') return '/teacher/settings';
    if (role === 'admin') return '/admin/settings';
    if (role === 'student') return '/settings/profile';
    return '/settings/profile';
});

const handleLogout = () => {
    router.post('/logout', {}, {
        preserveState: false,
        preserveScroll: false,
    });
};

</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="settingsUrl">
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <button class="flex w-full items-center cursor-pointer" @click="handleLogout">
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </button>
    </DropdownMenuItem>
</template>