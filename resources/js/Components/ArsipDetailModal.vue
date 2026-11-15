<script setup>
import ActionButtons from '@/Components/ActionButtons.vue';
import Modal from '@/Components/Modal.vue';
import { posisiLokasi } from '@/utils/posisiLokasi';
import { computed } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    arsip: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'edit', 'label', 'pinjam', 'kembalikan', 'settersedia', 'tandahilang']);

const formatDate = (value) => (value ? String(value).substring(0, 10) : '—');

const titikRakLabel = computed(() => posisiLokasi(props.arsip) || 'Belum diisi');

const pihakLabel = computed(() => {
    if (!props.arsip) return '—';
    if (props.arsip.jenis_surat === 'masuk') {
        return props.arsip.asal_instansi || '—';
    }
    return props.arsip.tujuan_instansi || '—';
});

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
    return map[props.arsip?.status_arsip] || map.tersedia;
});

const seksiCode = computed(() => {
    const nama = props.arsip?.seksi?.nama_seksi?.toLowerCase() || '';
    if (nama.includes('pemerintahan')) return 'SEKSI PEM';
    if (nama.includes('ekonomi')) return 'SEKSI EKB';
    if (nama.includes('kesejahteraan')) return 'SEKSI KSR';
    return 'SEKSI';
});

// Tahap 5: file terenkripsi → endpoint terkontrol; publik → /storage/
const fileUrl = computed(() => {
    if (!props.arsip?.file_path) return null;
    return props.arsip.is_encrypted
        ? route('arsip.file', props.arsip.id)
        : `/storage/${props.arsip.file_path}`;
});

// Tahap 6: badge retensi ringan
const retensiBadge = computed(() => {
    const a = props.arsip;
    if (!a) return null;
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
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div v-if="arsip" class="max-h-[85vh] overflow-y-auto">
            <div class="border-b border-slate-100 px-6 py-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-700 ring-1 ring-inset ring-slate-200">
                            {{ seksiCode }}
                        </span>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-bold text-slate-900">{{ arsip.nomor_surat }}</h2>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset"
                                :class="statusBadge.class"
                            >
                                <span class="h-2 w-2 rounded-full" :class="statusBadge.dot" />
                                {{ statusBadge.label }}
                            </span>
                            <span
                                v-if="retensiBadge"
                                class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold ring-1 ring-inset"
                                :class="retensiBadge.cls"
                            >
                                <AppIcon name="clock" :size="12" :stroke-width="1.8" decorative />
                                {{ retensiBadge.label }}
                            </span>
                        </div>
                        <p class="mt-1 font-medium text-slate-600">{{ arsip.kode_arsip }}</p>
                    </div>
                    <button type="button" class="shrink-0 rounded-md p-1 text-slate-400 hover:bg-slate-100" @click="emit('close')">
                        <AppIcon name="close" :size="20" :stroke-width="2" class="h-5 w-5" decorative />
                    </button>
                </div>
            </div>

            <div class="space-y-5 px-6 py-5">
                <div class="rounded-xl bg-slate-50 p-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs text-slate-500">Nama Pemohon / Pihak Terkait</p>
                            <p class="mt-1 font-semibold text-slate-800">{{ pihakLabel }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Tanggal Surat Dokumen</p>
                            <p class="mt-1 font-semibold text-slate-800">{{ formatDate(arsip.tanggal_surat) }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-xs text-slate-500">Perihal Dokumen</p>
                            <p class="mt-1 font-semibold text-slate-800">{{ arsip.perihal }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Titik Rak Lemari Fisik</p>
                            <span class="mt-1 inline-flex rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-200">
                                {{ titikRakLabel }}
                            </span>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <AppIcon name="file" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
                        Lampiran Salinan Scan Dokumen
                    </h3>
                    <div
                        v-if="arsip.file_path"
                        class="flex min-h-28 items-center justify-center rounded-xl bg-slate-100 text-center"
                    >
                        <a
                            :href="fileUrl"
                            target="_blank"
                            class="inline-flex items-center gap-2 rounded-xl bg-brand-blue px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                        >
                            <AppIcon v-if="arsip.is_encrypted" name="shield" :size="14" :stroke-width="2" decorative />
                            {{ arsip.is_encrypted ? 'Unduh PDF (Aman)' : 'Lihat Lampiran' }}
                        </a>
                    </div>
                    <div
                        v-else
                        class="flex min-h-28 items-center justify-center rounded-xl bg-slate-100 text-center text-sm text-slate-500"
                    >
                        Tidak ada salinan file digital yang dilampirkan.
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-6 py-4">
                <ActionButtons
                    :arsip="arsip"
                    :show-view="false"
                    :show-label-in-menu="false"
                    :show-dropdown="false"
                    @edit="emit('edit', $event)"
                    @label="emit('label', $event)"
                    @pinjam="emit('pinjam', $event)"
                    @kembalikan="emit('kembalikan', $event)"
                    @settersedia="emit('settersedia', $event)"
                    @tandahilang="emit('tandahilang', $event)"
                />
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="emit('close')"
                    >
                        Tutup
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                        @click="emit('label', arsip)"
                    >
                        Cetak Label
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
