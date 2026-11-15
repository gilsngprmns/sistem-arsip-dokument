<script setup>
import ArsipDetailModal from '@/Components/ArsipDetailModal.vue';
import ArsipFormModal from '@/Components/ArsipFormModal.vue';
import AppSelect from '@/Components/AppSelect.vue';
import ArsipTable from '@/Components/ArsipTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import PrintLabelModal from '@/Components/PrintLabelModal.vue';
import SirkulasiModal from '@/Components/SirkulasiModal.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    arsip: {
        type: Object,
        required: true,
    },
    retensiAlert: {
        type: Number,
        default: 0,
    },
    filters: {
        type: Object,
        default: () => ({
            seksi_id: null,
            status: 'semua',
            status_retensi: 'semua',
            q: '',
            tanggal_dari: '',
            tanggal_sampai: '',
            sort: 'terbaru',
            tanpa_lokasi: false,
            terlambat: false,
            tanpa_file: false,
            metadata_kosong: false,
            tanpa_qr: false,
        }),
    },
});

const page = usePage();
const seksiList = computed(() => page.props.seksiList ?? []);
const rakList = computed(() => page.props.rakList ?? []);
const boksList = computed(() => page.props.boksList ?? []);
const flashSuccess = computed(() => page.props.flash?.success);
const flashVisible = ref(false);
let flashTimer = null;

// Seksi yang dikelola user (staff). Admin = null (bebas memilih semua).
const mySeksiId = computed(() => {
    const u = page.props.auth?.user;
    return u?.role !== 'admin' ? Number(u.seksi_id) : null;
});
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');
const isSeksiOptionDisabled = (id) => mySeksiId.value !== null && Number(id) !== mySeksiId.value;

watch(
    () => page.props.flash?.success,
    (v) => {
        clearTimeout(flashTimer);
        if (v) {
            flashVisible.value = true;
            flashTimer = setTimeout(() => { flashVisible.value = false; }, 5000);
        }
    },
    { immediate: true },
);

const errors = computed(() => page.props.errors ?? {});
const sirkulasiError = computed(() => {
    const err = errors.value?.sirkulasi;
    if (!err) return null;
    return Array.isArray(err) ? err[0] : err;
});

const flashDanger = computed(() => page.props.flash?.danger);
const flashDangerVisible = ref(false);
let flashDangerTimer = null;

watch(
    () => page.props.flash?.danger,
    (v) => {
        clearTimeout(flashDangerTimer);
        if (v) {
            flashDangerVisible.value = true;
            flashDangerTimer = setTimeout(() => { flashDangerVisible.value = false; }, 5000);
        }
    },
    { immediate: true },
);

const localFilters = ref({
    seksi_id: props.filters.seksi_id ?? '',
    status: props.filters.status ?? 'semua',
    status_retensi: props.filters.status_retensi ?? 'semua',
    jenis_dokumen: props.filters.jenis_dokumen ?? 'semua',
    status_pengecekan: props.filters.status_pengecekan ?? 'semua',
    q: props.filters.q ?? '',
    tanggal_dari: props.filters.tanggal_dari ?? '',
    tanggal_sampai: props.filters.tanggal_sampai ?? '',
    sort: props.filters.sort ?? 'terbaru',
    tanpa_lokasi: Boolean(props.filters.tanpa_lokasi),
    terlambat: Boolean(props.filters.terlambat),
    tanpa_file: Boolean(props.filters.tanpa_file),
    metadata_kosong: Boolean(props.filters.metadata_kosong),
    tanpa_qr: Boolean(props.filters.tanpa_qr),
});

watch(
    () => props.filters,
    (value) => {
        localFilters.value = {
            seksi_id: value.seksi_id ?? '',
            status: value.status ?? 'semua',
            status_retensi: value.status_retensi ?? 'semua',
            jenis_dokumen: value.jenis_dokumen ?? 'semua',
            status_pengecekan: value.status_pengecekan ?? 'semua',
            q: value.q ?? '',
            tanggal_dari: value.tanggal_dari ?? '',
            tanggal_sampai: value.tanggal_sampai ?? '',
            sort: value.sort ?? 'terbaru',
            tanpa_lokasi: Boolean(value.tanpa_lokasi),
            terlambat: Boolean(value.terlambat),
            tanpa_file: Boolean(value.tanpa_file),
            metadata_kosong: Boolean(value.metadata_kosong),
            tanpa_qr: Boolean(value.tanpa_qr),
        };
    },
    { deep: true },
);

