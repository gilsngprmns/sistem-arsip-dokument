<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BoksEditModal from '@/Components/BoksEditModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import Modal from '@/Components/Modal.vue';
import PrintBoksLabelModal from '@/Components/PrintBoksLabelModal.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    boks: {
        type: Array,
        default: () => [],
    },
    rakList: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const errors = computed(() => page.props.errors ?? {});

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

const flashError = computed(() => page.props.flash?.error);
const flashErrorVisible = ref(false);
let flashErrorTimer = null;

watch(
    () => page.props.flash?.error,
    (v) => {
        clearTimeout(flashErrorTimer);
        if (v) {
            flashErrorVisible.value = true;
            flashErrorTimer = setTimeout(() => { flashErrorVisible.value = false; }, 5000);
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
    clearTimeout(flashErrorTimer);
    clearTimeout(flashDangerTimer);
});

// Create form
const emptyForm = () => ({
    rak_id: '',
    kode_boks: '',
    kapasitas: '',
    nomor_baris: '',
    nomor_boks: '',
    keterangan: '',
    is_active: true,
});

const form = ref(emptyForm());
const processing = ref(false);

const submitCreate = () => {
    processing.value = true;
    router.post(
        route('boks.store'),
        form.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                processing.value = false;
                form.value = emptyForm();
            },
            onError: () => {
                processing.value = false;
            },
        },
    );
};

// Edit state
const showEditModal = ref(false);
const editingBoks = ref(null);

const openEdit = (boks) => {
    editingBoks.value = boks;
    showEditModal.value = true;
};

const closeEdit = () => {
    showEditModal.value = false;
    editingBoks.value = null;
};

// Delete state
const showDeleteConfirm = ref(false);
const pendingDeleteId = ref(null);

const promptDelete = (boks) => {
    pendingDeleteId.value = boks.id;
    showDeleteConfirm.value = true;
};

const closeDeleteConfirm = () => {
    showDeleteConfirm.value = false;
    pendingDeleteId.value = null;
};

const confirmDelete = () => {
    const id = pendingDeleteId.value;
    closeDeleteConfirm();
    router.delete(
        route('boks.destroy', id),
        {
            preserveScroll: true,
            onError: () => { closeDeleteConfirm(); },
        },
    );
};

// ─── Bulk selection ───────────────────────────────────────────────
const selectedIds = ref([]);
const showBulkDeleteModal = ref(false);

const isAllSelected = computed(() =>
    props.boks.length > 0 && selectedIds.value.length === props.boks.length,
);

const toggleRow = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value.splice(idx, 1);
    }
};

const onRowCheckboxChange = (event, id) => {
    toggleRow(id);
    event.target.checked = selectedIds.value.includes(id);
};

const onHeaderCheckboxChange = (event) => {
    if (event.target.checked) {
        selectedIds.value = props.boks.map((b) => b.id);
    } else {
        selectedIds.value = [];
    }
    event.target.indeterminate = false;
};

const bulkDelete = () => { showBulkDeleteModal.value = true; };

const confirmBulkDelete = () => {
    router.post(route('boks.destroy-bulk'), { ids: selectedIds.value }, {
        preserveScroll: true,
        onSuccess: () => { showBulkDeleteModal.value = false; selectedIds.value = []; },
    });
};

const inlineErrors = computed(() => {
    if (showEditModal.value) return {};
    return errors.value;
});

// QR modal state
const showQrModal = ref(false);
const qrBoks = ref(null);

const openQrModal = (boks) => {
    qrBoks.value = boks;
    showQrModal.value = true;
};
</script>

