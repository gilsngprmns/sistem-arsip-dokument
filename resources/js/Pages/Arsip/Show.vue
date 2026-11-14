<script setup>
import BackLink from '@/Components/BackLink.vue';
import ArsipFormModal from '@/Components/ArsipFormModal.vue';
import SirkulasiModal from '@/Components/SirkulasiModal.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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
                            <div class="grid grid-cols-2 gap-3">
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
                        v-if="arsip.file_path"
                        :href="`/storage/${arsip.file_path}`"
                        target="_blank"
                        class="inline-flex rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900"
                    >
                        Lihat Lampiran
                    </a>
                </div>
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
</template>