const showFormModal = ref(false);
const editingArsip = ref(null);

const showDetailModal = ref(false);
const viewingArsip = ref(null);

const showLabelModal = ref(false);
const labelingArsip = ref(null);

const showSirkulasiModal = ref(false);
const sirkulasiMode = ref('pinjam');
const sirkulasiArsip = ref(null);

const showSettersediaModal = ref(false);
const settersediaArsip = ref(null);
const settersediaKeterangan = ref('');

const searchInput = ref(null);

const activeSeksi = computed(() => {
    if (!localFilters.value.seksi_id) return null;
    return seksiList.value.find((item) => String(item.id) === String(localFilters.value.seksi_id)) || null;
});

const pageTitle = computed(() => {
    if (activeSeksi.value) {
        return `Dokumen ${activeSeksi.value.nama_seksi}`;
    }
    return 'Semua Dokumen Arsip';
});

const sectionBadge = computed(() => activeSeksi.value?.nama_seksi ?? 'Semua Seksi');

const hasOperationalFilter = computed(
    () =>
        localFilters.value.tanpa_lokasi
        || localFilters.value.terlambat
        || localFilters.value.tanpa_file
        || localFilters.value.metadata_kosong
        || localFilters.value.tanpa_qr,
);

const activeOperationalCount = computed(
    () =>
        [
            localFilters.value.tanpa_lokasi,
            localFilters.value.terlambat,
            localFilters.value.tanpa_file,
            localFilters.value.metadata_kosong,
            localFilters.value.tanpa_qr,
        ].filter(Boolean).length,
);

const operationalFilterChips = computed(() => {
    const chips = [];
    if (localFilters.value.tanpa_lokasi) {
        chips.push({ key: 'tanpa_lokasi', label: 'Tanpa lokasi', tone: 'bg-amber-100 text-amber-800 hover:bg-amber-200' });
    }
    if (localFilters.value.terlambat) {
        chips.push({ key: 'terlambat', label: 'Peminjaman terlambat', tone: 'bg-rose-100 text-rose-800 hover:bg-rose-200' });
    }
    if (localFilters.value.tanpa_file) {
        chips.push({ key: 'tanpa_file', label: 'Tanpa file digital', tone: 'bg-sky-100 text-sky-800 hover:bg-sky-200' });
    }
    if (localFilters.value.metadata_kosong) {
        chips.push({ key: 'metadata_kosong', label: 'Metadata kosong', tone: 'bg-violet-100 text-violet-800 hover:bg-violet-200' });
    }
    if (localFilters.value.tanpa_qr) {
        chips.push({ key: 'tanpa_qr', label: 'Tanpa label QR', tone: 'bg-slate-200 text-slate-800 hover:bg-slate-300' });
    }
    return chips;
});

let searchTimer = null;

const applyFilters = () => {
    router.get(
        route('arsip.index'),
        {
            seksi_id: localFilters.value.seksi_id || undefined,
            status: localFilters.value.status !== 'semua' ? localFilters.value.status : undefined,
            status_retensi: localFilters.value.status_retensi !== 'semua' ? localFilters.value.status_retensi : undefined,
            jenis_dokumen: localFilters.value.jenis_dokumen !== 'semua' ? localFilters.value.jenis_dokumen : undefined,
            status_pengecekan: localFilters.value.status_pengecekan !== 'semua' ? localFilters.value.status_pengecekan : undefined,
            q: localFilters.value.q || undefined,
            tanggal_dari: localFilters.value.tanggal_dari || undefined,
            tanggal_sampai: localFilters.value.tanggal_sampai || undefined,
            sort: localFilters.value.sort !== 'terbaru' ? localFilters.value.sort : undefined,
            tanpa_lokasi: localFilters.value.tanpa_lokasi ? 1 : undefined,
            terlambat: localFilters.value.terlambat ? 1 : undefined,
            tanpa_file: localFilters.value.tanpa_file ? 1 : undefined,
            metadata_kosong: localFilters.value.metadata_kosong ? 1 : undefined,
            tanpa_qr: localFilters.value.tanpa_qr ? 1 : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['arsip', 'filters', 'seksiCounts', 'flash'],
        },
    );
};

