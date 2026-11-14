<script setup>
import SirkulasiModal from '@/Components/SirkulasiModal.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useStafOnline } from '@/composables/useStafOnline';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total_dokumen: 0,
            dokumen_tersedia: 0,
            dokumen_dipinjam: 0,
            dokumen_hilang: 0,
            total_user: 0,
            total_boks: 0,
            total_rak: 0,
            per_seksi: [],
            scope: '',
        }),
    },
    recentDocuments: {
        type: Array,
        default: () => [],
    },
    recentActivities: {
        type: Array,
        default: () => [],
    },
    trends: { type: Array, default: () => [] },
    notifications: { type: Object, default: () => ({}) },
    adminOverview: { type: Object, default: null },
    activeLoans: { type: Array, default: () => [] },
    trendGranularity: { type: String, default: 'month' },
    trendPeriod: { type: Number, default: 6 },
    dataHealth: {
        type: Object,
        default: () => ({
            tanpa_lokasi: 0,
            tanpa_file: 0,
            metadata_kosong: 0,
            tanpa_qr: 0,
        }),
    },
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');
// Label role: admin = "Admin", staff = nama seksi yang dikelolanya.
const roleLabel = computed(() => {
    if (isAdmin.value) return 'Admin';
    const u = page.props.userSeksi;
    return u?.nama_seksi ?? 'Staf';
});
const showSirkulasiModal = ref(false);
const sirkulasiArsip = ref(null);

