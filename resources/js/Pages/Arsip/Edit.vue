<script setup>
import BackLink from '@/Components/BackLink.vue';
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

const processing = ref(false);

const existingFileUrl = computed(() => {
    return props.arsip?.file_path ? `/storage/${props.arsip.file_path}` : null;
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
                    <div class="grid grid-cols-2 gap-4">
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
                    <label for="kode_arsip" class="block text-sm font-medium text-gray-700">
                        Kode Arsip <span class="text-red-500">*</span>
                    </label>
                    <TextInput id="kode_arsip" v-model="form.kode_arsip" class="mt-1 block w-full rounded-xl" required placeholder="Contoh: PEM-001/2026 atau RKYT-002" oninvalid="this.setCustomValidity('Kode arsip wajib diisi.')" oninput="this.setCustomValidity('')" />
                    <InputError class="mt-1" :message="form.errors.kode_arsip" />
                </div>

                <div>
                    <InputLabel for="status_arsip" value="Status Dokumen" />
                    <select id="status_arsip" v-model="form.status_arsip" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm">
                        <option value="tersedia">Tersedia</option>
                        <option value="dipinjam">Dipinjam</option>
                        <option value="hilang">Hilang</option>
                    </select>
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
                        <select id="jenis_surat" v-model="form.jenis_surat" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm" required oninvalid="this.setCustomValidity('Jenis surat wajib dipilih.')" oninput="this.setCustomValidity('')">
                            <option value="masuk">Masuk</option>
                            <option value="keluar">Keluar</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.jenis_surat" />
                    </div>
                    <div>
                        <InputLabel for="sifat_surat" value="Sifat Surat" />
                        <select id="sifat_surat" v-model="form.sifat_surat" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm">
                            <option value="biasa">Biasa</option>
                            <option value="penting">Penting</option>
                            <option value="segera">Segera</option>
                            <option value="rahasia">Rahasia</option>
                        </select>
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
                    <select id="seksi_id" v-model="form.seksi_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm" required oninvalid="this.setCustomValidity('Seksi penanggung jawab wajib dipilih.')" oninput="this.setCustomValidity('')">
                        <option
                            v-for="seksi in seksiList"
                            :key="seksi.id"
                            :value="seksi.id"
                            :disabled="isSeksiOptionDisabled(seksi.id)"
                        >
                            {{ seksi.nama_seksi }}
                        </option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.seksi_id" />
                </div>

                <div>
                    <InputLabel for="rak_id" value="Rak Penyimpanan *" />
                    <select id="rak_id" v-model="form.rak_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm" required>
                        <option value="" disabled>Pilih rak</option>
                        <option v-for="rak in filteredRakList" :key="rak.id" :value="rak.id">
                            {{ rak.kode_rak }} — No. {{ rak.nomor_rak || '—' }} · {{ rak.nama_rak }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Hanya menampilkan rak milik seksi yang dipilih.</p>
                    <InputError class="mt-1" :message="form.errors.rak_id" />
                </div>

                <div>
                    <InputLabel for="boks_id" value="Boks Penyimpanan *" />
                    <select id="boks_id" v-model="form.boks_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm" required>
                        <option value="" disabled>{{ form.rak_id ? 'Pilih boks' : 'Pilih rak terlebih dahulu' }}</option>
                        <option v-for="boks in filteredBoksList" :key="boks.id" :value="boks.id">
                            {{ boks.kode_boks }} — Baris {{ boks.nomor_baris || '—' }} · Boks {{ boks.nomor_boks || '—' }}
                        </option>
                    </select>
                    <p v-if="!form.rak_id" class="mt-1 text-xs text-slate-500">Pilih rak terlebih dahulu untuk menampilkan boks yang tersedia.</p>
                    <p v-else-if="filteredBoksList.length === 0" class="mt-1 text-xs text-amber-600">Rak ini belum punya boks. Buat boks di menu Master Data → Boks terlebih dahulu.</p>
                    <InputError class="mt-1" :message="form.errors.boks_id" />
                </div>

                <div>
                    <InputLabel for="catatan" value="Catatan / Lokasi Rak" />
                    <textarea id="catatan" v-model="form.catatan" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm" rows="3" placeholder="Tambahkan catatan khusus mengenai dokumen ini (opsional)..." />
                    <InputError class="mt-1" :message="form.errors.catatan" />
                </div>

                <div class="rounded-xl border border-dashed border-sky-200 bg-sky-50/60 p-4">
                    <InputLabel for="lampiran" value="Lampirkan File Scan (PDF / Foto Dokumen)" />
                    <input
                        id="lampiran"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/*"
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
                    <p class="mt-2 text-xs text-slate-500">
                        Maksimal 5 MB per file scan (PDF, JPG, JPEG, PNG). Kosongkan untuk tidak mengubah lampiran.
                    </p>
                    <InputError class="mt-1" :message="form.errors.lampiran" />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <PrimaryButton class="bg-brand-blue" :disabled="processing">Perbarui</PrimaryButton>
                    <Link :href="route('arsip.index')" class="text-sm font-medium text-slate-600 hover:underline">Batal</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
