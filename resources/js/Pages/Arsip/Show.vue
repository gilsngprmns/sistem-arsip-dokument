<script setup>
import BackLink from '@/Components/BackLink.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppSelect from '@/Components/AppSelect.vue';
import ArsipFormModal from '@/Components/ArsipFormModal.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SirkulasiModal from '@/Components/SirkulasiModal.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    arsip: {
        type: Object,
        required: true,
    },
    seksiList: {
        type: Array,
        default: () => [],
    },
    rakList: {
        type: Array,
        default: () => [],
    },
    boksList: {
        type: Array,
        default: () => [],
    },
    previousUrl: {
        type: String,
        default: '',
    },
});

const page = usePage();
const showEditModal = ref(false);
const editingArsip = ref(null);

// Flash messages
const flashSuccess = computed(() => page.props.flash?.success);
const flashVisible = ref(false);
let flashTimer = null;

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

onUnmounted(() => {
    clearTimeout(flashTimer);
    clearTimeout(flashDangerTimer);
});

const openEdit = () => {
    editingArsip.value = props.arsip;
    showEditModal.value = true;
};

const onEditSaved = () => {
    // Reload halaman Show dengan menyertakan flash props agar notifikasi muncul
    router.reload({ only: ['arsip', 'flash'] });
};

const showSirkulasiModal = ref(false);
const sirkulasiMode = ref('pinjam');
const showAllRiwayat = ref(false);

// ── Tahap 4: Pengecekan Dokumen Khusus ──────────────────────
const isDokumenKhusus = computed(
    () => ['surat_tanah', 'ahli_waris'].includes(props.arsip.jenis_dokumen ?? ''),
);

// Tahap 5: file terenkripsi — akses hanya lewat endpoint terkontrol
const isFileEncrypted = computed(() => Boolean(props.arsip.is_encrypted));

// ── Tahap 6: Badge & panel retensi ──────────────────────────
const retensiBadge = computed(() => {
    const a = props.arsip;
    if (a.retensi_dipertahankan) {
        return { label: 'Retensi Dipertahankan', cls: 'bg-blue-50 text-blue-800 ring-blue-300' };
    }
    if (a.is_kedaluwarsa) {
        return { label: 'Retensi Kedaluwarsa', cls: 'bg-rose-50 text-rose-800 ring-rose-300' };
    }
    if (a.is_akan_kedaluwarsa) {
        return { label: 'Akan Kedaluwarsa', cls: 'bg-amber-50 text-amber-800 ring-amber-300' };
    }
    if (a.retensi_kategori === 'permanen') {
        return { label: 'Permanen', cls: 'bg-sky-50 text-sky-800 ring-sky-300' };
    }
    if (a.retensi_kategori) {
        const label = a.retensi_kategori === 'kustom'
            ? `Retensi ${a.retensi_tahun ?? ''} thn`
            : 'Retensi Ringan';
        return { label, cls: 'bg-emerald-50 text-emerald-800 ring-emerald-300' };
    }
    return null;
});

const showRetensiModal = ref(false);
const formRetensi = useForm({ catatan_retensi: '' });
const submittingRetensi = ref(false);

const openRetensiModal = () => {
    formRetensi.catatan_retensi = '';
    formRetensi.clearErrors();
    showRetensiModal.value = true;
};

const submitRetensi = () => {
    submittingRetensi.value = true;
    formRetensi.post(route('arsip.retensi-tindakan', props.arsip.id), {
        preserveScroll: true,
        onSuccess: () => {
            showRetensiModal.value = false;
            formRetensi.reset();
            router.reload({ only: ['arsip', 'flash'] });
        },
        onFinish: () => {
            submittingRetensi.value = false;
        },
    });
};
const fileUrl = computed(() => {
    if (!props.arsip.file_path) return null;
    return isFileEncrypted.value
        ? route('arsip.file', props.arsip.id)
        : `/storage/${props.arsip.file_path}`;
});

