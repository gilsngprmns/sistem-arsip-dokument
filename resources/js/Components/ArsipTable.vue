<script setup>
import ActionButtons from '@/Components/ActionButtons.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    pagination: {
        type: Object,
        default: null,
    },
    selectedIds: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits([
    'edit', 'view', 'label', 'pinjam', 'kembalikan',
    'settersedia', 'tandahilang', 'delete',
    'toggle-row', 'select-all',
]);

const isAllSelected = computed(() => {
    if (!props.rows.length) return false;
    return props.rows.every((row) => props.selectedIds.includes(String(row.id)));
});

const isIndeterminate = computed(() => {
    if (!props.rows.length) return false;
    const count = props.rows.filter((row) => props.selectedIds.includes(String(row.id))).length;
    return count > 0 && count < props.rows.length;
});

const toggleAll = () => {
    emit('select-all', isAllSelected.value ? [] : props.rows.map((row) => String(row.id)));
};

const toggleRow = (id) => {
    emit('toggle-row', id);
};

const totalLabel = computed(() => {
    if (!props.pagination) {
        return `${props.rows.length} dokumen`;
    }

    return `Ditemukan ${props.pagination.total} dokumen`;
});

const seksiBadge = (nama) => {
    const lower = (nama || '').toLowerCase();
    const slate = 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-200';

    if (lower.includes('pemerintahan')) {
        return { code: 'PEM', class: slate };
    }
    if (lower.includes('ekonomi')) {
        return { code: 'EKB', class: slate };
    }
    if (lower.includes('kesejahteraan')) {
        return { code: 'KSR', class: slate };
    }

    return { code: 'SEK', class: slate };
};

const statusBadge = (status) => {
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

    return map[status] || map.tersedia;
};

const formatDate = (value) => {
    if (!value) return '—';
    return String(value).substring(0, 10);
};

const pihak = (row) => {
    if (row.jenis_surat === 'masuk') {
        return row.asal_instansi || '—';
    }

    return row.tujuan_instansi || '—';
};

