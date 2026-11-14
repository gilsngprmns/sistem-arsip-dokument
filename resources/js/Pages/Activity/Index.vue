<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useActivityLogLive } from '@/composables/useActivityLogLive';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    activities: {
        type: Object,
        default: () => ({ data: [] }),
    },
    filters: {
        type: Object,
        default: () => ({ tanggal_dari: '', tanggal_sampai: '', pengguna: '', aksi: '', q: '' }),
    },
    users: {
        type: Array,
        default: () => [],
    },
    actions: {
        type: Array,
        default: () => [],
    },
});

// --- Format waktu: "21 Okt 2026, 18.52.10" (zona Asia/Jakarta) ---
const formatDateTime = (value) =>
    value
        ? new Intl.DateTimeFormat('id-ID', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
              second: '2-digit',
              hour12: false,
              timeZone: 'Asia/Jakarta',
          }).format(new Date(value))
        : '—';

const actionMap = {
    'users.store': 'Tambah pengguna',
    'users.update': 'Perbarui pengguna',
    'users.destroy': 'Nonaktifkan pengguna',
    'users.activate': 'Aktifkan pengguna',
    'users.password': 'Reset kata sandi',
    'users.force-delete': 'Hapus permanen pengguna',
    'arsip.store': 'Tambah dokumen arsip',
    'arsip.update': 'Perbarui dokumen arsip',
    'arsip.destroy': 'Hapus dokumen arsip',
    'arsip.force-delete': 'Hapus permanen dokumen arsip',
    'arsip.pinjam': 'Catat peminjaman',
    'arsip.kembalikan': 'Catat pengembalian',
    'arsip.settersedia': 'Set arsip tersedia',
    'arsip.tandahilang': 'Tandai arsip hilang',
    'arsip.scan': 'Pindai QR arsip',
    'arsip.cadangkan': 'Cadangkan basis data arsip',
    'backup.export': 'Unduh cadangan',
    'backup.import': 'Impor cadangan',
    'backup.run': 'Buat cadangan (bundel)',
    'backup.files': 'Unduh cadangan tersimpan',
    'profile.update': 'Perbarui profil',
    'profile.destroy': 'Hapus akun',
    'arsip.export': 'Ekspor arsip',
    'backup.index': 'Buka halaman backup',
    'users.index': 'Buka daftar pengguna',
    'activity.index': 'Buka log aktivitas',
    'reports.activity': 'Ekspor laporan aktivitas',
    'reports.users': 'Ekspor laporan pengguna',
    'reports.racks': 'Ekspor laporan rak',
    'reports.sections': 'Ekspor laporan seksi',
    'rak.store': 'Tambah rak',
    'rak.update': 'Perbarui rak',
    'rak.destroy': 'Nonaktifkan rak',
    'boks.store': 'Tambah boks',
    'boks.update': 'Perbarui boks',
    'boks.destroy': 'Nonaktifkan boks',
    'beranda.slides.store': 'Tambah slide beranda',
    'beranda.slides.destroy': 'Hapus slide beranda',
    'manfaat.store': 'Tambah kartu manfaat',
    'manfaat.update': 'Perbarui kartu manfaat',
    'manfaat.destroy': 'Hapus kartu manfaat',
    'alur_kerja.store': 'Tambah langkah prosedur',
    'alur_kerja.update': 'Perbarui langkah prosedur',
    'alur_kerja.destroy': 'Hapus langkah prosedur',
    'faq.store': 'Tambah FAQ bantuan',
    'faq.update': 'Perbarui FAQ bantuan',
    'faq.destroy': 'Hapus FAQ bantuan',
    'auth.login': 'Login',
    'auth.logout': 'Logout',
    'password.otp.send': 'Aktivitas pemulihan kata sandi',
    'password.otp.verify': 'Verifikasi pemulihan kata sandi',
    'password.otp.finish': 'Selesai ganti kata sandi',
    // GET — halaman utama
    'dashboard': 'Dashboard',
    'arsip.index': 'Daftar arsip',
    'arsip.show': 'Lihat arsip',
    'arsip.create': 'Rekam berkas baru',
    'arsip.edit': 'Edit arsip',
    'rak.index': 'Daftar rak',
    'boks.index': 'Daftar boks',
    'scan.index': 'Pindai QR',
    'profile.edit': 'Profil',
    'halaman-publik.beranda': 'Foto Carousel',
    'halaman-publik.manfaat': 'Kartu Manfaat',
    'halaman-publik.alur-kerja': 'Langkah Prosedur',
    'halaman-publik.faq': 'FAQ Bantuan',
};

