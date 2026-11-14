<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import Modal from '@/Components/Modal.vue';
import RakEditModal from '@/Components/RakEditModal.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    rak: {
        type: Array,
        default: () => [],
    },
    seksiOptions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const errors = computed(() => page.props.errors ?? {});

// Flash message — success
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

// Flash message — error
const flashError = computed(() => page.props.flash?.error);
const flashVisibleError = ref(false);
let flashErrorTimer = null;

watch(
    () => page.props.flash?.error,
    (v) => {
        clearTimeout(flashErrorTimer);
        if (v) {
            flashVisibleError.value = true;
            flashErrorTimer = setTimeout(() => { flashVisibleError.value = false; }, 5000);
        }
    },
    { immediate: true },
);

// Flash message — danger (deletion)
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

// Rak form (create)
const emptyRakForm = () => ({
    seksi_id: props.seksiOptions[0]?.id ?? '',
    kode_rak: '',
    nama_rak: '',
    nomor_rak: '',
    deskripsi: '',
    is_active: true,
});

const rakForm = ref(emptyRakForm());

const submitRakForm = () => {
    router.post(route('rak.store'), rakForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            rakForm.value = emptyRakForm();
        },
    });
};

// Rak edit modal
const showEditModal = ref(false);
const editingRak = ref(null);

const openEditRak = (rak) => {
    editingRak.value = rak;
    showEditModal.value = true;
};

const closeEditRak = () => {
    showEditModal.value = false;
    editingRak.value = null;
};

// Delete state
const showDeleteConfirm = ref(false);
const pendingDeleteId = ref(null);

const promptDeleteRak = (id) => {
    pendingDeleteId.value = id;
    showDeleteConfirm.value = true;
};

const closeDeleteConfirm = () => {
    showDeleteConfirm.value = false;
    pendingDeleteId.value = null;
};

const confirmDelete = () => {
    const id = pendingDeleteId.value;
    closeDeleteConfirm();

    router.delete(route('rak.destroy', id), {
        preserveScroll: true,
        onError: () => { closeDeleteConfirm(); },
    });
};

// ─── Bulk selection ───────────────────────────────────────────────
const selectedIds = ref([]);
const showBulkDeleteModal = ref(false);

const isAllSelected = computed(() =>
    props.rak.length > 0 && selectedIds.value.length === props.rak.length,
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
        selectedIds.value = props.rak.map((r) => r.id);
    } else {
        selectedIds.value = [];
    }
    event.target.indeterminate = false;
};

const bulkDelete = () => { showBulkDeleteModal.value = true; };

const confirmBulkDelete = () => {
    router.post(route('rak.destroy-bulk'), { ids: selectedIds.value }, {
        preserveScroll: true,
        onSuccess: () => { showBulkDeleteModal.value = false; selectedIds.value = []; },
    });
};

// Scoped errors: form inline hanya tampil error saat modal TERTUTUP,
// modal hanya tampil error saat modal TERBUKA (pola Users/Index.vue)
const inlineRakErrors = computed(() => {
    if (showEditModal.value) return {};
    return errors.value;
});
</script>