const statusPengecekanBadge = computed(() => {
    const map = {
        belum_dicek:      { label: 'Belum Dicek',    cls: 'bg-slate-100 text-slate-700 ring-slate-300' },
        dalam_pengecekan: { label: 'Dalam Pengecekan', cls: 'bg-sky-50 text-sky-800 ring-sky-300' },
        terverifikasi:    { label: 'Terverifikasi',   cls: 'bg-emerald-50 text-emerald-800 ring-emerald-300' },
        perlu_perbaikan:  { label: 'Perlu Perbaikan', cls: 'bg-rose-50 text-rose-800 ring-rose-300' },
    };
    return map[props.arsip.status_pengecekan ?? 'belum_dicek'] ?? map.belum_dicek;
});

const riwayatPengecekan = computed(() => props.arsip.riwayat_pengecekan ?? []);
const showPengecekanModal = ref(false);
const formPengecekan = useForm({
    status_pengecekan: 'dalam_pengecekan',
    catatan: '',
});
const submittingPengecekan = ref(false);

const submitPengecekan = () => {
    submittingPengecekan.value = true;
    formPengecekan.post(route('arsip.pengecekan', props.arsip.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPengecekanModal.value = false;
            formPengecekan.reset();
            router.reload({ only: ['arsip', 'flash'] });
        },
        onFinish: () => {
            submittingPengecekan.value = false;
        },
    });
};

const openPengecekan = (statusDefault) => {
    formPengecekan.status_pengecekan = statusDefault;
    formPengecekan.catatan = '';
    formPengecekan.clearErrors();
    showPengecekanModal.value = true;
};

const RIWAYAT_PREVIEW = 5;
const MONTHS_ID = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

const parseDate = (value) => {
    if (!value) return null;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
};

const formatDate = (value) => {
    const date = parseDate(value);
    if (!date) return null;
    return `${date.getDate()} ${MONTHS_ID[date.getMonth()]} ${date.getFullYear()}`;
};

const formatDateTime = (value) => {
    const date = parseDate(value);
    if (!date) return '—';
    const dd = String(date.getDate()).padStart(2, '0');
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const yyyy = date.getFullYear();
    const hh = String(date.getHours()).padStart(2, '0');
    const min = String(date.getMinutes()).padStart(2, '0');
    return `${dd}/${mm}/${yyyy} ${hh}:${min}`;
};

const formatRelative = (value) => {
    const date = parseDate(value);
    if (!date) return '—';

    const diffMs = Date.now() - date.getTime();
    const diffSec = Math.round(diffMs / 1000);
    const absSec = Math.abs(diffSec);

    if (absSec < 60) return 'baru saja';
    if (absSec < 3600) {
        const m = Math.floor(absSec / 60);
        return `${m} menit lalu`;
    }
    if (absSec < 86400) {
        const h = Math.floor(absSec / 3600);
        return `${h} jam lalu`;
    }
    if (absSec < 86400 * 7) {
        const d = Math.floor(absSec / 86400);
        return `${d} hari lalu`;
    }
    if (absSec < 86400 * 30) {
        const w = Math.floor(absSec / (86400 * 7));
        return `${w} minggu lalu`;
    }

    return formatDate(value);
};

const statusBadge = computed(() => {
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

    return map[props.arsip.status_arsip] || map.tersedia;
});

const hasPosisiDetail = computed(() =>
    Boolean(props.arsip.boks?.kode_boks || props.arsip.boks?.nomor_baris || props.arsip.boks?.nomor_boks),
);

const identitasFields = computed(() => {
    const rows = [
        { label: 'Nomor Surat', value: props.arsip.nomor_surat },
        { label: 'Jenis', value: props.arsip.jenis_surat, capitalize: true },
        { label: 'Seksi', value: props.arsip.seksi?.nama_seksi },
        { label: 'Tanggal Surat', value: formatDate(props.arsip.tanggal_surat) },
    ];
    return rows.filter((row) => row.value);
});

