<script setup>
import ArsipDetailModal from '@/Components/ArsipDetailModal.vue';
import ArsipFormModal from '@/Components/ArsipFormModal.vue';
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
    filters: {
        type: Object,
        default: () => ({
            seksi_id: null,
            status: 'semua',
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
            <div class="mb-4 h-20 rounded-2xl bg-slate-100" />
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
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-700 sm:flex-none"
                        @click="bulkDelete"
                    >
                        <AppIcon name="trash" :size="16" :stroke-width="2" decorative />
                        Hapus {{ selectedIds.length }} Terpilih
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
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="relative min-w-0 flex-1">
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

                <div class="flex flex-wrap items-center gap-2">
                    <select
                        v-model="localFilters.status"
                        class="h-10 min-w-0 flex-1 rounded-lg border border-slate-300 pl-3 pr-9 text-[13px] shadow-sm focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/30"
                        @change="applyFilters"
                    >
                        <option value="semua">Semua Status</option>
                        <option value="tersedia">Tersedia</option>
                        <option value="dipinjam">Dipinjam</option>
                        <option value="hilang">Hilang</option>
                    </select>

                    <div class="flex items-center gap-1 w-full sm:w-auto">
                        <input
                            v-model="localFilters.tanggal_dari"
                            type="date"
                            class="h-10 flex-1 min-w-0 rounded-lg border border-slate-300 px-2.5 text-[13px] shadow-sm focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/30"
                            @change="applyFilters"
                        />
                        <span class="text-xs font-medium text-slate-400">s/d</span>
                        <input
                            v-model="localFilters.tanggal_sampai"
                            type="date"
                            class="h-10 flex-1 min-w-0 rounded-lg border border-slate-300 px-2.5 text-[13px] shadow-sm focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/30"
                            @change="applyFilters"
                        />
                    </div>

                    <select
                        v-model="localFilters.sort"
                        class="h-10 min-w-0 flex-1 rounded-lg border border-slate-300 pl-3 pr-9 text-[13px] shadow-sm focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/30"
                        @change="applyFilters"
                    >
                        <option value="terbaru">Terbaru</option>
                        <option value="terlama">Terlama</option>
                        <option value="kode">Kode</option>
                        <option value="batas">Batas kembali</option>
                    </select>

                    <div class="relative shrink-0 sm:flex-shrink">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center gap-1.5 whitespace-nowrap rounded-lg border border-slate-300 bg-white px-3 text-[13px] font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/30"
                            :class="hasOperationalFilter ? 'border-amber-300 bg-amber-50 text-amber-800' : ''"
                            @click="opFilterOpen = !opFilterOpen"
                        >
                            <AppIcon name="filter" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
                            Filter operasional
                            <span
                                v-if="activeOperationalCount > 0"
                                class="ml-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-100 px-1.5 text-xs font-bold text-amber-800"
                            >
                                {{ activeOperationalCount }}
                            </span>
                            <AppIcon name="chevron-down" :size="16" :stroke-width="2" class="ml-1 h-4 w-4 shrink-0 text-slate-400" decorative />
                        </button>

                        <template v-if="opFilterOpen">
                            <div
                                class="fixed inset-0 z-40"
                                @click="opFilterOpen = false"
                            ></div>
                            <div
                                class="absolute left-0 right-0 sm:left-auto sm:right-0 z-50 mt-2 w-full sm:w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg"
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
                                <div v-if="hasOperationalFilter" class="border-t border-slate-100 pt-1">
                                    <button
                                        type="button"
                                        class="w-full rounded-lg px-2 py-1.5 text-left text-sm font-medium text-slate-600 hover:bg-slate-50"
                                        @click="
                                            localFilters.tanpa_lokasi = false;
                                            localFilters.terlambat = false;
                                            localFilters.tanpa_file = false;
                                            localFilters.metadata_kosong = false;
                                            localFilters.tanpa_qr = false;
                                            applyFilters();
                                            opFilterOpen = false;
                                        "
                                    >
                                        Hapus semua filter operasional
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
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
        title="Hapus Dokumen"
        :message="`Dokumen ${deleteTarget?.kode_arsip ?? ''} akan dihapus dan dipindahkan ke daftar terhapus. Untuk memulihkan, hubungi admin.`"
        confirm-label="Hapus"
        @close="showDeleteModal = false"
        @confirm="confirmDeleteArsip"
    />

    <ConfirmModal
        :show="showBulkDeleteModal"
        title="Hapus Massal"
        :message="`Apakah Anda yakin ingin menghapus ${selectedIds.length} arsip yang dipilih? Tindakan ini tidak dapat dibatalkan.`"
        confirm-label="Hapus"
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
