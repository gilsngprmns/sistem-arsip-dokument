<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AppSelect from '@/Components/AppSelect.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    mode: {
        type: String,
        default: 'create',
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
    initialData: {
        type: Object,
        default: null,
    },
    onFinish: {
        type: Function,
        default: null,
    },
});

const emit = defineEmits(['close']);
const page = usePage();

const resolvedSeksiList = computed(() => {
    if (props.seksiList?.length) {
        return props.seksiList;
    }

    return page.props.seksiList ?? [];
});

// Seksi yang dikelola user (staff). Admin = null (bebas memilih semua).
const mySeksiId = computed(() => {
    const u = page.props.auth?.user;
    return u?.role !== 'admin' ? Number(u.seksi_id) : null;
});

const isSeksiOptionDisabled = (id) => mySeksiId.value !== null && Number(id) !== mySeksiId.value;

const emptyForm = () => ({
    kode_arsip: '',
    nomor_surat: '',
    perihal: '',
    status_arsip: 'tersedia',
    jenis_surat: 'masuk',
    sifat_surat: 'biasa',
    tanggal_surat: '',
    tanggal_diterima: '',
    asal_instansi: '',
    tujuan_instansi: '',
    seksi_id: mySeksiId.value ? String(mySeksiId.value) : (resolvedSeksiList.value[0]?.id ?? ''),
    rak_id: '',
    boks_id: '',
    catatan: '',
    lampiran: null,
    jenis_dokumen: 'umum',
    nama_pemilik: '',
    nik_pemilik: '',
    alamat_objek: '',
    nomor_alas_hak: '',
    keterangan_profiling: '',
});

const form = useForm(emptyForm());

const filteredBoksList = computed(() => {
    if (!form.rak_id) return [];
    return (props.boksList ?? []).filter((boks) => Number(boks.rak_id) === Number(form.rak_id));
});

// Tahap 4 — Klasifikasi Dokumen
const isSeksiPemerintahan = computed(() => {
    const seksi = resolvedSeksiList.value.find((s) => String(s.id) === String(form.seksi_id));
    if (!seksi) return false;
    const kode = seksi.kode_seksi ?? '';
    const nama = seksi.nama_seksi ?? '';
    return kode === 'PEM' || nama.toLowerCase().includes('pemerintahan');
});

const isDokumenKhusus = computed(() => form.jenis_dokumen === 'surat_tanah' || form.jenis_dokumen === 'ahli_waris');

const acceptLampiran = computed(() => isDokumenKhusus.value ? '.pdf' : '.pdf,.jpg,.jpeg,.png,application/pdf,image/*');
const selectedFileName = computed(() => {
    if (form.lampiran && typeof form.lampiran === 'object' && form.lampiran.name) {
        return form.lampiran.name;
    }
    return '';
});
const existingFileUrl = computed(() => {
    if (props.mode === 'edit' && props.initialData?.file_path) {
        return props.initialData.is_encrypted
            ? route('arsip.file', props.initialData.id)
            : `/storage/${props.initialData.file_path}`;
    }
    return null;
});

const filteredRakList = computed(() => {
    const seksiId = Number(form.seksi_id) || form.seksi_id;
    return props.rakList.filter((rak) => {
        if (rak.seksi_id === null || rak.seksi_id === undefined || rak.seksi_id === '') {
            return true;
        }
        return Number(rak.seksi_id) === Number(seksiId);
    });
});

const title = computed(() =>
    props.mode === 'edit' ? 'Perbarui Metadata Berkas' : 'Perekaman Berkas Arsip Baru',
);