const actionLabel = (activity) => {
    if (actionMap[activity.route_name]) return actionMap[activity.route_name];
    const desc = activity.description;
    const m = desc?.match(/^(?:GET|POST|PUT|PATCH|DELETE)\s+(\S+)/);
    if (m) {
        const routeGuess = m[1].replace(/\d+/g, '{id}');
        const byPath = {
            'users/{id}/activate': 'Aktifkan pengguna',
            'users/{id}/password': 'Reset kata sandi',
        };
        if (byPath[routeGuess]) return byPath[routeGuess];
    }
    return desc || 'Aksi tidak dikenal';
};

// Label opsion dropdown aksi (nilainya masih raw action/route name)
const actionOptionLabel = (value) => actionMap[value] ?? value;

const isGagal = (activity) => activity.metadata?.hasil === 'gagal' || activity.description?.startsWith('Gagal');

// --- Badge kolom Aksi (kelas Tailwind yang sudah dipakai ArsipTable) ---
const actionBadge = (activity) => {
    if (isGagal(activity)) {
        return 'bg-red-50 text-red-800 ring-red-300'; // gagal
    }
    const key = activity.route_name || activity.action || '';
    if (key === 'auth.login' || key === 'auth.logout') {
        return 'bg-sky-50 text-sky-800 ring-sky-300';
    }
    if (key === 'password.otp.finish') {
        return 'bg-emerald-50 text-emerald-800 ring-emerald-300';
    }
    if (key === 'password.otp.send' || key === 'password.otp.verify') {
        return 'bg-violet-50 text-violet-800 ring-violet-300';
    }
    const suffix = key.split('.').pop();
    if (suffix === 'store' || key === 'arsip.kembalikan' || key === 'arsip.settersedia' || key === 'users.activate') {
        return 'bg-emerald-50 text-emerald-800 ring-emerald-300'; // tambah / pengembalian
    }
    if (suffix === 'update' || key === 'arsip.pinjam' || key === 'arsip.scan' || key === 'arsip.tandahilang' || key === 'users.password') {
        return 'bg-amber-50 text-amber-900 ring-amber-300'; // ubah / peminjaman
    }
    if (suffix === 'destroy' || suffix === 'force-delete') {
        return 'bg-rose-50 text-rose-800 ring-rose-300'; // hapus / nonaktif
    }
    return 'bg-slate-100 text-slate-700 ring-slate-200'; // sistem / backup / lainnya
};

// --- Target di kolom Keterangan ---
const targetPrefix = { dokumen: 'Dokumen', user: 'User', rak: 'Rak', boks: 'Boks', halaman_publik: 'Halaman Publik' };

// Baris hapus/permanen: objek kemungkinan sudah tidak ada → jangan di-link
const targetGone = (activity) =>
    activity.route_name === 'arsip.destroy' ||
    activity.route_name === 'arsip.force-delete' ||
    activity.route_name === 'users.force-delete';

const targetLine = (activity) => {
    if (!activity.target_type) return null;
    return {
        prefix: targetPrefix[activity.target_type] ?? activity.target_type,
        label: activity.target_label || '—',
        // Dokumen → link halaman detail (kecuali baris hapus); user/rak/lokasi tidak
        // punya halaman detail sendiri → teks biasa.
        href:
            activity.target_type === 'dokumen' && activity.target_id && !targetGone(activity)
                ? route('arsip.show', activity.target_id)
                : null,
    };
};

