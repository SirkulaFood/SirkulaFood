<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    Building2,
    CheckCircle2,
    Handshake,
    HeartHandshake,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Plus,
    RefreshCw,
    Search,
    Trash2,
    User,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PartnerItem {
    id: number;
    name: string;
    partner_type?: 'foundation' | 'umkm';
    contact_person: string;
    phone: string;
    email: string;
    telegram_chat_id: string | null;
    address: string;
    is_active: boolean;
    created_at?: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedPartners {
    data: PartnerItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

interface PartnerStats {
    total_partners: number;
    active_partners: number;
    inactive_partners: number;
    donations_dispatched: number;
}

interface Filters {
    search: string;
    status: string;
}

const props = defineProps<{
    partners: PaginatedPartners;
    filters: Filters;
    stats: PartnerStats;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dasbor', href: '/dashboard' },
    { title: 'Mitra Donasi', href: '/donation-partners' },
];

const page = usePage();
const flashSuccess = computed(() => (page.props as any).flash?.success);

// Filter & Search State
const search = ref(props.filters.search || '');
const selectedStatus = ref(props.filters.status || '');
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;

const applyFilters = () => {
    router.get(
        '/donation-partners',
        {
            search: search.value || undefined,
            status: selectedStatus.value || undefined,
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

const clearFilters = () => {
    search.value = '';
    selectedStatus.value = '';
    router.get('/donation-partners', {}, { preserveState: true, replace: true });
};

const hasActiveFilters = computed(() => Boolean(search.value || selectedStatus.value));

// Modal State: Create / Ubah Mitra
const isFormModalOpen = ref(false);
const editingPartner = ref<PartnerItem | null>(null);
const clientErrors = ref<Record<string, string>>({});

const form = useForm({
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    telegram_chat_id: '',
    address: '',
    is_active: true,
});

const openCreateModal = () => {
    editingPartner.value = null;
    clientErrors.value = {};
    form.reset();
    form.clearErrors();
    form.is_active = true;
    isFormModalOpen.value = true;
};

const openEditModal = (partner: PartnerItem) => {
    editingPartner.value = partner;
    clientErrors.value = {};
    form.clearErrors();
    form.name = partner.name;
    form.contact_person = partner.contact_person;
    form.phone = partner.phone;
    form.email = partner.email;
    form.telegram_chat_id = partner.telegram_chat_id ?? '';
    form.address = partner.address;
    form.is_active = partner.is_active;
    isFormModalOpen.value = true;
};

const closeFormModal = () => {
    isFormModalOpen.value = false;
    editingPartner.value = null;
    form.reset();
    form.clearErrors();
    clientErrors.value = {};
};

const validateClientSide = (): boolean => {
    clientErrors.value = {};

    if (!form.name || !form.name.trim()) {
        clientErrors.value.name = 'Organization name is required.';
    }
    if (!form.contact_person || !form.contact_person.trim()) {
        clientErrors.value.contact_person = 'Contact person name is required.';
    }
    if (!form.phone || !form.phone.trim()) {
        clientErrors.value.phone = 'Phone number is required.';
    }
    if (!form.email || !form.email.trim()) {
        clientErrors.value.email = 'Email address is required.';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
        clientErrors.value.email = 'Please provide a valid email address.';
    }
    if (!form.address || !form.address.trim()) {
        clientErrors.value.address = 'Pickup delivery address is required.';
    }

    return Object.keys(clientErrors.value).length === 0;
};

const submitPartnerForm = () => {
    if (!validateClientSide()) {
        return;
    }

    if (editingPartner.value) {
        form.put(`/donation-partners/${editingPartner.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                closeFormModal();
            },
        });
    } else {
        form.post('/donation-partners', {
            preserveScroll: true,
            onSuccess: () => {
                closeFormModal();
            },
        });
    }
};

// Modal State: Delete Confirmation
const isDeleteModalOpen = ref(false);
const deleteTargetPartner = ref<PartnerItem | null>(null);
const isDeleting = ref(false);

const openDeleteModal = (partner: PartnerItem) => {
    deleteTargetPartner.value = partner;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = () => {
    isDeleteModalOpen.value = false;
    deleteTargetPartner.value = null;
    isDeleting.value = false;
};

const confirmDelete = () => {
    if (!deleteTargetPartner.value) return;
    isDeleting.value = true;

    router.delete(`/donation-partners/${deleteTargetPartner.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteModal();
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

// Status Toggle
const togglingId = ref<number | null>(null);

const toggleStatus = (partner: PartnerItem) => {
    togglingId.value = partner.id;
    router.post(
        `/donation-partners/${partner.id}/toggle-status`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                togglingId.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Mitra Donasi - SirkulaFood" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <!-- Toast Feedback Notification -->
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
                    class="flex items-center gap-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3.5 text-sm text-emerald-400 shadow-sm"
                >
                    <CheckCircle2 class="size-5 shrink-0 text-emerald-400" />
                    <span class="flex-1 font-medium">{{ flashSuccess }}</span>
                </div>
            </transition>

            <!-- Header Section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">Mitra Donasi & Jaringan Amal</h1>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">
                        Kelola bank pangan, penampungan, dan panti asuhan terverifikasi yang menerima penyaluran pangan berlebih.
                    </p>
                </div>
                <div class="flex items-center gap-2.5">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 active:bg-emerald-700"
                        @click="openCreateModal"
                    >
                        <Plus class="size-4" />
                        <span>Daftarkan Mitra</span>
                    </button>
                </div>
            </div>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900/60">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Total Mitra</span>
                        <Building2 class="size-4 text-neutral-400" />
                    </div>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                        {{ stats.total_partners }}
                    </p>
                    <p class="mt-0.5 text-xs text-neutral-400">Organisasi amal terdaftar</p>
                </div>

                <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Penerima Aktif</span>
                        <CheckCircle2 class="size-4 text-emerald-500" />
                    </div>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                        {{ stats.active_partners }}
                    </p>
                    <p class="mt-0.5 text-xs text-emerald-400/80">Siap untuk pengambilan</p>
                </div>

                <div class="rounded-xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900/60">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Mitra Nonaktif</span>
                        <Users class="size-4 text-neutral-400" />
                    </div>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
                        {{ stats.inactive_partners }}
                    </p>
                    <p class="mt-0.5 text-xs text-neutral-400">Dijeda sementara</p>
                </div>

                <div class="rounded-xl border border-purple-500/20 bg-purple-500/5 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-purple-600 dark:text-purple-400">Penyaluran Donasi</span>
                        <HeartHandshake class="size-4 text-purple-500" />
                    </div>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-purple-600 dark:text-purple-400">
                        {{ stats.donations_dispatched }}
                    </p>
                    <p class="mt-0.5 text-xs text-purple-400/80">Batch pangan berhasil disalurkan</p>
                </div>
            </div>

            <!-- Filter & Search Toolbar -->
            <div class="rounded-xl border border-neutral-200/80 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900/60">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <!-- Search Input -->
                    <div class="relative sm:col-span-2">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-neutral-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Cari organisasi, kontak, email, atau alamat..."
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50/50 py-2 pl-9 pr-8 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800/60 dark:text-neutral-100 dark:focus:border-emerald-500 dark:focus:bg-neutral-800"
                            @input="handleSearchInput"
                        />
                        <button
                            v-if="search"
                            type="button"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                            @click="
                                search = '';
                                applyFilters();
                            "
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <!-- Status Filter -->
                    <div class="flex items-center gap-2">
                        <select
                            v-model="selectedStatus"
                            class="w-full rounded-lg border border-neutral-200 bg-neutral-50/50 px-3 py-2 text-sm text-neutral-900 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800/60 dark:text-neutral-100 dark:focus:border-emerald-500 dark:focus:bg-neutral-800"
                            @change="applyFilters"
                        >
                            <option value="">Semua Status Mitra</option>
                            <option value="active">Penerima Aktif saja</option>
                            <option value="inactive">Nonaktif / Dijeda</option>
                        </select>

                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            title="Hapus semua filter"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 px-3 py-2 text-xs font-medium text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                            @click="clearFilters"
                        >
                            <RefreshCw class="size-3.5" />
                            <span>Atur ulang</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Partners Table -->
            <div class="overflow-hidden rounded-xl border border-neutral-200/80 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900/60">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-neutral-200 bg-neutral-50/75 text-xs font-semibold uppercase tracking-wider text-neutral-500 dark:border-neutral-800 dark:bg-neutral-800/50 dark:text-neutral-400"
                        >
                            <tr>
                                <th scope="col" class="px-4 py-3.5 sm:px-6">Organisasi Mitra</th>
                                <th scope="col" class="px-4 py-3.5">Nama Kontak</th>
                                <th scope="col" class="px-4 py-3.5">Informasi Kontak</th>
                                <th scope="col" class="px-4 py-3.5">Alamat Penyaluran / Pengambilan</th>
                                <th scope="col" class="px-4 py-3.5">Status</th>
                                <th scope="col" class="px-4 py-3.5 text-right sm:pr-6">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200/80 dark:divide-neutral-800/80">
                            <tr
                                v-for="partner in partners.data"
                                :key="partner.id"
                                class="transition-colors hover:bg-neutral-50/60 dark:hover:bg-neutral-800/40"
                            >
                                <!-- Name -->
                                <td class="px-4 py-4 sm:px-6">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                        >
                                            <Building2 class="size-4.5" />
                                        </div>
                                        <div>
                                            <p class="font-semibold text-neutral-900 dark:text-neutral-100">
                                                {{ partner.name }}
                                            </p>
                                            <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                                {{ partner.partner_type === 'umkm' ? 'Mitra UMKM · penawaran diskon' : 'Yayasan / badan amal · donasi' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Nama Kontak -->
                                <td class="whitespace-nowrap px-4 py-4 text-xs font-medium text-neutral-700 dark:text-neutral-300">
                                    <div class="flex items-center gap-1.5">
                                        <User class="size-3.5 text-neutral-400" />
                                        <span>{{ partner.contact_person }}</span>
                                    </div>
                                </td>

                                <!-- Informasi Kontak (Phone / Email) -->
                                <td class="whitespace-nowrap px-4 py-4 text-xs text-neutral-600 dark:text-neutral-300">
                                    <div class="flex flex-col gap-1">
                                        <a
                                            :href="`tel:${partner.phone}`"
                                            class="inline-flex items-center gap-1.5 font-mono text-emerald-600 hover:underline dark:text-emerald-400"
                                        >
                                            <Phone class="size-3" />
                                            <span>{{ partner.phone }}</span>
                                        </a>
                                        <a
                                            :href="`mailto:${partner.email}`"
                                            class="inline-flex items-center gap-1.5 text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200"
                                        >
                                            <Mail class="size-3" />
                                            <span>{{ partner.email }}</span>
                                        </a>
                                    </div>
                                </td>

                                <!-- Address -->
                                <td class="px-4 py-4 text-xs text-neutral-500 dark:text-neutral-400">
                                    <div class="flex max-w-xs items-start gap-1.5">
                                        <MapPin class="mt-0.5 size-3.5 shrink-0 text-neutral-400" />
                                        <span class="line-clamp-2">{{ partner.address }}</span>
                                    </div>
                                </td>

                                <!-- Status Badge & Quick Toggle -->
                                <td class="whitespace-nowrap px-4 py-4">
                                    <button
                                        type="button"
                                        :disabled="togglingId === partner.id"
                                        title="Klik untuk mengubah status aktif"
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold transition"
                                        :class="[
                                            partner.is_active
                                                ? 'border border-emerald-500/20 bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/20'
                                                : 'border border-neutral-500/20 bg-neutral-500/10 text-neutral-400 hover:bg-neutral-500/20',
                                        ]"
                                        @click="toggleStatus(partner)"
                                    >
                                        <span class="size-1.5 rounded-full" :class="partner.is_active ? 'bg-emerald-500' : 'bg-neutral-400'"></span>
                                        <span>{{ partner.is_active ? 'Active' : 'Inactive' }}</span>
                                    </button>
                                </td>

                                <!-- Tindakan -->
                                <td class="whitespace-nowrap px-4 py-4 text-right sm:pr-6">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Edit Button -->
                                        <button
                                            type="button"
                                            title="Ubah Mitra"
                                            class="inline-flex items-center gap-1 rounded-md border border-neutral-200 bg-white p-1 text-neutral-600 transition hover:bg-neutral-100 hover:text-neutral-900 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700 dark:hover:text-white"
                                            @click="openEditModal(partner)"
                                        >
                                            <Pencil class="size-3.5" />
                                        </button>

                                        <!-- Delete Button -->
                                        <button
                                            type="button"
                                            title="Hapus Mitra"
                                            class="inline-flex items-center rounded-md border border-neutral-200 bg-white p-1 text-neutral-400 transition hover:border-red-500/30 hover:bg-red-500/10 hover:text-red-500 dark:border-neutral-700 dark:bg-neutral-800 dark:hover:bg-red-500/20"
                                            @click="openDeleteModal(partner)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="partners.data.length === 0">
                                <td colspan="6" class="py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="rounded-full bg-neutral-100 p-3 text-neutral-400 dark:bg-neutral-800">
                                            <Handshake class="size-8" />
                                        </div>
                                        <h3 class="mt-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">Mitra donasi tidak ditemukan</h3>
                                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                            {{
                                                hasActiveFilters
                                                    ? 'Tidak ada mitra yang sesuai dengan filter.'
                                                    : 'Daftarkan bank pangan atau panti asuhan untuk mulai menyalurkan pangan berlebih.'
                                            }}
                                        </p>
                                        <div class="mt-4 flex gap-2">
                                            <button
                                                v-if="hasActiveFilters"
                                                type="button"
                                                class="rounded-lg border border-neutral-300 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                                @click="clearFilters"
                                            >
                                                Hapus filter
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500"
                                                @click="openCreateModal"
                                            >
                                                Daftarkan Mitra
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div
                    v-if="partners.total > 0"
                    class="flex flex-col items-center justify-between gap-3 border-t border-neutral-200 px-4 py-3 dark:border-neutral-800 sm:flex-row sm:px-6"
                >
                    <div class="text-xs text-neutral-500 dark:text-neutral-400">
                        Menampilkan
                        <span class="font-semibold text-neutral-900 dark:text-neutral-100">{{ partners.from ?? 0 }}</span>
                        to
                        <span class="font-semibold text-neutral-900 dark:text-neutral-100">{{ partners.to ?? 0 }}</span>
                        of
                        <span class="font-semibold text-neutral-900 dark:text-neutral-100">{{ partners.total }}</span>
                        partners
                    </div>

                    <div class="flex items-center gap-1">
                        <template v-for="(link, idx) in partners.links" :key="idx">
                            <span v-if="!link.url" class="rounded-md px-2.5 py-1 text-xs text-neutral-400" v-html="link.label" />
                            <Link
                                v-else
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                :class="[
                                    'rounded-md px-2.5 py-1 text-xs font-medium transition',
                                    link.active
                                        ? 'bg-emerald-600 text-white shadow-sm'
                                        : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800',
                                ]"
                            >
                                <span v-html="link.label" />
                            </Link>
                        </template>
                    </div>
                </div>
            </div>

            <!-- MODAL: Create / Ubah Mitra -->
            <div
                v-if="isFormModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm"
                @click.self="closeFormModal"
            >
                <div
                    class="w-full max-w-lg rounded-2xl border border-neutral-200 bg-white p-6 shadow-2xl dark:border-neutral-800 dark:bg-neutral-900"
                >
                    <div class="flex items-center justify-between border-b border-neutral-200 pb-4 dark:border-neutral-800">
                        <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                            <Building2 class="size-5" />
                            <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100">
                                {{ editingPartner ? 'Ubah Mitra Donasi' : 'Daftarkan Mitra Donasi' }}
                            </h3>
                        </div>

                        <button
                            type="button"
                            class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                            @click="closeFormModal"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <form class="mt-4 space-y-4" @submit.prevent="submitPartnerForm">
                        <!-- Organization Name -->
                        <div>
                            <label class="block text-xs font-semibold uppercase text-neutral-700 dark:text-neutral-300">
                                Organization Name <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="Contoh: Bank Pangan Komunitas Jakarta"
                                class="mt-1.5 w-full rounded-lg border border-neutral-200 bg-neutral-50/50 px-3.5 py-2.5 text-sm text-neutral-900 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800/60 dark:text-neutral-100"
                                :class="{ 'border-red-500': form.errors.name || clientErrors.name }"
                            />
                            <p v-if="form.errors.name || clientErrors.name" class="mt-1 flex items-center gap-1 text-xs text-red-500">
                                <AlertCircle class="size-3.5" />
                                <span>{{ form.errors.name || clientErrors.name }}</span>
                            </p>
                        </div>

                        <!-- Nama Kontak & Phone -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-700 dark:text-neutral-300">
                                    Nama Kontak <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.contact_person"
                                    type="text"
                                    placeholder="Contoh: Maria Kusuma"
                                    class="mt-1.5 w-full rounded-lg border border-neutral-200 bg-neutral-50/50 px-3.5 py-2.5 text-sm text-neutral-900 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800/60 dark:text-neutral-100"
                                    :class="{ 'border-red-500': form.errors.contact_person || clientErrors.contact_person }"
                                />
                                <p
                                    v-if="form.errors.contact_person || clientErrors.contact_person"
                                    class="mt-1 flex items-center gap-1 text-xs text-red-500"
                                >
                                    <AlertCircle class="size-3.5" />
                                    <span>{{ form.errors.contact_person || clientErrors.contact_person }}</span>
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-700 dark:text-neutral-300">
                                    Telepon (WhatsApp) <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.phone"
                                    type="text"
                                    placeholder="Contoh: +62 812 3456 7890"
                                    class="mt-1.5 w-full rounded-lg border border-neutral-200 bg-neutral-50/50 px-3.5 py-2.5 text-sm text-neutral-900 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800/60 dark:text-neutral-100"
                                    :class="{ 'border-red-500': form.errors.phone || clientErrors.phone }"
                                />
                                <p v-if="form.errors.phone || clientErrors.phone" class="mt-1 flex items-center gap-1 text-xs text-red-500">
                                    <AlertCircle class="size-3.5" />
                                    <span>{{ form.errors.phone || clientErrors.phone }}</span>
                                </p>
                            </div>
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-xs font-semibold uppercase text-neutral-700 dark:text-neutral-300">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.email"
                                type="email"
                                placeholder="Contoh: logistik@bankpangankota.id"
                                class="mt-1.5 w-full rounded-lg border border-neutral-200 bg-neutral-50/50 px-3.5 py-2.5 text-sm text-neutral-900 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800/60 dark:text-neutral-100"
                                :class="{ 'border-red-500': form.errors.email || clientErrors.email }"
                            />
                            <p v-if="form.errors.email || clientErrors.email" class="mt-1 flex items-center gap-1 text-xs text-red-500">
                                <AlertCircle class="size-3.5" />
                                <span>{{ form.errors.email || clientErrors.email }}</span>
                            </p>
                        </div>

                        <!-- Telegram Chat ID -->
                        <div>
                            <label class="block text-xs font-semibold uppercase text-neutral-700 dark:text-neutral-300">ID Obrolan Telegram (opsional)</label>
                            <input
                                v-model="form.telegram_chat_id"
                                type="text"
                                placeholder="Untuk mengirim pemberitahuan donasi melalui Telegram kepada mitra ini"
                                class="mt-1.5 w-full rounded-lg border border-neutral-200 bg-neutral-50/50 px-3.5 py-2.5 text-sm text-neutral-900 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800/60 dark:text-neutral-100"
                            />
                            <p class="mt-1 text-xs text-muted-foreground">Isi setelah mitra memulai bot dan mengirimkan ID obrolannya.</p>
                        </div>

                        <!-- Alamat Penyaluran / Pengambilan -->
                        <div>
                            <label class="block text-xs font-semibold uppercase text-neutral-700 dark:text-neutral-300">
                                Alamat / Gudang <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                v-model="form.address"
                                rows="2"
                                placeholder="Alamat lengkap dan petunjuk pengiriman untuk kurir rantai dingin..."
                                class="mt-1.5 w-full rounded-lg border border-neutral-200 bg-neutral-50/50 p-2.5 text-xs text-neutral-900 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-neutral-700 dark:bg-neutral-800/60 dark:text-neutral-100"
                                :class="{ 'border-red-500': form.errors.address || clientErrors.address }"
                            ></textarea>
                            <p v-if="form.errors.address || clientErrors.address" class="mt-1 flex items-center gap-1 text-xs text-red-500">
                                <AlertCircle class="size-3.5" />
                                <span>{{ form.errors.address || clientErrors.address }}</span>
                            </p>
                        </div>

                        <!-- Active Toggle Checkbox -->
                        <div class="flex items-center gap-2.5 pt-1">
                            <input
                                id="is_active_checkbox"
                                v-model="form.is_active"
                                type="checkbox"
                                class="size-4 rounded border-neutral-300 text-emerald-600 focus:ring-emerald-500/20 dark:border-neutral-700"
                            />
                            <label for="is_active_checkbox" class="text-xs font-medium text-neutral-700 dark:text-neutral-300">
                                Mitra aktif dapat menerima penyaluran otomatis pangan berlebih
                            </label>
                        </div>

                        <!-- Modal Tindakan -->
                        <div class="flex items-center justify-end gap-2.5 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                            <button
                                type="button"
                                class="rounded-lg px-4 py-2 text-xs font-medium text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                @click="closeFormModal"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-500 disabled:opacity-50"
                            >
                                <span v-if="form.processing">Menyimpan…</span>
                                <span v-else>{{ editingPartner ? 'Update Partner' : 'Save Partner' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL: Delete Confirmation -->
            <div
                v-if="isDeleteModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm"
                @click.self="closeDeleteModal"
            >
                <div
                    class="w-full max-w-sm rounded-2xl border border-neutral-200 bg-white p-6 shadow-2xl dark:border-neutral-800 dark:bg-neutral-900"
                >
                    <div class="flex items-center gap-3 text-red-500">
                        <AlertTriangle class="size-6 shrink-0" />
                        <h3 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Hapus Mitra?</h3>
                    </div>

                    <p class="mt-3 text-xs text-neutral-500 dark:text-neutral-400">
                        Apakah Anda yakin ingin menghapus
                        <span class="font-semibold text-neutral-900 dark:text-neutral-100">
                            {{ deleteTargetPartner?.name }}
                        </span>
                        from registered charity partners?
                    </p>

                    <div class="mt-6 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg px-3.5 py-2 text-xs font-medium text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
                            @click="closeDeleteModal"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            :disabled="isDeleting"
                            class="rounded-lg bg-red-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                            @click="confirmDelete"
                        >
                            <span v-if="isDeleting">Menghapus…</span>
                            <span v-else>Hapus Permanen</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
