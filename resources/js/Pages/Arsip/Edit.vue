<script setup>
import BackLink from '@/Components/BackLink.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppSelect from '@/Components/AppSelect.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();

// Seksi yang dikelola user (staff). Admin = null (bebas memilih semua).
const mySeksiId = computed(() => {
    const u = page.props.auth?.user;
    return u?.role !== 'admin' ? Number(u.seksi_id) : null;
});
const isSeksiOptionDisabled = (id) => mySeksiId.value !== null && Number(id) !== mySeksiId.value;

const props = defineProps({
    arsip: {
        type: Object,
        required: true,
    },
    seksiList: {
        type: Array,
        required: true,
    },
    rakList: {
        type: Array,
        default: () => [],
    },
    boksList: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    kode_arsip: props.arsip.kode_arsip,
    nomor_surat: props.arsip.nomor_surat,
    perihal: props.arsip.perihal,
    status_arsip: props.arsip.status_arsip ?? 'tersedia',
    jenis_surat: props.arsip.jenis_surat,
    sifat_surat: props.arsip.sifat_surat ?? 'biasa',
    tanggal_surat: props.arsip.tanggal_surat?.substring(0, 10) ?? props.arsip.tanggal_surat,
    tanggal_diterima: props.arsip.tanggal_diterima?.substring(0, 10) ?? props.arsip.tanggal_diterima ?? '',
    asal_instansi: props.arsip.asal_instansi ?? '',
    tujuan_instansi: props.arsip.tujuan_instansi ?? '',
    seksi_id: props.arsip.seksi_id,
    rak_id: props.arsip.rak_id ?? '',
    boks_id: props.arsip.boks_id ?? '',
    catatan: props.arsip.catatan ?? '',
    lampiran: null,
    // Tahap 4 — Dokumen Khusus
    jenis_dokumen: props.arsip.jenis_dokumen ?? 'umum',
    nama_pemilik: props.arsip.nama_pemilik ?? '',
    nik_pemilik: props.arsip.nik_pemilik ?? '',
    alamat_objek: props.arsip.alamat_objek ?? '',
    nomor_alas_hak: props.arsip.nomor_alas_hak ?? '',
    keterangan_profiling: props.arsip.keterangan_profiling ?? '',
    // Tahap 5 — Enkripsi File
    encrypt_file: false,
    // Tahap 6 — Klasifikasi Masa Aktif & Retensi Arsip
    retensi_kategori: props.arsip.retensi_kategori ?? 'ringan_5_tahun',
    retensi_tahun: props.arsip.retensi_tahun ?? '',
});

const isSeksiPemerintahan = computed(() => {
    const seksi = props.seksiList.find((s) => String(s.id) === String(form.seksi_id));
    if (!seksi) return false;
    const kode = seksi.kode_seksi ?? '';
    const nama = seksi.nama_seksi ?? '';
    return kode === 'PEM' || nama.toLowerCase().includes('pemerintahan');
});

const isDokumenKhusus = computed(() => form.jenis_dokumen === 'surat_tanah' || form.jenis_dokumen === 'ahli_waris');

const acceptLampiran = computed(() => isDokumenKhusus.value ? '.pdf' : '.pdf,.jpg,.jpeg,.png,application/pdf,image/*');

const filteredRakList = computed(() => {
    const seksiId = Number(form.seksi_id) || form.seksi_id;
    return props.rakList.filter((rak) => {
        if (rak.seksi_id === null || rak.seksi_id === undefined || rak.seksi_id === '') {
            return true;
        }
        return Number(rak.seksi_id) === Number(seksiId);
    });
});

const filteredBoksList = computed(() => {
    if (!form.rak_id) return [];
    return (props.boksList ?? []).filter((boks) => Number(boks.rak_id) === Number(form.rak_id));
});

watch(
    () => form.seksi_id,
    () => {
        if (!form.rak_id) return;
        const stillValid = filteredRakList.value.some((rak) => Number(rak.id) === Number(form.rak_id));
        if (!stillValid) {
            form.rak_id = '';
            form.boks_id = '';
        }
        // Reset jenis_dokumen jika pindah ke seksi non-pemerintahan
        if (!isSeksiPemerintahan.value) {
            form.jenis_dokumen = 'umum';
        }
    },
);

