<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BadgePercent, CalendarDays, FlaskConical, Handshake, HeartHandshake, History, LayoutGrid, Package, Percent, ScrollText, Settings, Store } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const page = usePage<{ auth: { user: { role: string } | null } }>();
const userRole = page.props.auth.user?.role;
const businessRoles = ['manager', 'cashier', 'business_owner'];
const supermarketNavItems: NavItem[] = [
    {
        title: 'Dasbor',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Stok Produk',
        href: '/inventory',
        icon: Package,
    },
    {
        title: 'Riwayat Sinkronisasi',
        href: '/inventory/sync-history',
        icon: History,
    },
    {
        title: 'Simulasi AI',
        href: '/simulation',
        icon: FlaskConical,
    },
    {
        title: 'Log Aktivitas Agen AI',
        href: '/agent-logs',
        icon: ScrollText,
    },
    {
        title: 'Pengaturan',
        href: '/settings',
        icon: Settings,
    },
];
const reportNavItems: NavItem[] = [
    { title: 'Rekap Donasi', href: '/reports?type=donation', icon: HeartHandshake },
    { title: 'Rekap Diskon', href: '/reports?type=discount', icon: Percent },
];

const isEcoLoopAdmin = ['SirkulaFood_admin', 'ecoloop_admin'].includes(userRole ?? '');
const mainNavItems: NavItem[] = isEcoLoopAdmin
    ? [{ title: 'Kelola Mitra', href: '/admin/partners', icon: Handshake }]
    : userRole === 'donation_partner'
        ? [
            { title: 'Pindai Produk', href: '/partner/scan', icon: FlaskConical },
            { title: 'Donasi Hari Ini', href: '/partner/donations/today', icon: HeartHandshake },
            { title: 'Daftar Supermarket/Toko', href: '/partner/stores', icon: Store },
            { title: 'Riwayat Donasi', href: '/partner/donations/history', icon: History },
        ]
        : userRole === 'umkm_partner'
          ? [
                { title: 'Diskon Tersedia', href: '/umkm/offers', icon: BadgePercent },
                { title: 'Daftar Supermarket/Toko', href: '/umkm/stores', icon: Store },
                { title: 'Riwayat Diskon', href: '/umkm/discount-history', icon: History },
            ]
        : businessRoles.includes(userRole ?? '')
          ? [...supermarketNavItems, ...reportNavItems]
          : supermarketNavItems;
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('home')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