const fillForm = () => {
    const data =
        props.mode === 'edit' && props.initialData
            ? {
                  kode_arsip: props.initialData.kode_arsip ?? '',
                  nomor_surat: props.initialData.nomor_surat ?? '',
                  perihal: props.initialData.perihal ?? '',
                  status_arsip: props.initialData.status_arsip ?? 'tersedia',
                  jenis_surat: props.initialData.jenis_surat ?? 'masuk',
                  sifat_surat: props.initialData.sifat_surat ?? 'biasa',
                  tanggal_surat: String(props.initialData.tanggal_surat ?? '').substring(0, 10),
                  tanggal_diterima: String(props.initialData.tanggal_diterima ?? '').substring(0, 10),
                  asal_instansi: props.initialData.asal_instansi ?? '',
                  tujuan_instansi: props.initialData.tujuan_instansi ?? '',
                  seksi_id: props.initialData.seksi_id ?? '',
                  rak_id: props.initialData.rak_id ?? '',
                  boks_id: props.initialData.boks_id ?? '',
                  catatan: props.initialData.catatan ?? '',
                  lampiran: null,
                  jenis_dokumen: props.initialData.jenis_dokumen ?? 'umum',
                  nama_pemilik: props.initialData.nama_pemilik ?? '',
                  nik_pemilik: props.initialData.nik_pemilik ?? '',
                  alamat_objek: props.initialData.alamat_objek ?? '',
                  nomor_alas_hak: props.initialData.nomor_alas_hak ?? '',
                  keterangan_profiling: props.initialData.keterangan_profiling ?? '',
              }
            : emptyForm();

    form.clearErrors();
    Object.assign(form, data);
};

watch(
    () => [props.show, props.mode, props.initialData],
    ([show]) => {
        if (show) {
            fillForm();
        }
    },
);

const previewKodeArsip = ref('');