<template>
    <Head title="Rak Arsip" />
    <AppLayout>
        <template #skeleton-header>
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <div class="h-7 w-40 rounded bg-slate-200" />
                    <div class="h-4 w-72 rounded bg-slate-200" />
                </div>
            </div>
        </template>

        <template #skeleton>
            <div class="space-y-6">
                <div class="rounded-2xl bg-slate-100 p-4">
                    <div class="h-5 w-48 rounded bg-slate-200" />
                    <div class="mt-1 h-3 w-72 rounded bg-slate-200" />
                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <div v-for="i in 5" :key="'sk-lok-' + i" class="h-10 rounded-xl bg-slate-200" />
                    </div>
                    <div class="mt-4 flex justify-end"><div class="h-10 w-28 rounded-xl bg-slate-200" /></div>
                    <div class="mt-4 h-48 rounded-xl bg-slate-200" />
                </div>
                <div class="rounded-2xl bg-slate-100 p-4">
                    <div class="h-5 w-40 rounded bg-slate-200" />
                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="i in 3" :key="'sk-rak-' + i" class="h-10 rounded-xl bg-slate-200" />
                    </div>
                    <div class="mt-4 h-20 rounded-xl bg-slate-200" />
                    <div class="mt-4 flex justify-end"><div class="h-10 w-28 rounded-xl bg-slate-200" /></div>
                </div>
                <div class="rounded-2xl bg-slate-100 p-4">
                    <div class="h-5 w-40 rounded bg-slate-200" />
                    <div class="mt-4 space-y-2">
                        <div v-for="i in 5" :key="'sk-rak-tbl-' + i" class="h-12 rounded bg-slate-200" />
                    </div>
                </div>
            </div>
        </template>

        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Rak Arsip</h1>
                    <p class="text-sm text-slate-500">Kelola rak fisik per seksi — satu record = satu rak bernomor di ruang arsip.</p>
                </div>
            </div>
        </template>

        <!-- Flash success message -->
        <div
            v-if="flashSuccess && flashVisible"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
        >
            {{ flashSuccess }}
        </div>

        <!-- Flash error message -->
        <div
            v-if="flashError && flashVisibleError"
            class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"
        >
            {{ flashError }}
        </div>

        <!-- Flash danger message (deletion) -->
        <div
            v-if="flashDanger && flashDangerVisible"
            class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"
        >
            {{ flashDanger }}
        </div>

        <!-- Tambah Rak Baru -->
        <div v-reveal :style="{ transitionDelay: '100ms' }" class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-800">Tambah Rak Baru</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Seksi pemilik rak</label>
                    <select v-model="rakForm.seksi_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-700">
                        <option v-for="seksi in props.seksiOptions" :key="seksi.id" :value="seksi.id">
                            {{ seksi.nama_seksi }}
                        </option>
                    </select>
                    <p v-if="inlineRakErrors.seksi_id" class="mt-1 text-xs text-red-600">{{ inlineRakErrors.seksi_id }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Kode Rak</label>
                    <input v-model="rakForm.kode_rak" type="text" placeholder="mis. RAK-PEM-01" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-700" />
                    <p v-if="inlineRakErrors.kode_rak" class="mt-1 text-xs text-red-600">{{ inlineRakErrors.kode_rak }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nama Rak</label>
                    <input v-model="rakForm.nama_rak" type="text" placeholder="mis. Rak No. 1 Seksi Pemerintahan" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-700" />
                    <p v-if="inlineRakErrors.nama_rak" class="mt-1 text-xs text-red-600">{{ inlineRakErrors.nama_rak }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nomor Rak</label>
                    <input v-model="rakForm.nomor_rak" type="text" placeholder="mis. 1" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-700" />
                    <p v-if="inlineRakErrors.nomor_rak" class="mt-1 text-xs text-red-600">{{ inlineRakErrors.nomor_rak }}</p>
                </div>

                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-700">
                        <input v-model="rakForm.is_active" type="checkbox" class="h-4 w-4" />
                        Aktif
                    </label>
                </div>
            </div>

            <div class="mt-4">
                <label class="mb-1 block text-sm font-medium text-slate-700">Deskripsi</label>
                <textarea v-model="rakForm.deskripsi" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-700"></textarea>
                <p v-if="inlineRakErrors.deskripsi" class="mt-1 text-xs text-red-600">{{ inlineRakErrors.deskripsi }}</p>
            </div>

            <div class="mt-4 flex justify-end">
                <button type="button" @click="submitRakForm" class="rounded-xl bg-brand-blue px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                    Simpan Rak
                </button>
            </div>
        </div>

        <!-- Tabel Rak -->
        <div v-reveal :style="{ transitionDelay: '200ms' }" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-slate-800">Daftar Rak Arsip</h2>
                <div v-if="selectedIds.length > 0" class="shrink-0">
                    <button type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-700"
                        @click="bulkDelete">
                        <AppIcon name="trash" :size="16" :stroke-width="2" decorative />
                        Hapus {{ selectedIds.length }} Terpilih
                    </button>
                </div>
            </div>

            <!-- Mobile / tablet: card list (< lg) -->
            <div class="lg:hidden">
                <!-- Header checkbox (mobile only) -->
                <div v-if="props.rak.length > 0" class="px-4 py-2 flex items-center justify-between border-b border-slate-100">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" :checked="isAllSelected"
                            class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30"
                            @change="onHeaderCheckboxChange" />
                        Pilih semua
                    </label>
                    <span v-if="selectedIds.length > 0" class="text-xs text-slate-500">{{ selectedIds.length }} terpilih</span>
                </div>
                <ul class="divide-y divide-slate-100">
                    <li
                        v-for="item in props.rak"
                        :key="`card-${item.id}`"
                        class="px-4 py-3"
                        :class="selectedIds.includes(item.id) ? 'bg-blue-50' : ''"
                        @click="toggleRow(item.id)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-2 min-w-0 flex-1">
                                <div class="pt-0.5 shrink-0">
                                    <input type="checkbox"
                                        :checked="selectedIds.includes(item.id)"
                                        class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30"
                                        @change="(e) => onRowCheckboxChange(e, item.id)"
                                        @click.stop />
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-slate-800">{{ item.kode_rak }}</span>
                                        <span class="text-sm text-slate-600 truncate">{{ item.nama_rak }}</span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        No. {{ item.nomor_rak || '—' }} · {{ item.seksi?.nama_seksi || '—' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2" @click.stop>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="item.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                                >{{ item.is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                <button
                                    type="button"
                                    class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-brand-blue"
                                    title="Edit rak"
                                    @click="openEditRak(item)"
                                >
                                    <AppIcon name="edit" :size="15" :stroke-width="2" class="h-3.5 w-3.5" decorative />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1.5 text-slate-400 hover:bg-rose-100 hover:text-rose-600"
                                    title="Hapus rak"
                                    @click="promptDeleteRak(item.id)"
                                >
                                    <AppIcon name="trash" :size="15" :stroke-width="2" class="h-3.5 w-3.5" decorative />
                                </button>
                            </div>
                        </div>
                    </li>
                    <li v-if="props.rak.length === 0" class="px-4 py-8 text-center text-sm text-slate-500">
                        Belum ada rak arsip. Tambahkan rak baru di atas.
                    </li>
                </ul>
            </div>

            <!-- Desktop: table (≥ lg) -->
            <div class="hidden overflow-x-auto lg:block">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-600">
                            <th class="py-2 w-10 text-center">
                                <input type="checkbox" :checked="isAllSelected"
                                    class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30"
                                    @change="onHeaderCheckboxChange" />
                            </th>
                            <th class="px-3 py-3 font-semibold text-center">Kode Rak</th>
                            <th class="px-3 py-3 font-semibold text-center">Nama Rak</th>
                            <th class="px-3 py-3 font-semibold text-center">No.</th>
                            <th class="px-3 py-3 font-semibold text-center">Seksi</th>
                            <th class="px-3 py-3 font-semibold text-center">Status</th>
                            <th class="px-3 py-3 font-semibold text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in props.rak" :key="item.id"
                            class="border-b border-slate-100 hover:bg-slate-50 cursor-pointer"
                            :class="selectedIds.includes(item.id) ? 'bg-blue-50 hover:bg-blue-50' : ''"
                            @click="toggleRow(item.id)">
                            <td class="py-2.5 text-center" @click.stop>
                                <input type="checkbox"
                                    :checked="selectedIds.includes(item.id)"
                                    class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30"
                                    @change="(e) => onRowCheckboxChange(e, item.id)" />
                            </td>
                            <td class="px-3 py-2.5 font-medium text-center text-slate-800">{{ item.kode_rak }}</td>
                            <td class="px-3 py-2.5 text-center">{{ item.nama_rak }}</td>
                            <td class="px-3 py-2.5 text-center">{{ item.nomor_rak || '—' }}</td>
                            <td class="px-3 py-2.5 text-center">{{ item.seksi?.nama_seksi || '—' }}</td>
                            <td class="px-3 py-2.5 text-center">
                                <span v-if="item.is_active" class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Aktif</span>
                                <span v-else class="rounded-full bg-slate-200 px-2 py-1 text-xs font-semibold text-slate-600">Nonaktif</span>
                            </td>
                            <td class="px-3 py-2.5 text-center" @click.stop>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-brand-blue"
                                        title="Edit rak"
                                        @click="openEditRak(item)"
                                    >
                                        <AppIcon name="edit" :size="16" :stroke-width="2" class="h-4 w-4" decorative />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded p-1 text-slate-400 hover:bg-rose-100 hover:text-rose-600"
                                        title="Hapus rak"
                                        @click="promptDeleteRak(item.id)"
                                    >
                                        <AppIcon name="trash" :size="16" :stroke-width="2" class="h-4 w-4" decorative />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="props.rak.length === 0">
                            <td colspan="7" class="px-3 py-8 text-center text-sm text-slate-500">
                                Belum ada rak arsip. Tambahkan rak baru di atas.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Rak Edit Modal -->
        <RakEditModal
            :show="showEditModal"
            mode="edit"
            :initial-data="editingRak"
            :seksi-list="props.seksiOptions"
            @close="closeEditRak"
        />

        <!-- Delete Confirmation Modal -->
        <ConfirmModal
            :show="showDeleteConfirm"
            title="Hapus Data"
            message="Hapus rak ini? Dokumen arsip di dalamnya akan tetap tersimpan."
            confirm-label="Hapus"
            @confirm="confirmDelete"
            @close="closeDeleteConfirm"
        />

        <!-- Bulk Delete Confirmation Modal -->
        <Modal :show="showBulkDeleteModal" max-width="md" @close="showBulkDeleteModal = false">
            <div class="px-6 py-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Hapus Rak Arsip</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Hapus
                            <span class="font-semibold">{{ selectedIds.length }}</span>
                            rak yang dipilih? Rak yang masih berisi arsip tidak akan terhapus.
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
    </AppLayout>
</template>