// Tahap 5: file terenkripsi → endpoint terkontrol; publik → /storage/
const fileUrl = (row) => {
    if (!row.file_path) return null;
    return row.is_encrypted
        ? route('arsip.file', row.id)
        : `/storage/${row.file_path}`;
};
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 text-sm text-slate-500 sm:px-5">
            <span class="font-medium text-slate-700">{{ totalLabel }}</span>
            <span class="hidden text-xs sm:inline">Pintasan: Tekan ESC untuk menutup jendela apa pun</span>
        </div>

        <!-- Mobile / tablet: card list (< lg) -->
        <div class="lg:hidden">
            <EmptyState
                v-if="!rows.length"
                title="Belum ada dokumen di daftar ini"
                description="Coba ubah filter pencarian, atau tambahkan arsip baru jika Anda memiliki wewenang."
            />
            <ul v-else class="divide-y divide-slate-100">
                <li
                    v-for="row in rows"
                    :key="`card-${row.id}`"
                    class="px-4 py-4"
                >
                    <div class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        :value="String(row.id)"
                        :checked="selectedIds.includes(String(row.id))"
                        class="mt-2 h-4 w-4 shrink-0 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30 cursor-pointer"
                        @change="toggleRow(String(row.id))"
                    />
                    <button
                        type="button"
                        class="min-w-0 flex-1 text-left"
                        @click="emit('view', row)"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-brand-blue">{{ row.kode_arsip }}</span>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset"
                                :class="statusBadge(row.status_arsip).class"
                            >
                                <span class="h-2 w-2 rounded-full" :class="statusBadge(row.status_arsip).dot" />
                                {{ statusBadge(row.status_arsip).label }}
                            </span>
                        </div>
                        <div class="mt-1 text-sm font-semibold text-slate-800">{{ row.nomor_surat }}</div>
                        <p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ row.perihal }}</p>
                    </button>
                    </div>

                    <div class="mt-2 ml-7 flex flex-wrap items-center gap-2 text-xs text-slate-500 sm:ml-7">
                        <span
                            class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-bold"
                            :class="seksiBadge(row.seksi?.nama_seksi).class"
                        >
                            {{ seksiBadge(row.seksi?.nama_seksi).code }}
                        </span>
                        <span class="capitalize">{{ row.jenis_surat }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ formatDate(row.tanggal_surat) }}</span>
                        <span aria-hidden="true">·</span>
                        <span class="min-w-0 truncate">{{ pihak(row) }}</span>
                    </div>

                    <div class="mt-1.5 ml-7">
                        <a
                            v-if="row.file_path"
                            :href="fileUrl(row)"
                            target="_blank"
                            class="text-xs font-semibold text-brand-blue hover:underline"
                        >
                            {{ row.is_encrypted ? 'Unduh PDF' : 'Lihat Lampiran' }}
                        </a>
                        <span v-else class="text-xs text-slate-400">Tidak ada lampiran</span>
                    </div>

                    <div class="mt-3 ml-7 border-t border-slate-100 pt-3">
                        <ActionButtons
                            :arsip="row"
                            :show-view="false"
                            @edit="emit('edit', $event)"
                            @label="emit('label', $event)"
                            @pinjam="emit('pinjam', $event)"
                            @kembalikan="emit('kembalikan', $event)"
                            @settersedia="emit('settersedia', $event)"
                            @tandahilang="emit('tandahilang', $event)"
                            @delete="emit('delete', $event)"
                        />
                    </div>
                </li>
            </ul>
        </div>

        <!-- Desktop: table (≥ lg) -->
        <div class="hidden overflow-x-auto w-full lg:block">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-10 px-4 py-3">
                            <input
                                type="checkbox"
                                :checked="isAllSelected"
                                :indeterminate="isIndeterminate"
                                class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30 cursor-pointer"
                                @change="toggleAll"
                            />
                        </th>
                        <th class="w-[140px] px-4 py-3 font-semibold">Kode Arsip</th>
                        <th class="flex-1 px-4 py-3 font-semibold">Informasi Surat</th>
                        <th class="w-[80px] px-4 py-3 font-semibold">Seksi</th>
                        <th class="w-[70px] px-4 py-3 font-semibold">Jenis</th>
                        <th class="w-[100px] px-4 py-3 font-semibold">File Digital</th>
                        <th class="w-[100px] px-4 py-3 font-semibold">Status</th>
                        <th class="w-[190px] whitespace-nowrap px-4 py-3 font-semibold">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.id"
                        class="border-t border-slate-100 even:bg-slate-50/60"
                    >
                        <td class="w-10 px-4 py-3 align-top">
                            <input
                                type="checkbox"
                                :value="String(row.id)"
                                :checked="selectedIds.includes(String(row.id))"
                                class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue/30 cursor-pointer"
                                @change="toggleRow(String(row.id))"
                            />
                        </td>
                        <td class="w-[140px] px-4 py-3 align-top">
                            <button
                                type="button"
                                class="block break-words font-semibold text-brand-blue hover:underline"
                                @click="emit('view', row)"
                            >
                                {{ row.kode_arsip }}
                            </button>
                        </td>
                        <td class="flex-1 px-4 py-3 align-top">
                            <div class="break-words font-semibold text-slate-800">{{ row.nomor_surat }}</div>
                            <div class="mt-0.5 text-xs text-slate-500">
                                {{ formatDate(row.tanggal_surat) }} • <span class="truncate">{{ pihak(row) }}</span>
                            </div>
                            <div class="mt-1 min-w-0 truncate text-xs text-slate-600" :title="row.perihal">{{ row.perihal }}</div>
                        </td>
                        <td class="w-[80px] px-4 py-3 align-top">
                            <span
                                class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-bold"
                                :class="seksiBadge(row.seksi?.nama_seksi).class"
                            >
                                {{ seksiBadge(row.seksi?.nama_seksi).code }}
                            </span>
                            <div class="mt-1 truncate text-xs text-slate-600" :title="row.seksi?.nama_seksi || ''">
                                {{ row.seksi?.nama_seksi || '—' }}
                            </div>
                        </td>
                        <td class="w-[70px] px-4 py-3 align-top capitalize text-slate-700">
                            {{ row.jenis_surat }}
                        </td>
                        <td class="w-[100px] px-4 py-3 align-top">
                            <a
                                v-if="row.file_path"
                                :href="fileUrl(row)"
                                target="_blank"
                                class="text-xs font-semibold text-brand-blue hover:underline"
                            >
                                {{ row.is_encrypted ? 'Unduh PDF' : 'Lihat Lampiran' }}
                            </a>
                            <span v-else class="text-xs text-slate-400">Tidak ada</span>
                        </td>
                        <td class="w-[100px] px-4 py-3 align-top">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset"
                                :class="statusBadge(row.status_arsip).class"
                            >
                                <span class="h-2 w-2 rounded-full" :class="statusBadge(row.status_arsip).dot" />
                                {{ statusBadge(row.status_arsip).label }}
                            </span>
                        </td>
                        <td class="w-[190px] whitespace-nowrap px-4 py-3 align-top">
                            <ActionButtons
                                :arsip="row"
                                :show-view="false"
                                @edit="emit('edit', $event)"
                                @label="emit('label', $event)"
                                @pinjam="emit('pinjam', $event)"
                                @kembalikan="emit('kembalikan', $event)"
                                @settersedia="emit('settersedia', $event)"
                                @tandahilang="emit('tandahilang', $event)"
                                @delete="emit('delete', $event)"
                            />
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="8">
                            <EmptyState
                                title="Belum ada dokumen di daftar ini"
                                description="Coba ubah filter pencarian, atau tambahkan arsip baru jika Anda memiliki wewenang."
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="pagination && pagination.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 sm:px-5"
        >
            <p class="text-xs text-slate-500">
                Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}
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
</template>