const fetchPreview = async (seksiId) => {
    if (!seksiId || props.mode === 'edit') {
        previewKodeArsip.value = '';
        return;
    }
    try {
        const res = await fetch(route('arsip.next-number', { seksi_id: seksiId }), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (res.ok) {
            const data = await res.json();
            previewKodeArsip.value = data.kode_arsip ?? '';
        } else {
            previewKodeArsip.value = '';
        }
    } catch {
        previewKodeArsip.value = '';
    }
};

watch(
    () => form.seksi_id,
    (seksiId) => {
        if (props.mode === 'create') {
            fetchPreview(seksiId);
        }
        if (!form.rak_id) return;
        const stillValid = filteredRakList.value.some((rak) => Number(rak.id) === Number(form.rak_id));
        if (!stillValid) {
            form.rak_id = '';
            form.boks_id = '';
        }
        if (!isSeksiPemerintahan.value) {
            form.jenis_dokumen = 'umum';
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

const close = () => emit('close');

const onFileChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.lampiran = file;
};

const submit = () => {
    const successOptions = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            close();
            props.onFinish?.();
        },
    };

    form.transform((data) => {
        if (data.status_arsip === '') delete data.status_arsip;
        return data;
    });

    if (props.mode === 'edit' && props.initialData?.id) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            route('arsip.update', props.initialData.id),
            successOptions,
        );
        return;
    }

    form.post(route('arsip.store'), successOptions);
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="close">
        <div class="border-b border-slate-100 px-6 py-4">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-bold text-slate-800">{{ title }}</h2>
                <button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" @click="close">
                    <span class="sr-only">Tutup</span>
                    <AppIcon name="close" :size="20" :stroke-width="2" class="h-5 w-5" decorative />
                </button>
            </div>
        </div>

        <form class="space-y-4 px-6 py-5" @submit.prevent="submit">
            <div>
                <InputLabel for="seksi_id" value="Seksi Penanggung Jawab *" />
                <AppSelect
                    id="seksi_id"
                    v-model="form.seksi_id"
                    class="mt-1 block w-full"
                    placeholder="Pilih seksi"
                    label="Seksi penanggung jawab"
                    required
                    :options="resolvedSeksiList.map((seksi) => ({
                        value: seksi.id,
                        label: seksi.nama_seksi,
                        disabled: isSeksiOptionDisabled(seksi.id),
                    }))"
                />
                <InputError class="mt-1" :message="form.errors.seksi_id" />
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
                <p class="mt-1 text-xs text-slate-500">Pilih "Hilang" bila dokumen belum ditemukan atau belum dikembalikan.</p>
                <InputError class="mt-1" :message="form.errors.status_arsip" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <div class="mb-1 flex items-center gap-1.5">
                        <AppIcon :name="mode === 'edit' ? 'lock' : 'key'" class="h-4 w-4 text-slate-500" />
                        <InputLabel for="kode_arsip" value="Kode Arsip" class="!mb-0" />
                    </div>
                    <input
                        id="kode_arsip"
                        type="text"
                        :value="mode === 'edit' ? form.kode_arsip : previewKodeArsip"
                        readonly
                        class="mt-1 block w-full rounded-lg border-slate-300 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 cursor-not-allowed"
                    />
                    <p class="mt-1 text-xs text-slate-400">
                        {{ mode === 'edit'
                            ? 'Nomor arsip bersifat permanen — akan berubah hanya jika seksi berpindah.'
                            : 'Nomor dibuat otomatis oleh sistem setelah Seksi dipilih.' }}
                    </p>
                </div>
                <div>
                    <InputLabel for="jenis_surat" value="Jenis Surat *" />
                    <AppSelect
                        id="jenis_surat"
                        v-model="form.jenis_surat"
                        class="mt-1 block w-full"
                        label="Jenis surat arsip"
                        :options="[
                            { value: 'masuk', label: 'Surat Masuk' },
                            { value: 'keluar', label: 'Surat Keluar' },
                        ]"
                    />
                    <InputError class="mt-1" :message="form.errors.jenis_surat" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="nomor_surat" value="Nomor Surat *" />
                    <TextInput
                        id="nomor_surat"
                        v-model="form.nomor_surat"
                        class="mt-1 block w-full"
                        placeholder="Contoh: 140/933/2026 atau 001/BPD/2026"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.nomor_surat" />
                </div>
                <div>
                    <InputLabel for="tanggal_surat" value="Tanggal Surat *" />
                    <TextInput
                        id="tanggal_surat"
                        v-model="form.tanggal_surat"
                        type="date"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.tanggal_surat" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                <div v-if="form.jenis_surat === 'masuk'">
                    <InputLabel for="tanggal_diterima" value="Tanggal Diterima" />
                    <TextInput
                        id="tanggal_diterima"
                        v-model="form.tanggal_diterima"
                        type="date"
                        class="mt-1 block w-full"
                    />
                    <p class="mt-1 text-xs text-slate-400">Opsional — isi hanya jika tanggal diterima berbeda dari tanggal surat.</p>
                    <InputError class="mt-1" :message="form.errors.tanggal_diterima" />
                </div>
            </div>

            <div v-if="form.jenis_surat === 'masuk'">
                <InputLabel for="asal_instansi" value="Nama Pemohon / Asal Instansi" />
                <TextInput
                    id="asal_instansi"
                    v-model="form.asal_instansi"
                    class="mt-1 block w-full"
                    placeholder="Contoh: Kecamatan Kebayoran Lama atau Warga RT 01/RW 02"
                />
                <InputError class="mt-1" :message="form.errors.asal_instansi" />
            </div>

            <div v-else>
                <InputLabel for="tujuan_instansi" value="Tujuan Instansi" />
                <TextInput
                    id="tujuan_instansi"
                    v-model="form.tujuan_instansi"
                    class="mt-1 block w-full"
                    placeholder="Contoh: Seksi Pemerintahan atau Kelurahan Grogol Selatan"
                />
                <InputError class="mt-1" :message="form.errors.tujuan_instansi" />
            </div>

            <div>
                <InputLabel for="perihal" value="Perihal Dokumen *" />
                <textarea
                    id="perihal"
                    v-model="form.perihal"
                    rows="3"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                    placeholder="Contoh: Undangan Rapat Musrenbang atau Pengantar SKTM"
                    required
                />
                <InputError class="mt-1" :message="form.errors.perihal" />
            </div>

            <div>
                <InputLabel for="gedung_label" value="Gedung" />
                <input
                    id="gedung_label"
                    type="text"
                    value="Gedung Kelurahan Grogol Selatan"
                    disabled
                    class="mt-1 block w-full rounded-lg border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500 cursor-not-allowed"
                />
                <p class="mt-1 text-xs text-slate-400">Satu gedung arsip kelurahan — tidak dapat diubah.</p>
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
                        label: `${boks.kode_boks} — Baris ${boks.nomor_baris || '—'} · Boks ${boks.nomor_boks || '—'} (Terisi: ${boks.dokumen_arsip_count ?? 0}/${boks.kapasitas ?? '—'})`,
                    }))"
                />
                <p v-if="!form.rak_id" class="mt-1 text-xs text-slate-500">Pilih rak terlebih dahulu untuk menampilkan boks yang tersedia.</p>
                <InputError class="mt-1" :message="form.errors.boks_id" />
            </div>

            <div>
                <InputLabel for="catatan" value="Catatan / Titik Rak (opsional)" />
                <TextInput
                    id="catatan"
                    v-model="form.catatan"
                    class="mt-1 block w-full"
                    placeholder="Tambahkan catatan khusus mengenai dokumen ini (opsional)..."
                />
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
                        <label for="nama_pemilik" class="block text-sm font-medium text-slate-700">
                            Nama Pemilik / Pewaris <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="nama_pemilik"
                            v-model="form.nama_pemilik"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm"
                            required
                            placeholder="Nama lengkap pemilik tanah atau pewaris"
                        />
                        <InputError class="mt-1" :message="form.errors.nama_pemilik" />
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="nik_pemilik" value="NIK Pemilik / Pewaris" />
                            <input
                                id="nik_pemilik"
                                v-model="form.nik_pemilik"
                                type="text"
                                inputmode="numeric"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm"
                                placeholder="16 digit NIK (opsional)"
                            />
                            <InputError class="mt-1" :message="form.errors.nik_pemilik" />
                        </div>
                        <div>
                            <InputLabel for="nomor_alas_hak" value="Nomor Alas Hak / Surat" />
                            <input
                                id="nomor_alas_hak"
                                v-model="form.nomor_alas_hak"
                                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm"
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
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm"
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
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm"
                            rows="2"
                            placeholder="Catatan tambahan, riwayat singkat, atau profil pemilik (opsional)"
                        />
                        <InputError class="mt-1" :message="form.errors.keterangan_profiling" />
                    </div>
                </div>
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
                    @change="onFileChange"
                />
                <p v-if="selectedFileName" class="mt-2 text-xs font-medium text-slate-700">
                    File dipilih: {{ selectedFileName }}
                </p>
                <p v-else-if="existingFileUrl" class="mt-2 text-xs text-slate-600">
                    Lampiran saat ini:
                    <a :href="existingFileUrl" target="_blank" class="font-semibold text-brand-blue hover:underline">
                        {{ initialData?.is_encrypted ? 'Unduh PDF (Aman)' : 'Lihat Lampiran' }}
                    </a>
                </p>
                <p class="mt-2 text-xs" :class="isDokumenKhusus ? 'text-amber-700 font-medium' : 'text-slate-500'">
                    <template v-if="isDokumenKhusus">
                        Dokumen khusus Surat Tanah / Ahli Waris wajib berformat PDF. Maksimal 5 MB.
                    </template>
                    <template v-else>
                        Maksimal 5 MB per file scan (PDF, JPG, JPEG, PNG).
                    </template>
                </p>
                <InputError class="mt-1" :message="form.errors.lampiran" />
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                <SecondaryButton type="button" @click="close">Batal</SecondaryButton>
                <PrimaryButton
                    class="bg-brand-blue hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-800"
                    :disabled="form.processing"
                >
                    {{ mode === 'edit' ? 'Simpan Perubahan' : 'Simpan & Buat QR' }}
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
