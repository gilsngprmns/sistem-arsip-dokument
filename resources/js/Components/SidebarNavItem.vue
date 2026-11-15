<script>
export default { inheritAttrs: false };
</script>

<script setup>
// SidebarNavItem — item navigasi sidebar seragam.
// Satu bentuk untuk semua item menu (Link Inertia, sub-item, atau <button>
// pemicu submenu) di Sidebar: latar, teks, ikon, hover, focus, dan
// micro-interaction klik identik di mode lebar dan rail (collapsed).
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    // 'link' = Inertia Link navigasi (default); 'button' = pemicu lokal.
    mode: {
        type: String,
        default: 'link',
    },
    href: {
        type: String,
        default: undefined,
    },
    // Ikon dari registry AppIcon (kunci kebab).
    icon: {
        type: String,
        required: true,
    },
    // Label yang tampil di mode lebar.
    label: {
        type: String,
        required: true,
    },
    // rail = mode terlipat (ikon saja); desktop rail selalu pakai tooltip.
    rail: {
        type: Boolean,
        default: false,
    },
    active: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    // Angka hitung opsional di ujung item ("Semua Dokumen").
    count: {
        type: [Number, String],
        default: null,
    },
    // <button mode="button">: chevron accordion di ujung kanan.
    // 'down' = arah turun; rotasi 180° saat `chevronOpen`.
    chevron: {
        type: String,
        default: '',
    },
    chevronOpen: {
        type: Boolean,
        default: false,
    },
    // Atribut khusus Link Inertia (method, as, preserveScroll).
    linkAs: {
        type: [String, Boolean],
        default: undefined,
    },
    linkMethod: {
        type: String,
        default: undefined,
    },
    preserveScroll: {
        type: Boolean,
        default: undefined,
    },
    'aria-expanded': {
        type: [Boolean, String],
        default: undefined,
    },
    'aria-controls': {
        type: String,
        default: undefined,
    },
});

const emit = defineEmits(['click', 'navigate', 'toggle']);

// Tooltip: hanya instans desktop rail (rail=true); mobile drawer selalu
// rail=false sehingga tooltip tak pernah ter-mount di sana.
const tooltip = ref({ show: false, text: '', top: 0, left: 0 });

const showTooltip = (event) => {
    if (!props.rail) return;
    const r = event.currentTarget.getBoundingClientRect();
    tooltip.value = {
        show: true,
        text: props.label,
        left: Math.max(8, Math.min(r.right + 10, window.innerWidth - 228)),
        top: Math.max(8, Math.min(r.top + r.height / 2 - 12, window.innerHeight - 44)),
    };
};

const hideTooltip = () => {
    tooltip.value.show = false;
};

const onPointerEnter = (e) => showTooltip(e);
const onPointerMove = (e) => showTooltip(e);
const onPointerLeave = () => hideTooltip();
const onFocus = (e) => showTooltip(e);
const onBlur = () => hideTooltip();

onBeforeUnmount(() => hideTooltip());
watch(() => props.rail, hideTooltip);

// Kelas seragam untuk Link & button: dasar + state (active/disabled/hover)
// + geometri (lebar vs rail) + micro-interaction.
const baseClasses = computed(
    () =>
        'flex items-center rounded-lg py-2 text-sm transition-all duration-200 ease-out ' +
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400/50 active:scale-[0.98]',
);

const stateClasses = computed(() => {
    if (props.disabled) {
        return 'cursor-not-allowed opacity-40';
    }
    if (props.active) {
        return 'bg-white/10 font-medium text-white';
    }
    return 'text-slate-300 hover:bg-white/5 hover:text-white';
});

const geometryClasses = computed(() =>
    props.rail ? 'justify-center px-2' : 'gap-2.5 px-3',
);

const iconColorClass = computed(() =>
    props.active && !props.disabled ? 'text-emerald-400' : 'text-slate-400',
);

const handleClick = (event) => {
    if (props.disabled) return;
    if (props.mode === 'button') {
        emit('toggle', event);
    } else {
        emit('navigate');
    }
};

const ariaLabel = computed(
    () => (props.rail ? props.label : undefined),
);
</script>

<template>
    <Link
        v-if="mode === 'link'"
        :href="href"
        :as="linkAs"
        :method="linkMethod"
        :preserve-scroll="preserveScroll"
        :tabindex="disabled ? -1 : undefined"
        :aria-disabled="disabled ? true : undefined"
        :aria-label="ariaLabel"
        :class="[
            baseClasses,
            stateClasses,
            geometryClasses,
            rail ? 'min-w-0 flex-1' : '',
        ]"
        @click.stop="handleClick"
        @mouseenter="onPointerEnter"
        @mousemove="onPointerMove"
        @mouseleave="onPointerLeave"
        @focus="onFocus"
        @blur="onBlur"
    >
        <AppIcon
            :name="icon"
            :size="20"
            :stroke-width="1.8"
            :class="['h-5 w-5 shrink-0 transition-colors duration-200', iconColorClass]"
            decorative
        />
        <span v-if="!rail" class="min-w-0 flex-1 truncate text-left">{{ label }}</span>
        <span
            v-if="!rail && count !== null && count !== ''"
            class="shrink-0 text-[11px] text-slate-400"
        >
            {{ count }}
        </span>
    </Link>
    <button
        v-else
        type="button"
        :class="[
            baseClasses,
            stateClasses,
            geometryClasses,
            'min-w-0 flex-1',
        ]"
        :disabled="disabled"
        :aria-label="ariaLabel"
        :aria-expanded="ariaExpanded"
        :aria-controls="ariaControls"
        @click="handleClick"
        @mouseenter="onPointerEnter"
        @mousemove="onPointerMove"
        @mouseleave="onPointerLeave"
        @focus="onFocus"
        @blur="onBlur"
    >
        <AppIcon
            :name="icon"
            :size="20"
            :stroke-width="1.8"
            :class="['h-5 w-5 shrink-0 transition-colors duration-200', iconColorClass]"
            decorative
        />
        <span
            v-if="!rail && chevron"
            class="min-w-0 flex-1 truncate text-left"
        >{{ label }}</span>
        <span v-if="!rail && count !== null && count !== ''" class="shrink-0 text-[11px] text-slate-400">
            {{ count }}
        </span>
        <AppIcon
            v-if="!rail && chevron"
            :name="chevron === 'down' ? 'chevron-down' : 'chevron-right'"
            :size="16"
            :stroke-width="1.8"
            :class="[
                'h-4 w-4 shrink-0 transition-transform duration-200 ease-in-out',
                chevronOpen ? 'rotate-180' : '',
            ]"
            decorative
        />
    </button>

    <!-- Tooltip rail: teleport keluar dari overflow sidebar; posisi fixed
         dihitung dari bounding rect item agar rapi di semua breakpoint. -->
    <Teleport v-if="rail" to="body">
        <div
            v-if="tooltip.show"
            class="pointer-events-none fixed z-[60] w-max max-w-[220px] break-words rounded-lg bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-white shadow-lg ring-1 ring-white/10"
            :style="{ top: tooltip.top + 'px', left: tooltip.left + 'px' }"
        >{{ tooltip.text }}</div>
    </Teleport>
</template>