const penyimpananFields = computed(() => {
    const rows = [];

    if (props.arsip.rak?.kode_rak) {
        rows.push({
            label: 'Rak Penyimpanan',
            value: props.arsip.rak.kode_rak,
            hint: null,
        });
    }

    if (props.arsip.boks) {
        rows.push({
            label: 'Boks Penyimpanan',
            value: props.arsip.boks.kode_boks,
            hint: [
                props.arsip.boks.nomor_baris ? `Baris ${props.arsip.boks.nomor_baris}` : null,
                props.arsip.boks.nomor_boks ? `Boks ${props.arsip.boks.nomor_boks}` : null,
            ].filter(Boolean).join(' · ') || null,
        });
    }

    if (props.arsip.catatan) {
        rows.push({ label: 'Catatan', value: props.arsip.catatan });
    }

    return rows;
});

const instansiFields = computed(() => {
    const rows = [
        { label: 'Asal Instansi', value: props.arsip.asal_instansi },
        { label: 'Tujuan Instansi', value: props.arsip.tujuan_instansi },
    ];
    return rows.filter((row) => row.value);
});

const allRiwayat = computed(() => props.arsip.riwayat_sirkulasi ?? []);

const visibleRiwayat = computed(() => {
    if (showAllRiwayat.value) {
        return allRiwayat.value;
    }
    return allRiwayat.value.slice(0, RIWAYAT_PREVIEW);
});

const hiddenRiwayatCount = computed(() =>
    Math.max(0, allRiwayat.value.length - RIWAYAT_PREVIEW),
);

const aksiLabel = (aksi) => {
    const map = {
        dibuat: 'Dibuat',
        dipindai: 'Dipindai',
        dipinjam: 'Dipinjam',
        dikembalikan: 'Dikembalikan',
        diarsipkan: 'Diarsipkan',
    };
    return map[aksi] || aksi;
};

const openPinjam = () => {
    sirkulasiMode.value = 'pinjam';
    showSirkulasiModal.value = true;
};

const openKembalikan = () => {
    sirkulasiMode.value = 'kembalikan';
    showSirkulasiModal.value = true;
};
</script>

