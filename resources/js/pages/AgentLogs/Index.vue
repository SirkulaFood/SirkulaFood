<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    Bot,
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    Code2,
    Copy,
    Cpu,
    Filter,
    HeartHandshake,
    Percent,
    RefreshCw,
    RotateCcw,
    Search,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    Tag,
    TrendingDown,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface ProductInfo {
    id: number;
    name: string;
    sku: string;
    category?: string;
    original_price?: number;
    discount_price?: number | null;
    status?: string;
    expiration_date?: string;
}

interface PartnerInfo {
    id?: number | null;
    name: string;
    contact_person?: string | null;
    email?: string | null;
    phone?: string | null;
}

interface AgentLogItem {
    id: number;
    product_id: number | null;
    partner_id: number | null;
    action_type: 'discount_applied' | 'donation_requested' | 'alert_sent' | 'manual_override';
    reasoning: string;
    payload: Record<string, any>;
    status: 'success' | 'failed' | 'pending';
    created_at: string;
    created_at_human: string;
    product?: ProductInfo | null;
    partner?: PartnerInfo | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedLogs {
    data: AgentLogItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

interface StatsInfo {
    total_logs: number;
    discounts_count: number;
    donations_count: number;
    alerts_count: number;
    overrides_count: number;
    success_rate: number;
}

interface FilterProps {
    search: string;
    action_type: string;
    status: string;
    date: string;
}

interface PartnerOption {
    id: number;
    name: string;
}

const props = defineProps<{
    logs: PaginatedLogs;
    filters: FilterProps;
    stats: StatsInfo;
    partners: PartnerOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dasbor', href: '/dashboard' },
    { title: 'Riwayat Audit', href: '/agent-logs' },
];

const page = usePage();
const flashSuccess = computed(() => (page.props as any).flash?.success);
const flashError = computed(() => (page.props as any).flash?.error);

// ─── Filter & Search State ──────────────────────────────────────────────────
const search = ref(props.filters.search || '');
const selectedActionType = ref(props.filters.action_type || 'all');
const selectedStatus = ref(props.filters.status || 'all');
const selectedDate = ref(props.filters.date || 'all');
const isSimulating = ref(false);
const isRefreshing = ref(false);

let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

const applyFilters = () => {
    router.get(
        '/agent-logs',
        {
            search: search.value || undefined,
            action_type: selectedActionType.value !== 'all' ? selectedActionType.value : undefined,
            status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
            date: selectedDate.value !== 'all' ? selectedDate.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const handleSearchInput = () => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
    searchDebounceTimer = setTimeout(() => {
        applyFilters();
    }, 350);
};

const clearSearch = () => {
    search.value = '';
    applyFilters();
};

const resetAllFilters = () => {
    search.value = '';
    selectedActionType.value = 'all';
    selectedStatus.value = 'all';
    selectedDate.value = 'all';
    applyFilters();
};

const hasActiveFilters = computed(() => {
    return search.value !== '' || selectedActionType.value !== 'all' || selectedStatus.value !== 'all' || selectedDate.value !== 'all';
});

// ─── AI Simulation & Refresh ────────────────────────────────────────────────
const runSimulation = () => {
    isSimulating.value = true;
    router.post(
        '/agent-logs/simulate',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isSimulating.value = false;
            },
        },
    );
};

const refreshLogs = () => {
    isRefreshing.value = true;
    router.reload({
        only: ['logs', 'stats'],
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
};

// ─── JSON Inspection Modal ──────────────────────────────────────────────────
const selectedLogForJson = ref<AgentLogItem | null>(null);
const isJsonModalOpen = ref(false);
const jsonViewMode = ref<'code' | 'summary'>('code');
const hasCopiedJson = ref(false);

const openJsonModal = (log: AgentLogItem) => {
    selectedLogForJson.value = log;
    jsonViewMode.value = 'code';
    hasCopiedJson.value = false;
    isJsonModalOpen.value = true;
};

const closeJsonModal = () => {
    isJsonModalOpen.value = false;
    selectedLogForJson.value = null;
};

const formattedJsonString = computed(() => {
    if (!selectedLogForJson.value) return '';
    return JSON.stringify(selectedLogForJson.value.payload, null, 2);
});

const copyJsonToClipboard = async () => {
    if (!formattedJsonString.value) return;
    try {
        await navigator.clipboard.writeText(formattedJsonString.value);
        hasCopiedJson.value = true;
        setTimeout(() => {
            hasCopiedJson.value = false;
        }, 2200);
    } catch (e) {
        console.error('Gagal menyalin ke papan klip', e);
    }
};

// Syntax-highlighted HTML generator for raw JSON
const syntaxHighlightedJson = computed(() => {
    if (!selectedLogForJson.value) return '';
    const json = JSON.stringify(selectedLogForJson.value.payload, null, 2);
    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, (match) => {
        let cls = 'text-amber-400 dark:text-amber-300'; // number
        if (/^"/.test(match)) {
            if (/:$/.test(match)) {
                cls = 'text-sky-600 dark:text-sky-300 font-semibold'; // key
            } else {
                cls = 'text-emerald-600 dark:text-emerald-400'; // string value
            }
        } else if (/true|false/.test(match)) {
            cls = 'text-violet-600 dark:text-violet-400 font-medium'; // boolean
        } else if (/null/.test(match)) {
            cls = 'text-rose-500 font-medium'; // null
        }
        return `<span class="${cls}">${match}</span>`;
    });
});

// Detect AI model from payload
const getModelBadge = (payload: Record<string, any>) => {
    if (payload.model) return payload.model;
    if (payload.gemini || payload.gemini_version) return 'Gemini 2.5 Flash';
    if (payload.groq || payload.groq_model) return 'Groq LLaMA 3.3';
    if (payload.n8n || payload.webhook) return 'Alur Kerja Otomatis n8n';
    if (payload.rule) return 'Mesin Heuristik SirkulaFood';
    return 'Layanan Otomatis';
};

const actionTypeLabel = (actionType: string) => ({
    discount_applied: 'Diskon diterapkan',
    donation_requested: 'Permintaan donasi',
    alert_sent: 'Peringatan dikirim',
    manual_override: 'Penyesuaian manual',
}[actionType] ?? actionType.replaceAll('_', ' '));

const statusLabel = (status: string) => ({
    success: 'Berhasil',
    failed: 'Gagal',
    pending: 'Menunggu',
}[status] ?? status);
const paginationLabel = (label: string) => label.includes('Previous') ? 'Sebelumnya' : label.includes('Next') ? 'Berikutnya' : label.replace(/<[^>]*>/g, '');

const formatCurrency = (value: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
const categoryLabel = (name?: string) => ({
    'Fresh Produce': 'Buah dan Sayur', Bakery: 'Roti dan Kue', 'Dairy & Eggs': 'Susu dan Telur',
    'Packaged Goods': 'Bahan Pangan Kemasan', 'Meat & Seafood': 'Daging dan Hasil Laut', General: 'Umum',
}[name ?? ''] ?? name ?? 'Umum');

// ─── Manual Override Modal ──────────────────────────────────────────────────
const selectedLogForOverride = ref<AgentLogItem | null>(null);
const isOverrideModalOpen = ref(false);
const overrideAction = ref<'revert' | 'reassign_partner'>('revert');
const overrideReason = ref('');
const overridePartnerId = ref<number | ''>('');
const isSubmittingOverride = ref(false);
const overrideErrorMessage = ref('');

const openOverrideModal = (log: AgentLogItem) => {
    selectedLogForOverride.value = log;
    overrideReason.value = '';
    overrideErrorMessage.value = '';

    if (log.action_type === 'discount_applied') {
        overrideAction.value = 'revert';
    } else if (log.action_type === 'donation_requested') {
        overrideAction.value = 'revert';
        overridePartnerId.value = props.partners[0]?.id || '';
    } else {
        overrideAction.value = 'revert';
    }

    isOverrideModalOpen.value = true;
};

const closeOverrideModal = () => {
    isOverrideModalOpen.value = false;
    selectedLogForOverride.value = null;
    overrideErrorMessage.value = '';
};

const submitManualOverride = () => {
    if (!selectedLogForOverride.value) return;

    if (!overrideReason.value.trim() || overrideReason.value.trim().length < 5) {
        overrideErrorMessage.value = 'Masukkan alasan penyesuaian minimal 5 karakter.';
        return;
    }

    if (overrideAction.value === 'reassign_partner' && !overridePartnerId.value) {
        overrideErrorMessage.value = 'Pilih mitra donasi tujuan.';
        return;
    }

    isSubmittingOverride.value = true;
    overrideErrorMessage.value = '';

    router.post(
        `/agent-logs/${selectedLogForOverride.value.id}/override`,
        {
            override_action: overrideAction.value,
            reason: overrideReason.value.trim(),
            partner_id: overrideAction.value === 'reassign_partner' ? overridePartnerId.value : undefined,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeOverrideModal();
            },
            onError: (errors) => {
                const firstErr = Object.values(errors)[0];
                overrideErrorMessage.value = typeof firstErr === 'string' ? firstErr : 'Gagal menerapkan penyesuaian manual.';
            },
            onFinish: () => {
                isSubmittingOverride.value = false;
            },
        },
    );
};
</script>

<template>
    <Head title="Riwayat Audit Agen AI - SirkulaFood" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 h-full flex-1 flex-col gap-4 p-3 sm:gap-6 sm:p-6 lg:p-8">
            <!-- Header Section with Live Status Badge and Actions -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex min-w-0 items-start gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white shadow-md shadow-emerald-500/20"
                        >
                            <Cpu class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100 sm:text-2xl">Riwayat Audit Agen AI</h1>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400"
                                >
                                    <span class="size-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                                    Catatan Otomatis
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-muted-foreground sm:text-sm">
                                Verifikasi keputusan yang transparan, pemeriksaan data AI, dan penyesuaian manual oleh operator.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid w-full grid-cols-[1fr_auto] items-center gap-2 sm:flex sm:w-auto sm:self-auto">
                    <button
                        type="button"
                        :disabled="isSimulating"
                        @click="runSimulation"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-emerald-600/30 transition-all hover:bg-emerald-500 hover:shadow-md hover:shadow-emerald-600/40 active:translate-y-0.5 disabled:opacity-60"
                        title="Simulasikan evaluasi AI otomatis untuk stok yang mendekati kedaluwarsa"
                    >
                        <Sparkles v-if="!isSimulating" class="size-4" />
                        <RefreshCw v-else class="size-4 animate-spin" />
                        <span>{{ isSimulating ? 'Mengevaluasi stok…' : 'Simulasikan Pemindaian AI' }}</span>
                    </button>

                    <button
                        type="button"
                        :disabled="isRefreshing"
                        @click="refreshLogs"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        title="Muat ulang riwayat aktivitas agen"
                    >
                        <RefreshCw class="size-4" :class="{ 'animate-spin': isRefreshing }" />
                        <span class="hidden sm:inline">Muat ulang</span>
                    </button>
                </div>
            </div>

            <!-- Flash Alert Messages -->
            <transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="transform -translate-y-2 opacity-0"
                enter-to-class="transform translate-y-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="transform translate-y-0 opacity-100"
                leave-to-class="transform -translate-y-2 opacity-0"
            >
                <div
                    v-if="flashSuccess"
                    class="flex items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-800 shadow-sm dark:text-emerald-300"
                >
                    <CheckCircle2 class="size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <span class="font-medium">{{ flashSuccess }}</span>
                </div>
            </transition>

            <div
                v-if="flashError"
                class="flex items-center gap-3 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-800 shadow-sm dark:text-rose-300"
            >
                <AlertCircle class="size-5 shrink-0 text-rose-600 dark:text-rose-400" />
                <span class="font-medium">{{ flashError }}</span>
            </div>

            <!-- Header Statistics Cards -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <!-- Card 1: Total Decisions -->
                <div
                    class="relative overflow-hidden rounded-2xl border border-neutral-200/80 bg-white/70 p-4 shadow-sm backdrop-blur-sm dark:border-neutral-800 dark:bg-neutral-900/70"
                >
                    <div class="flex items-center justify-between text-neutral-500 dark:text-neutral-400">
                        <span class="text-xs font-medium uppercase tracking-wider">Total Tindakan</span>
                        <Cpu class="size-4 text-neutral-600 dark:text-neutral-300" />
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">{{ stats.total_logs }}</span>
                        <span class="text-xs text-muted-foreground">tercatat</span>
                    </div>
                </div>

                <!-- Card 2: Diskons Applied -->
                <div
                    class="relative overflow-hidden rounded-2xl border border-emerald-500/20 bg-gradient-to-br from-emerald-50/50 to-white/70 p-4 shadow-sm backdrop-blur-sm dark:border-emerald-500/30 dark:from-emerald-950/20 dark:to-neutral-900/70"
                >
                    <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400">
                        <span class="text-xs font-medium uppercase tracking-wider">Diskon</span>
                        <TrendingDown class="size-4" />
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-300">{{ stats.discounts_count }}</span>
                        <span class="text-xs text-emerald-600/70 dark:text-emerald-400/70">penjualan otomatis</span>
                    </div>
                </div>

                <!-- Card 3: Donations Dispatched -->
                <div
                    class="relative overflow-hidden rounded-2xl border border-sky-500/20 bg-gradient-to-br from-sky-50/50 to-white/70 p-4 shadow-sm backdrop-blur-sm dark:border-sky-500/30 dark:from-sky-950/20 dark:to-neutral-900/70"
                >
                    <div class="flex items-center justify-between text-sky-600 dark:text-sky-400">
                        <span class="text-xs font-medium uppercase tracking-wider">Donasi</span>
                        <HeartHandshake class="size-4" />
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold tracking-tight text-sky-700 dark:text-sky-300">{{ stats.donations_count }}</span>
                        <span class="text-xs text-sky-600/70 dark:text-sky-400/70">mitra penerima</span>
                    </div>
                </div>

                <!-- Card 4: Peringatan dikirim -->
                <div
                    class="relative overflow-hidden rounded-2xl border border-amber-500/20 bg-gradient-to-br from-amber-50/50 to-white/70 p-4 shadow-sm backdrop-blur-sm dark:border-amber-500/30 dark:from-amber-950/20 dark:to-neutral-900/70"
                >
                    <div class="flex items-center justify-between text-amber-600 dark:text-amber-400">
                        <span class="text-xs font-medium uppercase tracking-wider">Peringatan</span>
                        <AlertTriangle class="size-4" />
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold tracking-tight text-amber-700 dark:text-amber-300">{{ stats.alerts_count }}</span>
                        <span class="text-xs text-amber-600/70 dark:text-amber-400/70">dikirim</span>
                    </div>
                </div>

                <!-- Card 5: Penyesuaian manual -->
                <div
                    class="relative overflow-hidden rounded-2xl border border-violet-500/20 bg-gradient-to-br from-violet-50/50 to-white/70 p-4 shadow-sm backdrop-blur-sm dark:border-violet-500/30 dark:from-violet-950/20 dark:to-neutral-900/70"
                >
                    <div class="flex items-center justify-between text-violet-600 dark:text-violet-400">
                        <span class="text-xs font-medium uppercase tracking-wider">Penyesuaian</span>
                        <ShieldAlert class="size-4" />
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold tracking-tight text-violet-700 dark:text-violet-300">{{ stats.overrides_count }}</span>
                        <span class="text-xs text-violet-600/70 dark:text-violet-400/70">ditinjau petugas</span>
                    </div>
                </div>

                <!-- Card 6: Success Rate -->
                <div
                    class="relative overflow-hidden rounded-2xl border border-teal-500/20 bg-gradient-to-br from-teal-50/50 to-white/70 p-4 shadow-sm backdrop-blur-sm dark:border-teal-500/30 dark:from-teal-950/20 dark:to-neutral-900/70"
                >
                    <div class="flex items-center justify-between text-teal-600 dark:text-teal-400">
                        <span class="text-xs font-medium uppercase tracking-wider">Keandalan</span>
                        <CheckCircle2 class="size-4" />
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold tracking-tight text-teal-700 dark:text-teal-300">{{ stats.success_rate }}%</span>
                        <span class="text-xs text-teal-600/70 dark:text-teal-400/70">tingkat keberhasilan</span>
                    </div>
                </div>
            </div>

            <!-- Filter Controls Bar -->
            <div class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <!-- Search Input -->
                    <div class="relative flex-1">
                        <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="search"
                            @input="handleSearchInput"
                            type="text"
                            placeholder="Cari SKU, nama produk, mitra, atau kata kunci alasan..."
                            class="w-full rounded-xl border border-neutral-200 bg-neutral-50 py-2 pl-10 pr-9 text-sm text-neutral-900 transition placeholder:text-muted-foreground focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-100"
                        />
                        <button
                            v-if="search"
                            @click="clearSearch"
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground transition hover:text-foreground"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <!-- Dropdown Filters Group -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Jenis Tindakan Filter -->
                        <div class="flex items-center gap-1.5">
                            <Tag class="size-3.5 text-muted-foreground" />
                            <select
                                v-model="selectedActionType"
                                @change="applyFilters"
                                class="rounded-xl border border-neutral-200 bg-neutral-50 px-3 py-2 text-xs font-medium text-neutral-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-200 sm:text-sm"
                            >
                                <option value="all">Semua jenis tindakan</option>
                                <option value="discount_applied">Diskon diterapkan</option>
                                <option value="donation_requested">Permintaan donasi</option>
                                <option value="alert_sent">Peringatan dikirim</option>
                                <option value="manual_override">Penyesuaian manual</option>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div class="flex items-center gap-1.5">
                            <Filter class="size-3.5 text-muted-foreground" />
                            <select
                                v-model="selectedStatus"
                                @change="applyFilters"
                                class="rounded-xl border border-neutral-200 bg-neutral-50 px-3 py-2 text-xs font-medium text-neutral-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-200 sm:text-sm"
                            >
                                <option value="all">Semua status</option>
                                <option value="success">Berhasil</option>
                                <option value="failed">Gagal</option>
                                <option value="pending">Menunggu</option>
                            </select>
                        </div>

                        <!-- Date Filter -->
                        <div class="flex items-center gap-1.5">
                            <Calendar class="size-3.5 text-muted-foreground" />
                            <select
                                v-model="selectedDate"
                                @change="applyFilters"
                                class="rounded-xl border border-neutral-200 bg-neutral-50 px-3 py-2 text-xs font-medium text-neutral-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-200 sm:text-sm"
                            >
                                <option value="all">Semua waktu</option>
                                <option value="today">Hari ini saja</option>
                                <option value="7days">7 hari terakhir</option>
                                <option value="30days">30 hari terakhir</option>
                            </select>
                        </div>

                        <!-- Reset Filters Button -->
                        <button
                            v-if="hasActiveFilters"
                            @click="resetAllFilters"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-xl border border-neutral-200 bg-white px-2.5 py-2 text-xs font-medium text-rose-600 transition hover:bg-rose-50 dark:border-neutral-800 dark:bg-neutral-900 dark:text-rose-400 dark:hover:bg-rose-950/30"
                        >
                            <X class="size-3.5" />
                            <span>Atur ulang filter</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Audit Log Table -->
            <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1080px] text-left text-sm">
                        <thead
                            class="border-b border-neutral-200 bg-neutral-50/80 text-xs font-semibold uppercase tracking-wider text-neutral-600 dark:border-neutral-800 dark:bg-neutral-950/50 dark:text-neutral-400"
                        >
                            <tr>
                                <th scope="col" class="px-4 py-3.5 sm:px-6">Waktu & Model</th>
                                <th scope="col" class="px-4 py-3.5">SKU / Nama Produk</th>
                                <th scope="col" class="px-4 py-3.5">Jenis Tindakan</th>
                                <th scope="col" class="min-w-[280px] px-4 py-3.5">Alasan Otomatis</th>
                                <th scope="col" class="px-4 py-3.5 text-center">Status</th>
                                <th scope="col" class="px-4 py-3.5 text-right sm:px-6">Tindakan</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800/80">
                            <!-- Empty State -->
                            <tr v-if="logs.data.length === 0">
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <div
                                        class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-neutral-100 text-neutral-400 dark:bg-neutral-800"
                                    >
                                        <Bot class="size-7" />
                                    </div>
                                    <h3 class="mt-4 text-base font-semibold text-neutral-900 dark:text-neutral-100">
                                        Tidak ada catatan aktivitas AI yang sesuai dengan filter
                                    </h3>
                                    <p class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                                        Ubah kriteria pencarian atau jalankan simulasi pemindaian untuk membuat catatan baru.
                                    </p>
                                    <div class="mt-5 flex items-center justify-center gap-3">
                                        <button
                                            v-if="hasActiveFilters"
                                            @click="resetAllFilters"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 rounded-xl border border-neutral-300 bg-white px-3.5 py-2 text-xs font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300"
                                        >
                                            <RotateCcw class="size-3.5" />
                                            <span>Hapus filter</span>
                                        </button>
                                        <button
                                            @click="runSimulation"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-500"
                                        >
                                            <Sparkles class="size-3.5" />
                                            <span>Simulasikan Pemindaian AI</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Data Rows -->
                            <tr v-for="log in logs.data" :key="log.id" class="transition-colors hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40">
                                <!-- Timestamp & Engine Model -->
                                <td class="whitespace-nowrap px-4 py-4 align-top sm:px-6">
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-1.5 text-xs font-semibold text-neutral-900 dark:text-neutral-100">
                                            <Clock class="size-3 text-muted-foreground" />
                                            <span>{{ log.created_at_human }}</span>
                                        </div>
                                        <span class="mt-0.5 font-mono text-[11px] text-muted-foreground" :title="log.created_at">
                                            {{ log.created_at ? new Date(log.created_at).toLocaleString('id-ID') : 'Baru saja' }}
                                        </span>
                                        <div class="mt-1.5 flex items-center gap-1">
                                            <span
                                                class="inline-flex items-center rounded-md bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300"
                                            >
                                                {{ getModelBadge(log.payload) }}
                                            </span>
                                            <span
                                                v-if="log.payload.is_overridden"
                                                class="inline-flex items-center rounded-md bg-violet-100 px-1.5 py-0.5 text-[10px] font-semibold text-violet-700 dark:bg-violet-950/60 dark:text-violet-300"
                                                title="Tindakan otomatis ini telah disesuaikan secara manual"
                                            >
                                                Disesuaikan manual
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Product Info -->
                                <td class="px-4 py-4 align-top">
                                    <div v-if="log.product" class="flex flex-col">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-mono text-xs font-bold text-neutral-900 dark:text-neutral-100">
                                                {{ log.product.sku }}
                                            </span>
                                            <span
                                                v-if="log.product.category"
                                                class="rounded-md bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400"
                                            >
                                                {{ categoryLabel(log.product.category) }}
                                            </span>
                                        </div>
                                        <span
                                            class="mt-0.5 line-clamp-1 text-sm font-medium text-neutral-800 dark:text-neutral-200"
                                            :title="log.product.name"
                                        >
                                            {{ log.product.name }}
                                        </span>
                                        <div class="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                                            <span v-if="log.product.discount_price">
                                                <span class="text-muted-foreground/70 line-through"
                                                    >{{ formatCurrency(Number(log.product.original_price)) }}</span
                                                >
                                                <span class="ml-1 font-semibold text-emerald-600 dark:text-emerald-400"
                                                    >{{ formatCurrency(Number(log.product.discount_price)) }}</span
                                                >
                                            </span>
                                            <span v-else-if="log.product.original_price">{{ formatCurrency(Number(log.product.original_price)) }}</span>
                                            <span v-if="log.product.expiration_date" class="text-[11px]">
                                                • Kedaluwarsa: {{ log.product.expiration_date }}
                                            </span>
                                        </div>
                                    </div>
                                    <div v-else class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <div class="flex size-6 items-center justify-center rounded-lg bg-neutral-100 dark:bg-neutral-800">
                                            <Cpu class="size-3.5 text-neutral-500" />
                                        </div>
                                        <div>
                                            <span class="font-semibold text-neutral-700 dark:text-neutral-300">Layanan Sistem</span>
                                            <p class="text-[10px] text-muted-foreground">Pemantau siklus stok</p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Jenis Tindakan Badge -->
                                <td class="whitespace-nowrap px-4 py-4 align-top">
                                    <!-- Discount Applied -->
                                    <div v-if="log.action_type === 'discount_applied'" class="flex flex-col items-start gap-1">
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300"
                                        >
                                            <Percent class="size-3.5" />
                                            <span>Diskon diterapkan</span>
                                        </span>
                                        <span
                                            v-if="log.payload.discount_percentage"
                                            class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400"
                                        >
                                            -{{ log.payload.discount_percentage }}% Diskon
                                        </span>
                                    </div>

                                    <!-- Donation Requested -->
                                    <div v-else-if="log.action_type === 'donation_requested'" class="flex flex-col items-start gap-1">
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-sky-500/20 bg-sky-500/10 px-2.5 py-1 text-xs font-semibold text-sky-700 dark:text-sky-300"
                                        >
                                            <HeartHandshake class="size-3.5" />
                                            <span>Donasi disiapkan</span>
                                        </span>
                                        <span
                                            v-if="log.partner?.name || log.payload.partner_name || log.payload.recipient"
                                            class="line-clamp-1 max-w-[140px] text-[11px] font-medium text-sky-600 dark:text-sky-400"
                                        >
                                            → {{ log.partner?.name || log.payload.partner_name || log.payload.recipient }}
                                        </span>
                                    </div>

                                    <!-- Alert Sent -->
                                    <div v-else-if="log.action_type === 'alert_sent'" class="flex flex-col items-start gap-1">
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:text-amber-300"
                                        >
                                            <AlertTriangle class="size-3.5" />
                                            <span>Peringatan dikirim</span>
                                        </span>
                                        <span class="text-[11px] text-amber-600/90 dark:text-amber-400/90"> Peringatan stok </span>
                                    </div>

                                    <!-- Manual Override -->
                                    <div v-else-if="log.action_type === 'manual_override'" class="flex flex-col items-start gap-1">
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-violet-500/20 bg-violet-500/10 px-2.5 py-1 text-xs font-semibold text-violet-700 dark:text-violet-300"
                                        >
                                            <ShieldAlert class="size-3.5" />
                                            <span>Penyesuaian manual</span>
                                        </span>
                                        <span v-if="log.payload.performed_by" class="text-[11px] font-medium text-violet-600 dark:text-violet-400">
                                            Oleh {{ log.payload.performed_by }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Reasoning Summary -->
                                <td class="px-4 py-4 align-top">
                                    <div class="flex flex-col gap-1.5">
                                        <p class="text-xs font-normal leading-relaxed text-neutral-800 dark:text-neutral-200 sm:text-sm">
                                            {{ log.reasoning }}
                                        </p>

                                        <!-- Highlight Chips from Payload -->
                                        <div class="flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
                                            <span
                                                v-if="log.payload.rule"
                                                class="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-[10px] dark:bg-neutral-800"
                                            >
                                                aturan: {{ log.payload.rule }}
                                            </span>
                                            <span
                                                v-if="log.payload.confidence"
                                                class="rounded bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 dark:text-emerald-400"
                                            >
                                                {{ Math.round(Number(log.payload.confidence) * 100) }}% keyakinan
                                            </span>
                                            <span
                                                v-if="log.payload.override_reason"
                                                class="rounded bg-violet-500/10 px-1.5 py-0.5 text-[10px] font-medium text-violet-700 dark:text-violet-300"
                                            >
                                                Alasan penyesuaian: "{{ log.payload.override_reason }}"
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Status Badge -->
                                <td class="whitespace-nowrap px-4 py-4 text-center align-top">
                                    <span
                                        v-if="log.status === 'success'"
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-400"
                                    >
                                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                        Berhasil
                                    </span>
                                    <span
                                        v-else-if="log.status === 'failed'"
                                        class="inline-flex items-center gap-1 rounded-full bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-700 dark:text-rose-400"
                                    >
                                        <span class="size-1.5 rounded-full bg-rose-500"></span>
                                        Gagal
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:text-amber-400"
                                    >
                                        <span class="size-1.5 rounded-full bg-amber-500"></span>
                                        Menunggu
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="whitespace-nowrap px-4 py-4 text-right align-top sm:px-6">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Inspect JSON Payload Button -->
                                        <button
                                            @click="openJsonModal(log)"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 rounded-xl border border-neutral-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm transition hover:bg-neutral-50 active:translate-y-0.5 dark:border-neutral-800 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700/80"
                                            title="Periksa data JSON mentah dari mesin AI"
                                        >
                                            <Code2 class="size-3.5 text-sky-600 dark:text-sky-400" />
                                            <span>Periksa JSON</span>
                                        </button>

                                        <!-- Manual Override Button -->
                                        <button
                                            v-if="log.product_id && log.action_type !== 'manual_override'"
                                            @click="openOverrideModal(log)"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 rounded-xl border border-violet-200 bg-violet-50/50 px-2.5 py-1.5 text-xs font-semibold text-violet-700 shadow-sm transition hover:bg-violet-100 active:translate-y-0.5 dark:border-violet-900/60 dark:bg-violet-950/30 dark:text-violet-300 dark:hover:bg-violet-900/50"
                                            title="Batalkan atau sesuaikan tindakan otomatis ini"
                                        >
                                            <RotateCcw class="size-3.5 text-violet-600 dark:text-violet-400" />
                                            <span>Sesuaikan</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div
                    v-if="logs.total > 0"
                    class="flex flex-col gap-3 border-t border-neutral-200 px-4 py-4 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                >
                    <div class="text-xs text-muted-foreground">
                        Menampilkan
                        <span class="font-semibold text-foreground">{{ logs.from || 0 }}</span>
                        sampai
                        <span class="font-semibold text-foreground">{{ logs.to || 0 }}</span>
                        dari
                        <span class="font-semibold text-foreground">{{ logs.total }}</span>
                        catatan audit
                    </div>

                    <div class="flex items-center gap-1">
                        <template v-for="(link, index) in logs.links" :key="index">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                :class="[
                                    'inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-xs font-medium transition',
                                    link.active
                                        ? 'bg-emerald-600 text-white shadow-sm'
                                        : 'border border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800',
                                ]"
                                preserve-scroll
                            >
                                <span>{{ paginationLabel(link.label) }}</span>
                            </Link>
                            <span
                                v-else
                                class="inline-flex items-center justify-center px-2 py-1.5 text-xs text-muted-foreground/60"
                                {{ paginationLabel(link.label) }}
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- MODAL: Inspect JSON Payload Formatted Code Viewer                   -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <div
            v-if="isJsonModalOpen && selectedLogForJson"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-2 backdrop-blur-sm transition-all sm:p-4"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-900"
                @click.stop
            >
                <!-- Modal Header -->
                <div
                    class="flex items-center justify-between gap-2 border-b border-neutral-200 bg-neutral-50/70 px-3 py-3 dark:border-neutral-800 dark:bg-neutral-950/40 sm:px-6 sm:py-4"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400">
                            <Code2 class="size-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Data Mentah Keputusan AI</h3>
                                <span
                                    class="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400"
                                >
                                    #LOG-{{ selectedLogForJson.id }}
                                </span>
                            </div>
                            <p class="text-xs text-muted-foreground">Data terverifikasi dari {{ getModelBadge(selectedLogForJson.payload) }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            @click="copyJsonToClipboard"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700"
                        >
                            <Check v-if="hasCopiedJson" class="size-3.5 text-emerald-500" />
                            <Copy v-else class="size-3.5" />
                            <span>{{ hasCopiedJson ? 'Tersalin!' : 'Salin JSON' }}</span>
                        </button>

                        <button
                            @click="closeJsonModal"
                            type="button"
                            class="rounded-xl p-2 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                        >
                            <X class="size-5" />
                        </button>
                    </div>
                </div>

                <!-- Modal Sub-Header: Payload Metadata Chips -->
                <div
                    class="grid grid-cols-2 gap-3 border-b border-neutral-200 bg-neutral-50/40 px-3 py-3 dark:border-neutral-800 dark:bg-neutral-950/20 sm:grid-cols-4 sm:px-6"
                >
                    <div>
                        <span class="text-[10px] font-bold uppercase text-muted-foreground">Jenis Tindakan</span>
                        <p class="mt-0.5 text-xs font-semibold text-neutral-800 dark:text-neutral-200">
                                            {{ actionTypeLabel(selectedLogForJson.action_type) }}
                        </p>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase text-muted-foreground">Produk Sasaran</span>
                        <p class="mt-0.5 truncate text-xs font-semibold text-neutral-800 dark:text-neutral-200">
                            {{ selectedLogForJson.product ? selectedLogForJson.product.sku : 'Proses Sistem' }}
                        </p>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase text-muted-foreground">Aturan Keputusan</span>
                        <p class="mt-0.5 truncate font-mono text-xs font-semibold text-neutral-800 dark:text-neutral-200">
                            {{ selectedLogForJson.payload.rule || 'expiring_threshold' }}
                        </p>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase text-muted-foreground">Status Pelaksanaan</span>
                        <p class="mt-0.5 flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                            {{ statusLabel(selectedLogForJson.status) }}
                        </p>
                    </div>
                </div>

                <!-- Code Canvas Body -->
                <div class="flex-1 overflow-auto bg-neutral-950 p-6 font-mono text-xs text-neutral-200">
                    <pre class="whitespace-pre-wrap leading-relaxed"><code v-html="syntaxHighlightedJson"></code></pre>
                </div>

                <!-- Modal Footer -->
                <div
                    class="flex items-center justify-between border-t border-neutral-200 bg-neutral-50/70 px-6 py-3.5 dark:border-neutral-800 dark:bg-neutral-950/40"
                >
                    <span class="text-xs text-muted-foreground"> Ukuran data: {{ formattedJsonString.length }} bytes </span>
                    <button
                        @click="closeJsonModal"
                        type="button"
                        class="rounded-xl bg-neutral-900 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-90 dark:bg-neutral-100 dark:text-neutral-900"
                    >
                        Tutup Pratinjau
                    </button>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- MODAL: Manual Operator Override                                    -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <div
            v-if="isOverrideModalOpen && selectedLogForOverride"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-2 backdrop-blur-sm sm:p-4"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-800 dark:bg-neutral-900"
                @click.stop
            >
                <!-- Modal Header -->
                <div
                    class="flex items-center justify-between gap-2 border-b border-neutral-200 bg-violet-50/40 px-3 py-3 dark:border-neutral-800 dark:bg-violet-950/20 sm:px-6 sm:py-4"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400">
                            <ShieldAlert class="size-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100">
                                Penyesuaian Manual: Log #{{ selectedLogForOverride.id }}
                            </h3>
                            <p class="text-xs text-muted-foreground">Sesuaikan atau batalkan keputusan otomatis dengan tetap menjaga jejak audit.</p>
                        </div>
                    </div>
                    <button
                        @click="closeOverrideModal"
                        type="button"
                        class="rounded-xl p-2 text-neutral-400 transition hover:bg-neutral-100 dark:hover:bg-neutral-800"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-4 sm:p-6">
                    <!-- Context Summary Card -->
                    <div class="rounded-xl border border-neutral-200 bg-neutral-50/70 p-3.5 text-xs dark:border-neutral-800 dark:bg-neutral-950/50">
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="font-mono font-bold text-neutral-900 dark:text-neutral-100">
                                    {{ selectedLogForOverride.product?.sku }}
                                </span>
                                <p class="mt-0.5 font-medium text-neutral-700 dark:text-neutral-300">
                                    {{ selectedLogForOverride.product?.name }}
                                </p>
                            </div>
                            <span class="rounded bg-neutral-200 px-2 py-0.5 text-[10px] font-semibold dark:bg-neutral-800">
                                Saat ini: {{ actionTypeLabel(selectedLogForOverride.action_type) }}
                            </span>
                        </div>
                        <p class="mt-2 line-clamp-2 text-[11px] italic text-muted-foreground">"{{ selectedLogForOverride.reasoning }}"</p>
                    </div>

                    <!-- Error Alert inside modal -->
                    <div
                        v-if="overrideErrorMessage"
                        class="flex items-center gap-2 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-700 dark:text-rose-300"
                    >
                        <AlertCircle class="size-4 shrink-0" />
                        <span>{{ overrideErrorMessage }}</span>
                    </div>

                    <!-- Override Action Selector -->
                    <div>
                        <label class="mb-2 block text-xs font-semibold text-neutral-700 dark:text-neutral-300"> Pilih Tindakan Penyesuaian </label>
                        <div class="space-y-2">
                            <!-- Revert Option -->
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                                :class="
                                    overrideAction === 'revert'
                                        ? 'border-emerald-500 bg-emerald-50/30 dark:bg-emerald-950/20'
                                        : 'border-neutral-200 hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/40'
                                "
                            >
                                <input
                                    type="radio"
                                    name="override_action"
                                    value="revert"
                                    v-model="overrideAction"
                                    class="mt-0.5 text-emerald-600 focus:ring-emerald-500"
                                />
                                <div>
                                    <span class="text-xs font-bold text-neutral-900 dark:text-neutral-100"> Kembalikan ke Stok Normal </span>
                                    <p class="mt-0.5 text-[11px] text-muted-foreground">
                                        Batalkan diskon atau donasi dan kembalikan produk ke stok aktif.
                                    </p>
                                </div>
                            </label>

                            <!-- Reassign Partner Option (if applicable) -->
                            <label
                                v-if="selectedLogForOverride.action_type === 'donation_requested'"
                                class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                                :class="
                                    overrideAction === 'reassign_partner'
                                        ? 'border-sky-500 bg-sky-50/30 dark:bg-sky-950/20'
                                        : 'border-neutral-200 hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/40'
                                "
                            >
                                <input
                                    type="radio"
                                    name="override_action"
                                    value="reassign_partner"
                                    v-model="overrideAction"
                                    class="mt-0.5 text-sky-600 focus:ring-sky-500"
                                />
                                <div>
                                    <span class="text-xs font-bold text-neutral-900 dark:text-neutral-100"> Alihkan Mitra Donasi </span>
                                    <p class="mt-0.5 text-[11px] text-muted-foreground">
                                        Salurkan batch pangan berlebih kepada yayasan terverifikasi lainnya.
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Dynamic field: Reassign Partner Dropdown -->
                    <div v-if="overrideAction === 'reassign_partner'" class="space-y-1.5">
                        <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300"> Pilih Mitra Donasi Baru </label>
                        <select
                            v-model="overridePartnerId"
                            class="w-full rounded-xl border border-neutral-200 bg-white px-3.5 py-2 text-xs text-neutral-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-100 sm:text-sm"
                        >
                            <option value="" disabled>Pilih penerima terverifikasi...</option>
                            <option v-for="partner in partners" :key="partner.id" :value="partner.id">
                                {{ partner.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Justification / Reason Textarea -->
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                            Alasan Penyesuaian <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            v-model="overrideReason"
                            rows="3"
                            placeholder="Jelaskan alasan operasional atau kebijakan untuk mengubah keputusan otomatis ini..."
                            class="w-full rounded-xl border border-neutral-200 bg-white p-3 text-xs text-neutral-900 transition placeholder:text-muted-foreground focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/20 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-100 sm:text-sm"
                        ></textarea>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div
                    class="flex items-center justify-end gap-2.5 border-t border-neutral-200 bg-neutral-50/70 px-3 py-3 dark:border-neutral-800 dark:bg-neutral-950/40 sm:px-6 sm:py-4"
                >
                    <button
                        @click="closeOverrideModal"
                        type="button"
                        class="rounded-xl border border-neutral-200 bg-white px-4 py-2 text-xs font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-800 dark:text-neutral-300"
                    >
                        Batal
                    </button>
                    <button
                        :disabled="isSubmittingOverride"
                        @click="submitManualOverride"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-violet-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-violet-500 active:translate-y-0.5 disabled:opacity-60"
                    >
                        <RefreshCw v-if="isSubmittingOverride" class="size-3.5 animate-spin" />
                        <ShieldCheck v-else class="size-3.5" />
                        <span>{{ isSubmittingOverride ? 'Menerapkan penyesuaian…' : 'Terapkan Penyesuaian Manual' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