/* ============ Panel staf online (admin-only, live via polling) ============ */
// AppLayout juga memanggil useStafOnline() — satu interval, tidak double.
const { active: staffOnline, count: staffOnlineCount } = useStafOnline();
const timeAgo = (value) => {
    if (!value) return '';
    const s = Math.floor((Date.now() - new Date(value).getTime()) / 1000);
    if (s < 60) return 'baru saja';
    const m = Math.floor(s / 60);
    if (m < 60) return `${m} mnt lalu`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h} jam lalu`;
    return `${Math.floor(h / 24)} hari lalu`;
};

const activeTab = ref('peminjaman');

const TREND_OPTIONS = {
    day:  { label: 'Hari',  values: [3, 7, 14] },
    month: { label: 'Bulan', values: [3, 6, 12] },
    year: { label: 'Tahun', values: [2, 3, 5] },
};
const trendUnit = ref(props.trendGranularity);
const trendCount = ref(props.trendPeriod);

const changeTrendUnit = (unit) => {
    trendUnit.value = unit;
    const opts = TREND_OPTIONS[unit];
    if (!opts.values.includes(trendCount.value)) {
        trendCount.value = opts.values[1];
    }
    navigateTrend();
};
const changeTrendPeriod = (count) => {
    trendCount.value = Number(count);
    navigateTrend();
};
const navigateTrend = () => {
    router.get(
        route('dashboard'),
        { trend_granularity: trendUnit.value, trend_period: trendCount.value },
        { preserveState: true, replace: true }
    );
};

/* ============ Skeleton loading ============ */
const SKELETON_MS = 1000;
const isLoading = ref(true);
let skeletonTimer = null;

const armSkeleton = () => {
    if (skeletonTimer) clearTimeout(skeletonTimer);
    skeletonTimer = setTimeout(() => { isLoading.value = false; skeletonTimer = null; }, SKELETON_MS);
};

onMounted(armSkeleton);

watch(
    () => page.url,
    () => {
        if (isLoading.value) return;
        isLoading.value = true;
        armSkeleton();
    }
);

onBeforeUnmount(() => {
    if (skeletonTimer) clearTimeout(skeletonTimer);
});

const overdueLoanCount = computed(() =>
    props.activeLoans.filter((loan) => loan.terlambat).length
);

/* ============ KPI cards ============ */
const kpiCards = computed(() => [
    {
        label: 'Total Dokumen',
        value: props.stats.total_dokumen,
        tone: 'border-brand-blue/20 bg-brand-blue/5 text-brand-blue',
        iconName: 'arsip',
    },
    {
        label: 'Tersedia',
        value: props.stats.dokumen_tersedia,
        tone: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        iconName: 'tersedia',
    },
    {
        label: 'Dipinjam',
        value: props.stats.dokumen_dipinjam,
        tone: 'border-amber-200 bg-amber-50 text-amber-700',
        iconName: 'dipinjam',
    },
    {
        label: 'Hilang',
        value: props.stats.dokumen_hilang,
        tone: 'border-rose-200 bg-rose-50 text-rose-700',
        iconName: 'hilang',
    },
]);

/* ============ Alerts panel (right col) ============ */
const alerts = computed(() => {
    const items = [];
    const terlambat = props.notifications.terlambat || 0;
    const tanpaLokasi = props.notifications.tanpa_lokasi || 0;
    const metadata = props.dataHealth.metadata_kosong || 0;
    const tanpaFile = props.dataHealth.tanpa_file || 0;
    const tanpaQr = props.dataHealth.tanpa_qr || 0;

    if (terlambat > 0) {
        items.push({
            key: 'terlambat',
            label: 'Peminjaman Terlambat',
            value: terlambat,
            hint: 'Dokumen sudah melewati batas pengembalian',
            tone: 'border-rose-200 bg-rose-50 text-rose-800',
            valueTone: 'text-rose-700',
        });
    }

    if (tanpaLokasi > 0) {
        items.push({
            key: 'tanpa_lokasi',
            label: 'Tanpa Lokasi',
            value: tanpaLokasi,
            hint: 'Dokumen belum ditempatkan di rak',
            tone: 'border-amber-200 bg-amber-50 text-amber-800',
            valueTone: 'text-amber-700',
        });
    }

    if (metadata > 0) {
        items.push({
            key: 'metadata_kosong',
            label: 'Metadata Kosong',
            value: metadata,
            hint: 'Nomor, tanggal, perihal, atau seksi belum terisi',
            tone: 'border-amber-200 bg-amber-50 text-amber-800',
            valueTone: 'text-amber-700',
        });
    }

    if (tanpaFile > 0) {
        items.push({
            key: 'tanpa_file',
            label: 'Tanpa File Digital',
            value: tanpaFile,
            hint: 'Berkas fisik belum dipindai atau diunggah',
            tone: 'border-slate-200 bg-slate-50 text-slate-700',
            valueTone: 'text-slate-700',
        });
    }

    if (tanpaQr > 0) {
        items.push({
            key: 'tanpa_qr',
            label: 'Tanpa Label QR',
            value: tanpaQr,
            hint: 'QR code belum dibuat atau terhubung',
            tone: 'border-slate-200 bg-slate-50 text-slate-700',
            valueTone: 'text-slate-700',
        });
    }

    return items;
});

/* ============ Capacity boks panel (right col) ============ */
const boksItems = computed(() => {
    if (!isAdmin.value || !props.adminOverview?.boks_perhatian) return [];
    return props.adminOverview.boks_perhatian.slice(0, 5);
});

const boksBarTone = (pct) => {
    if (pct >= 100) return 'bg-rose-500';
    return 'bg-amber-500';
};

const boksStatusLabel = (pct) => {
    if (pct >= 100) return 'Penuh';
    return 'Hampir penuh';
};

/* ============ Charts ============ */
const maxTrend = computed(() =>
    Math.max(1, ...props.trends.flatMap((item) => [item.pinjam || 0, item.kembali || 0]))
);

const SECT_COLORS = ['#2E6BE6', '#F59E0B', '#10B981', '#8B5CF6', '#F43F5E', '#0891B2'];

const perSeksi = computed(() => {
    const arr = Array.isArray(props.stats.per_seksi) ? props.stats.per_seksi : [];
    const total = arr.reduce((sum, s) => sum + Number(s.jumlah_arsip || 0), 0);
    const data = arr
        .map((s, i) => ({
            id: s.id,
            nama: s.nama_seksi,
            jumlah: Number(s.jumlah_arsip || 0),
            warna: SECT_COLORS[i % SECT_COLORS.length],
            user: s.jumlah_user || 0,
            pct: total > 0 ? Math.round((Number(s.jumlah_arsip || 0) / total) * 100) : 0,
        }))
        .sort((a, b) => b.jumlah - a.jumlah);
    return { list: arr, total, data };
});

/* ============ Admin shortcuts ============ */
const adminShortcuts = computed(() => [
    {
        key: 'backup',
        label: 'Backup & Restore',
        description: 'Ekspor atau pulihkan data arsip',
        href: route('backup.index'),
        tone: 'border-slate-100 bg-white text-slate-800',
    },
    {
        key: 'activity',
        label: 'Log Aktivitas',
        description: 'Pantau jejak aktivitas pengguna',
        href: route('activity.index'),
        tone: 'border-slate-100 bg-white text-slate-800',
    },
    {
        key: 'report-sections',
        label: 'Export Rekap Seksi',
        description: 'Excel ringkasan arsip per seksi',
        href: route('reports.sections'),
        external: true,
        tone: 'border-slate-100 bg-white text-slate-800',
    },
    {
        key: 'report-users',
        label: 'Export User',
        description: 'Excel daftar pengguna sistem',
        href: route('reports.users'),
        external: true,
        tone: 'border-slate-100 bg-white text-slate-800',
    },
    {
        key: 'report-racks',
        label: 'Export Rak',
        description: 'Excel kapasitas & isi rak',
        href: route('reports.racks'),
        external: true,
        tone: 'border-slate-100 bg-white text-slate-800',
    },
    {
        key: 'report-activity',
        label: 'Export Aktivitas',
        description: 'Excel log aktivitas sistem',
        href: route('reports.activity'),
        external: true,
        tone: 'border-slate-100 bg-white text-slate-800',
    },
]);

/* ============ Helpers ============ */
const formatDate = (value) =>
    value
        ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value))
        : '—';

const loanDeadlineLabel = (loan) => {
    if (loan.sisa_hari === null || loan.sisa_hari === undefined) {
        return 'Tanpa batas kembali';
    }
    if (loan.terlambat || loan.sisa_hari < 0) {
        const days = Math.abs(loan.sisa_hari);
        return days === 0 ? 'Jatuh tempo hari ini' : `Terlambat ${days} hari`;
    }
    if (loan.sisa_hari === 0) {
        return 'Jatuh tempo hari ini';
    }
    return `Sisa ${loan.sisa_hari} hari`;
};

const statusBadge = (status) => {
    const map = {
        tersedia: {
            label: 'Tersimpan',
            class: 'bg-emerald-50 text-emerald-800 ring-emerald-300',
            dot: 'bg-emerald-500',
        },
        dipinjam: {
            label: 'Dipinjam',
            class: 'bg-rose-50 text-rose-800 ring-rose-300',
            dot: 'bg-rose-500',
        },
        hilang: {
            label: 'Hilang',
            class: 'bg-amber-50 text-amber-900 ring-amber-300',
            dot: 'bg-amber-500',
        },
    };
    return map[status] || map.tersedia;
};

const openKembalikan = (loan) => {
    sirkulasiArsip.value = loan;
    showSirkulasiModal.value = true;
};

const closeSirkulasi = () => {
    showSirkulasiModal.value = false;
    sirkulasiArsip.value = null;
};

</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <template #header>
            <div v-if="!isLoading" class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-brand-navy sm:text-2xl">Dashboard</h1>
                    <p class="mt-1 text-sm text-slate-500">Command center operasional pengarsipan.</p>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <Link
                        :href="route('arsip.index')"
                        class="inline-flex shrink rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        Buka Arsip
                    </Link>
                    <span
                        class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider text-white"
                        :class="isAdmin ? 'bg-brand-navy' : 'bg-brand-blue'"
                    >
                        {{ roleLabel }}
                    </span>
                </div>
            </div>
            <div v-else class="motion-safe:animate-pulse space-y-3">
                <div class="flex items-center justify-between gap-4">
                    <div class="h-8 w-48 rounded-2xl bg-slate-200" />
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-32 rounded-2xl bg-slate-200" />
                        <div class="h-5 w-16 rounded-full bg-slate-200" />
                    </div>
                </div>
            </div>
        </template>

        <!-- =============== SKELETON LOADING =============== -->
        <div v-if="isLoading" role="status" aria-live="polite" class="grid motion-safe:animate-pulse grid-cols-1 gap-5 lg:grid-cols-12">
            <span class="sr-only">Memuat dashboard…</span>
            <!-- Kolom kiri skeleton -->
            <div class="space-y-5 lg:col-span-8">
                <!-- Baris 1: KPI 4 kolom -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="i in 4" :key="'kpi-' + i" class="h-24 rounded-2xl bg-slate-200" />
                </div>
                <!-- Baris 2: Grafik batang + list per-seksi -->
                <div class="space-y-5">
                    <div class="rounded-2xl bg-slate-200 p-5">
                        <div class="h-4 w-40 rounded bg-slate-100" />
                        <div class="mt-4 flex h-48 items-end justify-between gap-2">
                            <div v-for="i in 8" :key="'bat-' + i" class="w-3 rounded-t bg-slate-100" :style="{ height: (30 + (i % 5) * 12) + '%' }" />
                        </div>
                    </div>
                    <div class="rounded-2xl bg-slate-200 p-5">
                        <div class="h-4 w-40 rounded bg-slate-100" />
                        <div class="mt-4 space-y-3">
                            <div v-for="i in 5" :key="'list-' + i" class="h-3 rounded bg-slate-100" />
                        </div>
                    </div>
                </div>
                <!-- Baris 3: Tabel operasional -->
                <div class="rounded-2xl bg-slate-200 p-5">
                    <div class="flex items-center justify-between">
                        <div class="h-4 w-32 rounded bg-slate-100" />
                        <div class="h-4 w-24 rounded bg-slate-100" />
                    </div>
                    <div class="mt-4 space-y-3">
                        <div v-for="i in 4" :key="'row-' + i" class="h-3 rounded bg-slate-100" />
                    </div>
                </div>
            </div>
            <!-- Kolom kanan skeleton -->
            <div class="space-y-5 lg:col-span-4">
                <!-- Perlu Diperhatikan -->
                <div class="rounded-2xl bg-slate-200 p-4">
                    <div class="h-4 w-32 rounded bg-slate-100" />
                    <div class="mt-4 space-y-3">
                        <div v-for="i in 3" :key="'notif-' + i" class="h-3 rounded bg-slate-100" />
                    </div>
                </div>
                <!-- Kapasitas Boks -->
                <div class="rounded-2xl bg-slate-200 p-4">
                    <div class="h-4 w-32 rounded bg-slate-100" />
                    <div class="mt-4 space-y-3">
                        <div v-for="i in 3" :key="'boks-' + i" class="h-2 rounded bg-slate-100" />
                    </div>
                </div>
                <!-- Aktivitas Terbaru -->
                <div class="rounded-2xl bg-slate-200 p-4">
                    <div class="h-4 w-32 rounded bg-slate-100" />
                    <div class="mt-4 space-y-3">
                        <div v-for="i in 4" :key="'act-' + i" class="h-3 rounded bg-slate-100" />
                    </div>
                </div>
            </div>
            <!-- Bawah: Laporan & Backup + Statistik Sistem -->
            <div class="space-y-5 lg:col-span-12">
                <div class="rounded-2xl bg-slate-200 p-5">
                    <div class="h-4 w-40 rounded bg-slate-100" />
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div v-for="i in 3" :key="'backup-' + i" class="h-20 rounded-2xl bg-slate-100" />
                    </div>
                </div>
                <div class="rounded-2xl bg-slate-200 p-5">
                    <div class="h-4 w-40 rounded bg-slate-100" />
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <div v-for="i in 5" :key="'stat-' + i" class="h-16 rounded-2xl bg-slate-100" />
                    </div>
                </div>
            </div>
        </div>

        <!-- =============== DASHBOARD ORIGINAL =============== -->
        <div v-else class="grid grid-cols-1 gap-5 lg:grid-cols-12">
            <!-- =============== LEFT COLUMN (lg:col-span-8) =============== -->
            <div class="space-y-5 lg:col-span-8">
                <!-- Baris 1: KPI 4 kolom -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="(card, index) in kpiCards"
                        :key="card.label"
                        v-reveal
                        :style="{ transitionDelay: `${index * 100}ms` }"
                        class="rounded-2xl border bg-white p-3 shadow-sm sm:p-4 md:p-5"
                        :class="card.tone"
                    >
                        <div class="flex items-center gap-2">
                            <AppIcon
                                :name="card.iconName"
                                :size="18"
                                class="h-5 w-5 shrink-0 opacity-70"
                                decorative
                            />
                            <p class="text-xs font-semibold uppercase tracking-wider opacity-80">
                                {{ card.label }}
                            </p>
                        </div>
                        <p class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl">{{ card.value }}</p>
                    </div>
                </div>

                <!-- Baris 2: Visualisasi (tren + volume per seksi) -->
                <div class="grid grid-cols-1 gap-5">
                    <!-- Tren Sirkulasi -->
                    <div v-reveal class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2 sm:flex-nowrap">
                            <div class="whitespace-nowrap">
                                <h2 class="text-sm font-bold text-brand-navy">Tren Sirkulasi</h2>
                                <p class="text-xs text-slate-500">Peminjaman dan pengembalian</p>
                            </div>
                            <div class="flex items-center gap-3 sm:shrink-0">
                                <div class="flex items-center gap-3 text-xs text-slate-500">
                                    <span class="flex items-center gap-1.5 whitespace-nowrap">
                                        <span class="inline-block h-2 w-2 rounded-full bg-brand-blue"></span>
                                        Dipinjam
                                    </span>
                                    <span class="flex items-center gap-1.5 whitespace-nowrap">
                                        <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                                        Dikembalikan
                                    </span>
                                </div>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    <select
                                        :value="trendUnit"
                                        class="h-10 min-w-[142px] shrink-0 rounded-lg border border-slate-300 bg-white pl-3 pr-9 text-[13px] font-medium text-slate-700 shadow-sm focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/30 focus:outline-none"
                                        @change="changeTrendUnit($event.target.value)"
                                    >
                                        <option v-for="opt in Object.entries(TREND_OPTIONS)" :key="opt[0]" :value="opt[0]">
                                            Per {{ opt[1].label }}
                                        </option>
                                    </select>
                                    <select
                                        :value="trendCount"
                                        class="h-10 min-w-[104px] shrink-0 rounded-lg border border-slate-300 bg-white pl-3 pr-9 text-[13px] font-medium text-slate-700 shadow-sm focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/30 focus:outline-none"
                                        @change="changeTrendPeriod($event.target.value)"
                                    >
                                        <option v-for="val in TREND_OPTIONS[trendUnit].values" :key="val" :value="val">
                                            {{ val }} {{ TREND_OPTIONS[trendUnit].label.toLowerCase() }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="props.trends.length"
                            class="mt-4 grid items-end gap-2 sm:gap-3"
                            :style="{ gridTemplateColumns: `repeat(${props.trends.length}, minmax(0, 1fr))` }"
                        >
                            <div
                                v-for="item in props.trends"
                                :key="item.label"
                                class="flex flex-col items-center gap-2"
                            >
                                <div class="flex h-32 items-end gap-1">
                                    <div
                                        class="w-2.5 rounded-t-md bg-brand-blue sm:w-3"
                                        :style="{ height: `${item.pinjam ? Math.max(4, ((item.pinjam) / maxTrend) * 100) : 0}%` }"
                                        :title="`Dipinjam: ${item.pinjam || 0}`"
                                    />
                                    <div
                                        class="w-2.5 rounded-t-md bg-emerald-500 sm:w-3"
                                        :style="{ height: `${item.kembali ? Math.max(4, ((item.kembali) / maxTrend) * 100) : 0}%` }"
                                        :title="`Dikembalikan: ${item.kembali || 0}`"
                                    />
                                </div>
                                <span class="text-xs text-slate-500">{{ item.label }}</span>
                            </div>
                        </div>
                        <div v-else class="flex h-40 items-center justify-center text-sm text-slate-400">
                            Belum ada data tren
                        </div>
                    </div>

                    <!-- Volume per Seksi -->
                    <div v-reveal :style="{ transitionDelay: '100ms' }" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                        <div class="flex items-baseline justify-between gap-2">
                            <div class="min-w-0">
                                <h2 class="text-sm font-bold text-brand-navy">Volume per Seksi</h2>
                                <p class="mt-1 text-xs text-slate-500">
                                    Sebaran arsip antar {{ perSeksi.list.length }} seksi
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-2xl font-bold text-brand-navy">{{ perSeksi.total }}</p>
                                <p class="text-xs text-slate-500">total arsip</p>
                            </div>
                        </div>

                        <ul v-if="perSeksi.data.length" class="mt-4 space-y-2.5">
                            <li
                                v-for="seg in perSeksi.data"
                                :key="seg.id"
                                class="flex items-center justify-between gap-3 text-sm"
                            >
                                <div class="flex min-w-0 flex-1 items-center gap-2.5">
                                    <span
                                        class="h-2.5 w-2.5 shrink-0 rounded-full"
                                        :style="{ background: seg.warna }"
                                    />
                                    <span class="min-w-0 flex-1 font-medium text-slate-700 line-clamp-2">{{ seg.nama }}</span>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="font-bold text-slate-900">{{ seg.jumlah }}</span>
                                    <span class="ml-1 text-xs text-slate-400">({{ seg.pct }}%)</span>
                                </div>
                            </li>
                        </ul>
                        <div v-else class="mt-4 flex h-32 items-center justify-center text-sm text-slate-400">
                            Belum ada seksi tercatat
                        </div>
                    </div>
                </div>

                <!-- Baris 3: Tabel operasional via tab -->
                <div v-reveal :style="{ transitionDelay: '200ms' }" class="rounded-2xl border border-slate-100 bg-white shadow-sm">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5"
                    >
                        <div class="flex gap-1">
                            <button
                                type="button"
                                class="rounded-lg px-3.5 py-1.5 text-sm font-semibold transition"
                                :class="
                                    activeTab === 'peminjaman'
                                        ? 'bg-brand-blue/10 text-brand-blue'
                                        : 'text-slate-500 hover:bg-slate-50'
                                "
                                @click="activeTab = 'peminjaman'"
                            >
                                Peminjaman Aktif
                                <span v-if="props.activeLoans.length" class="ml-1.5 text-xs text-slate-400">
                                    ({{ props.activeLoans.length }})
                                </span>
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-3.5 py-1.5 text-sm font-semibold transition"
                                :class="
                                    activeTab === 'arsip'
                                        ? 'bg-brand-blue/10 text-brand-blue'
                                        : 'text-slate-500 hover:bg-slate-50'
                                "
                                @click="activeTab = 'arsip'"
                            >
                                Arsip Terbaru
                                <span v-if="props.recentDocuments.length" class="ml-1.5 text-xs text-slate-400">
                                    ({{ props.recentDocuments.length }})
                                </span>
                            </button>
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <span
                                v-if="activeTab === 'peminjaman' && overdueLoanCount"
                                class="rounded-full bg-rose-100 px-2.5 py-1 font-semibold text-rose-700"
                            >
                                {{ overdueLoanCount }} terlambat
                            </span>
                            <Link
                                :href="
                                    activeTab === 'peminjaman'
                                        ? route('arsip.index', { status: 'dipinjam', sort: 'batas' })
                                        : route('arsip.index')
                                "
                                class="font-semibold text-brand-blue hover:underline"
                            >
                                Lihat semua
                            </Link>
                        </div>
                    </div>

                    <!-- Tab 1: Peminjaman aktif -->
                    <div v-show="activeTab === 'peminjaman'" class="px-4 py-2 sm:px-5">
                        <div v-if="props.activeLoans.length" class="divide-y divide-slate-100">
                            <div
                                v-for="loan in props.activeLoans"
                                :key="loan.id"
                                class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Link
                                            :href="route('arsip.show', loan.id)"
                                            class="min-w-0 flex-1 truncate font-semibold text-slate-800 hover:text-brand-blue"
                                        >
                                            {{ loan.kode_arsip }} · {{ loan.perihal }}
                                        </Link>
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                            :class="
                                                loan.terlambat
                                                    ? 'bg-rose-100 text-rose-700'
                                                    : 'bg-amber-50 text-amber-700'
                                            "
                                        >
                                            {{ loanDeadlineLabel(loan) }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        {{ loan.peminjam || 'Peminjam tidak tercatat' }} ·
                                        {{ loan.seksi?.nama_seksi || 'Tanpa seksi' }} ·
                                        Batas {{ formatDate(loan.batas_pengembalian) }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 sm:ml-auto"
                                    @click="openKembalikan(loan)"
                                >
                                    Kembalikan
                                </button>
                            </div>
                        </div>
                        <div v-else class="py-10 text-center">
                            <p class="text-sm font-medium text-slate-600">
                                Tidak ada peminjaman aktif saat ini.
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                Arsip yang dipinjam akan muncul di sini.
                            </p>
                        </div>
                    </div>

                    <!-- Tab 2: Arsip terbaru -->
                    <div v-show="activeTab === 'arsip'" class="px-4 py-2 sm:px-5">
                        <div v-if="props.recentDocuments.length" class="divide-y divide-slate-100">
                            <div
                                v-for="doc in props.recentDocuments"
                                :key="doc.id"
                                class="py-3"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <Link
                                        :href="route('arsip.show', doc.id)"
                                        class="min-w-0 truncate font-semibold text-slate-800 hover:text-brand-blue"
                                    >
                                        {{ doc.kode_arsip }} · {{ doc.perihal }}
                                    </Link>
                                    <span
                                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-bold ring-1 ring-inset"
                                        :class="statusBadge(doc.status_arsip).class"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="statusBadge(doc.status_arsip).dot"
                                        />
                                        {{ statusBadge(doc.status_arsip).label }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ doc.seksi?.nama_seksi || 'Tanpa seksi' }} ·
                                    {{ doc.rak?.kode_rak || 'Belum ditempatkan' }}
                                    <span class="text-slate-400">· {{ formatDate(doc.created_at) }}</span>
                                </p>
                            </div>
                        </div>
                        <div v-else class="py-10 text-center">
                            <p class="text-sm font-medium text-slate-600">Belum ada arsip tercatat.</p>
                            <p class="mt-1 text-xs text-slate-400">
                                Dokumen yang baru diunggah akan muncul di sini.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- =============== RIGHT COLUMN (lg:col-span-4) =============== -->
            <div class="space-y-5 lg:col-span-4">
                <!-- Peringatan terpadu -->
                <div v-reveal class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="text-sm font-bold text-brand-navy">Perlu Diperhatikan</h2>
                            <p class="text-xs text-slate-500">Isu data &amp; operasional</p>
                        </div>
                        <span
                            v-if="alerts.length"
                            class="shrink-0 rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-700"
                        >
                            {{ alerts.length }}
                        </span>
                    </div>
                    <div v-if="alerts.length" class="mt-3 space-y-2">
                        <div
                            v-for="alert in alerts"
                            :key="alert.key"
                            class="flex flex-col gap-1.5 rounded-xl border p-3 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                            :class="alert.tone"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold">{{ alert.label }}</p>
                                <p class="mt-0.5 line-clamp-2 text-xs opacity-80">{{ alert.hint }}</p>
                            </div>
                            <span class="shrink-0 text-2xl font-bold sm:min-w-[3ch] sm:text-right" :class="alert.valueTone">
                                {{ alert.value }}
                            </span>
                        </div>
                    </div>
                    <div v-else class="mt-3 rounded-xl bg-emerald-50 p-4 text-center">
                        <p class="text-sm font-semibold text-emerald-800">Semua terkendali.</p>
                        <p class="mt-0.5 text-xs text-emerald-600">
                            Tidak ada isu data atau peminjaman terlambat.
                        </p>
                    </div>
                </div>

                <!-- Kapasitas boks (progress bar) -->
                <div v-reveal :style="{ transitionDelay: '100ms' }" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="text-sm font-bold text-brand-navy">Kapasitas Boks</h2>
                            <p class="mt-1 text-xs text-slate-500">Boks terisi di atas 80%</p>
                        </div>
                        <Link
                            v-if="isAdmin"
                            :href="route('boks.index')"
                            class="shrink-0 text-xs font-semibold text-brand-blue hover:underline"
                        >
                            Kelola
                        </Link>
                    </div>
                    <div v-if="boksItems.length" class="mt-3 space-y-3">
                        <div v-for="boks in boksItems" :key="boks.id">
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <span class="min-w-0 flex-1 truncate font-semibold text-slate-700">
                                    {{ boks.kode_boks }}
                                    <template v-if="boks.rak">· {{ boks.rak.kode_rak }}</template>
                                </span>
                                <span
                                    class="shrink-0 font-bold"
                                    :class="boks.pct >= 100 ? 'text-rose-600' : 'text-amber-600'"
                                >
                                    {{ boks.pct }}%
                                </span>
                            </div>
                            <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="boksBarTone(boks.pct)"
                                    :style="{ width: Math.min(boks.pct, 100) + '%' }"
                                />
                            </div>
                            <div class="mt-1 flex items-center justify-between text-[10px] text-slate-500">
                                <span>{{ boks.dokumen_arsip_count }} / {{ boks.kapasitas }} dokumen</span>
                                <span>{{ boksStatusLabel(boks.pct) }}</span>
                            </div>
                        </div>
                    </div>
                    <div
                        v-else
                        class="mt-3 rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-500"
                    >
                        Tidak ada boks yang terisi di atas 80%.
                    </div>
                </div>

                <!-- Aktivitas terbaru -->
                <div v-reveal :style="{ transitionDelay: '200ms' }" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="text-sm font-bold text-brand-navy">Aktivitas Terbaru</h2>
                            <p class="mt-1 text-xs text-slate-500">Riwayat pergerakan arsip terbaru.</p>
                        </div>
                        <Link
                            v-if="isAdmin"
                            :href="route('activity.index')"
                            class="shrink-0 text-xs font-semibold text-brand-blue hover:underline"
                        >
                            Buka log
                        </Link>
                    </div>
                    <div v-if="props.recentActivities.length" class="mt-3 divide-y divide-slate-100">
                        <div v-for="activity in props.recentActivities" :key="activity.id" class="py-2.5">
                            <p class="line-clamp-2 text-sm font-medium text-slate-800">
                                {{ activity.description }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ activity.user?.name || 'Sistem' }} · {{ formatDate(activity.created_at) }}
                            </p>
                        </div>
                    </div>
                    <div v-else class="mt-3 rounded-xl bg-slate-50 p-4 text-center">
                        <p class="text-xs text-slate-500">Belum ada riwayat aktivitas.</p>
                    </div>
                </div>

                <!-- Staf aktif (admin-only, live) -->
                <div v-if="isAdmin" v-reveal :style="{ transitionDelay: '300ms' }" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="text-sm font-bold text-brand-navy">Staf Aktif</h2>
                            <p class="mt-1 text-xs text-slate-500">Sesi staf yang aktif 30 menit terakhir.</p>
                        </div>
                        <span
                            v-if="staffOnlineCount"
                            class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700"
                        >
                            {{ staffOnlineCount }}
                        </span>
                    </div>
                    <div v-if="staffOnline.length" class="mt-3 divide-y divide-slate-100">
                        <div v-for="staff in staffOnline" :key="staff.id" class="flex items-center gap-2.5 py-2.5">
                            <span class="relative flex h-2 w-2 shrink-0">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75 motion-reduce:hidden" />
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-800">{{ staff.name }}</p>
                                <p class="text-xs text-slate-500">{{ staff.seksi || 'Seksi tidak ditentukan' }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="mt-3 rounded-xl bg-emerald-50 p-4 text-center">
                        <p class="text-sm font-semibold text-emerald-800">Tidak ada staf aktif.</p>
                        <p class="mt-0.5 text-xs text-emerald-600">Staf yang login akan muncul di sini.</p>
                    </div>
                </div>
            </div>

            <!-- =============== BOTTOM AREA (lg:col-span-12) =============== -->
            <div class="lg:col-span-12">
                <div
                    v-if="isAdmin && props.adminOverview"
                    v-reveal
                    class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold text-brand-navy">Laporan &amp; Backup</h2>
                            <p class="text-xs text-slate-500">
                                Pintasan admin untuk unduh laporan dan cadangan data.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-xs sm:gap-4">
                            <span class="rounded-full bg-violet-50 px-2.5 py-1 font-bold text-violet-700">
                                User: {{ props.stats.total_user }}
                            </span>
                            <span class="rounded-full bg-sky-50 px-2.5 py-1 font-bold text-sky-700">
                                Boks: {{ props.stats.total_boks }}
                            </span>
                            <span class="rounded-full bg-rose-50 px-2.5 py-1 font-bold text-rose-700">
                                Rak: {{ props.stats.total_rak }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <template v-for="item in adminShortcuts" :key="item.key">
                            <a
                                v-if="item.external"
                                :href="item.href"
                                target="_blank"
                                rel="noopener"
                                class="flex items-center justify-between rounded-xl border border-slate-100 bg-white p-4 shadow-sm transition hover:shadow-md"
                                :class="item.tone"
                            >
                                <div class="min-w-0">
                                    <p class="text-sm font-bold">{{ item.label }}</p>
                                    <p class="mt-0.5 text-xs opacity-80">{{ item.description }}</p>
                                </div>
                                <AppIcon name="arrow-right" :size="16" :stroke-width="2" class="h-4 w-4 shrink-0 opacity-50" decorative />
                            </a>
                            <Link
                                v-else
                                :href="item.href"
                                class="flex items-center justify-between rounded-xl border border-slate-100 bg-white p-4 shadow-sm transition hover:shadow-md"
                                :class="item.tone"
                            >
                                <div class="min-w-0">
                                    <p class="text-sm font-bold">{{ item.label }}</p>
                                    <p class="mt-0.5 text-xs opacity-80">{{ item.description }}</p>
                                </div>
                                <AppIcon name="arrow-right" :size="16" :stroke-width="2" class="h-4 w-4 shrink-0 opacity-50" decorative />
                            </Link>
                        </template>
                    </div>
                </div>

                <div
                    v-if="isAdmin && props.adminOverview"
                    v-reveal
                    :style="{ transitionDelay: '100ms' }"
                    class="mt-5 rounded-2xl border border-slate-100 bg-slate-50 p-5"
                >
                    <div>
                        <h2 class="text-sm font-bold text-brand-navy">Statistik Sistem</h2>
                        <p class="text-xs text-slate-500">Metrik admin yang diperbarui otomatis.</p>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <div class="rounded-xl border border-slate-100 bg-white p-3.5 shadow-sm">
                            <p class="text-xs text-slate-500">User aktif</p>
                            <p class="mt-1 text-xl font-bold text-emerald-700">
                                {{ props.adminOverview.user_aktif }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-white p-3.5 shadow-sm">
                            <p class="text-xs text-slate-500">User nonaktif</p>
                            <p class="mt-1 text-xl font-bold text-rose-700">
                                {{ props.adminOverview.user_nonaktif }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-white p-3.5 shadow-sm">
                            <p class="text-xs text-slate-500">Total seksi</p>
                            <p class="mt-1 text-xl font-bold text-slate-900">
                                {{ props.adminOverview.total_seksi }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-white p-3.5 shadow-sm">
                            <p class="text-xs text-slate-500">Tanpa lokasi</p>
                            <p class="mt-1 text-xl font-bold text-amber-700">
                                {{ props.adminOverview.dokumen_tanpa_lokasi }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-white p-3.5 shadow-sm">
                            <p class="text-xs text-slate-500">Aktivitas hari ini</p>
                            <p class="mt-1 text-xl font-bold text-brand-blue">
                                {{ props.adminOverview.aktivitas_hari_ini }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <SirkulasiModal
        :show="showSirkulasiModal"
        :arsip="sirkulasiArsip"
        mode="kembalikan"
        @close="closeSirkulasi"
    />
</template>
