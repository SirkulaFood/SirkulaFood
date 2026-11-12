<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    items: NavItem[];
}>();

const page = usePage<SharedData>();
const { setOpenMobile } = useSidebar();
const isItemActive = (href: string) => {
    const [itemPath, itemQuery] = href.split('?');
    const [currentPath, currentQuery] = page.url.split('?');
    if (itemPath === '/dashboard' && (currentPath === '/' || currentPath === '/dashboard')) return true;
    return itemPath === currentPath && (!itemQuery || itemQuery === currentQuery);
};
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Menu utama</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton as-child :is-active="isItemActive(item.href)">
                    <Link :href="item.href" @click="setOpenMobile(false)">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