<template>
    <Head title="Boks Arsip" />

    <AppLayout>
        <template #skeleton-header>
            <div class="h-7 w-48 rounded bg-slate-200" />
        </template>

        <template #skeleton>
            <div class="rounded-2xl bg-slate-100">
                <div class="space-y-4 p-6">
                    <div v-for="i in 3" :key="i" class="h-10 w-full rounded-xl bg-slate-200" />
                </div>
            </div>
        </template>

        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Boks Arsip</h1>
                    <p class="mt-1 text-sm text-slate-500">Kelola boks penyimpanan di setiap rak.</p>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Flash: success -->
            <div
                v-if="flashVisible"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
            >
                {{ flashSuccess }}
            </div>

            <!-- Flash: error -->
            <div
                v-if="flashErrorVisible"
                class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"
            >
                {{ flashError }}
            </div>

            <!-- Flash: danger -->
            <div
                v-if="flashDangerVisible"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800"
            >
                {{ flashDanger }}
            </div>

            <!-- Tambah Boks -->
            <div v-reveal class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-800">Tambah Boks</h2>
                <p class="mt-1 text-sm text-slate-500">Kode boks harus unik di seluruh sistem.</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Rak *</label>
                        <select v-model="form.rak_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            <option value="" disabled>Pilih rak</option>
                            <option v-for="rak in rakList" :key="rak.id" :value="rak.id">
                                {{ rak.kode_rak }} — {{ rak.nama_rak }}
                            </option>
                        </select>
                        <p v-if="inlineErrors.rak_id" class="mt-1 text-xs text-red-600">{{ inlineErrors.rak_id }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Kode Boks *</label>
                        <input v-model="form.kode_boks" type="text" placeholder="mis. BOK-RAK-01" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                        <p v-if="inlineErrors.kode_boks" class="mt-1 text-xs text-red-600">{{ inlineErrors.kode_boks }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Kapasitas Dokumen *</label>
                        <input v-model.number="form.kapasitas" type="number" min="1" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                        <p v-if="inlineErrors.kapasitas" class="mt-1 text-xs text-red-600">{{ inlineErrors.kapasitas }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Nomor Baris</label>
                        <input v-model="form.nomor_baris" type="text" placeholder="mis. B-01" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                        <p v-if="inlineErrors.nomor_baris" class="mt-1 text-xs text-red-600">{{ inlineErrors.nomor_baris }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Nomor Boks</label>
                        <input v-model="form.nomor_boks" type="text" placeholder="mis. BOX-A01" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                        <p v-if="inlineErrors.nomor_boks" class="mt-1 text-xs text-red-600">{{ inlineErrors.nomor_boks }}</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Keterangan</label>
                        <input v-model="form.keterangan" type="text" placeholder="Catatan tambahan (opsional)" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" />
                        <p v-if="inlineErrors.keterangan" class="mt-1 text-xs text-red-600">{{ inlineErrors.keterangan }}</p>
                    </div>

                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                            Boks aktif
                        </label>
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button
                        type="button"
                        :disabled="processing"
                        @click="submitCreate"
                        class="rounded-xl bg-brand-blue px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        Tambah Boks
                    </button>
                </div>
            </div>

            <!-- Tabel Boks -->
            <div v-reveal class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-slate-800">Daftar Boks</h2>
                    <div v-if="selectedIds.length > 0" class="shrink-0">
                        <button type="button"
                            class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-700"
                            @click="bulkDelete">
                            <AppIcon name="trash" :size="16" :stroke-width="2" decorative />
                            Hapus {{ selectedIds.length }} Terpilih
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-slate-600">
                                <th class="py-2 w-10 text-center">
                                    <input type="checkbox" :checked="isAllSelected"
                                        class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30"
                                        @change="onHeaderCheckboxChange" />
                                </th>
                                <th class="px-4 py-3 font-semibold text-center">Kode Boks</th>
                                <th class="px-4 py-3 font-semibold text-center">Rak</th>
                                <th class="px-4 py-3 font-semibold text-center">No. Baris</th>
                                <th class="px-4 py-3 font-semibold text-center">No. Boks</th>
                                <th class="px-4 py-3 font-semibold text-center">Keterangan</th>
                                <th class="px-4 py-3 font-semibold text-center">Kapasitas</th>
                                <th class="px-4 py-3 font-semibold text-center">Arsip</th>
                                <th class="px-4 py-3 font-semibold text-center">Status</th>
                                <th class="px-4 py-3 font-semibold text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in boks"
                                :key="item.id"
                                class="border-b border-slate-100 hover:bg-slate-50 cursor-pointer"
                                :class="selectedIds.includes(item.id) ? 'bg-blue-50 hover:bg-blue-50' : ''"
                                @click="toggleRow(item.id)"
                            >
                                <td class="py-2.5 text-center" @click.stop>
                                    <input type="checkbox"
                                        :checked="selectedIds.includes(item.id)"
                                        class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30"
                                        @change="(e) => onRowCheckboxChange(e, item.id)" />
                                </td>
                                <td class="px-4 py-2.5 font-medium text-slate-800 text-center">{{ item.kode_boks }}</td>
                                <td class="px-4 py-3 text-center text-slate-600">
                                    {{ item.rak?.kode_rak || '—' }}
                                    <span class="block text-xs text-slate-400">{{ item.rak?.nama_rak }}</span>
                                </td>
                                <td class="px-4 py-3 text-center text-slate-600">{{ item.nomor_baris || '—' }}</td>
                                <td class="px-4 py-3 text-center text-slate-600">{{ item.nomor_boks || '—' }}</td>
                                <td class="px-4 py-3 text-center text-slate-600">{{ item.keterangan || '—' }}</td>
                                <td class="px-4 py-3 text-center text-slate-700">
                                    {{ item.dokumen_arsip_count ?? 0 }} / {{ item.kapasitas ?? 50 }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center justify-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                        {{ item.dokumen_arsip_count ?? 0 }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="item.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'"
                                    >
                                        {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-center" @click.stop>
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="rounded p-1 text-slate-400 hover:bg-brand-blue/10 hover:text-brand-blue"
                                            title="Lihat QR Code boks"
                                            @click="openQrModal(item)"
                                        >
                                            <AppIcon name="qr" :size="14" :stroke-width="2" class="h-3.5 w-3.5" decorative />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-600"
                                            title="Edit boks"
                                            @click="openEdit(item)"
                                        >
                                            <AppIcon name="edit" :size="14" :stroke-width="2" class="h-3.5 w-3.5" decorative />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded p-1 text-slate-400 hover:bg-rose-100 hover:text-rose-600"
                                            title="Hapus boks"
                                            @click="promptDelete(item)"
                                        >
                                            <AppIcon name="trash" :size="14" :stroke-width="2" class="h-3.5 w-3.5" decorative />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="boks.length === 0">
                                <td colspan="10" class="px-4 py-8 text-center text-sm text-slate-500">
                                    Belum ada boks arsip. Tambahkan di atas.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <BoksEditModal
            :show="showEditModal"
            :boks="editingBoks"
            :rak-list="rakList"
            @close="closeEdit"
        />

        <!-- Delete Confirm -->
        <ConfirmModal
            :show="showDeleteConfirm"
            title="Hapus Boks"
            message="Hapus boks ini? Boks yang masih berisi arsip tidak dapat dihapus."
            confirm-label="Hapus"
            @confirm="confirmDelete"
            @close="closeDeleteConfirm"
        />

        <!-- Bulk Delete Confirmation Modal -->
        <Modal :show="showBulkDeleteModal" max-width="md" @close="showBulkDeleteModal = false">
            <div class="px-6 py-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Hapus Boks Arsip</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Hapus
                            <span class="font-semibold">{{ selectedIds.length }}</span>
                            boks yang dipilih? Boks yang masih berisi arsip tidak akan terhapus.
                        </p>
                    </div>
                    <button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-100" aria-label="Tutup" @click="showBulkDeleteModal = false">
                        <AppIcon name="close" :size="20" :stroke-width="2" class="h-5 w-5" decorative />
                    </button>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="showBulkDeleteModal = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700"
                        @click="confirmBulkDelete"
                    >
                        Hapus
                    </button>
                </div>
            </div>
        </Modal>

        <!-- QR Label Modal -->
        <PrintBoksLabelModal
            :show="showQrModal"
            :boks="qrBoks"
            @close="showQrModal = false"
        />
    </AppLayout>
</template>
