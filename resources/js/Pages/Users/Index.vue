<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';
import UserActionButtons from '@/Components/UserActionButtons.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    users: { type: Object, default: () => ({ data: [], links: [], current_page: 1, last_page: 1 }) },
    seksiList: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ q: '', role: '', status: '', seksi_id: '' }) },
    activeAdminCount: { type: Number, default: 0 },
});

const page = usePage();
const errors = computed(() => page.props.errors ?? {});
const flashSuccess = computed(() => page.props.flash?.success);
const flashVisible = ref(false);
let flashTimer = null;

const flashDanger = computed(() => page.props.flash?.danger);
const flashDangerVisible = ref(false);
let flashDangerTimer = null;

const pageRecovery = computed(() =>
    page.props.flash?.recovery && page.props.flash?.recovery_email
        ? page.props.flash.recovery_email
        : false,
);

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

const pageError = computed(() => {
    const err = errors.value?.user;
    if (!err) return null;
    return Array.isArray(err) ? err[0] : err;
});

const filters = ref({
    q: props.filters.q ?? '',
    role: props.filters.role ?? '',
    status: props.filters.status ?? '',
    seksi_id: props.filters.seksi_id ?? '',
});

const showCreatePassword = ref(false);
const showResetPassword = ref(false);
const showPasswordValue = ref(false);
const showPasswordConfirm = ref(false);

const userRows = computed(() => props.users?.data ?? []);
const pagination = computed(() => props.users);

const hasActiveFilters = computed(() =>
    Boolean(filters.value.q || filters.value.role || filters.value.status || filters.value.seksi_id),
);

const emptyMessage = computed(() =>
    hasActiveFilters.value
        ? 'Tidak ada pengguna yang cocok dengan filter'
        : 'Belum ada akun petugas',
);

const emptyDescription = computed(() =>
    hasActiveFilters.value
        ? 'Coba ubah kata kunci atau reset filter untuk melihat daftar lagi.'
        : 'Tambah pengguna baru lewat formulir di atas agar petugas dapat masuk ke sistem.',
);

const emptyForm = () => ({
    name: '',
    email: '',
    password: '',
    role: 'staf',
    seksi_id: props.seksiList[0]?.id ?? '',
    is_active: true,
});

const createForm = ref(emptyForm());
const showEditModal = ref(false);
const editingUser = ref(null);
const editForm = ref({
    name: '',
    email: '',
    role: 'staf',
    seksi_id: '',
    is_active: true,
});

const resetTarget = ref(null);
const resetForm = ref({
    password: '',
    password_confirmation: '',
});

const createErrors = computed(() => {
    if (showEditModal.value || showResetPassword.value) return {};
    return errors.value;
});

const editErrors = computed(() => {
    if (!showEditModal.value) return {};
    return errors.value;
});

const resetErrors = computed(() => {
    if (!showResetPassword.value) return {};
    return errors.value;
});

const syncSeksiForRole = (form) => {
    if (form.role === 'staf' && !form.seksi_id && props.seksiList.length) {
        form.seksi_id = props.seksiList[0].id;
    }
};

const onCreateRoleChange = () => syncSeksiForRole(createForm.value);
const onEditRoleChange = () => syncSeksiForRole(editForm.value);

const canDeactivateUser = (user) => {
    if (user.role === 'admin' && user.is_active && props.activeAdminCount <= 1) return false;
    return true;
};

const resetCreateForm = () => {
    createForm.value = emptyForm();
    showCreatePassword.value = false;
};

const openEdit = (user) => {
    editingUser.value = user;
    editForm.value = {
        name: user.name,
        email: user.email,
        role: user.role,
        seksi_id: user.seksi_id ?? (user.role === 'staf' ? (props.seksiList[0]?.id ?? '') : ''),
        is_active: Boolean(user.is_active),
    };
    showEditModal.value = true;
};

const closeEdit = () => {
    showEditModal.value = false;
    editingUser.value = null;
};

const openResetPassword = (user) => {
    resetTarget.value = user;
    resetForm.value = { password: '', password_confirmation: '' };
    showPasswordValue.value = false;
    showPasswordConfirm.value = false;
    showResetPassword.value = true;
};

const openResetFromEdit = () => {
    const user = editingUser.value;
    closeEdit();
    if (user) {
        openResetPassword(user);
    }
};