watch(
    () => form.rak_id,
    () => {
        if (!form.boks_id) return;
        const stillValid = filteredBoksList.value.some((boks) => Number(boks.id) === Number(form.boks_id));
        if (!stillValid) {
            form.boks_id = '';
        }
    },
);

watch(
    () => form.jenis_dokumen,
    (val) => {
        if (!isDokumenKhusus.value) {
            form.nama_pemilik = '';
            form.nik_pemilik = '';
            form.alamat_objek = '';
            form.nomor_alas_hak = '';
            form.keterangan_profiling = '';
            form.lampiran = null;
        }
    },
);

const processing = ref(false);

const existingFileUrl = computed(() => {
    if (!props.arsip?.file_path) return null;
    // Tahap 5: file terenkripsi pakai endpoint terkontrol; file publik tetap /storage/
    return props.arsip.is_encrypted
        ? route('arsip.file', props.arsip.id)
        : `/storage/${props.arsip.file_path}`;
});

const selectedFileName = computed(() => {
    const f = form.lampiran;
    if (f && typeof f === 'object' && f.name) return f.name;
    return '';
});

const submit = () => {
    processing.value = true;
    form.transform((data) => {
        if (data.status_arsip === '') delete data.status_arsip;
        // Tahap 6: kategori permanen → buang retensi_tahun
        if (data.retensi_kategori === 'permanen') {
            delete data.retensi_tahun;
        }
        return { ...data, _method: 'put' };
    }).post(
        route('arsip.update', props.arsip.id),
        { forceFormData: true, preserveScroll: true, onSuccess: () => { processing.value = false; }, onFinish: () => { processing.value = false; } },
    );
};
</script>

