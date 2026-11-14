<script setup>
import Dropdown from '@/Components/Dropdown.vue';

const props = defineProps({
    arsip: {
        type: Object,
        required: true,
    },
    /** Sembunyikan Buka (mis. di modal detail yang sudah terbuka) */
    showView: {
        type: Boolean,
        default: true,
    },
    /** Sembunyikan Label di menu (mis. sudah ada tombol Cetak Stiker di luar) */
    showLabelInMenu: {
        type: Boolean,
        default: true,
    },
    /** Sembunyikan dropdown "Tindakan Lain" (mis. di modal detail yang sudah punya tombol khusus) */
    showDropdown: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['edit', 'view', 'label', 'pinjam', 'kembalikan', 'settersedia', 'tandahilang', 'delete']);

const iconBtn =
    'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-blue/25';

const menuItem =
    'flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50';
</script>

<template>
    <div class="inline-flex flex-nowrap items-center gap-1">
        <button
            v-if="showView"
            type="button"
            :class="iconBtn"
            title="Lihat Detail"
            aria-label="Lihat detail dokumen"
            @click="emit('view', arsip)"
        >
            <AppIcon name="eye" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
        </button>

        <button
            type="button"
            :class="iconBtn"
            title="Edit Data"
            aria-label="Edit data arsip"
            @click="emit('edit', arsip)"
        >
            <AppIcon name="edit" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
        </button>

        <button
            v-if="arsip.status_arsip === 'tersedia'"
            type="button"
            :class="iconBtn"
            title="Pinjamkan"
            aria-label="Pinjamkan dokumen"
            @click="emit('pinjam', arsip)"
        >
            <!-- Ikon: dokumen dengan panah keluar (pinjam keluar) -->
            <AppIcon name="file-down" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
        </button>
        <button
            v-if="arsip.status_arsip === 'dipinjam'"
            type="button"
            :class="iconBtn"
            title="Kembalikan"
            aria-label="Kembalikan dokumen"
            @click="emit('kembalikan', arsip)"
        >
            <AppIcon name="kembalikan" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
        </button>

        <button
            v-if="arsip.status_arsip === 'hilang'"
            type="button"
            :class="iconBtn"
            title="Tandai Tersedia"
            aria-label="Tandai dokumen tersedia kembali"
            @click="emit('settersedia', arsip)"
        >
            <!-- Ikon: centang dalam lingkaran (kembali tersedia) -->
            <AppIcon name="tersedia" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
        </button>

        <button
            v-if="arsip.status_arsip !== 'hilang'"
            type="button"
            :class="iconBtn"
            title="Tandai Hilang"
            aria-label="Tandai dokumen sebagai hilang"
            @click="emit('tandahilang', arsip)"
        >
            <!-- Ikon: dokumen dengan tanda hilang (garis coret diagonal) -->
            <AppIcon name="hilang" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
        </button>

        <Dropdown v-if="showDropdown" align="right" width="48" auto-flip>
            <template #trigger>
                <button
                    type="button"
                    :class="iconBtn"
                    title="Tindakan Lain"
                    aria-label="Menu tindakan lain"
                    aria-haspopup="menu"
                >
                    <AppIcon name="more" :size="16" class="h-4 w-4" decorative />
                </button>
            </template>
            <template #content>
                <div class="py-1" role="menu">
                    <button
                        v-if="showLabelInMenu"
                        type="button"
                        :class="menuItem"
                        role="menuitem"
                        @click="emit('label', arsip)"
                    >
                        <AppIcon name="grid" :size="16" :stroke-width="1.8" class="h-4 w-4 text-slate-500" decorative />
                        Cetak Label
                    </button>
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-rose-600 transition hover:bg-rose-50"
                        role="menuitem"
                        @click="emit('delete', arsip)"
                    >
                        <AppIcon name="trash" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
                        Hapus
                    </button>
                </div>
            </template>
        </Dropdown>
    </div>
</template>
