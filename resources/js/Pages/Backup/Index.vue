<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    totalArsip: {
        type: Number,
        default: 0,
    },
    aktifArsip: {
        type: Number,
        default: 0,
    },
    daftarBackup: {
        type: Array,
        default: () => [],
    },
    limit: {
        type: Number,
        default: 10,
    },
    adaLebihLama: {
        type: Boolean,
        default: false,
    },
    cadanganTerakhir: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashVisible = ref(false);
let flashTimer = null;

// Panel hasil pemulihan bersifat persisten (bukan auto-hide) — operator
// perlu angka nyata untuk menilai apakah pemulihan berjalan utuh.
const backupResult = computed(() => page.props.flash?.backup_result);

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

const formatUkur = (bytes) => {
    if (!bytes) return '';
    const kb = bytes / 1024;
    return kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : Math.ceil(kb) + ' KB';
};

const formatWaktu = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
};

const showConfirm = ref(false);
const running = ref(false);
const selectedName = ref('');
const selectedIds = ref([]);
const showBulkDeleteModal = ref(false);

const isAllSelected = computed(() =>
    props.daftarBackup.length > 0 &&
    selectedIds.value.length === props.daftarBackup.length,
);

const toggleRow = (namaFile) => {
    const idx = selectedIds.value.indexOf(namaFile);
    if (idx === -1) {
        selectedIds.value.push(namaFile);
    } else {
        selectedIds.value.splice(idx, 1);
    }
};

const onRowCheckboxChange = (event, namaFile) => {
    toggleRow(namaFile);
    event.target.checked = selectedIds.value.includes(namaFile);
};

const onHeaderCheckboxChange = (event) => {
    if (event.target.checked) {
        selectedIds.value = props.daftarBackup.map((b) => b.nama_file);
    } else {
        selectedIds.value = [];
    }
    event.target.indeterminate = false;
};

const bulkDelete = () => {
    showBulkDeleteModal.value = true;
};

const confirmBulkDelete = () => {
    router.post(
        route('backup.destroy-bulk'),
        { files: selectedIds.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showBulkDeleteModal.value = false;
                selectedIds.value = [];
            },
        },
    );
};

const form = useForm({
    backup_file: null,
});

const onFileChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.backup_file = file;
    selectedName.value = file?.name ?? '';
};

const openConfirm = () => {
    if (!form.backup_file) {
        form.setError('backup_file', 'Pilih file cadangan (ZIP atau JSON) terlebih dahulu.');
        return;
    }
    showConfirm.value = true;
};

const runImport = () => {
    form.post(route('backup.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showConfirm.value = false;
            form.reset();
            selectedName.value = '';
        },
    });
};

const runNow = () => {
    running.value = true;
    router.post(route('backup.run'), {}, {
        preserveScroll: true,
        onFinish: () => { running.value = false; },
    });
};
</script>