const closeResetPassword = () => {
    showResetPassword.value = false;
    resetTarget.value = null;
    resetForm.value = { password: '', password_confirmation: '' };
};

const submitCreate = () => {
    syncSeksiForRole(createForm.value);
    router.post(route('users.store'), createForm.value, {
        preserveScroll: true,
        onSuccess: resetCreateForm,
    });
};

const submitEdit = () => {
    if (!editingUser.value) return;
    syncSeksiForRole(editForm.value);

    router.put(route('users.update', editingUser.value.id), editForm.value, {
        preserveScroll: true,
        onSuccess: closeEdit,
    });
};

const submitResetPassword = () => {
    if (!resetTarget.value) return;

    router.post(route('users.password', resetTarget.value.id), resetForm.value, {
        preserveScroll: true,
        onSuccess: closeResetPassword,
    });
};

const showDeleteModal = ref(false);
const deleteTarget = ref(null);

const deletePermanent = (user) => {
    deleteTarget.value = user;
    showDeleteModal.value = true;
};

const confirmDeleteUser = () => {
    router.post(route('users.force-delete', deleteTarget.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            deleteTarget.value = null;
        },
    });
};

// ─── Bulk selection ───────────────────────────────────────────────
const selectedIds = ref([]);
const showBulkDeleteModal = ref(false);

const isAllSelected = computed(() =>
    userRows.value.length > 0 && selectedIds.value.length === userRows.value.length,
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
        selectedIds.value = userRows.value.map((u) => u.id);
    } else {
        selectedIds.value = [];
    }
    event.target.indeterminate = false;
};

const bulkDelete = () => { showBulkDeleteModal.value = true; };

const confirmBulkDelete = () => {
    router.post(route('users.destroy-bulk'), { ids: selectedIds.value }, {
        preserveScroll: true,
        onSuccess: () => { showBulkDeleteModal.value = false; selectedIds.value = []; },
    });
};

onMounted(() => {
    if (pageRecovery.value) {
        const target = userRows.value.find((u) => u.email === pageRecovery.value);
        if (target) openResetPassword(target);
    }
});