const isRawDescription = (activity) =>
    !actionMap[activity.route_name] && /^(?:GET|POST|PUT|PATCH|DELETE)\s/.test(activity.description || '');

const descriptionLabel = (activity) => (isRawDescription(activity) ? actionLabel(activity) : activity.description);

const FIELD_LABELS = {
    is_active: 'Status akun',
    name: 'Nama',
    email: 'Email',
    role: 'Peran',
    nomor_surat: 'Nomor surat',
    judul: 'Judul',
    status_arsip: 'Status arsip',
    batas_pengembalian: 'Batas pengembalian',
};

const formatValue = (val, key) => {
    if (val === null || val === undefined) return '—';
    // DB returns is_active as 0/1 (int or string), not JS boolean
    if (key === 'is_active') {
        const active = val === true || val === 1 || val === '1';
        return active ? 'Aktif' : 'Nonaktif';
    }
    // Snapshot datetime ISO (mis. batas pengembalian) → format yang sama dengan kolom Waktu
    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(val)) {
        const parsed = new Date(val);
        if (!Number.isNaN(parsed.getTime())) return formatDateTime(val);
    }
    if (typeof val === 'boolean') return val ? 'Ya' : 'Tidak';
    return String(val);
};

const changedDetails = (activity) => {
    const oldValues = activity.old_values || {};
    const newValues = activity.new_values || {};

    return Object.keys(newValues)
        .filter((key) => JSON.stringify(oldValues[key]) !== JSON.stringify(newValues[key]))
        .slice(0, 4)
        .map((key) => ({
            label: FIELD_LABELS[key] ?? key.replaceAll('_', ' '),
            old: formatValue(oldValues[key], key),
            new: formatValue(newValues[key], key),
        }));
};

// --- Filter (state lokal di-seed dari query string server) ---
const localFilters = ref({ ...props.filters });
watch(
    () => props.filters,
    (f) => {
        localFilters.value = { ...f };
    },
);

const hasActiveFilters = computed(() =>
    ['tanggal_dari', 'tanggal_sampai', 'pengguna', 'aksi', 'q'].some((k) => localFilters.value[k]),
);

const applyFilters = () => {
    router.get(
        route('activity.index'),
        {
            tanggal_dari: localFilters.value.tanggal_dari || undefined,
            tanggal_sampai: localFilters.value.tanggal_sampai || undefined,
            pengguna: localFilters.value.pengguna || undefined,
            aksi: localFilters.value.aksi || undefined,
            q: localFilters.value.q || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['activities', 'filters', 'users', 'actions'],
        },
    );
};

let searchTimer = null;
const onSearchInput = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
};

const resetFilters = () => {
    localFilters.value = { tanggal_dari: '', tanggal_sampai: '', pengguna: '', aksi: '', q: '' };
    applyFilters();
};

// ── Live updates: prepending entry baru tanpa refresh ─────────────────
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

// initialMaxId: id terbesar saat halaman pertama kali dimuat.
// Entry live hanya prepend bila id > initialMaxId (jaga duplikat setelah Inertia refresh).
const initialMaxId = ref(0);
const liveActivities = ref([]);
const liveCount = ref(0);

const { newEntries, lastPolled, resetNewEntries } = useActivityLogLive();

watch(
    newEntries,
    (entries) => {
        if (!entries.length || initialMaxId.value === 0) return;
        // Prepend entry yang belum ada di liveActivities dan belum ada di activities.data
        const existingIds = new Set(
            [...liveActivities.value, ...props.activities.data].map((a) => a.id),
        );
        const fresh = entries.filter((e) => !existingIds.has(e.id) && e.id > initialMaxId.value);
        if (fresh.length) {
            liveActivities.value = [...fresh, ...liveActivities.value];
            liveCount.value += fresh.length;
        }
    },
    { deep: true },
);

onMounted(() => {
    const ids = props.activities.data.map((a) => a.id);
    initialMaxId.value = ids.length ? Math.max(...ids) : 0;
    resetNewEntries();
});