const clearOperationalFilter = (key) => {
    localFilters.value[key] = false;
    if (key === 'terlambat' && localFilters.value.status === 'dipinjam') {
        localFilters.value.status = 'semua';
    }
    applyFilters();
};

const onSearchInput = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
};

const openEdit = (row) => {
    editingArsip.value = row;
    showDetailModal.value = false;
    showFormModal.value = true;
};

const openView = (row) => {
    viewingArsip.value = row;
    showDetailModal.value = true;
};

const openLabel = (row) => {
    showDetailModal.value = false;
    labelingArsip.value = row;
    showLabelModal.value = true;
};

const openPinjam = (row) => {
    sirkulasiMode.value = 'pinjam';
    sirkulasiArsip.value = row;
    showDetailModal.value = false;
    showSirkulasiModal.value = true;
};

const openKembalikan = (row) => {
    sirkulasiMode.value = 'kembalikan';
    sirkulasiArsip.value = row;
    showDetailModal.value = false;
    showSirkulasiModal.value = true;
};

const openSettersedia = (row) => {
    settersediaArsip.value = row;
    settersediaKeterangan.value = '';
    showDetailModal.value = false;
    showSettersediaModal.value = true;
};

const confirmSettersedia = () => {
    if (!settersediaArsip.value) return;
    const arsip = settersediaArsip.value;
    const keterangan = settersediaKeterangan.value;
    router.post(
        route('arsip.settersedia', arsip.id),
        { keterangan },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['arsip', 'filters', 'seksiCounts', 'flash', 'errors'],
            onSuccess: () => {
                showSettersediaModal.value = false;
                settersediaArsip.value = null;
                settersediaKeterangan.value = '';
            },
            onError: () => {
                showSettersediaModal.value = false;
                settersediaArsip.value = null;
                settersediaKeterangan.value = '';
            },
        },
    );
};

const showHilangModal = ref(false);
const hilangArsip = ref(null);
const hilangKeterangan = ref('');

const opFilterOpen = ref(false);
const opFilterLabels = [
    { key: 'tanpa_lokasi', label: 'Tanpa lokasi' },
    { key: 'terlambat', label: 'Peminjaman terlambat' },
    { key: 'tanpa_file', label: 'Tanpa file digital' },
    { key: 'metadata_kosong', label: 'Metadata kosong' },
    { key: 'tanpa_qr', label: 'Tanpa label QR' },
];

const openTandahilang = (row) => {
    hilangArsip.value = row;
    hilangKeterangan.value = '';
    showDetailModal.value = false;
    showHilangModal.value = true;
};

const confirmTandahilang = () => {
    if (!hilangArsip.value) return;
    const arsip = hilangArsip.value;
    const keterangan = hilangKeterangan.value;
    router.post(
        route('arsip.tandahilang', arsip.id),
        { keterangan },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['arsip', 'filters', 'seksiCounts', 'flash', 'errors'],
            onSuccess: () => {
                showHilangModal.value = false;
                hilangArsip.value = null;
                hilangKeterangan.value = '';
            },
            onError: () => {
                showHilangModal.value = false;
                hilangArsip.value = null;
                hilangKeterangan.value = '';
            },
        },
    );
};