const applyFilters = () => {
    router.get(route('users.index'), filters.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filters.value = { q: '', role: '', status: '', seksi_id: '' };
    applyFilters();
};

const roleLabel = (role) => (role === 'admin' ? 'Admin' : 'Staf');

const inputClass = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700';
const selectClass = 'w-full rounded-xl border border-slate-300 py-2 pl-3 pr-10 text-sm text-slate-700';
const filterSelectClass = 'min-w-[9.5rem] rounded-xl border border-slate-300 py-2 pl-3 pr-10 text-sm text-slate-700';
const sectionTitle = 'text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400';
const eyeBtnClass = 'absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-700';
</script>

<template>
    <Head title="Manajemen Pengguna" />
    <AppLayout>
        <template #skeleton-header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-1">
                    <div class="h-7 w-56 rounded bg-slate-200" />
                    <div class="h-4 w-72 rounded bg-slate-200" />
                </div>
                <div class="h-10 w-32 rounded-xl bg-slate-200" />
            </div>
        </template>

        <template #skeleton>
            <div class="space-y-4">
                <div class="rounded-2xl bg-slate-100 p-4">
                    <div class="h-5 w-40 rounded bg-slate-200" />
                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="i in 3" :key="'sk-user-' + i" class="h-10 rounded-xl bg-slate-200" />
                    </div>
                    <div class="mt-4 h-10 w-32 rounded-xl bg-slate-200" />
                </div>
                <div class="flex flex-wrap gap-2 rounded-2xl bg-slate-100 p-3">
                    <div class="h-10 flex-1 rounded-xl bg-slate-200" />
                    <div v-for="i in 3" :key="'sk-flt-' + i" class="h-10 w-32 rounded-xl bg-slate-200" />
                </div>
                <div class="h-48 rounded-2xl bg-slate-100">
                    <div class="h-9 border-b border-slate-100" />
                    <div v-for="i in 4" :key="'sk-usr-row-' + i" class="h-12 border-b border-slate-100" />
                </div>
            </div>
        </template>

        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Manajemen Pengguna</h1>
                    <p class="mt-1 text-sm text-slate-500">Kelola akun, role, seksi, dan status akses pengguna.</p>
                </div>
                <a
                    :href="route('reports.users')"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-emerald-600 bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                >
                    <AppIcon name="excel" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
                    Ekspor Excel
                </a>
            </div>
        </template>

        <div
            v-if="flashSuccess && flashVisible"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
            role="status"
        >
            {{ flashSuccess }}
        </div>

        <div
            v-if="flashDanger && flashDangerVisible"
            class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"
            role="alert"
        >
            {{ flashDanger }}
        </div>

        <div
            v-if="pageError"
            class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"
            role="alert"
        >
            {{ pageError }}
        </div>

        <div v-reveal class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-base font-semibold text-slate-800">Tambah Pengguna</h2>

            <div class="mt-3 space-y-3">
                <section>
                    <h3 :class="sectionTitle">Identitas</h3>
                    <div class="mt-2 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Nama lengkap</label>
                            <input v-model="createForm.name" type="text" :class="inputClass">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                            <input v-model="createForm.email" type="email" :class="inputClass">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Password</label>
                            <div class="relative">
                                <input
                                    v-model="createForm.password"
                                    :type="showCreatePassword ? 'text' : 'password'"
                                    autocomplete="new-password"
                                    :class="`${inputClass} pr-11`"
                                >
                                <button
                                    type="button"
                                    :class="eyeBtnClass"
                                    :title="showCreatePassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                    :aria-label="showCreatePassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                    @click="showCreatePassword = !showCreatePassword"
                                >
                                    <AppIcon v-if="!showCreatePassword" name="eye" :size="20" :stroke-width="1.8" class="h-5 w-5" decorative />
                                    <AppIcon v-else name="eye-off" :size="20" :stroke-width="1.8" class="h-5 w-5" decorative />
                                </button>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500">Minimal 8 karakter.</p>
                        </div>
                    </div>
                </section>

                <section class="border-t border-slate-100 pt-3">
                    <h3 :class="sectionTitle">Akses</h3>
                    <div class="mt-2 grid items-start gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Role</label>
                            <select v-model="createForm.role" :class="selectClass" @change="onCreateRoleChange">
                                <option value="staf">Staf</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">
                                Seksi
                                <span v-if="createForm.role === 'staf'" class="text-rose-600">*</span>
                            </label>
                            <select
                                v-model="createForm.seksi_id"
                                :class="selectClass"
                                :required="createForm.role === 'staf'"
                            >
                                <option v-if="createForm.role === 'admin'" value="">Tanpa seksi</option>
                                <option v-if="createForm.role === 'staf'" disabled value="">Pilih seksi</option>
                                <option v-for="seksi in props.seksiList" :key="seksi.id" :value="seksi.id">{{ seksi.nama_seksi }}</option>
                            </select>
                            <p v-if="createForm.role === 'staf'" class="mt-0.5 text-xs text-slate-500">Wajib untuk role Staf.</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                            <label class="inline-flex min-h-[38px] w-full items-center gap-2.5 text-sm text-slate-700">
                                <input
                                    v-model="createForm.is_active"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30"
                                >
                                Akun aktif
                            </label>
                        </div>
                    </div>
                </section>
            </div>

            <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
                <div class="text-xs text-red-600">
                    <span v-for="(message, key) in createErrors" :key="key" class="mr-3">{{ Array.isArray(message) ? message[0] : message }}</span>
                </div>
                <button
                    type="button"
                    class="rounded-xl bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                    @click="submitCreate"
                >
                    Tambah Pengguna
                </button>
            </div>
        </div>

        <div v-reveal :style="{ transitionDelay: '100ms' }" class="mb-4 flex flex-col gap-2.5 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm md:flex-row md:flex-wrap md:items-center md:gap-3 md:p-4">
            <input
                v-model="filters.q"
                type="search"
                placeholder="Cari nama atau email"
                class="rounded-xl border border-slate-300 px-3 py-2 text-sm md:min-w-[12rem] md:flex-1"
                @keyup.enter="applyFilters"
            >
            <select v-model="filters.role" :class="filterSelectClass" @change="applyFilters">
                <option value="">Semua role</option>
                <option value="admin">Admin</option>
                <option value="staf">Staf</option>
            </select>
            <select v-model="filters.seksi_id" :class="`${filterSelectClass} min-w-[10.5rem]`" @change="applyFilters">
                <option value="">Semua seksi</option>
                <option value="none">Tanpa seksi</option>
                <option v-for="seksi in props.seksiList" :key="seksi.id" :value="String(seksi.id)">{{ seksi.nama_seksi }}</option>
            </select>
            <select v-model="filters.status" :class="`${filterSelectClass} min-w-[10rem]`" @change="applyFilters">
                <option value="">Semua status</option>
                <option value="aktif">Aktif</option>
                <option value="nonaktif">Nonaktif</option>
            </select>
            <button
                type="button"
                class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                @click="applyFilters"
            >
                Cari
            </button>
        </div>

        <div v-reveal :style="{ transitionDelay: '200ms' }" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h2 class="text-lg font-semibold text-slate-800">Daftar Pengguna</h2>
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
                <div v-if="userRows.length > 0" class="px-4 py-2 flex items-center justify-between border-b border-slate-100">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" :checked="isAllSelected"
                            class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30"
                            @change="onHeaderCheckboxChange" />
                        Pilih semua
                    </label>
                    <span v-if="selectedIds.length > 0" class="text-xs text-slate-500">{{ selectedIds.length }} terpilih</span>
                </div>
                <EmptyState
                    v-if="!userRows.length"
                    :title="emptyMessage"
                    :description="emptyDescription"
                />
                <ul v-else class="divide-y divide-slate-100">
                    <li
                        v-for="user in userRows"
                        :key="`card-${user.id}`"
                        class="px-4 py-4"
                        :class="selectedIds.includes(user.id) ? 'bg-blue-50' : ''"
                        @click="toggleRow(user.id)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-2 min-w-0 flex-1">
                                <div class="pt-0.5 shrink-0">
                                    <input type="checkbox"
                                        :checked="selectedIds.includes(user.id)"
                                        class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30"
                                        @change="(e) => onRowCheckboxChange(e, user.id)"
                                        @click.stop />
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-slate-800">{{ user.name }}</span>
                                        <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-200">
                                            {{ roleLabel(user.role) }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-sm text-slate-500 truncate">{{ user.email }}</p>
                                    <p class="mt-0.5 text-xs text-slate-400">
                                        {{ user.seksi?.nama_seksi || 'Tanpa seksi' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2" @click.stop>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-200">
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="user.is_active ? 'bg-emerald-500' : 'bg-slate-400'"
                                    />
                                    {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                                <UserActionButtons
                                    :user="user"
                                    :can-deactivate="canDeactivateUser(user)"
                                    @edit="openEdit"
                                    @delete-permanent="deletePermanent"
                                    @reset-password="openResetPassword"
                                />
                            </div>
                        </div>
                    </li>
                </ul>
                <div
                    v-if="!userRows.length && hasActiveFilters"
                    class="px-4 py-4"
                >
                    <button
                        type="button"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                        @click="resetFilters"
                    >
                        Reset filter
                    </button>
                </div>
            </div>

            <!-- Desktop: table (≥ lg) -->
            <div class="hidden overflow-x-auto overflow-y-clip lg:block">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-600">
                            <th class="py-2 w-10 text-center">
                                <input type="checkbox" :checked="isAllSelected"
                                    class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30"
                                    @change="onHeaderCheckboxChange" />
                            </th>
                            <th class="px-3 py-3 font-semibold text-center">Nama</th>
                            <th class="px-3 py-3 font-semibold text-center">Email</th>
                            <th class="px-3 py-3 font-semibold text-center">Role</th>
                            <th class="px-3 py-3 font-semibold text-center">Seksi</th>
                            <th class="px-3 py-3 font-semibold text-center">Status</th>
                            <th class="whitespace-nowrap px-3 py-3 font-semibold text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in userRows" :key="user.id"
                            class="border-b border-slate-100 hover:bg-slate-50 cursor-pointer"
                            :class="selectedIds.includes(user.id) ? 'bg-blue-50 hover:bg-blue-50' : ''"
                            @click="toggleRow(user.id)">
                            <td class="py-2.5 text-center" @click.stop>
                                <input type="checkbox"
                                    :checked="selectedIds.includes(user.id)"
                                    class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30"
                                    @change="(e) => onRowCheckboxChange(e, user.id)" />
                            </td>
                            <td class="px-3 py-2.5 font-medium text-center text-slate-800">{{ user.name }}</td>
                            <td class="px-3 py-2.5 text-center text-slate-600">{{ user.email }}</td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-200">
                                    {{ roleLabel(user.role) }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-center text-slate-600">{{ user.seksi?.nama_seksi || 'Tanpa seksi' }}</td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-200">
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="user.is_active ? 'bg-emerald-500' : 'bg-slate-400'"
                                    />
                                    {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-center" @click.stop>
                                <UserActionButtons
                                    :user="user"
                                    :can-deactivate="canDeactivateUser(user)"
                                    @edit="openEdit"
                                    @delete-permanent="deletePermanent"
                                    @reset-password="openResetPassword"
                                />
                            </td>
                        </tr>
                        <tr v-if="!userRows.length">
                            <td colspan="7" class="px-3 py-12 text-center">
                                <div class="mx-auto flex max-w-sm flex-col items-center gap-3">
                                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                        <AppIcon name="users" :size="24" :stroke-width="1.6" class="h-6 w-6" decorative />
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-700">{{ emptyMessage }}</p>
                                        <p class="mt-1 text-xs leading-5 text-slate-500">
                                            {{ emptyDescription }}
                                        </p>
                                    </div>
                                    <button
                                        v-if="hasActiveFilters"
                                        type="button"
                                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                        @click="resetFilters"
                                    >
                                        Reset filter
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination (shared) -->
            <div
                v-if="pagination.last_page > 1"
                class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3"
            >
                <p class="text-xs text-slate-500">
                    Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}
                    <span class="text-slate-400">· {{ pagination.total }} pengguna</span>
                </p>
                <div class="flex flex-wrap gap-1">
                    <template v-for="link in pagination.links" :key="`${link.label}-${link.url}`">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="rounded-md px-2.5 py-1 text-xs"
                            :class="link.active
                                ? 'bg-brand-blue text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            preserve-scroll
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="rounded-md px-2.5 py-1 text-xs text-slate-400"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- Edit: tanpa password (reset terpisah) -->
        <Modal :show="showEditModal" max-width="lg" @close="closeEdit">
            <div class="px-6 py-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Edit Pengguna</h2>
                        <p class="mt-0.5 text-sm text-slate-500">{{ editingUser?.email }}</p>
                    </div>
                    <button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-100" aria-label="Tutup" @click="closeEdit">
                        <AppIcon name="close" :size="20" :stroke-width="2" class="h-5 w-5" decorative />
                    </button>
                </div>

                <div class="mt-5 space-y-5">
                    <section>
                        <h3 :class="sectionTitle">Identitas</h3>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-slate-700">Nama lengkap</label>
                                <input v-model="editForm.name" type="text" :class="inputClass">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                                <input v-model="editForm.email" type="email" :class="inputClass">
                            </div>
                        </div>
                    </section>

                    <section class="border-t border-slate-100 pt-5">
                        <h3 :class="sectionTitle">Akses</h3>
                        <div class="mt-3 grid items-start gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Role</label>
                                <select v-model="editForm.role" :class="selectClass" @change="onEditRoleChange">
                                    <option value="staf">Staf</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">
                                    Seksi
                                    <span v-if="editForm.role === 'staf'" class="text-rose-600">*</span>
                                </label>
                                <select
                                    v-model="editForm.seksi_id"
                                    :class="selectClass"
                                    :required="editForm.role === 'staf'"
                                >
                                    <option v-if="editForm.role === 'admin'" value="">Tanpa seksi</option>
                                    <option v-if="editForm.role === 'staf'" disabled value="">Pilih seksi</option>
                                    <option v-for="seksi in props.seksiList" :key="seksi.id" :value="seksi.id">{{ seksi.nama_seksi }}</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                                <label class="inline-flex min-h-[42px] items-center gap-2.5 text-sm text-slate-700">
                                    <input
                                        v-model="editForm.is_active"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30"
                                        :disabled="editingUser && !canDeactivateUser(editingUser) && editForm.is_active"
                                    >
                                    Akun aktif
                                </label>
                                <p
                                    v-if="editingUser && !canDeactivateUser(editingUser) && editForm.is_active"
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    Admin aktif terakhir tidak dapat dinonaktifkan di sini.
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <div v-if="Object.keys(editErrors).length" class="mt-3 text-xs text-red-600">
                    <span v-for="(message, key) in editErrors" :key="key" class="mr-3">{{ Array.isArray(message) ? message[0] : message }}</span>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-2">
                    <button
                        type="button"
                        class="text-sm font-semibold text-slate-600 hover:text-brand-blue"
                        @click="openResetFromEdit"
                    >
                        Reset password…
                    </button>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeEdit"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-brand-blue px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                            @click="submitEdit"
                        >
                            Simpan Perubahan
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Reset password terpisah -->
        <Modal :show="showResetPassword" max-width="md" @close="closeResetPassword">
            <div class="px-6 py-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Reset Password</h2>
                        <p class="mt-0.5 text-sm text-slate-500">{{ resetTarget?.name }} · {{ resetTarget?.email }}</p>
                    </div>
                    <button type="button" class="rounded-md p-1 text-slate-400 hover:bg-slate-100" aria-label="Tutup" @click="closeResetPassword">
                        <AppIcon name="close" :size="20" :stroke-width="2" class="h-5 w-5" decorative />
                    </button>
                </div>

                <div class="mt-5 space-y-4">
                    <div
                        v-if="resetTarget && (!resetTarget.is_active || pageRecovery)"
                        class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800"
                        role="alert"
                    >
                        Akun terindikasi dibobol — wajib isi kata sandi baru sebelum staf
                        dapat login kembali.
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Password baru</label>
                        <div class="relative">
                            <input
                                v-model="resetForm.password"
                                :type="showPasswordValue ? 'text' : 'password'"
                                autocomplete="new-password"
                                :class="`${inputClass} pr-11`"
                            >
                            <button
                                type="button"
                                :class="eyeBtnClass"
                                :title="showPasswordValue ? 'Sembunyikan password' : 'Tampilkan password'"
                                :aria-label="showPasswordValue ? 'Sembunyikan password' : 'Tampilkan password'"
                                @click="showPasswordValue = !showPasswordValue"
                            >
                                <AppIcon v-if="!showPasswordValue" name="eye" :size="20" :stroke-width="1.8" class="h-5 w-5" decorative />
                                <AppIcon v-else name="eye-off" :size="20" :stroke-width="1.8" class="h-5 w-5" decorative />
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Minimal 8 karakter.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Konfirmasi password</label>
                        <div class="relative">
                            <input
                                v-model="resetForm.password_confirmation"
                                :type="showPasswordConfirm ? 'text' : 'password'"
                                autocomplete="new-password"
                                :class="`${inputClass} pr-11`"
                            >
                            <button
                                type="button"
                                :class="eyeBtnClass"
                                :title="showPasswordConfirm ? 'Sembunyikan password' : 'Tampilkan password'"
                                :aria-label="showPasswordConfirm ? 'Sembunyikan password' : 'Tampilkan password'"
                                @click="showPasswordConfirm = !showPasswordConfirm"
                            >
                                <AppIcon v-if="!showPasswordConfirm" name="eye" :size="20" :stroke-width="1.8" class="h-5 w-5" decorative />
                                <AppIcon v-else name="eye-off" :size="20" :stroke-width="1.8" class="h-5 w-5" decorative />
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="Object.keys(resetErrors).length" class="mt-3 text-xs text-red-600">
                    <span v-for="(message, key) in resetErrors" :key="key" class="mr-3">{{ Array.isArray(message) ? message[0] : message }}</span>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeResetPassword"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-brand-blue px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                        @click="submitResetPassword"
                    >
                        Simpan Password
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Delete permanent user confirmation -->
        <ConfirmModal
            :show="showDeleteModal"
            title="Hapus Pengguna"
            :message="`Hapus permanen pengguna ${deleteTarget?.name ?? ''}? Data akan dihapus sampai ke database dan tidak dapat dikembalikan.`"
            confirm-label="Hapus Permanen"
            @close="showDeleteModal = false"
            @confirm="confirmDeleteUser"
        />

        <!-- Bulk Delete Confirmation Modal -->
        <Modal :show="showBulkDeleteModal" max-width="md" @close="showBulkDeleteModal = false">
            <div class="px-6 py-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Hapus Pengguna</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Hapus permanen
                            <span class="font-semibold">{{ selectedIds.length }}</span>
                            pengguna yang dipilih? Tindakan ini tidak dapat dibatalkan.
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
