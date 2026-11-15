<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Head } from '@inertiajs/vue3';
import { institution } from '@/config/institution';
import { computed } from 'vue';

const props = defineProps({
    arsip: {
        type: Object,
        required: true,
    },
});

const arsip = computed(() => props.arsip);

const jenisLabel = computed(() => {
    const map = {
        surat_tanah: 'Surat Tanah',
        ahli_waris: 'Surat Ahli Waris',
    };
    return map[arsip.value.jenis_dokumen] ?? 'Dokumen Khusus';
});

const statusPengecekanLabel = computed(() => {
    const map = {
        belum_dicek: 'Belum Dicek',
        dalam_pengecekan: 'Dalam Pengecekan',
        terverifikasi: 'Terverifikasi',
        perlu_perbaikan: 'Perlu Perbaikan',
    };
    return map[arsip.value.status_pengecekan ?? 'belum_dicek'] ?? 'Belum Dicek';
});

const riwayatTertinggi = computed(() =>
    (arsip.value.riwayat_pengecekan ?? []).slice(0, 3),
);

const posisiTeks = computed(() => {
    const rak = arsip.value.rak?.nama_rak || arsip.value.rak?.kode_rak || 'Belum diketahui';
    const boks = arsip.value.boks
        ? [
            arsip.value.boks.kode_boks,
            arsip.value.boks.nomor_baris ? `Baris ${arsip.value.boks.nomor_baris}` : null,
            arsip.value.boks.nomor_boks ? `Boks ${arsip.value.boks.nomor_boks}` : null,
        ]
            .filter(Boolean)
            .join(' / ')
        : 'Belum diketahui';
    const extra = arsip.value.keterangan_posisi || arsip.value.catatan;
    return [rak, boks, extra].filter(Boolean).join(' · ');
});

const tanggalSekarang = computed(() => {
    const d = new Date();
    const bulan = [
        'Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober','November','Desember',
    ];
    return `${d.getDate()} ${bulan[d.getMonth()]} ${d.getFullYear()}`;
});

const formatTanggal = (value) => {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    const bulan = [
        'Jan','Feb','Mar','Apr','Mei','Jun',
        'Jul','Agu','Sep','Okt','Nov','Des',
    ];
    return `${d.getDate()} ${bulan[d.getMonth()]} ${d.getFullYear()}`;
};

const doPrint = () => window.print();
</script>