<template>
    <Head title="Cadangkan & Pulihkan" />

    <AppLayout>
        <template #skeleton-header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <div class="h-7 w-72 rounded bg-slate-200" />
                    <div class="h-4 w-64 rounded bg-slate-200" />
                </div>
                <div class="h-16 w-48 rounded-xl bg-slate-200" />
            </div>
        </template>

        <template #skeleton>
            <div class="space-y-4">
                <div class="h-24 rounded-2xl bg-slate-100 p-4">
                    <div class="h-5 w-40 rounded bg-slate-200" />
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div v-for="i in 4" :key="'sk-stat-' + i" class="h-12 rounded-xl bg-slate-200" />
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="h-48 rounded-2xl bg-slate-100 p-6">
                        <div class="h-5 w-32 rounded bg-slate-200" />
                        <div class="mt-4 space-y-2">
                            <div class="h-12 w-full rounded-xl bg-slate-200" />
                            <div class="h-10 w-full rounded-xl bg-slate-200" />
                        </div>
                    </div>
                    <div class="h-48 rounded-2xl bg-slate-100 p-6">
                        <div class="h-5 w-32 rounded bg-slate-200" />
                        <div class="mt-4 space-y-2">
                            <div class="h-10 w-full rounded-xl bg-slate-200" />
                            <div class="h-12 w-full rounded-xl bg-slate-200" />
                        </div>
                    </div>
                </div>
                <div class="h-40 rounded-2xl bg-slate-100">
                    <div class="h-5 w-40 rounded bg-slate-200 p-4" />
                    <div class="mx-4 h-28 rounded-xl bg-slate-200" />
                </div>
            </div>
        </template>

        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Cadangkan & Pulihkan Basis Data</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Fitur ini khusus admin. Total arsip di backup: {{ totalArsip }}
                        (aktif {{ aktifArsip }}).
                    </p>
                </div>

                <!-- Indikator cadangan terakhir -->
                <div
                    v-if="cadanganTerakhir?.diambil_pada"
                    class="rounded-xl border px-4 py-3 text-right text-sm"
                    :class="cadanganTerakhir.menunggak ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-slate-50'"
                >
                    <p class="text-xs text-slate-500">Cadangan terakhir</p>
                    <p class="font-semibold" :class="cadanganTerakhir.menunggak ? 'text-rose-700' : 'text-slate-800'">
                        {{ formatWaktu(cadanganTerakhir.diambil_pada) }}
                    </p>
                    <p v-if="cadanganTerakhir.menunggak" class="mt-1 text-xs font-semibold text-rose-600">
                        Terlewat &gt; 7 hari — jalankan cadangan sekarang.
                    </p>
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
            v-if="backupResult"
            v-reveal
            class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-800">
                    Hasil Pemulihan — {{ backupResult.nama_file }}
                </h3>
                <span
                    class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                    :class="
                        backupResult.periksa_checksum === 'lulus'
                            ? 'bg-emerald-100 text-emerald-700'
                            : 'bg-amber-100 text-amber-700'
                    "
                >
                    Checksum {{ backupResult.periksa_checksum === 'lulus' ? 'terverifikasi' : 'tanpa verifikasi' }}
                </span>
            </div>

            <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg bg-slate-50 p-3">
                    <dt class="text-xs text-slate-500">Dibuat</dt>
                    <dd class="text-lg font-bold text-emerald-600">{{ backupResult.dibuat }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <dt class="text-xs text-slate-500">Diperbarui</dt>
                    <dd class="text-lg font-bold text-blue-600">{{ backupResult.diperbarui }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <dt class="text-xs text-slate-500">Dilewati</dt>
                    <dd class="text-lg font-bold" :class="backupResult.dilewati > 0 ? 'text-rose-600' : 'text-slate-400'">
                        {{ backupResult.dilewati }}
                    </dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <dt class="text-xs text-slate-500">File fisik dipulihkan</dt>
                    <dd class="text-lg font-bold text-slate-700">{{ backupResult.file_dipulihkan ?? '-' }}</dd>
                </div>
            </dl>

            <div v-if="backupResult.peringatan?.length" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
                <p class="text-xs font-semibold text-amber-800">
                    {{ backupResult.peringatan.length }} peringatan saat pemulihan:
                </p>
                <ul class="mt-1 list-disc pl-5 text-xs text-amber-800">
                    <li v-for="p in backupResult.peringatan.slice(0, 10)" :key="p">{{ p }}</li>
                </ul>
            </div>

            <div v-if="backupResult.detil_lewati?.length" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3">
                <p class="text-xs font-semibold text-rose-800">Baris yang dilewati:</p>
                <ul class="mt-1 list-disc pl-5 text-xs text-rose-800">
                    <li v-for="s in backupResult.detil_lewati.slice(0, 10)" :key="s.alamat + s.alasan">
                        <span class="font-semibold">{{ s.alamat }}</span> — {{ s.alasan }}
                    </li>
                    <li v-if="backupResult.detil_lewati.length > 10" class="text-rose-600">
                        …dan {{ backupResult.detil_lewati.length - 10 }} lainnya
                    </li>
                </ul>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <!-- Kartu 1: Unduh + buat cadangan lengkap -->
            <div v-reveal class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-800">Cadangkan Data</h2>
                <p class="mt-2 text-sm text-slate-500">
                    <span class="font-medium">Backup JSON</span> (ringkas, data saja) atau
                    <span class="font-medium">Cadangkan Lengkap</span> — bundel ZIP berisi seluruh arsip,
                    seksi/rak/lokasi, riwayat, dan file fisik lampiran & QR, lengkap dengan checksum.
                </p>
                <div class="mt-6 flex flex-col gap-3">
                    <a
                        :href="route('backup.export')"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-navy px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Unduh File Backup (JSON)
                    </a>
                    <button
                        type="button"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50 disabled:opacity-60"
                        :disabled="running"
                        @click="runNow"
                    >
                        {{ running ? 'Membuat cadangan…' : 'Cadangkan Sekarang (ZIP)' }}
                    </button>
                </div>
            </div>

            <!-- Kartu 2: Pulihkan -->
            <div v-reveal :style="{ transitionDelay: '100ms' }" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-800">Pulihkan Data (Restore)</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Unggah file cadangan (<span class="font-medium">.zip</span> lengkap atau
                    <span class="font-medium">.json</span>). Data dipulihkan berdasarkan
                    <span class="font-semibold">kode_arsip</span> tanpa menghapus data lain —
                    arsip yang dibuat setelah tanggal backup tidak dihapus, hanya yang ada di file cadangan yang diperbarui.
                </p>

                <input
                    type="file"
                    accept=".json,.zip,application/json"
                    class="mt-5 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-brand-blue file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white"
                    @change="onFileChange"
                />
                <p v-if="selectedName" class="mt-2 text-xs text-slate-600">
                    File dipilih: {{ selectedName }}
                </p>
                <p v-if="form.errors.backup_file" class="mt-2 text-sm text-rose-600">
                    {{ form.errors.backup_file }}
                </p>

                <button
                    type="button"
                    class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-brand-blue px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                    :disabled="form.processing"
                    @click="openConfirm"
                >
                    Pulihkan dari Backup
                </button>
            </div>
        </div>

        <!-- Daftar cadangan tersimpan -->
        <div v-reveal :style="{ transitionDelay: '200ms' }" class="mt-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800">Cadangan Tersimpan</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Cadangan terjadwal harian disimpan di server ini (retensi 14 hari).
                    </p>
                </div>
                <div v-if="selectedIds.length > 0" class="shrink-0">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-700"
                        @click="bulkDelete"
                    >
                        <AppIcon name="trash" :size="16" :stroke-width="2" decorative />
                        Hapus {{ selectedIds.length }} Terpilih
                    </button>
                </div>
            </div>

            <div v-if="daftarBackup.length === 0" class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-500">
                Belum ada cadangan tersimpan. Klik "Cadangkan Sekarang" untuk membuatnya.
            </div>

            <template v-else>
                <p v-if="selectedIds.length === 0" class="mt-1 text-xs text-slate-400">Centang cadangan untuk menghapusnya.</p>

                <table class="mt-4 w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 w-10 text-center">
                                <input
                                    type="checkbox"
                                    :checked="isAllSelected"
                                    class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30"
                                    @change="onHeaderCheckboxChange"
                                />
                            </th>
                            <th class="py-2 pr-4 text-center">File</th>
                            <th class="py-2 pr-4 text-center">Waktu</th>
                            <th class="py-2 pr-4 text-center">Isi</th>
                            <th class="py-2 pr-4 text-center">Ukuran</th>
                            <th class="py-2 text-center"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="b in daftarBackup"
                            :key="b.nama_file"
                            class="hover:bg-slate-50 cursor-pointer"
                            :class="selectedIds.includes(b.nama_file) ? 'bg-blue-50 hover:bg-blue-50' : ''"
                            @click="toggleRow(b.nama_file)"
                        >
                            <td class="py-2.5 text-center" @click.stop>
                                <input
                                    type="checkbox"
                                    :checked="selectedIds.includes(b.nama_file)"
                                    class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30"
                                    @change="(e) => onRowCheckboxChange(e, b.nama_file)"
                                />
                            </td>
                            <td class="py-2.5 pr-4 font-mono text-center text-xs">{{ b.nama_file }}</td>
                            <td class="py-2.5 pr-4 text-center text-slate-600">{{ formatWaktu(b.diambil_pada) }}</td>
                            <td class="py-2.5 pr-4 text-center text-slate-600">
                                <span v-if="b.rincian">{{ b.rincian.dokumen_arsip ?? 0 }} arsip · {{ b.rincian.file_fisik ?? 0 }} file</span>
                            </td>
                            <td class="py-2.5 pr-4 text-center text-slate-600">{{ formatUkur(b.ukuran_bytes) }}</td>
                            <td class="py-2.5 text-center" @click.stop>
=======
            <div v-else>
                <table class="mt-4 w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">File</th>
                            <th class="py-2 pr-4">Waktu</th>
                            <th class="py-2 pr-4">Isi</th>
                            <th class="py-2 pr-4">Ukuran</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="b in daftarBackup" :key="b.nama_file" class="hover:bg-slate-50">
                            <td class="py-2.5 pr-4 font-mono text-xs">{{ b.nama_file }}</td>
                            <td class="py-2.5 pr-4 text-slate-600">{{ formatWaktu(b.diambil_pada) }}</td>
                            <td class="py-2.5 pr-4 text-slate-600">
                                <span v-if="b.rincian">{{ b.rincian.dokumen_arsip ?? 0 }} arsip · {{ b.rincian.file_fisik ?? 0 }} file</span>
                            </td>
                            <td class="py-2.5 pr-4 text-slate-600">{{ formatUkur(b.ukuran_bytes) }}</td>
                            <td class="py-2.5 text-right">
                                <a
                                    :href="route('backup.files', b.nama_file)"
                                    class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-blue hover:text-blue-700"
                                >
                                    <AppIcon name="download" :size="14" :stroke-width="1.8" class="shrink-0" decorative />
                                    Unduh
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="adaLebihLama" class="mt-4 flex items-center justify-between">
                    <button
                        type="button"
                        class="text-sm font-medium text-brand-blue hover:text-blue-700"
                        @click="router.get(route('backup.index'), { limit: limit + 10 })"
                    >
                        Lihat yang lebih lama
                    </button>
                </div>

                <p class="mt-3 text-xs text-slate-400">
                    Daftar menampilkan {{ limit }} cadangan terbaru. File cadangan lebih tua dari 14 hari
                    dihapus otomatis oleh retensi server. Unduh file untuk menyimpannya secara lokal.
                </p>
            </template>
        </div>

    <Modal :show="showConfirm" max-width="md" @close="showConfirm = false">
        <div class="px-6 py-5">
            <h3 class="text-lg font-bold text-slate-900">Konfirmasi Restore</h3>
            <p class="mt-2 text-sm text-slate-600">
                Proses ini dapat menimpa data arsip yang punya
                <span class="font-semibold">kode_arsip</span> sama dengan isi cadangan.
                Untuk bundel ZIP, referensi & file fisik ikut dipulihkan.
                Lanjutkan dari file
                <span class="font-semibold">{{ selectedName || 'terpilih' }}</span>?
            </p>
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton type="button" @click="showConfirm = false">Batal</SecondaryButton>
                <PrimaryButton
                    class="bg-brand-blue hover:bg-blue-700"
                    :disabled="form.processing"
                    @click="runImport"
                >
                    Ya, Pulihkan Data
                </PrimaryButton>
            </div>
        </div>
    </Modal>

    <Modal :show="showBulkDeleteModal" max-width="md" @close="showBulkDeleteModal = false">
        <div class="px-6 py-5">
            <h3 class="text-lg font-bold text-slate-900">Hapus Cadangan</h3>
            <p class="mt-2 text-sm text-slate-600">
                Apakah Anda yakin ingin menghapus
                <span class="font-semibold">{{ selectedIds.length }}</span>
                cadangan yang dipilih? Tindakan ini tidak dapat dibatalkan.
            </p>
            <div class="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                <ul class="list-disc pl-5 space-y-0.5">
                    <li v-for="f in selectedIds" :key="f" class="font-mono">{{ f }}</li>
                </ul>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton type="button" @click="showBulkDeleteModal = false">Batal</SecondaryButton>
                <PrimaryButton
                    class="bg-red-600 hover:bg-red-700"
                    @click="confirmBulkDelete"
                >
                    Hapus
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</AppLayout>
</template>
