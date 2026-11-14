<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    Bot,
    CheckCircle2,
    Clock,
    Coins,
    ExternalLink,
    Handshake,
    Leaf,
    Package,
    Plus,
    RefreshCw,
    Sparkles,
    Tag,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface DashboardMetrics {
    total_expiring_soon: number;
    food_saved_kg: number;
    financial_loss_prevented: number;
    financial_loss_prevented_formatted: string;
    co2_emission_reduced_kg: number;
    active_partners_count: number;
}

interface ProductSummary {
    id: number;
    name: string;
    sku: string;
    category: string;
    quantity: number;
    unit: string;
    original_price: number;
    discount_price: number | null;
    expiration_date: string;
    days_left: number;
    status: 'active' | 'discounted' | 'reserved_donation' | 'donated' | 'disposed';
}

interface AgentLogItem {
    id: number;
    action_type: 'discount_applied' | 'donation_requested' | 'alert_sent' | 'manual_override';
    reasoning: string;
    payload: Record<string, any>;
    status: 'success' | 'failed' | 'pending';
    created_at: string;
    created_at_exact?: string;
    product: {
        id: number;
        name: string;
        sku: string;
        category: string;
    } | null;
    partner?: {
        id: number;
        name: string;
        email?: string;
        phone?: string;
    } | null;
    partner_name?: string | null;
}

interface Props {
    metrics: DashboardMetrics;
    recent_agent_logs: AgentLogItem[];
    urgent_products: ProductSummary[];
    mock_ai_enabled: boolean;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dasbor',
        href: '/dashboard',
    },
];

// Flash notifications & feedback toasts
const page = usePage();
const flash = computed(() => page.props.flash as Record<string, string> | undefined);
const dismissedFlashes = ref<Set<string>>(new Set());
const isRefreshing = ref(false);

function dismissFlash(type: string) {
    dismissedFlashes.value.add(type);
}

function isFlashVisible(type: string): boolean {
    return !dismissedFlashes.value.has(type) && !!flash.value?.[type];
}