<template>
    <Head :title="`Cetak Lembar Arsip ${arsip.kode_arsip}`" />

    <AppLayout>
        <!-- Tombol trigger cetak (disembunyikan saat print) -->
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between print:hidden">
            <div class="min-w-0">
                <h1 class="text-lg font-bold text-slate-900">
                    Cetak Lembar Arsip — {{ arsip.kode_arsip }}
                </h1>
                <p class="text-sm text-slate-500">
                    Halaman ini dirancang untuk dicetak. Gunakan tombol di bawah.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-navy px-5 py-3 text-sm font-bold text-white shadow-md transition hover:bg-[#122a4a]"
                @click="doPrint"
            >
                <AppIcon name="printer" :size="16" :stroke-width="1.8" decorative />
                Cetak Sekarang
            </button>
        </div>

        <!-- Lembar Arsip -->
        <div
            id="lembar-arsip"
            class="overflow-x-auto rounded-2xl border-2 border-brand-navy bg-white shadow-lg"
            style="print: border: 0; margin: 0; box-shadow: none;"
        >
            <!-- Kop Surat -->
            <div class="border-b-2 border-brand-navy bg-white">
                <div class="flex items-start gap-4 px-6 py-5">
                    <img
                        :src="institution.logoPath"
                        alt="Logo Kelurahan"
                        class="h-20 w-20 shrink-0 object-contain"
                    />
                    <div class="flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            {{ institution.city }}
                        </p>
                        <p class="text-base font-bold text-brand-navy">
                            KELURAHAN GROGOL SELATAN
                        </p>
                        <p class="text-xs text-slate-600">
                            Tata Usaha & Kearsipan · {{ institution.systemName }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-medium text-slate-500">{{ tanggalSekarang }}</p>
                        <p class="mt-0.5 text-xs text-slate-400">Dokumen resmi</p>
                    </div>
                </div>
                <div class="flex border-t-2 border-brand-navy px-6 py-2">
                    <div class="w-1/3 text-center">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-brand-navy">Kepala Lurah</p>
                    </div>
                    <div class="w-1/3 text-center">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-brand-navy">Petugas Arsip</p>
                    </div>
                    <div class="w-1/3 text-center">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-brand-navy">Mengetahui</p>
                    </div>
                </div>
            </div>

            <!-- Header Dokumen -->
            <div class="grid grid-cols-2 gap-0 border-b border-slate-300 bg-slate-50">
                <div class="px-6 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Kode Arsip</p>
                    <p class="mt-1 text-lg font-bold tracking-tight text-brand-navy">{{ arsip.kode_arsip }}</p>
                </div>
                <div class="border-l border-slate-300 px-6 py-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Klasifikasi Dokumen</p>
                    <p class="mt-1 text-sm font-semibold text-slate-800">{{ jenisLabel }}</p>
                </div>
            </div>

            <!-- Detail Penyimpanan -->
            <div class="border-b border-slate-300 px-6 py-4">
                <h3 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    Lokasi Penyimpanan
                </h3>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-800">
                    <strong>Gedung:</strong> Gedung Kelurahan Grogol Selatan<br>
                    <strong>Rak / Boks:</strong> {{ posisiTeks || 'Belum diketahui' }}
                </p>
            </div>

            <!-- Data Profiling / Biografi -->
            <div class="border-b border-slate-300 px-6 py-4">
                <h3 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    Data Profiling / Biografi
                </h3>
                <table class="mt-2 w-full text-sm">
                    <tbody>
                        <tr class="border-b border-slate-100">
                            <td class="w-1/3 py-1.5 pr-4 text-slate-500">Nama Pemilik / Pewaris</td>
                            <td class="py-1.5 font-semibold text-slate-800">{{ arsip.nama_pemilik || '—' }}</td>
                        </tr>
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5 pr-4 text-slate-500">NIK</td>
                            <td class="py-1.5 font-semibold text-slate-800">{{ arsip.nik_pemilik || '—' }}</td>
                        </tr>
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5 pr-4 text-slate-500">Nomor Alas Hak / Surat</td>
                            <td class="py-1.5 font-semibold text-slate-800">{{ arsip.nomor_alas_hak || '—' }}</td>
                        </tr>
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5 pr-4 text-slate-500">Alamat / Lokasi Objek</td>
                            <td class="py-1.5 font-semibold text-slate-800">
                                <span v-if="arsip.alamat_objek">{{ arsip.alamat_objek }}</span>
                                <span v-else class="text-slate-400">Belum tercatat</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-1.5 pr-4 text-slate-500">Keterangan Tambahan</td>
                            <td class="py-1.5 font-semibold text-slate-800">
                                <span v-if="arsip.keterangan_profiling">{{ arsip.keterangan_profiling }}</span>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Status Pengecekan & Riwayat -->
            <div class="border-b border-slate-300 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                        Status Pengecekan Terakhir
                    </h3>
                    <span
                        class="rounded-full px-3 py-0.5 text-xs font-bold"
                        :class="{
                            'bg-slate-100 text-slate-700': arsip.status_pengecekan === 'belum_dicek',
                            'bg-sky-100 text-sky-800': arsip.status_pengecekan === 'dalam_pengecekan',
                            'bg-emerald-100 text-emerald-800': arsip.status_pengecekan === 'terverifikasi',
                            'bg-rose-100 text-rose-800': arsip.status_pengecekan === 'perlu_perbaikan',
                        }"
                    >
                        {{ statusPengecekanLabel }}
                    </span>
                </div>

                <div v-if="riwayatTertinggi.length" class="mt-3 space-y-2">
                    <div
                        v-for="item in riwayatTertinggi"
                        :key="item.id"
                        class="flex items-start gap-2 rounded-lg bg-slate-50 px-3 py-2 text-xs"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-slate-700">
                                {{ item.user?.name || 'Petugas' }}
                            </p>
                            <p v-if="item.catatan" class="mt-0.5 whitespace-pre-line text-slate-500">{{ item.catatan }}</p>
                        </div>
                        <time class="shrink-0 text-slate-400">{{ formatTanggal(item.created_at) }}</time>
                    </div>
                </div>
                <p v-else class="mt-2 text-xs text-slate-400">Belum ada catatan pengecekan.</p>
            </div>

            <!-- Footer Lembar -->
            <div class="bg-brand-navy px-6 py-3 text-center text-white">
                <p class="text-xs font-medium">
                    {{ institution.identityLine }}
                </p>
                <p class="mt-0.5 text-[10px] text-slate-300">
                    Cetak Lembar Arsip · Dibuat oleh {{ institution.systemName }} · {{ tanggalSekarang }}
                </p>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    #lembar-arsip {
        border: none !important;
        box-shadow: none !important;
        margin: 0 !important;
    }
    .print\:hidden {
        display: none !important;
    }
}
</style>