// Saat filter apply → reset liveActivities agar hanya entry yang lolos filter
watch(hasActiveFilters, () => {
    liveActivities.value = [];
    liveCount.value = 0;
});

const lastPolledLabel = computed(() => {
    if (!lastPolled.value) return '';
    return new Intl.DateTimeFormat('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).format(lastPolled.value);
});

// ── Data gabungan untuk tabel: live + Inertia ──────────────────────────
const allActivities = computed(() => [
    ...liveActivities.value,
    ...props.activities.data,
]);

// Pintasan tanggal — hari kalender zona Asia/Jakarta (format YYYY-MM-DD)
const jakartaDay = (offset) =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Jakarta',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date(Date.now() + offset * 86400000));

const applyShortcut = (days) => {
    localFilters.value.tanggal_sampai = jakartaDay(0);
    localFilters.value.tanggal_dari = jakartaDay(-(days - 1));
    applyFilters();
};

// --- Ekspor Excel mengikuti filter aktif ---
const excelUrl = computed(() => {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(localFilters.value)) {
        if (value) params.set(key, value);
    }
    const qs = params.toString();
    return route('reports.activity') + (qs ? `?${qs}` : '');
});

// Kelas kontrol filter — disamakan dengan pola halaman Users/Index.vue
const inputClass = 'rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700';
const filterSelectClass = 'min-w-[9.5rem] rounded-xl border border-slate-300 py-2 pl-3 pr-10 text-sm text-slate-700';
const buttonClass = 'rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50';
const shortcutClass = 'rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50';
</script>