function refreshDashboard() {
    router.reload({
        only: ['metrics', 'recent_agent_logs', 'urgent_products'],
        onStart: () => {
            isRefreshing.value = true;
        },
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
}

function categoryLabel(name: string): string {
    return ({
        'Fresh Produce': 'Buah dan Sayur', Bakery: 'Roti dan Kue', 'Dairy & Eggs': 'Susu dan Telur',
        'Packaged Goods': 'Bahan Pangan Kemasan', 'Meat & Seafood': 'Daging dan Hasil Laut', General: 'Umum',
    } as Record<string, string>)[name] ?? name;
}

function statusLabel(status: ProductSummary['status']): string {
    return {
        active: 'Aktif',
        discounted: 'Diskon',
        reserved_donation: 'Disiapkan untuk donasi',
        donated: 'Didonasikan',
        disposed: 'Dibuang',
    }[status];
}

function actionLabel(action: AgentLogItem['action_type']): string {
    return {
        discount_applied: 'Diskon diterapkan',
        donation_requested: 'Donasi diminta',
        alert_sent: 'Peringatan dikirim',
        manual_override: 'Perubahan manual',
    }[action];
}
</script>

<template>
    <Head title="Dasbor SirkulaFood" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-full min-w-0 flex-1 flex-col gap-5 p-3 sm:gap-6 sm:p-6">
            <!-- Flash notification -->
            <Transition name="flash">
                <div
                    v-if="isFlashVisible('success')"
                    class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                >
                    <CheckCircle2 class="size-5 shrink-0" />
                    <span class="flex-1">{{ flash?.success }}</span>
                    <button @click="dismissFlash('success')" class="shrink-0 rounded-md p-1 hover:bg-emerald-200 dark:hover:bg-emerald-800">
                        <X class="size-4" />
                    </button>
                </div>
            </Transition>

            <!-- Quick Action Bar & Agent Status Banner -->
            <div class="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h1 class="text-xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100 sm:text-3xl">Dasbor Ekonomi Sirkular</h1>
                    <p class="mt-1 max-w-2xl text-xs leading-5 text-muted-foreground sm:text-sm sm:leading-normal">
                        Prediksi masa simpan berbasis AI, pengelolaan diskon dinamis, dan metrik pengurangan limbah pangan.
                    </p>
                </div>

                <!-- Quick Action Buttons -->
                <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-center sm:gap-2.5">
                    <button
                        type="button"
                        :disabled="isRefreshing"
                        @click="refreshDashboard"
                        class="col-span-2 inline-flex min-w-0 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50 sm:col-span-1 sm:px-4 sm:text-sm"
                    >
                        <RefreshCw class="size-4" :class="{ 'animate-spin': isRefreshing }" />
                        <span>{{ isRefreshing ? 'Memuat ulang…' : 'Muat ulang data' }}</span>
                    </button>

                    <Link
                        href="/inventory"
                        prefetch
                        class="inline-flex min-w-0 items-center justify-center gap-1.5 rounded-lg border border-sidebar-border bg-card px-2.5 py-2.5 text-xs font-medium text-foreground shadow-sm transition hover:bg-accent sm:justify-start sm:gap-2 sm:px-3.5 sm:text-sm"
                    >
                        <Package class="size-4 text-muted-foreground" />
                        <span class="sm:hidden">Lihat stok</span><span class="hidden sm:inline">Lihat produk mendekati kedaluwarsa</span>
                    </Link>

                    <Link
                        href="/inventory"
                        class="inline-flex min-w-0 items-center justify-center gap-1.5 rounded-lg border border-sidebar-border bg-card px-2.5 py-2.5 text-xs font-medium text-foreground shadow-sm transition hover:bg-accent sm:justify-start sm:px-3.5 sm:text-sm"
                    >
                        <Plus class="size-4 text-muted-foreground" />
                        <span>Tambah stok</span>
                    </Link>
                </div>
            </div>

            <!-- KPI Metric Summary Cards -->
            <div class="grid min-w-0 grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <!-- 1. Expiring Soon -->
                <div
                    class="group relative min-w-0 overflow-hidden rounded-2xl border border-sidebar-border/80 bg-card p-3.5 shadow-sm transition-all duration-200 hover:shadow-md sm:p-5 dark:border-sidebar-border"
                >
                    <div class="flex items-start justify-between">
                        <div class="space-y-1.5">
                            <p class="text-[10px] font-semibold uppercase leading-4 tracking-wide text-muted-foreground sm:text-xs sm:tracking-wider">Segera Kedaluwarsa</p>
                            <p class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-50 sm:text-3xl">
                                {{ metrics.total_expiring_soon }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-amber-500/10 p-2 text-amber-600 sm:p-3 dark:text-amber-400">
                            <Clock class="size-4 sm:size-5" />
                        </div>
                    </div>
                    <div class="mt-3 flex flex-col items-start gap-2 text-[10px] leading-4 sm:mt-4 sm:flex-row sm:items-center sm:justify-between sm:text-xs">
                        <span class="inline-flex items-center gap-1 font-medium text-amber-600 dark:text-amber-400">
                            <AlertTriangle class="size-3.5" />
                            <span>Perlu ditangani dalam ≤ 7 hari</span>
                        </span>
                        <Link href="/inventory" class="text-muted-foreground hover:text-foreground">Lihat semua &rarr;</Link>
                    </div>
                    <div
                        class="pointer-events-none absolute -right-6 -top-6 size-24 rounded-full bg-amber-500/10 opacity-0 blur-2xl transition-opacity group-hover:opacity-100"
                    />
                </div>

                <!-- 2. Food Waste Prevented -->
                <div
                    class="group relative min-w-0 overflow-hidden rounded-2xl border border-sidebar-border/80 bg-card p-3.5 shadow-sm transition-all duration-200 hover:shadow-md sm:p-5 dark:border-sidebar-border"
                >
                    <div class="flex items-start justify-between">
                        <div class="space-y-1.5">
                            <p class="text-[10px] font-semibold uppercase leading-4 tracking-wide text-muted-foreground sm:text-xs sm:tracking-wider">Pangan Terselamatkan</p>
                            <p class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-50 sm:text-3xl">
                                {{ metrics.food_saved_kg }} <span class="text-lg font-medium text-muted-foreground">kg</span>
                            </p>
                        </div>
                        <div class="rounded-xl bg-emerald-500/10 p-2 text-emerald-600 sm:p-3 dark:text-emerald-400">
                            <Leaf class="size-4 sm:size-5" />
                        </div>
                    </div>
                    <div class="mt-3 flex flex-col items-start gap-2 text-[10px] leading-4 sm:mt-4 sm:flex-row sm:items-center sm:justify-between sm:text-xs">
                        <span class="inline-flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400">
                            <ArrowUpRight class="size-3.5" />
                            <span>Dialihkan dari tempat pembuangan</span>
                        </span>
                        <span class="text-muted-foreground">Sasaran pangan organik</span>
                    </div>
                    <div
                        class="pointer-events-none absolute -right-6 -top-6 size-24 rounded-full bg-emerald-500/10 opacity-0 blur-2xl transition-opacity group-hover:opacity-100"
                    />
                </div>

                <!-- 3. Financial Loss Prevented (in IDR) -->
                <div
                    class="group relative min-w-0 overflow-hidden rounded-2xl border border-sidebar-border/80 bg-card p-3.5 shadow-sm transition-all duration-200 hover:shadow-md sm:p-5 dark:border-sidebar-border"
                >
                    <div class="flex items-start justify-between">
                        <div class="space-y-1.5">
                            <p class="text-[10px] font-semibold uppercase leading-4 tracking-wide text-muted-foreground sm:text-xs sm:tracking-wider">Potensi Kerugian Dicegah</p>
                            <p class="break-words text-lg font-bold tracking-tight text-neutral-900 dark:text-neutral-50 sm:text-2xl">
                                {{ metrics.financial_loss_prevented_formatted }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-blue-500/10 p-2 text-blue-600 sm:p-3 dark:text-blue-400">
                            <Coins class="size-4 sm:size-5" />
                        </div>
                    </div>
                    <div class="mt-3 flex flex-col items-start gap-1 text-[10px] leading-4 sm:mt-4 sm:flex-row sm:items-center sm:justify-between sm:text-xs">
                        <span class="font-medium text-blue-600 dark:text-blue-400"> Melalui diskon dan penyelamatan pangan </span>
                        <span class="text-muted-foreground">Setara rupiah</span>
                    </div>
                    <div
                        class="pointer-events-none absolute -right-6 -top-6 size-24 rounded-full bg-blue-500/10 opacity-0 blur-2xl transition-opacity group-hover:opacity-100"
                    />
                </div>

                <!-- 4. CO2 Emission Reduced -->
                <div
                    class="group relative min-w-0 overflow-hidden rounded-2xl border border-sidebar-border/80 bg-card p-3.5 shadow-sm transition-all duration-200 hover:shadow-md sm:p-5 dark:border-sidebar-border"
                >
                    <div class="flex items-start justify-between">
                        <div class="space-y-1.5">
                            <p class="text-[10px] font-semibold uppercase leading-4 tracking-wide text-muted-foreground sm:text-xs sm:tracking-wider">Emisi CO₂e Dikurangi</p>
                            <p class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-50 sm:text-3xl">
                                {{ metrics.co2_emission_reduced_kg }} <span class="text-lg font-medium text-muted-foreground">kg</span>
                            </p>
                        </div>
                        <div class="rounded-xl bg-teal-500/10 p-2 text-teal-600 sm:p-3 dark:text-teal-400">
                            <Sparkles class="size-4 sm:size-5" />
                        </div>
                    </div>
                    <div class="mt-3 flex flex-col items-start gap-1 text-[10px] leading-4 sm:mt-4 sm:flex-row sm:items-center sm:justify-between sm:text-xs">
                        <span class="font-medium text-teal-600 dark:text-teal-400"> Faktor 2,5 kg CO₂e / kg pangan </span>
                        <span class="text-muted-foreground">Acuan EPA</span>
                    </div>
                    <div
                        class="pointer-events-none absolute -right-6 -top-6 size-24 rounded-full bg-teal-500/10 opacity-0 blur-2xl transition-opacity group-hover:opacity-100"
                    />
                </div>
            </div>

            <!-- Main Content Area: Urgent Items Attention Table & Live Agent Activity Feed -->
            <div class="grid min-w-0 flex-1 gap-5 sm:gap-6 lg:grid-cols-3">
                <!-- Urgent Items Attention (2 cols) -->
                <div class="flex min-w-0 flex-col rounded-2xl border border-sidebar-border/80 bg-card shadow-sm dark:border-sidebar-border lg:col-span-2">
                    <div class="flex min-w-0 items-start justify-between gap-3 border-b border-sidebar-border/80 px-4 py-4 sm:px-6">
                        <div class="flex items-center gap-2.5">
                            <div class="rounded-lg bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                                <AlertTriangle class="size-4" />
                            </div>
                            <div>
                                <h3 class="text-base font-semibold leading-tight text-neutral-900 dark:text-neutral-100">Produk Prioritas</h3>
                                <p class="text-[11px] leading-4 text-muted-foreground sm:text-xs">Produk yang kedaluwarsa dalam 1–3 hari dan memerlukan diskon atau donasi</p>
                            </div>
                        </div>

                        <Link
                            href="/inventory"
                            class="inline-flex shrink-0 items-center gap-1 text-[10px] font-medium text-emerald-600 hover:text-emerald-700 sm:text-xs dark:text-emerald-400"
                        >
                            <span>Buka stok produk</span>
                            <ExternalLink class="size-3" />
                        </Link>
                    </div>

                    <!-- Table of Urgent Items -->
                    <div class="min-w-0 flex-1 overflow-x-auto">
                        <table class="w-full min-w-[780px] text-left text-sm">
                            <thead class="border-b border-sidebar-border/60 bg-muted/40 text-xs font-medium text-muted-foreground">
                                <tr>
                                    <th class="px-6 py-3">Produk / SKU</th>
                                    <th class="px-4 py-3">Kategori</th>
                                    <th class="px-4 py-3">Jumlah</th>
                                    <th class="px-4 py-3">Harga</th>
                                    <th class="px-4 py-3">Kedaluwarsa dalam</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border/60">
                                <tr v-for="item in urgent_products" :key="item.id" class="transition-colors hover:bg-muted/30">
                                    <td class="px-6 py-3.5">
                                        <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ item.name }}</p>
                                        <p class="font-mono text-xs text-muted-foreground">{{ item.sku }}</p>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex rounded-md bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                            {{ categoryLabel(item.category) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 font-medium">{{ item.quantity }} {{ item.unit }}</td>
                                    <td class="px-4 py-3.5">
                                        <div v-if="item.discount_price" class="flex flex-col">
                                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                                {{ formatCurrency(item.discount_price) }}
                                            </span>
                                            <span class="text-xs text-muted-foreground line-through">{{ formatCurrency(item.original_price) }}</span>
                                        </div>
                                        <span v-else class="font-medium">{{ formatCurrency(item.original_price) }}</span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                            :class="{
                                                'bg-red-500/10 text-red-700 dark:text-red-400': item.days_left <= 1,
                                                'bg-amber-500/10 text-amber-700 dark:text-amber-400': item.days_left === 2,
                                                'bg-yellow-500/10 text-yellow-800 dark:text-yellow-400': item.days_left >= 3,
                                            }"
                                        >
                                            <Clock class="size-3" />
                                            <span>{{ item.days_left <= 0 ? 'Hari ini' : `${item.days_left} hari lagi` }}</span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span
                                            class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium capitalize"
                                            :class="{
                                                'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400': item.status === 'discounted',
                                                'bg-violet-500/10 text-violet-700 dark:text-violet-400': item.status === 'reserved_donation',
                                                'bg-blue-500/10 text-blue-700 dark:text-blue-400': item.status === 'active',
                                            }"
                                        >
                                            {{ statusLabel(item.status) }}
                                        </span>
                                    </td>
                                </tr>

                                <tr v-if="urgent_products.length === 0">
                                    <td colspan="6" class="py-12 text-center text-sm text-muted-foreground">
                                        Tidak ada produk yang harus segera ditangani. Masa simpan semua produk masih aman.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Live Agent Activity Feed Widget (1 col) -->
                <div class="flex min-w-0 flex-col rounded-2xl border border-sidebar-border/80 bg-card shadow-sm dark:border-sidebar-border">
                    <div class="flex items-center justify-between gap-3 border-b border-sidebar-border/80 px-4 py-4 sm:px-6">
                        <div class="flex items-center gap-2.5">
                            <div class="rounded-lg bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                                <Bot class="size-4" />
                            </div>
                            <div>
                                <h3 class="text-base font-semibold leading-tight text-neutral-900 dark:text-neutral-100">Aktivitas Agen</h3>
                                <p class="text-xs text-muted-foreground">Catatan keputusan otomatis</p>
                            </div>
                        </div>

                        <span class="relative flex size-2.5">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                            <span class="relative inline-flex size-2.5 rounded-full bg-emerald-500" />
                        </span>
                    </div>

                    <!-- Logs List -->
                    <div class="max-h-[520px] flex-1 space-y-3.5 divide-y divide-sidebar-border/60 overflow-y-auto p-4">
                        <div
                            v-for="log in recent_agent_logs"
                            :key="log.id"
                            class="rounded-xl border border-sidebar-border/60 bg-muted/20 p-3.5 transition hover:bg-muted/40"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-semibold"
                                        :class="{
                                            'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400': log.action_type === 'discount_applied',
                                            'bg-violet-500/10 text-violet-700 dark:text-violet-400': log.action_type === 'donation_requested',
                                            'bg-amber-500/10 text-amber-700 dark:text-amber-400': log.action_type === 'alert_sent',
                                            'bg-neutral-500/10 text-neutral-700 dark:text-neutral-400': log.action_type === 'manual_override',
                                        }"
                                    >
                                        <Tag v-if="log.action_type === 'discount_applied'" class="size-3" />
                                        <Handshake v-else-if="log.action_type === 'donation_requested'" class="size-3" />
                                        <Zap v-else class="size-3" />
                                        <span>{{ actionLabel(log.action_type) }}</span>
                                    </span>

                                    <!-- Status Pill -->
                                    <span
                                        class="inline-flex size-1.5 rounded-full"
                                        :class="{
                                            'bg-emerald-500': log.status === 'success',
                                            'bg-amber-500': log.status === 'pending',
                                            'bg-red-500': log.status === 'failed',
                                        }"
                                    />
                                </div>

                                <span class="text-xs text-muted-foreground">{{ log.created_at }}</span>
                            </div>

                            <!-- Target Product / Partner context -->
                            <div v-if="log.product" class="mt-2 flex items-center gap-1.5 text-xs font-medium text-neutral-800 dark:text-neutral-200">
                                <Package class="size-3.5 text-muted-foreground" />
                                <span>{{ log.product.name }}</span>
                                <span class="font-mono text-muted-foreground">({{ log.product.sku }})</span>
                            </div>

                            <div v-if="log.partner_name" class="mt-1 flex items-center gap-1.5 text-xs text-violet-700 dark:text-violet-300">
                                <Handshake class="size-3.5" />
                                <span>Disalurkan kepada: {{ log.partner_name }}</span>
                            </div>

                            <!-- AI Reasoning text -->
                            <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                                {{ log.reasoning }}
                            </p>
                        </div>

                        <div v-if="recent_agent_logs.length === 0" class="py-12 text-center text-sm text-muted-foreground">
                            <Bot class="mx-auto mb-2 size-8 opacity-40" />
                            <p>Belum ada catatan aktivitas otomatis.</p>
                            <p class="text-xs">Stok baru akan dievaluasi otomatis setelah disimpan.</p>
                        </div>
                    </div>

                    <div class="rounded-b-2xl border-t border-sidebar-border/80 bg-muted/20 p-3 text-center">
                        <Link href="/agent-logs" class="text-xs font-medium text-muted-foreground transition hover:text-foreground">
                            Lihat seluruh riwayat aktivitas &rarr;
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.flash-enter-active,
.flash-leave-active {
    transition: all 0.3s ease;
}
.flash-enter-from {
    opacity: 0;
    transform: translateY(-8px);
}
.flash-leave-to {
    opacity: 0;
    transform: translateX(16px);
}
</style>