<template>
    <Head title="Edit Arsip" />

    <AppLayout>
        <template #skeleton-header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <div class="h-7 w-48 rounded bg-slate-200" />
                    <div class="h-4 w-32 rounded bg-slate-200" />
                </div>
                <div class="h-8 w-20 rounded-lg bg-slate-200" />
            </div>
        </template>

        <template #skeleton>
            <div class="mx-auto max-w-3xl rounded-2xl bg-slate-100">
                <div class="space-y-4 p-6">
                    <div v-for="i in 4" :key="'sk-f-' + i" class="space-y-1">
                        <div class="h-3 w-28 rounded bg-slate-200" />
                        <div class="h-10 w-full rounded-xl bg-slate-200" />
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1">
                            <div class="h-3 w-24 rounded bg-slate-200" />
                            <div class="h-10 w-full rounded-xl bg-slate-200" />
                        </div>
                        <div class="space-y-1">
                            <div class="h-3 w-24 rounded bg-slate-200" />
                            <div class="h-10 w-full rounded-xl bg-slate-200" />
                        </div>
                    </div>
                    <div class="h-24 rounded-xl bg-slate-200" />
                    <div class="h-10 w-32 rounded-xl bg-slate-200" />
                </div>
            </div>
        </template>

        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Edit Dokumen Arsip</h1>
                    <p class="mt-1 text-sm text-brand-blue">{{ arsip.kode_arsip }}</p>
                </div>
                <BackLink :href="route('arsip.index')" />
            </div>
        </template>

        <div v-reveal class="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <div class="mb-1 flex items-center gap-1.5">
                        <AppIcon name="lock" class="h-4 w-4 text-slate-500" />
                        <label for="kode_arsip" class="text-sm font-medium text-gray-700">Kode Arsip</label>
                    </div>
                    <input
                        id="kode_arsip"
                        type="text"
                        :value="form.kode_arsip"
                        readonly
                        class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 cursor-not-allowed"
                    />
                    <p class="mt-1 text-xs text-slate-400">Nomor arsip bersifat permanen — akan berubah hanya jika seksi berpindah.</p>
                </div>

                <div>
                    <InputLabel for="status_arsip" value="Status Dokumen" />
                    <AppSelect
                        id="status_arsip"
                        v-model="form.status_arsip"
                        class="mt-1 block w-full"
                        label="Status dokumen arsip"
                        :options="[
                            { value: 'tersedia', label: 'Tersedia' },
                            { value: 'dipinjam', label: 'Dipinjam' },
                            { value: 'hilang', label: 'Hilang' },
                        ]"
                    />
                    <InputError class="mt-1" :message="form.errors.status_arsip" />
                </div>

                <div>
                    <label for="nomor_surat" class="block text-sm font-medium text-gray-700">
                        Nomor Surat <span class="text-red-500">*</span>
                    </label>
                    <TextInput id="nomor_surat" v-model="form.nomor_surat" class="mt-1 block w-full rounded-xl" required placeholder="Contoh: 140/933/2026 atau 001/BPD/2026" oninvalid="this.setCustomValidity('Nomor surat wajib diisi.')" oninput="this.setCustomValidity('')" />
                    <InputError class="mt-1" :message="form.errors.nomor_surat" />
                </div>

                <div>
                    <label for="perihal" class="block text-sm font-medium text-gray-700">
                        Perihal <span class="text-red-500">*</span>
                    </label>
                    <TextInput id="perihal" v-model="form.perihal" class="mt-1 block w-full rounded-xl" required placeholder="Contoh: Undangan Rapat Musrenbang atau Pengantar SKTM" oninvalid="this.setCustomValidity('Perihal surat wajib diisi.')" oninput="this.setCustomValidity('')" />
                    <InputError class="mt-1" :message="form.errors.perihal" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="jenis_surat" class="block text-sm font-medium text-gray-700">
                            Jenis Surat <span class="text-red-500">*</span>
                        </label>
                        <AppSelect
                            id="jenis_surat"
                            v-model="form.jenis_surat"
                            class="mt-1 block w-full"
                            label="Jenis surat arsip"
                            required
                            :options="[
                                { value: 'masuk', label: 'Masuk' },
                                { value: 'keluar', label: 'Keluar' },
                            ]"
                        />
                        <InputError class="mt-1" :message="form.errors.jenis_surat" />
                    </div>
                    <div>
                        <InputLabel for="sifat_surat" value="Sifat Surat" />
                        <AppSelect
                            id="sifat_surat"
                            v-model="form.sifat_surat"
                            class="mt-1 block w-full"
                            label="Sifat surat arsip"
                            :options="[
                                { value: 'biasa', label: 'Biasa' },
                                { value: 'penting', label: 'Penting' },
                                { value: 'segera', label: 'Segera' },
                                { value: 'rahasia', label: 'Rahasia' },
                            ]"
                        />
                        <InputError class="mt-1" :message="form.errors.sifat_surat" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="tanggal_surat" class="block text-sm font-medium text-gray-700">
                            Tanggal Surat <span class="text-red-500">*</span>
                        </label>
                        <TextInput id="tanggal_surat" v-model="form.tanggal_surat" type="date" class="mt-1 block w-full rounded-xl" required oninvalid="this.setCustomValidity('Tanggal surat wajib dipilih.')" oninput="this.setCustomValidity('')" />
                        <InputError class="mt-1" :message="form.errors.tanggal_surat" />
                    </div>
                    <div>
                        <InputLabel for="tanggal_diterima" value="Tanggal Diterima" />
                        <TextInput id="tanggal_diterima" v-model="form.tanggal_diterima" type="date" class="mt-1 block w-full rounded-xl" />
                        <InputError class="mt-1" :message="form.errors.tanggal_diterima" />
                    </div>
                </div>

                <div>
                    <InputLabel for="asal_instansi" value="Asal Instansi" />
                    <TextInput id="asal_instansi" v-model="form.asal_instansi" class="mt-1 block w-full rounded-xl" placeholder="Contoh: Kecamatan Kebayoran Lama atau Warga RT 01/RW 02" />
                    <InputError class="mt-1" :message="form.errors.asal_instansi" />
                </div>

                <div>
                    <InputLabel for="tujuan_instansi" value="Tujuan Instansi" />
                    <TextInput id="tujuan_instansi" v-model="form.tujuan_instansi" class="mt-1 block w-full rounded-xl" placeholder="Contoh: Seksi Pemerintahan atau Kelurahan Grogol Selatan" />
                    <InputError class="mt-1" :message="form.errors.tujuan_instansi" />
                </div>

                <div>
                    <label for="seksi_id" class="block text-sm font-medium text-gray-700">
                        Seksi Penanggung Jawab <span class="text-red-500">*</span>
                    </label>
                    <AppSelect
                        id="seksi_id"
                        v-model="form.seksi_id"
                        class="mt-1 block w-full"
                        placeholder="Pilih seksi"
                        label="Seksi penanggung jawab"
                        required
                        :options="seksiList.map((seksi) => ({
                            value: seksi.id,
                            label: seksi.nama_seksi,
                            disabled: isSeksiOptionDisabled(seksi.id),
                        }))"
                    />
                    <InputError class="mt-1" :message="form.errors.seksi_id" />
                </div>

                <div>
                    <InputLabel for="rak_id" value="Rak Penyimpanan *" />
                    <AppSelect
                        id="rak_id"
                        v-model="form.rak_id"
                        class="mt-1 block w-full"
                        placeholder="Pilih rak"
                        label="Rak penyimpanan arsip"
                        required
                        :options="filteredRakList.map((rak) => ({
                            value: rak.id,
                            label: `${rak.kode_rak} — No. ${rak.nomor_rak || '—'} · ${rak.nama_rak}`,
                        }))"
                    />
                    <p class="mt-1 text-xs text-slate-500">Hanya menampilkan rak milik seksi yang dipilih.</p>
                    <InputError class="mt-1" :message="form.errors.rak_id" />
                </div>

                <div>
                    <InputLabel for="boks_id" value="Boks Penyimpanan *" />
                    <AppSelect
                        id="boks_id"
                        v-model="form.boks_id"
                        class="mt-1 block w-full"
                        :placeholder="form.rak_id ? 'Pilih boks' : 'Pilih rak terlebih dahulu'"
                        label="Boks penyimpanan arsip"
                        required
                        :disabled="!form.rak_id"
                        :options="filteredBoksList.map((boks) => ({
                            value: boks.id,
                            label: `${boks.kode_boks} — Baris ${boks.nomor_baris || '—'} · Boks ${boks.nomor_boks || '—'}`,
                        }))"
                    />
                    <p v-if="!form.rak_id" class="mt-1 text-xs text-slate-500">Pilih rak terlebih dahulu untuk menampilkan boks yang tersedia.</p>
                    <p v-else-if="filteredBoksList.length === 0" class="mt-1 text-xs text-amber-600">Rak ini belum punya boks. Buat boks di menu Master Data → Boks terlebih dahulu.</p>
                    <InputError class="mt-1" :message="form.errors.boks_id" />
                </div>

                <div>
                    <InputLabel for="catatan" value="Catatan / Lokasi Rak" />
                    <textarea id="catatan" v-model="form.catatan" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm" rows="3" placeholder="Tambahkan catatan khusus mengenai dokumen ini (opsional)..." />
                    <InputError class="mt-1" :message="form.errors.catatan" />
                </div>

                <!-- Tahap 4: Klasifikasi Dokumen (khusus Seksi Pemerintahan) -->
                <div v-if="isSeksiPemerintahan" class="rounded-xl border border-violet-200 bg-violet-50/50 p-4">
                    <div class="mb-1 flex items-center gap-1.5">
                        <AppIcon name="file-text" class="h-4 w-4 text-violet-600" decorative />
                        <InputLabel for="jenis_dokumen" value="Klasifikasi Dokumen" />
                    </div>
                    <AppSelect
                        id="jenis_dokumen"
                        v-model="form.jenis_dokumen"
                        class="mt-1 block w-full"
                        label="Klasifikasi dokumen"
                        :options="[
                            { value: 'umum', label: 'Dokumen Umum' },
                            { value: 'surat_tanah', label: 'Surat Tanah' },
                            { value: 'ahli_waris', label: 'Surat Ahli Waris' },
                        ]"
                    />
                    <p v-if="isDokumenKhusus" class="mt-1.5 text-xs font-medium text-amber-700">
                        Dokumen khusus — lampiran wajib berformat PDF.
                    </p>
                    <InputError class="mt-1" :message="form.errors.jenis_dokumen" />
                </div>

                <!-- Tahap 4: Panel Profiling / Biografi -->
                <div v-if="isDokumenKhusus" class="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                    <div class="mb-3 flex items-center gap-1.5">
                        <AppIcon name="key" class="h-4 w-4 text-amber-600" decorative />
                        <h3 class="text-sm font-bold text-amber-900">Profiling / Biografi & Keterangan Tambahan</h3>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label for="nama_pemilik" class="block text-sm font-medium text-gray-700">
                                Nama Pemilik / Pewaris <span class="text-red-500">*</span>
                            </label>
                            <TextInput
                                id="nama_pemilik"
                                v-model="form.nama_pemilik"
                                class="mt-1 block w-full rounded-xl"
                                required
                                placeholder="Nama lengkap pemilik tanah atau pewaris"
                            />
                            <InputError class="mt-1" :message="form.errors.nama_pemilik" />
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel for="nik_pemilik" value="NIK Pemilik / Pewaris" />
                                <TextInput
                                    id="nik_pemilik"
                                    v-model="form.nik_pemilik"
                                    type="text"
                                    inputmode="numeric"
                                    class="mt-1 block w-full rounded-xl"
                                    placeholder="16 digit NIK (opsional)"
                                />
                                <InputError class="mt-1" :message="form.errors.nik_pemilik" />
                            </div>
                            <div>
                                <InputLabel for="nomor_alas_hak" value="Nomor Alas Hak / Surat" />
                                <TextInput
                                    id="nomor_alas_hak"
                                    v-model="form.nomor_alas_hak"
                                    class="mt-1 block w-full rounded-xl"
                                    placeholder="Contoh: Girik No. 123, Sertifikat No. 456/2026"
                                />
                                <InputError class="mt-1" :message="form.errors.nomor_alas_hak" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="alamat_objek" value="Alamat / Lokasi Objek Tanah" />
                            <textarea
                                id="alamat_objek"
                                v-model="form.alamat_objek"
                                class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm"
                                rows="2"
                                placeholder="Alamat lengkap objek tanah atau keterangan domisili (opsional)"
                            />
                            <InputError class="mt-1" :message="form.errors.alamat_objek" />
                        </div>

                        <div>
                            <InputLabel for="keterangan_profiling" value="Keterangan Tambahan / Riwayat Singkat" />
                            <textarea
                                id="keterangan_profiling"
                                v-model="form.keterangan_profiling"
                                class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm"
                                rows="2"
                                placeholder="Catatan tambahan, riwayat singkat, atau profil pemilik (opsional)"
                            />
                            <InputError class="mt-1" :message="form.errors.keterangan_profiling" />
                        </div>
                    </div>
                </div>

                <!-- Tahap 6: Panel Klasifikasi Masa Aktif (Retensi) -->
                <div class="rounded-xl border border-sky-200 bg-sky-50/60 p-4">
                    <div class="mb-3 flex items-center gap-1.5">
                        <AppIcon name="clock" class="h-4 w-4 text-sky-600" decorative />
                        <h3 class="text-sm font-bold text-sky-900">Klasifikasi Masa Aktif (Retensi)</h3>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="retensi_kategori" value="Kategori Retensi" />
                            <AppSelect
                                id="retensi_kategori"
                                v-model="form.retensi_kategori"
                                class="mt-1 block w-full"
                                label="Kategori retensi arsip"
                                :options="[
                                    { value: 'ringan_5_tahun', label: 'Ringan — 5 Tahun' },
                                    { value: 'permanen', label: 'Permanen' },
                                    { value: 'kustom', label: 'Kustom (Tentukan Tahun)' },
                                ]"
                            />
                            <p v-if="form.retensi_kategori === 'kustom'" class="mt-1 text-xs text-sky-700">
                                Untuk kategori Kustom, isilah durasi tahun pada kolom berikutnya.
                            </p>
                            <InputError class="mt-1" :message="form.errors.retensi_kategori" />
                        </div>

                        <div>
                            <InputLabel for="retensi_tahun" value="Durasi Retensi (Tahun)" />
                            <TextInput
                                id="retensi_tahun"
                                v-model="form.retensi_tahun"
                                type="number"
                                min="1"
                                max="100"
                                :disabled="form.retensi_kategori !== 'kustom'"
                                class="mt-1 block w-full rounded-xl border-sky-300 bg-white shadow-sm disabled:bg-slate-100 disabled:text-slate-500"
                                placeholder="Contoh: 10"
                            />
                            <InputError class="mt-1" :message="form.errors.retensi_tahun" />
                        </div>
                    </div>

                    <p class="mt-3 text-xs text-sky-800">
                        <template v-if="form.retensi_kategori === 'permanen'">
                            Masa aktif: Permanen — tidak ada tanggal kedaluwarsa.
                        </template>
                        <template v-else-if="form.retensi_kategori === 'kustom'">
                            Masa aktif: Kustom — {{ form.retensi_tahun || 0 }} tahun dari tanggal surat.
                        </template>
                        <template v-else>
                            Masa aktif: Ringan — 5 tahun dari tanggal surat.
                        </template>
                    </p>
                </div>

                <div class="rounded-xl border border-dashed border-sky-200 bg-sky-50/60 p-4">
                    <InputLabel
                        for="lampiran"
                        :value="isDokumenKhusus ? 'Lampirkan File PDF (Wajib)' : 'Lampirkan File Scan (PDF / Foto Dokumen)'"
                    />
                    <input
                        id="lampiran"
                        type="file"
                        :accept="acceptLampiran"
                        class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-brand-blue file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white"
                        @change="form.lampiran = $event.target.files?.[0] ?? null"
                    />
                    <p v-if="selectedFileName" class="mt-2 text-xs font-medium text-slate-700">
                        File dipilih: {{ selectedFileName }}
                    </p>
                    <p v-else-if="existingFileUrl" class="mt-2 text-xs text-slate-600">
                        Lampiran saat ini:
                        <a :href="existingFileUrl" target="_blank" class="font-semibold text-brand-blue hover:underline">
                            Lihat Lampiran
                        </a>
                    </p>
                    <p class="mt-2 text-xs" :class="isDokumenKhusus ? 'text-amber-700 font-medium' : 'text-slate-500'">
                        <template v-if="isDokumenKhusus">
                            Dokumen khusus Surat Tanah / Ahli Waris wajib berformat PDF. Maksimal 5 MB.
                        </template>
                        <template v-else>
                            Maksimal 5 MB per file scan (PDF, JPG, JPEG, PNG). Kosongkan untuk tidak mengubah lampiran.
                        </template>
                    </p>
                    <InputError class="mt-1" :message="form.errors.lampiran" />

                    <!-- Tahap 5: Banner + Checkbox Enkripsi -->
                    <div v-if="isDokumenKhusus" class="mt-3 flex items-center gap-2 rounded-lg bg-violet-50 px-3 py-2 ring-1 ring-violet-200">
                        <AppIcon name="shield" class="h-4 w-4 shrink-0 text-violet-600" decorative />
                        <p class="text-xs font-medium text-violet-800">
                            File PDF akan otomatis dienkripsi (AES-256) saat disimpan.
                        </p>
                    </div>
                    <div v-else class="mt-3 flex items-start gap-2">
                        <input
                            id="encrypt_file"
                            v-model="form.encrypt_file"
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30"
                        />
                        <label for="encrypt_file" class="text-xs font-medium text-slate-600">
                            Enkripsi file PDF (Keamanan Ekstra)
                            <span class="text-slate-400">— file tidak dapat dibuka tanpa login</span>
                        </label>
                    </div>

                    <!-- Badge status enkripsi file lama (jika sudah ada) -->
                    <div
                        v-if="props.arsip?.is_encrypted && !form.lampiran"
                        class="mt-3 flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 ring-1 ring-emerald-200"
                    >
                        <AppIcon name="lock" class="h-4 w-4 shrink-0 text-emerald-600" decorative />
                        <p class="text-xs font-medium text-emerald-800">
                            File saat ini sudah terenkripsi AES-256.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <PrimaryButton class="bg-brand-blue" :disabled="processing">Perbarui</PrimaryButton>
                    <Link :href="route('arsip.index')" class="text-sm font-medium text-slate-600 hover:underline">Batal</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
