<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    canDeactivate: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['edit', 'delete-permanent', 'reset-password']);

const page = usePage();
const isSelf = computed(() => Number(page.props.auth?.user?.id) === Number(props.user.id));

const showOverflow = computed(() => true);

const iconBtn =
    'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-blue/25';
</script>

<template>
    <div class="inline-flex flex-nowrap items-center gap-1">
        <button
            type="button"
            :class="iconBtn"
            title="Edit"
            aria-label="Edit pengguna"
            @click="emit('edit', user)"
        >
            <AppIcon name="edit" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
        </button>

        <Dropdown v-if="showOverflow" align="right" width="48" auto-flip>
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
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                        role="menuitem"
                        @click="emit('reset-password', user)"
                    >
                        <AppIcon name="key" :size="16" :stroke-width="1.8" class="h-4 w-4 text-slate-500" decorative />
                        Reset Password
                    </button>
                    <button
                        v-if="!isSelf && canDeactivate"
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-rose-600 transition hover:bg-rose-50"
                        role="menuitem"
                        @click="emit('delete-permanent', user)"
                    >
                        <AppIcon name="trash" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
                        Hapus Permanen
                    </button>
                    <p
                        v-else-if="!isSelf && !canDeactivate"
                        class="px-3 py-2 text-xs text-slate-400"
                    >
                        Admin aktif terakhir — tidak bisa dihapus
                    </p>
                </div>
            </template>
        </Dropdown>
    </div>
</template>
