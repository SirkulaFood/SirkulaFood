<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    Bot,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Handshake,
    Leaf,
    Menu,
    Package,
    ScanEye,
    Sparkles,
    Store,
    Tag,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();
const isSignedIn = computed(() => Boolean((page.props as unknown as { auth?: { user?: unknown } }).auth?.user));
const primaryHref = computed(() => (isSignedIn.value ? route('dashboard') : route('login')));
const primaryLabel = computed(() => (isSignedIn.value ? 'Buka dasbor' : 'Masuk ke SirkulaFood'));
const carousel = ref<HTMLElement | null>(null);
const proofSection = ref<HTMLElement | null>(null);
const counts = ref([0, 0, 0]);
const openFaq = ref(0);
const mobileMenuOpen = ref(false);
let revealObserver: IntersectionObserver | undefined;
let counterObserver: IntersectionObserver | undefined;
let parallaxFrame = 0;
let counterFrame = 0;
let counted = false;

const features = [
    {
        eyebrow: '01 · PEMANTAUAN',
        title: 'Kenali stok sebelum terlambat.',
        description: 'Pantau produk dan batch berdasarkan masa simpan, jumlah, dan sumber dalam satu tampilan.',
        image: 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=1200&q=85',
        alt: 'Hasil bumi segar di pasar',
        icon: Package,
    },
    {
        eyebrow: '02 · PEMULIHAN NILAI',
        title: 'Berikan diskon pada saat yang tepat.',
        description: 'Rekomendasi diskon membantu produk tetap bernilai bagi pelanggan dan mengurangi risiko terbuang.',
        image: 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=1200&q=85',
        alt: 'Roti yang baru dipanggang',
        icon: Tag,
    },
    {
        eyebrow: '03 · DONASI TEPAT SASARAN',
        title: 'Hubungkan pangan berlebih dengan mitra.',
        description: 'Salurkan produk yang layak konsumsi, koordinasikan peninjauan, dan catat serah terima donasi dengan jelas.',
        image: 'https://images.unsplash.com/photo-1464226184884-fa280b87c399?auto=format&fit=crop&w=1200&q=85',
        alt: 'Panen sayuran segar',
        icon: Handshake,
    },
];
const faqs = [
    {
        question: 'Apa itu SirkulaFood?',
        answer: 'SirkulaFood membantu peritel mengelola stok pangan yang mendekati kedaluwarsa. Sistem ini memantau masa simpan, merekomendasikan diskon, dan menghubungkan produk layak konsumsi dengan mitra donasi.',
    },
    {
        question: 'Apakah SirkulaFood mengubah harga secara otomatis?',
        answer: 'SirkulaFood dapat merekomendasikan diskon berdasarkan kondisi stok dan sisa masa simpan. Tim operasional dapat meninjau dan menyetujui tindakan sebelum perubahan diterapkan.',
    },
    {
        question: 'Bagaimana mitra menerima donasi?',
        answer: 'Produk donasi disalurkan melalui alur kerja mitra. SirkulaFood mendukung notifikasi dan peninjauan agar setiap serah terima tercatat dengan jelas.',
    },
    {
        question: 'Bisakah SirkulaFood terhubung ke stok toko?',
        answer: 'SirkulaFood menyinkronkan data produk, jumlah, harga, dan tanggal kedaluwarsa dengan operasional toko Anda.',
    },
];
const sdgContributions = [
    {
        number: '02',
        title: 'Tanpa Kelaparan',
        description: 'Pangan layak konsumsi yang berlebih dapat diarahkan kepada mitra sosial untuk membantu memperluas akses pangan.',
        icon: Leaf,
        color: '#C99A2E',
    },
    {
        number: '09',
        title: 'Industri, Inovasi, dan Infrastruktur',
        description: 'Pemantauan stok dan rekomendasi berbasis data mendukung proses operasional pangan yang lebih tanggap.',
        icon: Bot,
        color: '#D66A45',
    },
    {
        number: '12',
        title: 'Konsumsi dan Produksi yang Bertanggung Jawab',
        description: 'Pemantauan masa simpan, diskon tepat waktu, dan penyaluran surplus membantu menekan pangan terbuang.',
        icon: Package,
        color: '#9B7B35',
    },
    {
        number: '17',
        title: 'Kemitraan untuk Mencapai Tujuan',
        description: 'SirkulaFood menghubungkan toko, UMKM, dan mitra sosial dalam alur penyelamatan pangan yang tercatat.',
        icon: Handshake,
        color: '#2F9182',
    },
];
const nodes = [
    { label: 'Kasir (POS)', icon: Store, pos: 'node-top' },
    { label: 'AI', icon: Bot, pos: 'node-right-top' },
    { label: 'Visi komputer', icon: ScanEye, pos: 'node-right-bottom' },
    { label: 'Mitra', icon: Handshake, pos: 'node-bottom' },
    { label: 'Stok', icon: Package, pos: 'node-left-bottom' },
    { label: 'Diskon', icon: Tag, pos: 'node-left-top' },
];
function scrollFeatures(direction: -1 | 1): void {
    carousel.value?.scrollBy({ left: direction * 440, behavior: 'smooth' });
}
function animateCounters(): void {
    if (counted) return;
    counted = true;
    const targets = [3, 2, 1];
    const start = performance.now();
    const tick = (now: number) => {
        const progress = Math.min((now - start) / 1200, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        counts.value = targets.map((target) => Math.round(target * eased));
        if (progress < 1) counterFrame = requestAnimationFrame(tick);
        else counts.value = targets;
    };
    counterFrame = requestAnimationFrame(tick);
}
function splitHeadingWords(): void {
    document.querySelectorAll<HTMLElement>('.w-anim').forEach((heading) => {
        const walker = document.createTreeWalker(heading, NodeFilter.SHOW_TEXT);
        const nodes: Text[] = [];
        while (walker.nextNode()) nodes.push(walker.currentNode as Text);
        let wordIndex = 0;
        nodes.forEach((node) => {
            const fragment = document.createDocumentFragment();
            (node.textContent ?? '').split(/(\s+)/).forEach((part) => {
                if (!part) return;
                if (/^\s+$/.test(part)) {
                    fragment.appendChild(document.createTextNode(part));
                    return;
                }
                const word = document.createElement('span');
                word.className = 'word-reveal';
                word.textContent = part;
                word.style.animationDelay = wordIndex * 55 + 'ms';
                wordIndex += 1;
                fragment.appendChild(word);
            });
            node.parentNode?.replaceChild(fragment, node);
        });
    });
}
function updateParallax(): void {
    if (parallaxFrame) cancelAnimationFrame(parallaxFrame);
    parallaxFrame = requestAnimationFrame(() =>
        document.querySelectorAll<HTMLElement>('.parallax').forEach((element) => {
            const rect = element.getBoundingClientRect();
            element.style.transform = 'translate3d(0, ' + (rect.top - window.innerHeight / 2) * 0.06 + 'px, 0)';
        }),
    );
}
onMounted(() => {
    splitHeadingWords();
    revealObserver = new IntersectionObserver(
        (entries, observer) =>
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    observer.unobserve(entry.target);
                }
            }),
        { threshold: 0.12 },
    );
    document.querySelectorAll('.reveal').forEach((element) => revealObserver?.observe(element));
    if (proofSection.value) {
        counterObserver = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    animateCounters();
                    counterObserver?.disconnect();
                }
            },
            { threshold: 0.35 },
        );
        counterObserver.observe(proofSection.value);
    }
    window.addEventListener('scroll', updateParallax, { passive: true });
    updateParallax();
});
onBeforeUnmount(() => {
    revealObserver?.disconnect();
    counterObserver?.disconnect();
    window.removeEventListener('scroll', updateParallax);
    cancelAnimationFrame(parallaxFrame);
    cancelAnimationFrame(counterFrame);
});
</script>
<template>
    <Head title="SirkulaFood — Selamatkan pangan, jaga nilainya">
        <meta
            name="description"
            content="Pantau masa simpan, jaga nilai produk melalui diskon, dan salurkan pangan layak konsumsi kepada mitra donasi."
        />
        <link rel="preconnect" href="https://fonts.googleapis.com" /><link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin="anonymous"
        />
        <link
            href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,500&family=Inter:wght@400;500;600;700&display=swap"
            rel="stylesheet"
        />
    </Head>
    <div class="SirkulaFood min-h-screen overflow-hidden bg-[#F2EEE5] text-[#171512] antialiased">
        <header class="fixed inset-x-0 top-0 z-50 px-4 pt-4 sm:px-8 lg:px-12">
            <div class="mx-auto flex max-w-[1440px] items-center justify-between gap-2 rounded-full border border-white/65 bg-[#F2EEE5]/85 px-3 py-2.5 shadow-lg backdrop-blur-2xl sm:px-7 sm:py-3">
                <a href="#home" class="inline-flex items-center rounded-lg px-1" aria-label="SirkulaFood — kembali ke beranda"><img src="/images/SirkulaFood-logo-light.png" alt="SirkulaFood" class="h-7 w-auto max-w-[92px] sm:h-9 sm:max-w-[150px]" /></a>
                <nav class="hidden items-center gap-8 text-[12px] font-medium text-[#6d6a5f] lg:flex" aria-label="Navigasi utama">
                    <a href="#solusi">Solusi</a><a href="#sdgs">Kontribusi SDGs</a><a href="#alur">Cara kerja</a><a href="#integrasi">Integrasi</a><a href="#faq">FAQ</a>
                </nav>
                <div class="flex items-center gap-2"><Link :href="primaryHref" class="btn-lift inline-flex items-center gap-2 rounded-full bg-[#171512] px-3 py-2.5 text-[10px] font-semibold text-[#F2EEE5] sm:px-5 sm:text-[12px]"><span class="hidden sm:inline">{{ primaryLabel }}</span><span class="sm:hidden">{{ isSignedIn ? 'Dasbor' : 'Masuk' }}</span> <ArrowUpRight class="size-3.5" /></Link><button type="button" class="grid size-10 place-items-center rounded-full border border-[#d8d2c5] text-[#3B4B34] transition active:scale-95 lg:hidden" :aria-expanded="mobileMenuOpen" aria-controls="mobile-site-menu" aria-label="Buka navigasi" @click="mobileMenuOpen = !mobileMenuOpen"><X v-if="mobileMenuOpen" class="size-4" /><Menu v-else class="size-4" /></button></div>
            </div>
            <nav v-if="mobileMenuOpen" id="mobile-site-menu" class="mx-auto mt-2 grid max-w-[1440px] gap-1 rounded-3xl border border-white/70 bg-[#F2EEE5]/95 p-3 text-sm text-[#3b4b34] shadow-xl backdrop-blur-2xl lg:hidden">
                <a v-for="item in [{ label: 'Solusi', href: '#solusi' }, { label: 'Kontribusi SDGs', href: '#sdgs' }, { label: 'Cara kerja', href: '#alur' }, { label: 'Integrasi', href: '#integrasi' }, { label: 'FAQ', href: '#faq' }]" :key="item.href" :href="item.href" class="rounded-xl px-4 py-3 transition hover:bg-[#e7ebde] active:scale-[.99]" @click="mobileMenuOpen = false">{{ item.label }}</a>
            </nav>        </header>
        <main id="home">
            <section class="hero relative isolate flex min-h-[760px] items-center px-5 pb-20 pt-36 sm:px-8 lg:min-h-[900px] lg:px-12 lg:pt-40">
                <div class="hero-photo parallax absolute inset-0 -z-20" role="img" aria-label="Panen sayuran segar" />
                <div class="hero-shade absolute inset-0 -z-10" />
                <div class="hero-glow absolute -left-36 top-32 -z-10 size-[460px] rounded-full" />
                <div class="mx-auto grid w-full max-w-[1320px] items-center gap-14 lg:grid-cols-[.88fr_1.12fr] lg:gap-8">
                    <div class="relative z-10 max-w-[610px] text-white">
                        <div
                            class="reveal mb-7 inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3.5 py-2 text-[10px] font-semibold uppercase tracking-[.16em] backdrop-blur-xl"
                        >
                            <span class="size-2 rounded-full bg-[#b4c29a]"></span>Sistem sirkular untuk pangan ritel
                        </div>
                        <h1 class="w-anim reveal font-serif text-[clamp(3.6rem,8vw,7.3rem)] leading-[.9] tracking-[-.055em] text-[#F7F4ED]">
                            <span class="block">Selamatkan</span><span class="block">pangan<span class="text-[#bdcaa6]">.</span></span
                            ><span class="block italic text-[#d8dfc8]">Jaga nilainya.</span>
                        </h1>
                        <p class="reveal mt-7 max-w-[450px] text-[14px] leading-7 text-white/75 sm:text-[15px]">
                            SirkulaFood membantu peritel mengenali risiko lebih awal, mengambil keputusan tepat, dan menyalurkan pangan yang masih bernilai kepada mereka yang membutuhkan.
                        </p>
                        <div class="reveal mt-8 flex flex-wrap gap-3">
                            <a
                                href="#solusi"
                                class="inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/5 px-5 py-3.5 text-[12px] backdrop-blur-lg"
                                >Jelajahi solusi <ArrowDownRight class="size-4"
                            /></a>
                        </div>
                        <div class="reveal mt-12 flex flex-wrap gap-5 text-[10px] text-white/65">
                            <span class="inline-flex items-center gap-2"><Check class="size-3.5 text-[#bdcaa6]" /> Rekomendasi dapat ditinjau</span
                            ><span class="inline-flex items-center gap-2"><Check class="size-3.5 text-[#bdcaa6]" /> Setiap langkah tercatat</span>
                        </div>
                    </div>
                    <div class="reveal relative mx-auto w-full max-w-[710px] lg:ml-auto">
                        <div class="absolute -right-5 -top-8 size-32 rounded-full bg-[#c68c62]/35 blur-3xl"></div>
                        <div class="absolute -bottom-8 -left-8 size-40 rounded-full bg-[#8e9c75]/40 blur-3xl"></div>
                        <div
                            class="grad-border relative overflow-hidden rounded-[30px] bg-[#f8f5ed]/90 p-2.5 shadow-[0_35px_100px_rgba(20,30,20,.34)] backdrop-blur-[24px] sm:p-3.5"
                        >
                            <div class="overflow-hidden rounded-[24px] bg-[#fbfaf6] text-[#171512]">
                                <div class="flex items-center justify-between border-b border-[#e9e5dc] px-4 py-3 sm:px-6 sm:py-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="grid size-8 place-items-center rounded-xl bg-[#3B4B34] text-white"><Leaf class="size-4" /></div>
                                        <div>
                                            <p class="text-[11px] font-semibold">Dasbor SirkulaFood</p>
                                            <p class="text-[9px] text-[#8a877d]">Pratinjau konsep</p>
                                        </div>
                                    </div>
                                    <span class="rounded-full bg-[#edf1e7] px-2.5 py-1 text-[9px] text-[#3B4B34]">● Sistem terhubung</span>
                                </div>
                                <div class="grid min-h-[370px] sm:grid-cols-[140px_1fr]">
                                    <aside class="hidden border-r border-[#e9e5dc] bg-[#f6f3eb] p-4 sm:flex sm:flex-col">
                                        <p class="mb-3 px-2 text-[8px] font-semibold uppercase tracking-[.15em] text-[#a29e93]">Ruang kerja</p>
                                        <div class="flex flex-col gap-1 text-[10px]">
                                            <div class="flex items-center gap-2 rounded-xl bg-[#e7ebde] px-2.5 py-2.5 text-[#3B4B34]">
                                                <Package class="size-3.5" /> Dasbor
                                            </div>
                                            <div class="flex items-center gap-2 px-2.5 py-2.5 text-[#817e74]">
                                                <Bot class="size-3.5" /> Stok
                                            </div>
                                            <div class="flex items-center gap-2 px-2.5 py-2.5 text-[#817e74]">
                                                <Handshake class="size-3.5" /> Simulasi AI
                                            </div>
                                        </div>
                                    </aside>
                                    <div class="min-w-0 p-4 sm:p-5">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-[8px] font-semibold uppercase tracking-[.15em] text-[#929084]">
                                                    Ringkasan operasional
                                                </p>
                                                <h2 class="mt-1 font-serif text-[25px] sm:text-[30px]">
                                                    Stok terkendali<span class="text-[#8E9C75]">.</span>
                                                </h2>
                                            </div>
                                        </div>
                                        <div class="mt-4 grid grid-cols-2 gap-2 xl:grid-cols-4">
                                            <div class="rounded-2xl border border-[#ebe7de] bg-white/75 p-2.5"><p class="text-[8px] text-[#8d8a7e]">Segera kedaluwarsa</p><p class="mt-1 font-serif text-[20px]">7</p><p class="text-[8px] text-[#bb7a54]">Perlu ditangani segera</p></div>
                                            <div class="rounded-2xl border border-[#ebe7de] bg-white/75 p-2.5"><p class="text-[8px] text-[#8d8a7e]">Pangan terselamatkan</p><p class="mt-1 font-serif text-[20px]">88 <span class="text-[10px]">kg</span></p><p class="text-[8px] text-[#71845f]">Pangan terselamatkan</p></div>
                                            <div class="rounded-2xl border border-[#ebe7de] bg-white/75 p-2.5"><p class="text-[8px] text-[#8d8a7e]">Kerugian dicegah</p><p class="mt-1 font-serif text-[18px]">Rp 5.7jt</p><p class="text-[8px] text-[#71845f]">Diskon dan penyelamatan</p></div>
                                            <div class="rounded-2xl border border-[#ebe7de] bg-white/75 p-2.5"><p class="text-[8px] text-[#8d8a7e]">Emisi CO₂e berkurang</p><p class="mt-1 font-serif text-[20px]">220 <span class="text-[10px]">kg</span></p><p class="text-[8px] text-[#71845f]">Dampak tercatat</p></div>
                                        </div>
                                        <div class="mt-3 grid gap-3 lg:grid-cols-[1.2fr_.8fr]">
                                            <div class="rounded-2xl border border-[#ebe7de] bg-white/75 p-3.5">
                                                <div class="flex justify-between">
                                                    <div>
                                                        <p class="text-[9px] font-semibold">Produk prioritas</p>
                                                        <p class="text-[8px] text-[#949084]">Produk mendekati kedaluwarsa</p>
                                                    </div>
                                                </div>
                                                <div class="mt-3 flex flex-col gap-2.5">
                                                    <div
                                                        v-for="item in [
                                                            {
                                                                emoji: '🥑',
                                                                name: 'Alpukat lokal',
                                                                status: 'Tinjau',
                                                                color: 'bg-[#bb7a54]',
                                                                width: '72%',
                                                            },
                                                            {
                                                                emoji: '🥬',
                                                                name: 'Sayuran daun',
                                                                status: 'Pantau',
                                                                color: 'bg-[#8e9c75]',
                                                                width: '40%',
                                                            },
                                                            {
                                                                emoji: '🥖',
                                                                name: 'Roti gandum',
                                                                status: 'Diskon',
                                                                color: 'bg-[#d2a16e]',
                                                                width: '55%',
                                                            },
                                                        ]"
                                                        :key="item.name"
                                                        class="flex items-center gap-2.5"
                                                    >
                                                        <div class="grid size-8 place-items-center rounded-xl bg-[#f4ead8] text-[15px]">
                                                            {{ item.emoji }}
                                                        </div>
                                                        <div class="min-w-0 flex-1">
                                                            <div class="flex justify-between text-[9px]">
                                                                <span class="font-semibold">{{ item.name }}</span
                                                                ><span class="text-[#a25e42]">{{ item.status }}</span>
                                                            </div>
                                                            <div class="mt-1.5 h-1 rounded-full bg-[#eeeae0]">
                                                                <div
                                                                    class="h-full rounded-full"
                                                                    :class="item.color"
                                                                    :style="{ width: item.width }"
                                                                ></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="rounded-2xl border border-[#ebe7de] bg-white/75 p-3.5">
                                                <div class="mb-3 flex items-center justify-between"><span class="text-[9px] font-semibold">Aktivitas Agen</span><span class="size-2 rounded-full bg-[#7a9c6b]"></span></div>
                                                <div class="space-y-2">
                                                    <div class="rounded-xl border border-[#ebe7de] bg-[#faf8f2] p-2"><p class="text-[8px] font-semibold text-[#8b6949]">Peringatan dikirim · Susu segar organik</p><p class="mt-1 text-[7px] text-[#858277]">19 produk dipantau · diskon aktif</p></div>
                                                    <div class="rounded-xl border border-[#ebe7de] bg-[#faf8f2] p-2"><p class="text-[8px] font-semibold text-[#596d48]">Donasi diminta · Roti sourdough</p><p class="mt-1 text-[7px] text-[#858277]">Disalurkan kepada mitra komunitas</p></div>
                                                    <div class="rounded-xl border border-[#ebe7de] bg-[#faf8f2] p-2"><p class="text-[8px] font-semibold text-[#8b6949]">Perlu ditinjau · Bayam muda</p><p class="mt-1 text-[7px] text-[#858277]">Sisa masa simpan: 48 jam</p></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3 flex items-center gap-2 rounded-xl border border-[#eae6dd] bg-white/65 px-3 py-2.5">
                                            <Check class="size-3 text-[#3B4B34]" />
                                            <p class="text-[8px] text-[#77746b]">Setiap tindakan dapat ditinjau dan tercatat.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                id="dampak"
                ref="proofSection"
                class="proof relative overflow-hidden bg-[#171512] px-5 py-14 text-[#F2EEE5] sm:px-8 lg:px-12 lg:py-[72px]"
            >
                <div class="proof-glow proof-left absolute -left-20 top-[-120px] size-[350px] rounded-full"></div>
                <div class="proof-glow proof-right absolute -right-10 bottom-[-160px] size-[420px] rounded-full"></div>
                <div class="relative mx-auto grid max-w-[1160px] gap-8 sm:grid-cols-3 sm:gap-6">
                    <div
                        v-for="(item, index) in [
                            { suffix: ' langkah', label: 'dari pemantauan hingga tindakan', icon: ScanEye },
                            { suffix: ' pilihan', label: 'diskon atau donasi tepat sasaran', icon: ArrowRight },
                            { suffix: ' alur', label: 'terhubung untuk tim operasional', icon: Sparkles },
                        ]"
                        :key="item.label"
                        class="reveal flex items-center gap-4 border-b border-white/10 pb-7 last:border-0 sm:border-0"
                    >
                        <div class="grid size-11 place-items-center rounded-full border border-white/15 bg-white/[.06] text-[#bdcaa6]">
                            <component :is="item.icon" class="size-[18px]" />
                        </div>
                        <div>
                            <p class="font-serif text-[38px] leading-none">
                                {{ counts[index] }}<span class="ml-1 text-[16px] italic text-[#9eab88]">{{ item.suffix }}</span>
                            </p>
                            <p class="mt-2 text-[10px] text-white/50">{{ item.label }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="solusi" class="px-5 py-24 sm:px-8 lg:px-12 lg:py-32">
                <div class="mx-auto max-w-[1320px]">
                    <div class="reveal mb-10 flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                        <div>
                            <p class="mb-4 text-[10px] font-semibold uppercase tracking-[.18em] text-[#71805f]">Satu ekosistem, tiga keputusan</p>
                            <h2 class="w-anim font-serif text-[clamp(2.8rem,6vw,5.3rem)] leading-[.92]">
                                Pangan bergerak<br /><span class="italic text-[#3B4B34]">lebih jauh.</span>
                            </h2>
                        </div>
                        <p class="max-w-[360px] text-[12px] leading-6 text-[#77746a]">
                            Ubah stok yang sulit diprediksi menjadi keputusan terukur dengan langkah tindak lanjut yang jelas.
                        </p>
                    </div>
                    <div class="reveal mb-5 flex justify-between">
                        <p class="text-[10px] text-[#79766d]">Solusi untuk setiap tahap penyelamatan pangan</p>
                        <div class="flex gap-2">
                            <button
                                aria-label="Geser fitur ke kiri"
                                @click="scrollFeatures(-1)"
                                class="grid size-10 place-items-center rounded-full border border-[#dcd7cb] bg-white/40"
                            >
                                <ChevronLeft class="size-4" /></button
                            ><button
                                aria-label="Geser fitur ke kanan"
                                @click="scrollFeatures(1)"
                                class="grid size-10 place-items-center rounded-full border border-[#dcd7cb] bg-white/40"
                            >
                                <ChevronRight class="size-4" />
                            </button>
                        </div>
                    </div>
                    <div
                        ref="carousel"
                        class="feature-carousel flex snap-x snap-mandatory gap-5 overflow-x-auto px-[max(2rem,calc((100vw-1200px)/2+2rem))] pb-5 [scrollbar-width:none]"
                    >
                        <article
                            v-for="feature in features"
                            :key="feature.eyebrow"
                            class="grad-border group min-w-[82vw] snap-start overflow-hidden rounded-[28px] bg-[#faf8f2] sm:min-w-[440px] lg:min-w-[calc((100vw-120px)/3)] lg:max-w-[420px]"
                        >
                            <div class="relative h-[240px] overflow-hidden sm:h-[280px]">
                                <img
                                    :src="feature.image"
                                    :alt="feature.alt"
                                    loading="lazy"
                                    class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105"
                                />
                                <div class="absolute inset-0 bg-gradient-to-t from-[#171512]/45 via-transparent"></div>
                                <span
                                    class="absolute left-5 top-5 inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/15 px-3 py-1.5 text-[9px] text-white backdrop-blur-xl"
                                    ><component :is="feature.icon" class="size-3" />{{ feature.eyebrow }}</span
                                ><ArrowUpRight class="absolute bottom-5 right-5 size-4 text-white" />
                            </div>
                            <div class="p-5 sm:p-6">
                                <h3 class="font-serif text-[29px] leading-[1.02]">{{ feature.title }}</h3>
                                <p class="mt-3 text-[11px] leading-5 text-[#7b786e]">{{ feature.description }}</p>
                                <div class="mt-5 h-px bg-[#e7e2d8]"></div>
                                <div class="mt-4 flex justify-between text-[9px] text-[#77746a]">
                                    <span>SirkulaFood · {{ feature.eyebrow.split(' · ')[1] }}</span
                                    ><ArrowRight class="size-3.5 text-[#3B4B34]" />
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section id="sdgs" class="scroll-mt-24 overflow-hidden bg-[#e9e5da] px-5 py-20 sm:px-8 lg:px-12 lg:py-28">
                <div class="mx-auto max-w-[1320px]">
                    <div class="grid items-center gap-12 lg:grid-cols-[.85fr_1.15fr] lg:gap-20">
                        <div class="reveal">
                            <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-[#c7c1b3] bg-[#F2EEE5] px-3 py-1.5 text-[9px] font-semibold uppercase tracking-[.18em] text-[#667352]">
                                <Leaf class="size-3.5" /> Dampak yang saling terhubung
                            </p>
                            <h2 class="w-anim max-w-[580px] font-serif text-[clamp(2.8rem,5.5vw,5rem)] leading-[.94] tracking-[-.035em] text-[#20271d]">
                                Pangan terselamatkan,<br /><span class="italic text-[#667352]">dampak berlanjut.</span>
                            </h2>
                            <p class="mt-6 max-w-[480px] text-[12px] leading-6 text-[#77746a]">
                                Melalui pemantauan stok, keputusan yang lebih tepat, dan kerja sama lintas mitra, SirkulaFood mendukung beberapa Tujuan Pembangunan Berkelanjutan (SDGs).
                            </p>
                            <p class="mt-4 text-[9px] leading-5 text-[#8c887c]">Kontribusi ini menggambarkan dukungan melalui alur kerja dan kemitraan, bukan klaim pencapaian target SDGs secara keseluruhan.</p>
                        </div>

                        <div class="reveal relative mx-auto aspect-[1.15] w-full max-w-[560px] overflow-hidden rounded-[32px] bg-[#193b32] p-5 text-[#F2EEE5] shadow-[0_28px_70px_rgba(28,44,34,.18)] sm:p-8">
                            <div class="absolute -right-20 -top-24 size-72 rounded-full border border-[#c5aa66]/25"></div>
                            <div class="absolute -bottom-36 -left-20 size-80 rounded-full border border-[#c5aa66]/20"></div>
                            <svg class="absolute inset-0 h-full w-full opacity-70" viewBox="0 0 560 480" fill="none" aria-hidden="true">
                                <path d="M280 242 C220 188 135 155 88 124 M280 242 C360 182 423 154 477 124 M280 242 C210 312 147 341 92 369 M280 242 C354 313 418 345 468 369" stroke="#C5AA66" stroke-width="1.5" stroke-dasharray="5 8" />
                                <circle cx="280" cy="242" r="142" stroke="#F2EEE5" stroke-opacity=".16" />
                                <circle cx="280" cy="242" r="184" stroke="#C5AA66" stroke-opacity=".28" />
                            </svg>
                            <div class="relative flex h-full items-center justify-center">
                                <div class="absolute left-0 top-2 flex items-center gap-2 rounded-2xl border border-white/10 bg-white/[.08] px-3 py-2 backdrop-blur-md sm:left-3 sm:top-5">
                                    <Store class="size-4 text-[#d7c28d]" /><span class="text-[9px] font-medium">Toko</span>
                                </div>
                                <div class="absolute right-0 top-2 flex items-center gap-2 rounded-2xl border border-white/10 bg-white/[.08] px-3 py-2 backdrop-blur-md sm:right-3 sm:top-5">
                                    <Tag class="size-4 text-[#d7c28d]" /><span class="text-[9px] font-medium">Diskon tepat waktu</span>
                                </div>
                                <div class="absolute bottom-2 left-0 flex items-center gap-2 rounded-2xl border border-white/10 bg-white/[.08] px-3 py-2 backdrop-blur-md sm:bottom-5 sm:left-3">
                                    <Leaf class="size-4 text-[#bdcaa6]" /><span class="text-[9px] font-medium">Pangan terselamatkan</span>
                                </div>
                                <div class="absolute bottom-2 right-0 flex items-center gap-2 rounded-2xl border border-white/10 bg-white/[.08] px-3 py-2 backdrop-blur-md sm:bottom-5 sm:right-3">
                                    <Handshake class="size-4 text-[#bdcaa6]" /><span class="text-[9px] font-medium">Mitra sosial</span>
                                </div>
                                <div class="relative z-10 grid size-28 place-items-center rounded-full border border-[#d3bd7d]/60 bg-[#F2EEE5] text-[#193b32] shadow-[0_0_0_14px_rgba(242,238,229,.06)] sm:size-36">
                                    <div class="text-center"><Package class="mx-auto size-8" /><span class="mt-1 block text-[9px] font-bold tracking-wide">SIKLUS PANGAN</span></div>
                                </div>
                            </div>
                            <div class="absolute left-1/2 top-1/2 z-20 grid size-7 -translate-x-1/2 -translate-y-[5.7rem] place-items-center rounded-full bg-[#c5aa66] text-[#193b32] sm:-translate-y-[7.2rem]"><ArrowRight class="size-3.5" /></div>
                        </div>
                    </div>

                    <div class="mt-12 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article v-for="goal in sdgContributions" :key="goal.number" class="group rounded-[22px] border border-[#dcd7cb] bg-[#F7F4ED] p-5 transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                            <div class="flex items-start justify-between gap-4">
                                <span class="font-serif text-[42px] leading-none" :style="{ color: goal.color }">{{ goal.number }}</span>
                                <span class="grid size-10 place-items-center rounded-full bg-[#e9e5da] text-[#3B4B34] transition group-hover:bg-[#193b32] group-hover:text-[#F2EEE5]"><component :is="goal.icon" class="size-4" /></span>
                            </div>
                            <h3 class="mt-5 min-h-[42px] text-[12px] font-semibold leading-5 text-[#283326]">{{ goal.title }}</h3>
                            <p class="mt-2 text-[10px] leading-5 text-[#77746a]">{{ goal.description }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="alur" class="bg-[#e9e5da] px-5 py-24 sm:px-8 lg:px-12 lg:py-32">
                <div class="mx-auto grid max-w-[1180px] items-center gap-12 lg:grid-cols-[.9fr_1.1fr]">
                    <div class="reveal">
                        <p class="mb-4 text-[10px] font-semibold uppercase tracking-[.18em] text-[#71805f]">Alur kerja yang mudah dipahami</p>
                        <h2 class="w-anim font-serif text-[clamp(2.8rem,6vw,5.1rem)] leading-[.94]">
                            Dari rak,<br />menuju <span class="italic text-[#3B4B34]">dampak.</span>
                        </h2>
                        <p class="mt-6 max-w-[410px] text-[12px] leading-6 text-[#77746a]">
                            Satukan pemantauan stok, rekomendasi yang dapat ditinjau, dan koordinasi mitra dalam satu alur kerja.
                        </p>
                        <a href="#integrasi" class="mt-8 inline-flex items-center gap-2 text-[11px] font-semibold text-[#3B4B34]">Lihat pilihan integrasi <ArrowRight class="size-4" /></a>
                    </div>
                    <div class="reveal relative mx-auto w-full max-w-[560px]">
                        <div class="absolute inset-[15%] rounded-full bg-[#b5bf9f]/30 blur-3xl"></div>
                        <svg class="absolute inset-0 size-full" viewBox="0 0 760 380" fill="none" aria-hidden="true">
                            <path
                                d="M380 190 L110 80 M380 190 L640 90 M380 190 L90 260 M380 190 L660 270 M380 190 L250 330 M380 190 L510 40"
                                stroke="#8E9C75"
                                stroke-opacity=".35"
                                stroke-dasharray="3 6"
                            />
                            <circle cx="380" cy="190" r="124" stroke="#8E9C75" stroke-opacity=".25" />
                            <circle cx="380" cy="190" r="180" stroke="#8E9C75" stroke-opacity=".18" />
                        </svg>
                        <div class="relative flex aspect-[1.35] items-center justify-center">
                            <div class="relative z-10 grid size-[110px] place-items-center rounded-full bg-[#3B4B34] text-white shadow-xl">
                                <div class="text-center"><Leaf class="mx-auto size-6" /><span class="text-[9px] font-semibold">SirkulaFood</span></div>
                            </div>
                            <div
                                v-for="(node, index) in nodes"
                                :key="node.label"
                                class="orbit-node absolute z-10 flex flex-col items-center gap-1.5"
                                :class="node.pos"
                                :style="{ animationDelay: index * -0.65 + 's' }"
                            >
                                <div
                                    class="grid size-11 place-items-center rounded-2xl border border-white/70 bg-[#F8F6F0]/90 text-[#3B4B34] shadow-lg"
                                >
                                    <component :is="node.icon" class="size-[17px]" />
                                </div>
                                <span class="rounded-full bg-[#F2EEE5]/80 px-2 py-1 text-[8px]">{{ node.label }}</span>
                            </div>
                        </div>
                        <div class="relative mx-auto grid max-w-[450px] gap-2 sm:grid-cols-3">
                            <div class="rounded-2xl border border-white/65 bg-white/35 p-3">
                                <p class="text-[9px] font-semibold">01 · Pantau</p>
                                <p class="text-[8px] text-[#817e73]">Stok dan masa simpan</p>
                            </div>
                            <div class="rounded-2xl border border-white/65 bg-white/35 p-3">
                                <p class="text-[9px] font-semibold">02 · Tentukan</p>
                                <p class="text-[8px] text-[#817e73]">Diskon atau donasi</p>
                            </div>
                            <div class="rounded-2xl border border-white/65 bg-white/35 p-3">
                                <p class="text-[9px] font-semibold">03 · Salurkan</p>
                                <p class="text-[8px] text-[#817e73]">Tindak lanjut mitra</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="integrasi" class="relative px-5 py-24 sm:px-8 lg:px-12 lg:py-32">
                <div class="mx-auto grid max-w-[1180px] items-center gap-12 lg:grid-cols-2">
                    <div class="reveal">
                        <p class="mb-4 text-[10px] font-semibold uppercase tracking-[.18em] text-[#71805f]">Terhubung dengan ekosistem Anda</p>
                        <h2 class="w-anim font-serif text-[clamp(2.8rem,6vw,5rem)] leading-[.94]">
                            Satu siklus.<br /><span class="italic text-[#3B4B34]">Banyak koneksi.</span>
                        </h2>
                        <p class="mt-6 max-w-[420px] text-[12px] leading-6 text-[#77746a]">
                            Hubungkan sumber stok, pemantauan cerdas, dan mitra donasi agar setiap keputusan didukung konteks operasional
                            it needs.
                        </p>
                        <div class="mt-7 flex flex-wrap gap-2">
                            <span class="rounded-full border border-[#d9d6ca] bg-white/35 px-3 py-1.5 text-[9px]">Sinkronisasi Kasir (POS)</span
                            ><span class="rounded-full border border-[#d9d6ca] bg-white/35 px-3 py-1.5 text-[9px]">Notifikasi mitra</span
                            ><span class="rounded-full border border-[#d9d6ca] bg-white/35 px-3 py-1.5 text-[9px]">AI dan visi komputer</span>
                        </div>
                    </div>
                    <div class="reveal rounded-[30px] border border-white/75 bg-white/30 p-5 shadow-xl backdrop-blur-2xl sm:p-8">
                        <p class="text-[9px] font-semibold uppercase tracking-[.16em] text-[#8b887d]">Jaringan SirkulaFood</p>
                        <p class="mt-1 font-serif text-[28px]">Alur kerja terhubung</p>
                        <div class="mt-6 flex flex-col gap-2.5">
                            <div
                                v-for="(item, index) in [
                                    { title: 'Dasbor toko', desc: 'Produk · batch · masa simpan', icon: Store },
                                    { title: 'Sistem cerdas SirkulaFood', desc: 'Prediksi · rekomendasi · audit', icon: Bot },
                                    { title: 'Mitra donasi', desc: 'Diskon · donasi · notifikasi', icon: Handshake },
                                ]"
                                :key="item.title"
                                class="flex items-center gap-3 rounded-2xl border border-[#e4e0d6] bg-white/70 p-3.5"
                            >
                                <div class="grid size-10 place-items-center rounded-xl bg-[#e8ecdf] text-[#3B4B34]">
                                    <component :is="item.icon" class="size-[17px]" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold">{{ item.title }}</p>
                                    <p class="mt-1 text-[8px] text-[#908c81]">{{ item.desc }}</p>
                                </div>
                                <Check v-if="index === 2" class="ml-auto size-4 text-[#71805f]" />
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-between rounded-2xl bg-[#3B4B34] px-4 py-3.5 text-white">
                            <span class="text-[9px]">Data mengalir dalam satu siklus</span><ArrowRight class="size-4" />
                        </div>
                    </div>
                </div>
            </section>

            <section id="pricing" class="px-5 py-12 sm:px-8 lg:px-12 lg:py-20">
                <div
                    class="pilot grad-border relative mx-auto max-w-[1320px] overflow-hidden rounded-[32px] bg-[#3B4B34] px-6 py-12 text-[#F2EEE5] sm:px-12 lg:px-16 lg:py-16"
                >
                    <div class="pilot-grain absolute inset-0 opacity-25"></div>
                    <div class="absolute -right-20 -top-36 size-[450px] rounded-full border border-white/10"></div>
                    <div class="relative grid items-end gap-10 lg:grid-cols-[1fr_auto]">
                        <div class="reveal max-w-[650px]">
                            <p
                                class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/[.07] px-3 py-1.5 text-[9px] uppercase tracking-[.14em] text-white/75"
                            >
                                <Sparkles class="size-3" /> Program percontohan SirkulaFood
                            </p>
                            <h2 class="font-serif text-[clamp(2.8rem,6vw,5.5rem)] leading-[.92]">
                                Mulai dari satu<br /><span class="italic text-[#c9d2b6]">langkah bermakna.</span>
                            </h2>
                            <p class="mt-5 max-w-[470px] text-[11px] leading-6 text-white/65">
                                Sesuaikan alur kerja SirkulaFood dengan operasional toko Anda. Kami menyesuaikan rencana penerapan dengan
                                kebutuhan operasional.
                            </p>
                        </div>
                        <div class="reveal w-full max-w-[290px] rounded-[22px] border border-white/15 bg-white/[.08] p-5 backdrop-blur-xl">
                            <div class="flex justify-between text-[9px] uppercase tracking-[.14em] text-white/60">
                                <span>Penerapan bertahap</span><Leaf class="size-4" />
                            </div>
                            <p class="mt-3 font-serif text-[28px]">Dirancang bersama.</p>
                            <p class="mt-2 text-[9px] leading-4 text-white/55">Lingkup, integrasi, dan indikator keberhasilan disepakati bersama.</p>
                            <a href="mailto:hello@SirkulaFood.id" class="mt-5 flex justify-between rounded-full bg-[#F2EEE5] px-4 py-3 text-[10px] font-semibold text-[#171512]">Jadwalkan konsultasi <ArrowUpRight class="size-4" /></a>
                            <p class="mt-3 text-[8px] text-white/40">Biaya uji coba dibahas bersama dan disesuaikan dengan kebutuhan Anda</p>
                        </div>
                    </div>
                </div>
            </section>



            <section id="faq" class="scroll-mt-24 px-5 py-20 sm:px-8 lg:px-12 lg:py-28">
                <div class="mx-auto grid max-w-[1120px] gap-12 lg:grid-cols-[.72fr_1.28fr]">
                    <div class="reveal">
                        <p class="mb-4 text-[10px] uppercase tracking-[.18em] text-[#71805f]">FAQ</p>
                        <h2 class="w-anim font-serif text-[clamp(2.8rem,5vw,4.6rem)] leading-[.95]">
                            Kami siap<br /><span class="italic text-[#3B4B34]">membantu.</span>
                        </h2>
                        <p class="mt-5 max-w-[310px] text-[11px] leading-6 text-[#77746a]">Tim kami siap membantu merancang alur kerja yang sesuai dengan kebutuhan Anda.</p>
                        <a href="mailto:hello@SirkulaFood.id" class="mt-5 inline-flex items-center gap-2 text-[10px] font-semibold text-[#3B4B34]"
                            >Hubungi tim SirkulaFood <ArrowUpRight class="size-3.5"
                        /></a>
                    </div>
                    <div class="reveal divide-y divide-[#dcd7cb] border-y border-[#dcd7cb]">
                        <article v-for="(faq, index) in faqs" :key="faq.question" class="py-5">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-5 text-left"
                                :aria-expanded="openFaq === index"
                                :aria-controls="'faq-panel-' + index"
                                @click="openFaq = openFaq === index ? -1 : index"
                            >
                                <span class="font-serif text-[20px] sm:text-[23px]">{{ faq.question }}</span
                                ><span
                                    class="grid size-8 place-items-center rounded-full border border-[#d9d5c9] text-[#3B4B34] transition-transform"
                                    :class="{ 'rotate-180 bg-[#3B4B34] text-white': openFaq === index }"
                                    ><ChevronDown class="size-4"
                                /></span>
                            </button>
                            <div :id="'faq-panel-' + index" class="faq-body" :style="{ maxHeight: openFaq === index ? '220px' : '0px' }">
                                <p class="max-w-[590px] pr-10 pt-3 text-[11px] leading-6 text-[#77746a]">{{ faq.answer }}</p>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section id="sdgs" class="overflow-hidden bg-[#e9e5da] px-5 py-20 sm:px-8 lg:px-12 lg:py-28">
                <div class="mx-auto max-w-[1320px]">
                    <div class="grid items-center gap-12 lg:grid-cols-[.85fr_1.15fr] lg:gap-20">
                        <div class="reveal">
                            <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-[#c7c1b3] bg-[#F2EEE5] px-3 py-1.5 text-[9px] font-semibold uppercase tracking-[.18em] text-[#667352]">
                                <Leaf class="size-3.5" /> Dampak yang saling terhubung
                            </p>
                            <h2 class="w-anim max-w-[580px] font-serif text-[clamp(2.8rem,5.5vw,5rem)] leading-[.94] tracking-[-.035em] text-[#20271d]">
                                Pangan terselamatkan,<br /><span class="italic text-[#667352]">dampak berlanjut.</span>
                            </h2>
                            <p class="mt-6 max-w-[480px] text-[12px] leading-6 text-[#77746a]">
                                Melalui pemantauan stok, keputusan yang lebih tepat, dan kerja sama lintas mitra, SirkulaFood mendukung beberapa Tujuan Pembangunan Berkelanjutan (SDGs).
                            </p>
                            <p class="mt-4 text-[9px] leading-5 text-[#8c887c]">Kontribusi ini menggambarkan dukungan melalui alur kerja dan kemitraan, bukan klaim pencapaian target SDGs secara keseluruhan.</p>
                        </div>

                        <div class="reveal relative mx-auto aspect-[1.15] w-full max-w-[560px] overflow-hidden rounded-[32px] bg-[#193b32] p-5 text-[#F2EEE5] shadow-[0_28px_70px_rgba(28,44,34,.18)] sm:p-8">
                            <div class="absolute -right-20 -top-24 size-72 rounded-full border border-[#c5aa66]/25"></div>
                            <div class="absolute -bottom-36 -left-20 size-80 rounded-full border border-[#c5aa66]/20"></div>
                            <svg class="absolute inset-0 h-full w-full opacity-70" viewBox="0 0 560 480" fill="none" aria-hidden="true">
                                <path d="M280 242 C220 188 135 155 88 124 M280 242 C360 182 423 154 477 124 M280 242 C210 312 147 341 92 369 M280 242 C354 313 418 345 468 369" stroke="#C5AA66" stroke-width="1.5" stroke-dasharray="5 8" />
                                <circle cx="280" cy="242" r="142" stroke="#F2EEE5" stroke-opacity=".16" />
                                <circle cx="280" cy="242" r="184" stroke="#C5AA66" stroke-opacity=".28" />
                            </svg>
                            <div class="relative flex h-full items-center justify-center">
                                <div class="absolute left-0 top-2 flex items-center gap-2 rounded-2xl border border-white/10 bg-white/[.08] px-3 py-2 backdrop-blur-md sm:left-3 sm:top-5">
                                    <Store class="size-4 text-[#d7c28d]" /><span class="text-[9px] font-medium">Toko</span>
                                </div>
                                <div class="absolute right-0 top-2 flex items-center gap-2 rounded-2xl border border-white/10 bg-white/[.08] px-3 py-2 backdrop-blur-md sm:right-3 sm:top-5">
                                    <Tag class="size-4 text-[#d7c28d]" /><span class="text-[9px] font-medium">Diskon tepat waktu</span>
                                </div>
                                <div class="absolute bottom-2 left-0 flex items-center gap-2 rounded-2xl border border-white/10 bg-white/[.08] px-3 py-2 backdrop-blur-md sm:bottom-5 sm:left-3">
                                    <Leaf class="size-4 text-[#bdcaa6]" /><span class="text-[9px] font-medium">Pangan terselamatkan</span>
                                </div>
                                <div class="absolute bottom-2 right-0 flex items-center gap-2 rounded-2xl border border-white/10 bg-white/[.08] px-3 py-2 backdrop-blur-md sm:bottom-5 sm:right-3">
                                    <Handshake class="size-4 text-[#bdcaa6]" /><span class="text-[9px] font-medium">Mitra sosial</span>
                                </div>
                                <div class="relative z-10 grid size-28 place-items-center rounded-full border border-[#d3bd7d]/60 bg-[#F2EEE5] text-[#193b32] shadow-[0_0_0_14px_rgba(242,238,229,.06)] sm:size-36">
                                    <div class="text-center"><Package class="mx-auto size-8" /><span class="mt-1 block text-[9px] font-bold tracking-wide">SIKLUS PANGAN</span></div>
                                </div>
                            </div>
                            <div class="absolute left-1/2 top-1/2 z-20 grid size-7 -translate-x-1/2 -translate-y-[5.7rem] place-items-center rounded-full bg-[#c5aa66] text-[#193b32] sm:-translate-y-[7.2rem]"><ArrowRight class="size-3.5" /></div>
                        </div>
                    </div>

                    <div class="mt-12 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article v-for="goal in sdgContributions" :key="goal.number" class="group rounded-[22px] border border-[#dcd7cb] bg-[#F7F4ED] p-5 transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                            <div class="flex items-start justify-between gap-4">
                                <span class="font-serif text-[42px] leading-none" :style="{ color: goal.color }">{{ goal.number }}</span>
                                <span class="grid size-10 place-items-center rounded-full bg-[#e9e5da] text-[#3B4B34] transition group-hover:bg-[#193b32] group-hover:text-[#F2EEE5]"><component :is="goal.icon" class="size-4" /></span>
                            </div>
                            <h3 class="mt-5 min-h-[42px] text-[12px] font-semibold leading-5 text-[#283326]">{{ goal.title }}</h3>
                            <p class="mt-2 text-[10px] leading-5 text-[#77746a]">{{ goal.description }}</p>
                        </article>
                    </div>
                </div>
            </section>
        </main>
        <footer class="footer-gradient px-5 pb-8 pt-12 text-[#F2EEE5] sm:px-8 lg:px-12 lg:pt-16">
            <div class="mx-auto max-w-[1320px]">
                <div class="grid gap-10 border-b border-white/15 pb-9 sm:grid-cols-2 lg:grid-cols-[1.4fr_.8fr_1fr] lg:gap-16">
                    <div>
                        <a href="#home" class="inline-flex items-center rounded-2xl bg-[#F2EEE5] px-4 py-2.5 shadow-lg shadow-black/10" aria-label="SirkulaFood — kembali ke atas">
                            <img src="/images/SirkulaFood-logo-light.png" alt="SirkulaFood" class="h-11 w-auto max-w-[190px]" />
                        </a>
                        <p class="mt-4 max-w-sm text-[10px] leading-5 text-white/65">Sistem sirkular yang menjaga nilai pangan. Dari stok toko, menuju pangan yang lebih bermanfaat.</p>
                        <div class="mt-4 flex items-center gap-3" aria-label="Media sosial SirkulaFood">
                            <a href="https://youtu.be/5un13SpsDpI?si=6xmhCDbSdV4CBz9I" target="_blank" rel="noopener noreferrer" aria-label="SirkulaFood di YouTube" title="YouTube" class="inline-flex items-center transition hover:scale-110 hover:opacity-80">
                                <img src="/images/youtube-social.png" alt="" class="h-5 w-7 object-contain" />
                            </a>
                            <a href="https://t.me/sirkulafood_bot" target="_blank" rel="noopener noreferrer" aria-label="SirkulaFood di Telegram" title="Telegram" class="inline-flex items-center transition hover:scale-110 hover:opacity-80">
                                <img src="/images/telegram-social.png" alt="" class="size-6 object-contain" />
                            </a>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-[9px] font-semibold uppercase tracking-[.18em] text-[#d4c28f]">Jelajahi</h2>
                        <nav class="mt-4 flex flex-col items-start gap-3 text-[10px] text-white/65" aria-label="Navigasi footer">
                            <a href="#solusi" class="transition hover:text-white">Solusi</a>
                            <a href="#alur" class="transition hover:text-white">Cara kerja</a>
                            <a href="#sdgs" class="transition hover:text-white">Kontribusi SDGs</a>
                            <a href="#faq" class="transition hover:text-white">FAQ</a>
                        </nav>
                    </div>
                    <div>
                        <h2 class="text-[9px] font-semibold uppercase tracking-[.18em] text-[#d4c28f]">Mari berkolaborasi</h2>
                        <p class="mt-4 max-w-xs text-[10px] leading-5 text-white/65">Punya pertanyaan atau ingin menjajaki penerapan di tempat Anda?</p>
                        <a href="mailto:hello@SirkulaFood.id" class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/[.07] px-4 py-2.5 text-[10px] font-semibold transition hover:bg-white/[.14]">
                            Hubungi tim SirkulaFood <ArrowUpRight class="size-3.5" />
                        </a>
                    </div>
                    <div>
                        <h2 class="text-[9px] font-semibold uppercase tracking-[.18em] text-[#d4c28f]">Jelajahi</h2>
                        <nav class="mt-4 flex flex-col items-start gap-3 text-[10px] text-white/65" aria-label="Navigasi footer">
                            <a href="#solusi" class="transition hover:text-white">Solusi</a>
                            <a href="#sdgs" class="transition hover:text-white">Kontribusi SDGs</a>
                            <a href="#alur" class="transition hover:text-white">Cara kerja</a>
                            <a href="#integrasi" class="transition hover:text-white">Integrasi</a>
                            <a href="#faq" class="transition hover:text-white">FAQ</a>
                        </nav>
                    </div>
                    <div>
                        <h2 class="text-[9px] font-semibold uppercase tracking-[.18em] text-[#d4c28f]">Mari berkolaborasi</h2>
                        <p class="mt-4 max-w-xs text-[10px] leading-5 text-white/65">Punya pertanyaan atau ingin menjajaki penerapan di tempat Anda?</p>
                        <a href="mailto:hello@SirkulaFood.id" class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/[.07] px-4 py-2.5 text-[10px] font-semibold transition hover:bg-white/[.14]">
                            Hubungi tim SirkulaFood <ArrowUpRight class="size-3.5" />
                        </a>
                    </div>
                </div>
                <div class="flex flex-col gap-3 pt-5 text-[8px] text-white/45 sm:flex-row sm:items-center sm:justify-between">
                    <span>© {{ new Date().getFullYear() }} SirkulaFood · Selamatkan pangan, jaga nilainya.</span>
                    <span class="inline-flex items-center gap-2"><span class="text-[#d4c28f]">02 · 09 · 12 · 17</span> Berkontribusi melalui aksi dan kemitraan pangan.</span>
                </div>
            </div>
        </footer>
    </div>
</template>
<style scoped>
.SirkulaFood {
    --serif: 'Cormorant Garamond', Georgia, serif;
    font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
    scroll-behavior: smooth;
}
.font-serif {
    font-family: var(--serif);
}
.hero-photo {
    background-image: url('https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=2400&q=90');
    background-position: center 48%;
    background-size: cover;
    will-change: transform;
}
.hero-shade {
    background:
        linear-gradient(90deg, rgba(16, 20, 15, 0.82), rgba(20, 24, 18, 0.67) 42%, rgba(20, 24, 18, 0.35)),
        linear-gradient(0deg, rgba(19, 22, 17, 0.52), transparent 48%, rgba(20, 23, 18, 0.18));
}
.hero-glow {
    background: radial-gradient(circle, rgba(142, 156, 117, 0.42), transparent 70%);
    filter: blur(32px);
}
.proof-glow {
    filter: blur(50px);
}
.proof-left {
    background: radial-gradient(circle, rgba(142, 156, 117, 0.5), transparent 70%);
}
.proof-right {
    background: radial-gradient(circle, rgba(185, 106, 74, 0.32), transparent 70%);
}
.grad-border {
    position: relative;
    isolation: isolate;
}
.grad-border::before {
    position: absolute;
    z-index: -1;
    padding: 1px;
    border-radius: inherit;
    background: linear-gradient(135deg, rgba(142, 156, 117, 0.45), rgba(255, 255, 255, 0.76) 42%, rgba(185, 106, 74, 0.24));
    content: '';
    inset: 0;
    -webkit-mask:
        linear-gradient(#fff 0 0) content-box,
        linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}
.btn-lift {
    transition:
        transform 220ms cubic-bezier(0.2, 0.8, 0.2, 1),
        box-shadow 220ms ease;
}
.btn-lift:hover {
    transform: scale(1.03) translateY(-2px);
    box-shadow: 0 12px 28px rgba(23, 21, 18, 0.16);
}
.reveal {
    opacity: 0;
    transform: translateY(22px);
    transition:
        opacity 700ms cubic-bezier(0.2, 0.7, 0.2, 1),
        transform 800ms cubic-bezier(0.16, 1, 0.3, 1);
}
.reveal.is-revealed {
    opacity: 1;
    transform: translateY(0);
}
.word-reveal {
    display: inline-block;
    animation: word-rise 850ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
.feature-carousel {
    padding-left: max(2rem, calc((100vw - 1200px) / 2 + 2rem));
    padding-right: max(2rem, calc((100vw - 1200px) / 2 + 2rem));
    margin-left: calc(-1 * max(1.25rem, (100vw - 1320px)/2));
    margin-right: calc(-1 * max(1.25rem, (100vw - 1320px)/2));
    scroll-padding-left: max(2rem, calc((100vw - 1200px) / 2 + 2rem));
    -ms-overflow-style: none;
}
.feature-carousel::-webkit-scrollbar {
    display: none;
}
.orbit-node {
    animation: float-y 4.8s ease-in-out infinite;
}
.node-top {
    left: 50%;
    top: 1%;
    transform: translateX(-50%);
}
.node-right-top {
    right: 7%;
    top: 21%;
}
.node-right-bottom {
    right: 8%;
    bottom: 13%;
}
.node-bottom {
    left: 50%;
    bottom: 0;
    transform: translateX(-50%);
}
.node-left-bottom {
    left: 7%;
    bottom: 13%;
}
.node-left-top {
    left: 7%;
    top: 21%;
}
.faq-body {
    overflow: hidden;
    transition: max-height 420ms cubic-bezier(0.2, 0.8, 0.2, 1);
}
.footer-gradient {
    background: linear-gradient(180deg, #f2eee5 0%, #3b4b34 31%, #273225 100%);
}
.pilot-grain {
    background-image: radial-gradient(rgba(242, 238, 229, 0.17) 0.6px, transparent 0.6px);
    background-size: 7px 7px;
}
@keyframes word-rise {
    from {
        opacity: 0;
        transform: translateY(0.38em) rotate(1deg);
    }
    to {
        opacity: 1;
        transform: translateY(0) rotate(0);
    }
}
@keyframes float-y {
    0%,
    100% {
        translate: 0 0;
    }
    50% {
        translate: 0 -8px;
    }
}
@media (max-width: 640px) {
    .hero-section {
        min-height: auto;
    }
    .hero-shade {
        background:
            linear-gradient(90deg, rgba(16, 20, 15, 0.8), rgba(20, 24, 18, 0.45)), linear-gradient(0deg, rgba(19, 22, 17, 0.48), transparent 52%);
    }
    .feature-carousel {
        padding-left: 1.25rem;
        padding-right: 1.25rem;
        margin-left: -1.25rem;
        margin-right: -1.25rem;
    }
    .node-right-top,
    .node-right-bottom {
        right: 0;
    }
    .node-left-top,
    .node-left-bottom {
        left: 0;
    }
}
@media (prefers-reduced-motion: reduce) {
    .SirkulaFood *,
    .SirkulaFood *::before,
    .SirkulaFood *::after {
        scroll-behavior: auto !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
    .reveal {
        opacity: 1;
        transform: none;
    }
}
</style>