<template>
    <Head title="Log Aktivitas" />
    <AppLayout>
        <template #skeleton-header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="h-7 w-40 rounded bg-slate-200" />
                    <div class="h-4 w-64 rounded bg-slate-200" />
                </div>
                <div class="h-10 w-32 rounded-xl bg-slate-200" />
            </div>
        </template>

        <template #skeleton>
            <div class="space-y-4">
                <div class="flex flex-wrap gap-2 rounded-2xl bg-slate-100 p-3">
                    <div class="h-10 w-28 rounded-xl bg-slate-200" />
                    <div class="h-7 w-16 rounded-lg bg-slate-200" />
                    <div class="h-7 w-16 rounded-lg bg-slate-200" />
                    <div class="h-7 w-16 rounded-lg bg-slate-200" />
                    <div class="h-10 w-32 rounded-xl bg-slate-200" />
                    <div class="h-10 w-32 rounded-xl bg-slate-200" />
                    <div class="h-10 flex-1 rounded-xl bg-slate-200" />
                </div>
                <div class="h-64 rounded-2xl bg-slate-100">
                    <div class="h-9 border-b border-slate-100" />
                    <div v-for="i in 6" :key="'sk-act-' + i" class="h-12 border-b border-slate-100" />
                </div>
            </div>
        </template>

        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-slate-900">Log Aktivitas</h1>
                    <p class="text-sm text-slate-500">Riwayat aktivitas pengguna dan sistem.</p>
                    <!-- Live badge (admin-only) -->
                    <span
                        v-if="isAdmin"
                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200"
                        role="status"
                        aria-live="polite"
                    >
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75 motion-reduce:hidden" />
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                        </span>
                        Live
                        <span v-if="liveCount > 0" class="text-emerald-600">+{{ liveCount }}</span>
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <p v-if="isAdmin && lastPolledLabel" class="text-xs text-slate-400">
                        Diperbarui {{ lastPolledLabel }}
                    </p>
                    <a
                        :href="excelUrl"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-emerald-600 bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 sm:flex-none"
                        title="Unduh log aktivitas sesuai filter aktif"
                    >
                        <AppIcon name="excel" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
                        Ekspor Excel
                    </a>
                </div>
            </div>
        </template>

        <!-- Baris filter (gaya Users/Index.vue) -->
        <div v-reveal class="mb-4 flex flex-col gap-2.5 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm md:flex-row md:flex-wrap md:items-center md:gap-3 md:p-4">
            <div class="flex items-center gap-1.5">
                <input v-model="localFilters.tanggal_dari" type="date" :class="inputClass" title="Dari" @change="applyFilters" />
                <span class="text-xs text-slate-400">–</span>
                <input v-model="localFilters.tanggal_sampai" type="date" :class="inputClass" title="Sampai" @change="applyFilters" />
            </div>
            <div class="flex items-center gap-1.5">
                <button type="button" :class="shortcutClass" @click="applyShortcut(1)">Hari ini</button>
                <button type="button" :class="shortcutClass" @click="applyShortcut(7)">7 hari</button>
                <button type="button" :class="shortcutClass" @click="applyShortcut(30)">30 hari</button>
            </div>
            <select v-model="localFilters.pengguna" :class="filterSelectClass" @change="applyFilters">
                <option value="">Semua pengguna</option>
                <option value="sistem">Sistem</option>
                <option v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
            </select>
            <select v-model="localFilters.aksi" :class="filterSelectClass" @change="applyFilters">
                <option value="">Semua aksi</option>
                <option v-for="a in actions" :key="a" :value="a">{{ actionOptionLabel(a) }}</option>
            </select>
            <input
                v-model="localFilters.q"
                type="search"
                placeholder="Cari keterangan atau target"
                :class="inputClass + ' md:min-w-[12rem] md:flex-1'"
                @input="onSearchInput"
                @keyup.enter="applyFilters"
            >
            <button type="button" :class="buttonClass" @click="resetFilters">Reset</button>
        </div>

        <div v-reveal :style="{ transitionDelay: '100ms' }" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <!-- Mobile / tablet: card list (< lg) -->
            <div class="lg:hidden">
                <div
                    v-if="!allActivities.length"
                    class="px-4 py-10 text-center text-sm text-slate-500"
                >
                    <template v-if="hasActiveFilters">
                        Tidak ada aktivitas yang cocok dengan filter.
                        <button type="button" class="mt-1 block text-xs font-semibold text-brand-blue hover:underline" @click="resetFilters">
                            Reset filter
                        </button>
                    </template>
                    <template v-else>Belum ada riwayat kegiatan. Aktivitas akan muncul setelah petugas mengelola arsip.</template>
                </div>
                <ul v-else class="divide-y divide-slate-100">
                    <li
                        v-for="activity in allActivities"
                        :key="`card-${activity.id}`"
                        class="px-4 py-3"
                        :class="liveActivities.some(a => a.id === activity.id) ? 'bg-emerald-50/40' : ''"
                    >
                        <div class="flex items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-medium text-slate-500">{{ formatDateTime(activity.created_at) }}</span>
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold ring-1 ring-inset"
                                        :class="actionBadge(activity)"
                                    >{{ actionLabel(activity) }}</span>
                                </div>
                                <p class="mt-1 text-sm font-medium text-slate-800">
                                    {{ activity.user?.name || 'Sistem' }}
                                    <span class="text-xs text-slate-400">· {{ activity.user?.role || '' }}</span>
                                </p>
                                <p v-if="activity.user?.email" class="mt-0.5 text-xs text-slate-400">{{ activity.user.email }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ descriptionLabel(activity) }}</p>
                                <div v-if="targetLine(activity)" class="mt-0.5 text-xs">
                                    <template v-if="targetLine(activity).href">
                                        <Link
                                            :href="targetLine(activity).href"
                                            preserve-scroll
                                            class="font-medium text-brand-blue hover:underline"
                                        >{{ targetLine(activity).prefix }}: {{ targetLine(activity).label }}</Link>
                                    </template>
                                    <template v-else>
                                        <span class="font-medium text-slate-700">{{ targetLine(activity).prefix }}: {{ targetLine(activity).label }}</span>
                                    </template>
                                </div>
                                <div v-for="d in changedDetails(activity)" :key="d.label" class="mt-0.5 text-xs text-slate-400">
                                    {{ d.label }}: {{ d.old }} → {{ d.new }}
                                </div>
                            </div>
                            <span class="shrink-0 text-xs text-slate-400">{{ activity.ip_address || '—' }}</span>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Desktop: table (≥ lg) -->
            <div class="hidden overflow-x-auto lg:block">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-600">
                            <th class="px-3 py-3 font-semibold text-center">Waktu</th>
                            <th class="px-3 py-3 font-semibold text-center">Pengguna</th>
                            <th class="px-3 py-3 font-semibold text-center">Aksi</th>
                            <th class="px-3 py-3 font-semibold text-center">Keterangan</th>
                            <th class="px-3 py-3 font-semibold text-center">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="activity in allActivities"
                            :key="activity.id"
                            class="border-b border-slate-100"
                            :class="liveActivities.some(a => a.id === activity.id) ? 'bg-emerald-50/40' : ''"
                        >
                            <td class="whitespace-nowrap px-3 py-3 text-center text-slate-600">{{ formatDateTime(activity.created_at) }}</td>
                            <td class="px-3 py-3 text-center">
                                <div class="font-medium text-slate-800">{{ activity.user?.name || 'Sistem' }}</div>
                                <div class="text-xs uppercase text-slate-400">{{ activity.user?.role || '' }}</div>
                                <div v-if="activity.user?.email" class="text-xs text-slate-500 truncate max-w-[14rem]">{{ activity.user.email }}</div>
                            </td>
                            <td class="px-3 py-3 text-center">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset"
                                    :class="actionBadge(activity)"
                                >{{ actionLabel(activity) }}</span>
                            </td>
                            <td class="px-3 py-3 text-center">
                                <div v-if="targetLine(activity)" class="text-xs">
                                    <template v-if="targetLine(activity).href">
                                        <Link
                                            :href="targetLine(activity).href"
                                            preserve-scroll
                                            class="font-medium text-brand-blue hover:underline"
                                        >{{ targetLine(activity).prefix }}: {{ targetLine(activity).label }}</Link>
                                    </template>
                                    <template v-else>
                                        <span class="font-medium text-slate-700">{{ targetLine(activity).prefix }}:
                                            {{ targetLine(activity).label }}</span>
                                    </template>
                                </div>
                                <div class="text-xs text-slate-400" v-else>Target: —</div>
                                <div class="mt-0.5">{{ descriptionLabel(activity) }}</div>
                                <div v-for="d in changedDetails(activity)" :key="d.label" class="mt-0.5 text-xs text-slate-500">
                                    {{ d.label }}: {{ d.old }} → {{ d.new }}
                                </div>
                            </td>
                            <td class="px-3 py-3 text-center text-slate-500">{{ activity.ip_address || '—' }}</td>
                        </tr>
                        <tr v-if="!allActivities.length">
                            <td colspan="5" class="px-3 py-10 text-center text-sm leading-7 text-slate-500">
                                <template v-if="hasActiveFilters">
                                    Tidak ada aktivitas yang cocok dengan filter.
                                    <button type="button" class="mt-1 block text-xs font-semibold text-brand-blue hover:underline" @click="resetFilters">
                                        Reset filter
                                    </button>
                                </template>
                                <template v-else>Belum ada riwayat kegiatan. Aktivitas akan muncul setelah petugas mengelola arsip.</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination (filter ikut lewat query string server) -->
            <div
                v-if="activities.last_page > 1"
                class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 pt-3 sm:px-5"
            >
                <p class="text-xs text-slate-500">
                    Halaman {{ activities.current_page }} dari {{ activities.last_page }}
                    <span class="text-slate-400">· {{ activities.total }} aktivitas</span>
                </p>
                <div class="flex flex-wrap gap-1">
                    <template v-for="link in activities.links" :key="`${link.label}-${link.url}`">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            v-html="link.label"
                            class="rounded-md px-2.5 py-1 text-xs"
                            :class="link.active ? 'bg-brand-blue text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        />
                        <span v-else v-html="link.label" class="rounded-md px-2.5 py-1 text-xs text-slate-400"></span>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