<template>
    <Head :title="`Arsip ${arsip.kode_arsip}`" />

    <AppLayout>
        <!-- Flash notifications -->
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

        <template #skeleton-header>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <div class="h-6 w-32 rounded bg-slate-200" />
                        <div class="h-6 w-20 rounded-full bg-slate-200" />
                    </div>
                    <div class="mt-2 h-4 w-48 rounded bg-slate-200" />
                </div>
                <div class="h-8 w-20 rounded-lg bg-slate-200" />
            </div>
        </template>

        <template #skeleton>
            <div class="mx-auto grid max-w-5xl gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] lg:items-start">
                <div class="rounded-2xl bg-slate-100">
                    <div class="space-y-4 p-6">
                        <div v-for="i in 3" :key="'sk-sec-' + i" class="space-y-2">
                            <div class="h-3 w-32 rounded bg-slate-200" />
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="h-4 w-full rounded bg-slate-200" />
                                <div class="h-4 w-full rounded bg-slate-200" />
                                <div class="h-4 w-full rounded bg-slate-200" />
                                <div class="h-4 w-full rounded bg-slate-200" />
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 border-t border-slate-200/50 pt-4">
                            <div class="h-9 w-20 rounded-xl bg-slate-200" />
                            <div class="h-9 w-24 rounded-xl bg-slate-200" />
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl bg-slate-100 p-6">
                    <div class="h-4 w-32 rounded bg-slate-200" />
                    <div class="mt-4 space-y-3">
                        <div v-for="i in 4" :key="'sk-hist-' + i" class="h-14 rounded bg-slate-200" />
                    </div>
                </div>
            </div>
        </template>

        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="truncate text-xl font-bold text-slate-900 sm:text-2xl">
                            {{ arsip.kode_arsip }}
                        </h1>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold ring-1 ring-inset sm:text-sm"
                            :class="statusBadge.class"
                        >
                            <span class="h-2 w-2 rounded-full" :class="statusBadge.dot" />
                            {{ statusBadge.label }}
                        </span>
                        <!-- Tahap 4: Badge klasifikasi dokumen khusus -->
                        <span
                            v-if="isDokumenKhusus"
                            class="inline-flex items-center gap-1.5 rounded-full bg-violet-50 px-3 py-1 text-xs font-bold text-violet-800 ring-1 ring-violet-300 sm:text-sm"
                        >
                            {{ arsip.jenis_dokumen === 'surat_tanah' ? 'Surat Tanah' : 'Surat Ahli Waris' }}
                        </span>
                        <!-- Tahap 4: Badge status pengecekan -->
                        <span
                            v-if="isDokumenKhusus"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold ring-1 ring-inset sm:text-sm"
                            :class="statusPengecekanBadge.cls"
                        >
                            {{ statusPengecekanBadge.label }}
                        </span>
                        <!-- Tahap 5: Badge terenkripsi -->
                        <span
                            v-if="isFileEncrypted"
                            class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-200"
                        >
                            <AppIcon name="shield" :size="12" :stroke-width="2" decorative />
                            Terenkripsi AES-256
                        </span>
                        <!-- Tahap 6: Badge retensi -->
                        <span
                            v-if="retensiBadge"
                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                            :class="retensiBadge.cls"
                        >
                            <AppIcon name="clock" :size="12" :stroke-width="2" decorative />
                            {{ retensiBadge.label }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ arsip.perihal || arsip.nomor_surat || 'Detail dokumen arsip' }}
                    </p>
                </div>
                <BackLink class="shrink-0" :use-history="true" />
            </div>
        </template>

        <div class="mx-auto grid max-w-5xl gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] lg:items-start">
            <div v-reveal class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <section v-if="identitasFields.length">
                    <h2 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                        Identitas Surat
                    </h2>
                    <dl class="mt-3 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                        <div v-for="field in identitasFields" :key="field.label">
                            <dt class="text-slate-500">{{ field.label }}</dt>
                            <dd
                                class="mt-0.5 font-medium text-slate-800"
                                :class="field.capitalize ? 'capitalize' : ''"
                            >
                                {{ field.value }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section v-if="penyimpananFields.length" class="mt-6 border-t border-slate-100 pt-5">
                    <h2 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                        Penyimpanan
                    </h2>
                    <dl class="mt-3 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                        <div v-for="field in penyimpananFields" :key="field.label">
                            <dt class="text-slate-500">{{ field.label }}</dt>
                            <dd class="mt-0.5 font-medium text-slate-800">
                                {{ field.value }}
                                <span v-if="field.hint" class="block text-xs font-normal text-slate-500">
                                    {{ field.hint }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section v-if="instansiFields.length" class="mt-6 border-t border-slate-100 pt-5">
                    <h2 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                        Instansi
                    </h2>
                    <dl class="mt-3 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                        <div v-for="field in instansiFields" :key="field.label">
                            <dt class="text-slate-500">{{ field.label }}</dt>
                            <dd class="mt-0.5 font-medium text-slate-800">{{ field.value }}</dd>
                        </div>
                    </dl>
                </section>

                <!-- Tahap 4: Card Data Profiling / Biografi -->
                <section v-if="isDokumenKhusus" class="mt-6 border-t border-amber-200/60 pt-5">
                    <div class="flex items-center gap-2">
                        <AppIcon name="key" class="h-4 w-4 text-amber-600" decorative />
                        <h2 class="text-[11px] font-semibold uppercase tracking-[0.12em] text-amber-700">
                            Data Profiling / Biografi
                        </h2>
                    </div>
                    <dl class="mt-3 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-slate-500">Nama Pemilik / Pewaris</dt>
                            <dd class="mt-0.5 font-medium text-slate-800">{{ arsip.nama_pemilik || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">NIK</dt>
                            <dd class="mt-0.5 font-medium text-slate-800">{{ arsip.nik_pemilik || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Nomor Alas Hak / Surat</dt>
                            <dd class="mt-0.5 font-medium text-slate-800">{{ arsip.nomor_alas_hak || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Alamat / Lokasi Objek</dt>
                            <dd class="mt-0.5 font-medium text-slate-800">{{ arsip.alamat_objek || '—' }}</dd>
                        </div>
                        <div v-if="arsip.keterangan_profiling" class="col-span-full sm:col-span-2">
                            <dt class="text-slate-500">Keterangan Tambahan</dt>
                            <dd class="mt-0.5 whitespace-pre-line font-medium text-slate-800">{{ arsip.keterangan_profiling }}</dd>
                        </div>
                    </dl>
                </section>

                <div class="mt-6 flex flex-wrap gap-2 border-t border-slate-100 pt-5">
                    <button
                        type="button"
                        class="inline-flex rounded-xl bg-brand-blue px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
                        @click="openEdit"
                    >
                        Edit
                    </button>
                    <button
                        v-if="arsip.status_arsip === 'tersedia'"
                        type="button"
                        class="inline-flex rounded-xl border border-brand-blue/30 bg-white px-4 py-2 text-sm font-semibold text-brand-blue hover:bg-blue-50"
                        @click="openPinjam"
                    >
                        Catat Pinjam
                    </button>
                    <button
                        v-if="arsip.status_arsip === 'dipinjam'"
                        type="button"
                        class="inline-flex rounded-xl border border-brand-blue/30 bg-white px-4 py-2 text-sm font-semibold text-brand-blue hover:bg-blue-50"
                        @click="openKembalikan"
                    >
                        Kembalikan
                    </button>
                    <a
                        v-if="fileUrl"
                        :href="fileUrl"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900"
                    >
                        <AppIcon v-if="isFileEncrypted" name="shield" :size="14" :stroke-width="1.8" decorative />
                        {{ isFileEncrypted ? 'Unduh PDF (Aman)' : 'Lihat Lampiran' }}
                    </a>
                    <!-- Tahap 4: Tombol Cetak Lembar Arsip (dokumen khusus only) -->
                    <a
                        v-if="isDokumenKhusus"
                        :href="route('arsip.print', arsip.id)"
                        target="_blank"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                    >
                        <AppIcon name="printer" :size="16" :stroke-width="1.8" decorative />
                        Cetak Lembar Arsip
                    </a>
                </div>
            </div>

            <div class="space-y-4">
                <!-- Tahap 6: Panel Peringatan Retensi -->
                <div
                    v-if="arsip.is_kedaluwarsa || arsip.is_akan_kedaluwarsa"
                    v-reveal
                    class="rounded-2xl p-5 shadow-sm"
                    :class="arsip.is_kedaluwarsa ? 'border border-rose-200 bg-rose-50/60' : 'border border-amber-200 bg-amber-50/60'"
                >
                    <div class="flex items-start gap-3">
                        <AppIcon
                            name="alert-triangle"
                            :size="20"
                            :stroke-width="1.8"
                            decorative
                            :class="arsip.is_kedaluwarsa ? 'text-rose-500' : 'text-amber-500'"
                        />
                        <div class="flex-1">
                            <h3
                                class="text-sm font-bold"
                                :class="arsip.is_kedaluwarsa ? 'text-rose-800' : 'text-amber-800'"
                            >
                                {{ arsip.is_kedaluwarsa ? 'Masa Aktif Retensi Berakhir' : 'Masa Aktif Retensi Akan Berakhir' }}
                            </h3>
                            <p
                                class="mt-1 text-sm leading-6"
                                :class="arsip.is_kedaluwarsa ? 'text-rose-700' : 'text-amber-800'"
                            >
                                <template v-if="arsip.is_kedaluwarsa">
                                    Dokumen ini telah melewati tanggal retensi ({{ formatDate(arsip.tanggal_kedaluwarsa) }}).
                                    Pertimbangkan untuk mengambil tindakan: pertahankan retensi atau arsipkan secara permanen.
                                </template>
                                <template v-else>
                                    Retensi dokumen ini akan berakhir pada <strong>{{ formatDate(arsip.tanggal_kedaluwarsa) }}</strong>.
                                    Pertimbangkan apakah dokumen ini masih diperlukan secara aktif.
                                </template>
                            </p>

                            <div v-if="arsip.retensi_dipertahankan" class="mt-2 flex items-center gap-2 text-xs text-blue-700">
                                <AppIcon name="check-circle" :size="14" :stroke-width="1.8" decorative />
                                Retensi telah diperpanjang oleh {{ arsip.retensi_ditindak_oleh }}
                                <template v-if="arsip.retensi_ditindak_waktu"> ({{ formatDate(arsip.retensi_ditindak_waktu) }})</template>
                            </div>

                            <div v-if="arsip.catatan_retensi" class="mt-2 rounded-lg bg-white/60 px-3 py-2 text-xs text-slate-600">
                                Catatan: {{ arsip.catatan_retensi }}
                            </div>

                            <button
                                v-if="!arsip.retensi_dipertahankan"
                                type="button"
                                class="mt-3 inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
                                @click="openRetensiModal"
                            >
                                <AppIcon name="clock" :size="16" :stroke-width="1.8" decorative />
                                Pertahankan Retensi
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tahap 4: Panel Riwayat & Proses Pengecekan -->
                <div v-if="isDokumenKhusus" v-reveal class="rounded-2xl border border-amber-200 bg-amber-50/40 p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <AppIcon name="check-circle" class="h-4 w-4 text-amber-600" decorative />
                            <h3 class="text-sm font-semibold text-amber-900">Riwayat & Proses Pengecekan</h3>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg border border-amber-300 bg-white px-2.5 py-1 text-xs font-semibold text-amber-800 hover:bg-amber-50"
                            @click="openPengecekan('dalam_pengecekan')"
                        >
                            + Tambah Catatan
                        </button>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="opt in [
                                { val: 'dalam_pengecekan', label: 'Mulai Pengecekan' },
                                { val: 'terverifikasi',    label: 'Terverifikasi' },
                                { val: 'perlu_perbaikan',  label: 'Perlu Perbaikan' },
                            ]"
                            :key="opt.val"
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset transition"
                            :class="{
                                'bg-sky-100 text-sky-800 ring-sky-300': opt.val === 'dalam_pengecekan',
                                'bg-emerald-100 text-emerald-800 ring-emerald-300': opt.val === 'terverifikasi',
                                'bg-rose-100 text-rose-800 ring-rose-300': opt.val === 'perlu_perbaikan',
                            }"
                            @click="openPengecekan(opt.val)"
                        >
                            {{ opt.label }}
                        </button>
                    </div>

                    <ul v-if="riwayatPengecekan.length" class="mt-4 divide-y divide-amber-200/50">
                        <li v-for="item in riwayatPengecekan" :key="item.id" class="py-3 text-sm">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-bold ring-1 ring-inset"
                                            :class="{
                                                'bg-slate-100 text-slate-700 ring-slate-300': item.status_sebelum === 'belum_dicek',
                                                'bg-sky-100 text-sky-800 ring-sky-300': item.status_sebelum === 'dalam_pengecekan',
                                                'bg-emerald-100 text-emerald-800 ring-emerald-300': item.status_sebelum === 'terverifikasi',
                                                'bg-rose-100 text-rose-800 ring-rose-300': item.status_sebelum === 'perlu_perbaikan',
                                            }"
                                        >
                                            {{ {
                                                belum_dicek: 'Belum Dicek',
                                                dalam_pengecekan: 'Dalam Pengecekan',
                                                terverifikasi: 'Terverifikasi',
                                                perlu_perbaikan: 'Perlu Perbaikan',
                                            }[item.status_sebelum] || 'Belum Dicek' }}
                                        </span>
                                        <span class="text-xs text-slate-400">→</span>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-bold ring-1 ring-inset"
                                            :class="{
                                                'bg-slate-100 text-slate-700 ring-slate-300': item.status_sesudah === 'belum_dicek',
                                                'bg-sky-100 text-sky-800 ring-sky-300': item.status_sesudah === 'dalam_pengecekan',
                                                'bg-emerald-100 text-emerald-800 ring-emerald-300': item.status_sesudah === 'terverifikasi',
                                                'bg-rose-100 text-rose-800 ring-rose-300': item.status_sesudah === 'perlu_perbaikan',
                                            }"
                                        >
                                            {{ {
                                                belum_dicek: 'Belum Dicek',
                                                dalam_pengecekan: 'Dalam Pengecekan',
                                                terverifikasi: 'Terverifikasi',
                                                perlu_perbaikan: 'Perlu Perbaikan',
                                            }[item.status_sesudah] || item.status_sesudah }}
                                        </span>
                                    </div>
                                    <p v-if="item.catatan" class="mt-1.5 whitespace-pre-line text-xs text-slate-600">{{ item.catatan }}</p>
                                    <p class="mt-1 text-xs text-slate-400">oleh {{ item.user?.name || '—' }}</p>
                                </div>
                                <time class="shrink-0 text-xs text-slate-500" :title="formatDateTime(item.created_at)">
                                    {{ formatRelative(item.created_at) }}
                                </time>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm leading-6 text-slate-500">
                        Belum ada catatan pengecekan. Klik "Tambah Catatan" untuk memulai.
                    </p>
                </div>

                <div v-reveal :style="{ transitionDelay: '100ms' }" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:sticky lg:top-4">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-slate-800">Riwayat Sirkulasi</h3>
                    <span v-if="allRiwayat.length" class="text-xs text-slate-400">
                        {{ allRiwayat.length }} entri
                    </span>
                </div>

                <ul v-if="visibleRiwayat.length" class="mt-3 divide-y divide-slate-100">
                    <li
                        v-for="item in visibleRiwayat"
                        :key="item.id"
                        class="flex flex-wrap items-start justify-between gap-2 py-3 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-800">{{ aksiLabel(item.jenis_aksi) }}</p>
                            <p v-if="item.pihak_terkait" class="text-slate-600">{{ item.pihak_terkait }}</p>
                            <p v-if="item.keterangan" class="text-xs text-slate-500">{{ item.keterangan }}</p>
                            <p class="mt-0.5 text-xs text-slate-400">oleh {{ item.user?.name || '—' }}</p>
                        </div>
                        <time
                            class="shrink-0 text-xs text-slate-500"
                            :title="formatDateTime(item.waktu_aksi)"
                        >
                            {{ formatRelative(item.waktu_aksi) }}
                        </time>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm leading-7 text-slate-500">
                    Belum ada riwayat peminjaman untuk dokumen ini.
                </p>

                <button
                    v-if="hiddenRiwayatCount > 0"
                    type="button"
                    class="mt-3 text-sm font-semibold text-brand-blue hover:underline"
                    @click="showAllRiwayat = !showAllRiwayat"
                >
                    {{ showAllRiwayat ? 'Tampilkan lebih sedikit' : `Lihat semua (${hiddenRiwayatCount} lainnya)` }}
                </button>
                </div>
            </div>
        </div>
    </AppLayout>

    <SirkulasiModal
        :show="showSirkulasiModal"
        :arsip="arsip"
        :mode="sirkulasiMode"
        @close="showSirkulasiModal = false"
    />

    <ArsipFormModal
        :show="showEditModal"
        mode="edit"
        :seksi-list="seksiList"
        :rak-list="rakList"
        :boks-list="boksList"
        :initial-data="editingArsip"
        :on-finish="onEditSaved"
        @close="showEditModal = false"
    />

    <!-- Tahap 6: Modal Pertahankan Retensi -->
    <Modal :show="showRetensiModal" max-width="md" @close="showRetensiModal = false">
        <form
            v-if="showRetensiModal"
            class="p-6"
            @submit.prevent="submitRetensi"
        >
            <div class="flex items-center gap-2">
                <AppIcon name="clock" :size="18" :stroke-width="1.8" class="text-blue-500" decorative />
                <h2 class="text-lg font-bold text-slate-900">Pertahankan Retensi</h2>
            </div>
            <p class="mt-1 text-sm text-brand-blue">{{ arsip.kode_arsip }}</p>
            <p class="mt-2 text-sm text-slate-600">
                Dengan mengambil tindakan ini, dokumen akan ditandai sebagai "Retensi Dipertahankan" dan
                tidak akan ditampilkan sebagai kedaluwarsa lagi. Tindakan ini tercatat di log aktivitas.
            </p>

            <div class="mt-4">
                <label for="retensi_catatan" class="block text-sm font-medium text-slate-700">
                    Alasan / Catatan (opsional)
                </label>
                <textarea
                    id="retensi_catatan"
                    v-model="formRetensi.catatan_retensi"
                    rows="3"
                    class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500/30"
                    placeholder="Contoh: Dokumen diperlukan untuk audit 2026, dipertahankan hingga 2027."
                />
                <p v-if="formRetensi.errors.catatan_retensi" class="mt-1 text-xs text-rose-600">{{ formRetensi.errors.catatan_retensi }}</p>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                    @click="showRetensiModal = false"
                >
                    Batal
                </button>
                <PrimaryButton class="bg-blue-600" :disabled="submittingRetensi">
                    {{ submittingRetensi ? 'Menyimpan…' : 'Pertahankan' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>

    <!-- Tahap 4: Modal Pengecekan Dokumen Khusus -->
    <Modal :show="showPengecekanModal" max-width="md" @close="showPengecekanModal = false">
        <form
            v-if="isDokumenKhusus"
            class="p-6"
            @submit.prevent="submitPengecekan"
        >
            <h2 class="text-lg font-bold text-slate-900">Catatan Pengecekan</h2>
            <p class="mt-1 text-sm text-brand-blue">{{ arsip.kode_arsip }} — {{ arsip.nama_pemilik || arsip.perihal }}</p>
            <p class="mt-2 text-sm text-slate-600">
                Pilih status pengecekan dan tambahkan catatan bila diperlukan.
            </p>

            <div class="mt-4">
                <label for="pengecekan_status" class="block text-sm font-medium text-slate-700">Status Pengecekan</label>
                <AppSelect
                    id="pengecekan_status"
                    v-model="formPengecekan.status_pengecekan"
                    class="mt-1 block w-full"
                    label="Status pengecekan arsip"
                    :options="[
                        { value: 'dalam_pengecekan', label: 'Dalam Pengecekan' },
                        { value: 'terverifikasi', label: 'Terverifikasi' },
                        { value: 'perlu_perbaikan', label: 'Perlu Perbaikan' },
                        { value: 'belum_dicek', label: 'Belum Dicek (reset)' },
                    ]"
                />
                <p v-if="formPengecekan.errors.status_pengecekan" class="mt-1 text-xs text-red-600">{{ formPengecekan.errors.status_pengecekan }}</p>
            </div>

            <div class="mt-4">
                <label for="pengecekan_catatan" class="block text-sm font-medium text-slate-700">Catatan Pengecekan</label>
                <textarea
                    id="pengecekan_catatan"
                    v-model="formPengecekan.catatan"
                    rows="3"
                    class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                    placeholder="Contoh: Dokumen lengkap, tanda tangan lurah sudah ada, nomor alas hak sesuai sertifikat."
                />
                <p v-if="formPengecekan.errors.catatan" class="mt-1 text-xs text-red-600">{{ formPengecekan.errors.catatan }}</p>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                    @click="showPengecekanModal = false"
                >
                    Batal
                </button>
                <PrimaryButton class="bg-brand-blue" :disabled="submittingPengecekan">
                    {{ submittingPengecekan ? 'Menyimpan…' : 'Simpan Pengecekan' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
