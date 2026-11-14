<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    href: {
        type: String,
        default: null,
    },
    useHistory: {
        type: Boolean,
        default: false,
    },
});

const handleClick = () => {
    if (props.useHistory) {
        window.history.back();
    }
};
</script>

<template>
    <button
        v-if="useHistory"
        type="button"
        @click="handleClick"
        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm transition duration-200 hover:bg-slate-50 hover:text-brand-blue focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue/40"
    >
        <AppIcon name="arrow-left" :size="16" :stroke-width="2" class="h-4 w-4 shrink-0" decorative />
        <slot>Kembali</slot>
    </button>
    <Link
        v-else
        :href="href"
        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm transition duration-200 hover:bg-slate-50 hover:text-brand-blue focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue/40"
    >
        <AppIcon name="arrow-left" :size="16" :stroke-width="2" class="h-4 w-4 shrink-0" decorative />
        <slot>Kembali</slot>
    </Link>
</template>