const exportExcel = () => {
    const params = new URLSearchParams();

    if (localFilters.value.seksi_id) params.set('seksi_id', localFilters.value.seksi_id);
    if (localFilters.value.status && localFilters.value.status !== 'semua') {
        params.set('status', localFilters.value.status);
    }
    if (localFilters.value.status_retensi && localFilters.value.status_retensi !== 'semua') {
        params.set('status_retensi', localFilters.value.status_retensi);
    }
    if (localFilters.value.q) params.set('q', localFilters.value.q);
    if (localFilters.value.tanggal_dari) params.set('tanggal_dari', localFilters.value.tanggal_dari);
    if (localFilters.value.tanggal_sampai) params.set('tanggal_sampai', localFilters.value.tanggal_sampai);
    if (localFilters.value.sort && localFilters.value.sort !== 'terbaru') {
        params.set('sort', localFilters.value.sort);
    }
    if (localFilters.value.tanpa_lokasi) params.set('tanpa_lokasi', '1');
    if (localFilters.value.terlambat) params.set('terlambat', '1');
    if (localFilters.value.tanpa_file) params.set('tanpa_file', '1');
    if (localFilters.value.metadata_kosong) params.set('metadata_kosong', '1');
    if (localFilters.value.tanpa_qr) params.set('tanpa_qr', '1');

    const query = params.toString();
    window.location.href = route('arsip.export') + (query ? `?${query}` : '');
};

const closeForm = () => {
    showFormModal.value = false;
    editingArsip.value = null;
};

const showDeleteModal = ref(false);
const deleteTarget = ref(null);

const deleteFromDetail = (row) => {
    deleteTarget.value = row;
    showDeleteModal.value = true;
};

const confirmDeleteArsip = () => {
    router.delete(route('arsip.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDetailModal.value = false;
            viewingArsip.value = null;
            showDeleteModal.value = false;
            deleteTarget.value = null;
        },
    });
};

// ── Bulk Delete ──────────────────────────────────────────────
const selectedIds = ref([]);
const showBulkDeleteModal = ref(false);

const toggleRow = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value.splice(idx, 1);
    }
};

const selectAll = async (ids) => {
    if (ids.length > 0) {
        // Memilih semua: fetch seluruh ID yang cocok filter dari backend
        try {
            const params = new URLSearchParams();
            if (localFilters.value.seksi_id) params.set('seksi_id', localFilters.value.seksi_id);
            if (localFilters.value.status && localFilters.value.status !== 'semua') {
                params.set('status', localFilters.value.status);
            }
            if (localFilters.value.status_retensi && localFilters.value.status_retensi !== 'semua') {
                params.set('status_retensi', localFilters.value.status_retensi);
            }
            if (localFilters.value.q) params.set('q', localFilters.value.q);
            if (localFilters.value.tanggal_dari) params.set('tanggal_dari', localFilters.value.tanggal_dari);
            if (localFilters.value.tanggal_sampai) params.set('tanggal_sampai', localFilters.value.tanggal_sampai);
            if (localFilters.value.sort && localFilters.value.sort !== 'terbaru') {
                params.set('sort', localFilters.value.sort);
            }
            if (localFilters.value.tanpa_lokasi) params.set('tanpa_lokasi', '1');
            if (localFilters.value.terlambat) params.set('terlambat', '1');
            if (localFilters.value.tanpa_file) params.set('tanpa_file', '1');
            if (localFilters.value.metadata_kosong) params.set('metadata_kosong', '1');
            if (localFilters.value.tanpa_qr) params.set('tanpa_qr', '1');

            const query = params.toString();
            const resp = await fetch(route('arsip.bulk-select-all') + (query ? `?${query}` : ''));
            const data = await resp.json();
            selectedIds.value = data.ids.map(String);
        } catch {
            // fallback: gunakan ID dari halaman saat ini
            selectedIds.value = props.rows.map((row) => String(row.id));
        }
    } else {
        selectedIds.value = [];
    }
};

const bulkDelete = () => {
    showBulkDeleteModal.value = true;
};

const confirmBulkDelete = () => {
    router.post(
        route('arsip.bulk-destroy'),
        { ids: selectedIds.value.map((id) => Number(id)) },
        {
            preserveScroll: true,
            onSuccess: () => {
                showBulkDeleteModal.value = false;
                selectedIds.value = [];
            },
        },
    );
};

const onSlashFocus = (event) => {
    if (event.key !== '/') return;
    const tag = event.target?.tagName?.toLowerCase();
    if (tag === 'input' || tag === 'textarea' || event.target?.isContentEditable) {
        return;
    }
    event.preventDefault();
    searchInput.value?.focus();
};

onMounted(() => window.addEventListener('keydown', onSlashFocus));
onUnmounted(() => {
    window.removeEventListener('keydown', onSlashFocus);
    clearTimeout(searchTimer);
});
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout
        :active-seksi-id="localFilters.seksi_id || null"
    >
        <template #skeleton-header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="h-7 w-48 rounded bg-slate-200" />
                    <div class="h-6 w-24 rounded-full bg-slate-200" />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="h-10 w-32 rounded-xl bg-slate-200" />
                </div>
            </div>
        </template>

        <template #skeleton>
            <div class="mb-4 h-28 rounded-2xl bg-slate-100" />
            <div class="h-64 rounded-2xl bg-slate-100">
                <div class="h-9 border-b border-slate-100" />
                <div v-for="i in 5" :key="'sk-' + i" class="h-12 border-b border-slate-100" />
            </div>
        </template>

        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-slate-900">{{ pageTitle }}</h1>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                        {{ sectionBadge }}
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="selectedIds.length > 0"
                        type="button"
                        :class="[
                            'inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm sm:flex-none',
                            isAdmin ? 'bg-red-700 hover:bg-red-800' : 'bg-red-600 hover:bg-red-700',
                        ]"
                        @click="bulkDelete"
                    >
                        <AppIcon name="trash" :size="16" :stroke-width="2" decorative />
                        {{ isAdmin ? `Hapus Permanen (${selectedIds.length})` : `Hapus ${selectedIds.length} Terpilih` }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-600 bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 sm:flex-none"
                        title="Unduh daftar sesuai filter aktif"
                        @click="exportExcel"
                    >
                        <AppIcon name="excel" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
                        Ekspor Excel
                    </button>
                </div>
            </div>
        </template>

        <div
            v-if="retensiAlert > 0"
            class="mb-4 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3"
        >
            <AppIcon name="alert-triangle" :size="18" :stroke-width="1.8" class="mt-0.5 h-[18px] w-[18px] shrink-0 text-rose-500" decorative />
            <div class="flex-1 text-sm text-rose-800">
                <strong>{{ retensiAlert }} dokumen</strong> memiliki masa aktif retensi yang sudah berakhir atau akan berakhir dalam 30 hari.
                Gunakan filter "Status Retensi" untuk menyaring dokumen tersebut, atau buka detail arsip untuk mengambil tindakan.
            </div>
        </div>

        <div
            v-if="flashSuccess && flashVisible"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
        >
            {{ flashSuccess }}
        </div>

        <div
            v-if="flashDanger && flashDangerVisible"
            class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"
        >
            {{ flashDanger }}
        </div>

        <div v-reveal class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <!-- BARIS 1: Pencarian + Urutan + Filter operasional -->
            <div class="flex w-full flex-col gap-2.5 md:flex-row md:items-center">
                <div class="relative w-full min-w-0 md:flex-1">
                    <AppIcon
                        name="search"
                        :size="16"
                        :stroke-width="1.8"
                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                        decorative
                    />
                    <input
                        ref="searchInput"
                        v-model="localFilters.q"
                        type="search"
                        title="Tekan / untuk fokus ke pencarian"
                        class="h-10 w-full rounded-lg border border-slate-300 pl-10 pr-3 text-[13px] shadow-sm focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/30"
                        placeholder="Cari nomor, perihal, atau rak..."
                        @input="onSearchInput"
                    />
                </div>

                <div class="flex items-center gap-2.5">
                    <div class="w-full shrink-0 md:w-44">
                        <AppSelect
                        v-model="localFilters.sort"
                        size="sm"
                        label="Urutkan arsip"
                        :options="[
                            { value: 'terbaru', label: 'Terbaru' },
                            { value: 'terlama', label: 'Terlama' },
                            { value: 'kode', label: 'Kode' },
                            { value: 'batas', label: 'Batas kembali' },
                        ]"
                        @change="applyFilters"
                    />
                    </div>

                    <div class="relative min-w-0 flex-1 md:shrink-0 md:flex-none">
                        <button
                            type="button"
                            class="inline-flex h-10 w-full items-center justify-between gap-1.5 whitespace-nowrap rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus-visible:border-brand-blue focus-visible:ring-2 focus-visible:ring-brand-blue/30 md:w-auto md:justify-center"
                            :class="hasOperationalFilter ? 'border-brand-blue bg-brand-blue/5 text-brand-blue' : ''"
                            @click="opFilterOpen = !opFilterOpen"
                        >
                            <AppIcon name="filter" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
                            Filter operasional
                            <span
                                v-if="activeOperationalCount > 0"
                                class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-blue/10 px-1.5 text-xs font-bold text-brand-blue"
                            >
                                {{ activeOperationalCount }}
                            </span>
                            <AppIcon
                                name="chevron-down"
                                :size="16"
                                :stroke-width="2"
                                class="ml-1 h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
                                :class="{ 'rotate-180': opFilterOpen }"
                                decorative
                            />
                        </button>

                        <div
                            v-show="opFilterOpen"
                            class="fixed inset-0 z-40"
                            @click="opFilterOpen = false"
                        ></div>
                        <Transition
                            enter-active-class="transition ease-out duration-150"
                            enter-from-class="opacity-0 scale-95"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition ease-in duration-100"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95"
                        >
                            <div
                                v-if="opFilterOpen"
                                class="absolute right-0 left-0 z-50 mt-1.5 w-full sm:left-auto sm:right-0 sm:w-60 rounded-xl border border-slate-200 bg-white p-2 shadow-lg"
                            >
                                <p class="px-2 py-1 text-xs font-semibold text-slate-500">Pilih filter (bisa lebih dari satu)</p>
                                <label
                                    v-for="item in opFilterLabels"
                                    :key="item.key"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="localFilters[item.key]"
                                        class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30"
                                        @change="applyFilters"
                                    />
                                    {{ item.label }}
                                </label>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>
            <!-- BARIS 2: dropdown filter + date range -->
            <div class="mt-3 grid w-full grid-cols-1 items-center gap-2.5 border-t border-slate-100 pt-3 sm:grid-cols-2 lg:grid-cols-12">
                <!-- Filter Status -->
                <div class="w-full min-w-0 lg:col-span-2">
                    <AppSelect
                        v-model="localFilters.status"
                        size="sm"
                        label="Filter status arsip"
                        :options="[
                            { value: 'semua', label: 'Semua Status' },
                            { value: 'tersedia', label: 'Tersedia' },
                            { value: 'dipinjam', label: 'Dipinjam' },
                            { value: 'hilang', label: 'Hilang' },
                        ]"
                        @change="applyFilters"
                    />
                </div>

                <!-- Filter Status Retensi -->
                <div class="w-full min-w-0 lg:col-span-2">
                    <AppSelect
                        v-model="localFilters.status_retensi"
                        size="sm"
                        label="Filter status retensi"
                        :options="[
                            { value: 'semua', label: 'Semua Retensi' },
                            { value: 'aman', label: 'Retensi Aman' },
                            { value: 'akan_kedaluwarsa', label: 'Akan Kedaluwarsa (30h)' },
                            { value: 'kedaluwarsa', label: 'Kedaluwarsa' },
                        ]"
                        @change="applyFilters"
                    />
                </div>

                <!-- Filter Klasifikasi Dokumen -->
                <div class="w-full min-w-0 lg:col-span-2">
                    <AppSelect
                        v-model="localFilters.jenis_dokumen"
                        size="sm"
                        label="Filter klasifikasi dokumen"
                        :options="[
                            { value: 'semua', label: 'Semua Klasifikasi' },
                            { value: 'umum', label: 'Dokumen Umum' },
                            { value: 'surat_tanah', label: 'Surat Tanah' },
                            { value: 'ahli_waris', label: 'Ahli Waris' },
                        ]"
                        @change="applyFilters"
                    />
                </div>

                <!-- Filter Status Pengecekan -->
                <div class="w-full min-w-0 lg:col-span-2">
                    <AppSelect
                        v-model="localFilters.status_pengecekan"
                        size="sm"
                        label="Filter status pengecekan"
                        :options="[
                            { value: 'semua', label: 'Semua Pengecekan' },
                            { value: 'belum_dicek', label: 'Belum Dicek' },
                            { value: 'dalam_pengecekan', label: 'Dalam Pengecekan' },
                            { value: 'terverifikasi', label: 'Terverifikasi' },
                            { value: 'perlu_perbaikan', label: 'Perlu Perbaikan' },
                        ]"
                        @change="applyFilters"
                    />
                </div>

                <!-- Date range (span-4 di lg karena berisi 2 input + label; w-full di sm:col-span-2) -->
                <div class="flex w-full min-w-0 items-center gap-1.5 sm:col-span-2 sm:gap-2 lg:col-span-4">
                    <input
                        v-model="localFilters.tanggal_dari"
                        type="date"
                        class="h-10 w-full flex-1 min-w-0 rounded-lg border border-slate-300 px-2.5 text-xs shadow-sm focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/30 sm:px-3 sm:text-sm"
                        @change="applyFilters"
                    />
                    <span class="shrink-0 text-xs text-slate-400">s/d</span>
                    <input
                        v-model="localFilters.tanggal_sampai"
                        type="date"
                        class="h-10 w-full flex-1 min-w-0 rounded-lg border border-slate-300 px-2.5 text-xs shadow-sm focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/30 sm:px-3 sm:text-sm"
                        @change="applyFilters"
                    />
                </div>
            </div>

            <p class="mt-2 text-xs text-slate-400">Tekan / untuk fokus ke pencarian</p>
            <div v-if="hasOperationalFilter" class="mt-3 flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold text-slate-500">Filter operasional:</span>
                <button
                    v-for="chip in operationalFilterChips"
                    :key="chip.key"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                    :class="chip.tone"
                    @click="clearOperationalFilter(chip.key)"
                >
                    {{ chip.label }}
                    <span aria-hidden="true">×</span>
                </button>
                <button
                    v-if="activeOperationalCount > 1"
                    type="button"
                    class="text-xs font-medium text-rose-600 hover:text-rose-700 hover:underline"
                    @click="
                        localFilters.tanpa_lokasi = false;
                        localFilters.terlambat = false;
                        localFilters.tanpa_file = false;
                        localFilters.metadata_kosong = false;
                        localFilters.tanpa_qr = false;
                        applyFilters();
                    "
                >
                    Reset semua
                </button>
            </div>
        </div>

        <div v-reveal :style="{ transitionDelay: '100ms' }">
            <ArsipTable
                :rows="arsip.data"
                :pagination="arsip"
                :selected-ids="selectedIds"
                @toggle-row="toggleRow"
                @select-all="selectAll"
                @edit="openEdit"
                @view="openView"
                @label="openLabel"
                @pinjam="openPinjam"
                @kembalikan="openKembalikan"
                @settersedia="openSettersedia"
                @tandahilang="openTandahilang"
                @delete="deleteFromDetail"
            />
        </div>
    </AppLayout>

    <ArsipFormModal
        :show="showFormModal"
        mode="edit"
        :seksi-list="seksiList"
        :rak-list="rakList"
        :boks-list="boksList"
        :initial-data="editingArsip"
        @close="closeForm"
    />

    <ArsipDetailModal
        :show="showDetailModal"
        :arsip="viewingArsip"
        @close="showDetailModal = false"
        @edit="openEdit"
        @label="openLabel"
        @pinjam="openPinjam"
        @kembalikan="openKembalikan"
        @settersedia="openSettersedia"
        @tandahilang="openTandahilang"
    />

    <SirkulasiModal
        :show="showSirkulasiModal"
        :arsip="sirkulasiArsip"
        :mode="sirkulasiMode"
        @close="showSirkulasiModal = false"
    />

    <PrintLabelModal
        :show="showLabelModal"
        :arsip="labelingArsip"
        @close="showLabelModal = false"
    />

    <ConfirmModal
        :show="showDeleteModal"
        :title="isAdmin ? 'Hapus Permanen' : 'Hapus Dokumen'"
        :message="isAdmin
            ? `Dokumen ${deleteTarget?.kode_arsip ?? ''} akan dihapus permanen dari database. Nomor arsip ini akan dapat dipakai ulang. Tindakan ini tidak dapat dibatalkan.`
            : `Dokumen ${deleteTarget?.kode_arsip ?? ''} akan dihapus dan dipindahkan ke daftar terhapus. Untuk memulihkan, hubungi admin.`"
        :confirm-label="isAdmin ? 'Hapus Permanen' : 'Hapus'"
        @close="showDeleteModal = false"
        @confirm="confirmDeleteArsip"
    />

    <ConfirmModal
        :show="showBulkDeleteModal"
        :title="isAdmin ? 'Hapus Massal Permanen' : 'Hapus Massal'"
        :message="isAdmin
            ? `Apakah Anda yakin ingin menghapus ${selectedIds.length} arsip secara permanen dari database? Nomor arsip akan dapat dipakai ulang. Tindakan ini tidak dapat dibatalkan.`
            : `Apakah Anda yakin ingin menghapus ${selectedIds.length} arsip yang dipilih? Tindakan ini tidak dapat dibatalkan.`"
        :confirm-label="isAdmin ? 'Hapus Permanen' : 'Hapus'"
        @close="showBulkDeleteModal = false"
        @confirm="confirmBulkDelete"
    />

    <Modal :show="showSettersediaModal" max-width="md" @close="showSettersediaModal = false">
        <form
            v-if="settersediaArsip"
            class="p-6"
            @submit.prevent="confirmSettersedia"
        >
            <h2 class="text-lg font-bold text-slate-900">Tandai Dokumen Tersedia</h2>
            <p class="mt-1 text-sm text-brand-blue">{{ settersediaArsip.kode_arsip }} — {{ settersediaArsip.perihal }}</p>
            <p class="mt-3 text-sm text-slate-600">
                Dokumen ini saat ini berstatus <strong>Hilang</strong>. Tandai tersedia jika dokumen sudah ditemukan kembali.
            </p>

            <div v-if="sirkulasiError" class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                {{ sirkulasiError }}
            </div>

            <div class="mt-4">
                <InputLabel for="keterangan_settersedia" value="Keterangan (opsional)" />
                <textarea
                    id="keterangan_settersedia"
                    v-model="settersediaKeterangan"
                    rows="3"
                    class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                    placeholder="Contoh: Ditemukan kembali di rak PEM-3"
                />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                    @click="showSettersediaModal = false"
                >
                    Batal
                </button>
                <PrimaryButton class="bg-brand-blue">Tandai Tersedia</PrimaryButton>
            </div>
        </form>
    </Modal>

    <Modal :show="showHilangModal" max-width="md" @close="showHilangModal = false">
        <form
            v-if="hilangArsip"
            class="p-6"
            @submit.prevent="confirmTandahilang"
        >
            <h2 class="text-lg font-bold text-slate-900">Tandai Dokumen Hilang</h2>
            <p class="mt-1 text-sm text-brand-blue">{{ hilangArsip.kode_arsip }} — {{ hilangArsip.perihal }}</p>
            <p class="mt-3 text-sm text-slate-600">
                Dokumen ini akan ditandai sebagai <strong>Hilang</strong> dan batas pengembalian akan dihapus.
                Gunakan bila dokumen tidak dapat ditemukan atau belum dikembalikan.
            </p>

            <div v-if="sirkulasiError" class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                {{ sirkulasiError }}
            </div>

            <div class="mt-4">
                <InputLabel for="keterangan_tandahilang" value="Keterangan (opsional)" />
                <textarea
                    id="keterangan_tandahilang"
                    v-model="hilangKeterangan"
                    rows="3"
                    class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                    placeholder="Contoh: Tidak ditemukan saat pengecekan rak, masih dicari"
                />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                    @click="showHilangModal = false"
                >
                    Batal
                </button>
                <PrimaryButton class="bg-amber-500">Tandai Hilang</PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
