<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const model = defineModel({
    type: [String, Number, Boolean, null],
    default: '',
});

const props = defineProps({
    options: {
        type: Array,
        required: true,
    },
    placeholder: {
        type: String,
        default: 'Pilih opsi',
    },
    label: {
        type: String,
        default: '',
    },
    id: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md'].includes(value),
    },
    align: {
        type: String,
        default: 'left',
        validator: (value) => ['left', 'right'].includes(value),
    },
    onChange: {
        type: Function,
        default: null,
    },
    autoFlip: {
        type: Boolean,
        default: false,
    },
});

const open = ref(false);
const root = ref(null);
const trigger = ref(null);
const panel = ref(null);

const isSm = computed(() => props.size === 'sm');
const dropUp = ref(false);

const EST_PANEL_HEIGHT = 260;

const measure = () => {
    if (!props.autoFlip || !trigger.value) return;
    const rect = trigger.value.getBoundingClientRect();
    dropUp.value = rect.bottom + EST_PANEL_HEIGHT > window.innerHeight;
};

const normalize = (option) => {
    if (option && typeof option === 'object' && 'value' in option) {
        return {
            value: option.value,
            label: option.label ?? option.value,
            disabled: Boolean(option.disabled),
        };
    }
    return {
        value: option,
        label: String(option),
        disabled: false,
    };
};

const normalized = computed(() => props.options.map(normalize));

const selected = computed(() =>
    normalized.value.find((option) => option.value === model.value) ?? null,
);

const triggerText = computed(() =>
    selected.value ? selected.value.label : props.placeholder,
);

const showRequiredAlert = computed(
    () =>
        props.required &&
        (model.value === '' || model.value === null || model.value === undefined),
);

const triggerClasses = computed(() =>
    [
        'flex items-center justify-between gap-2 rounded-lg border border-slate-300 px-3 text-sm shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue/30',
        isSm.value ? 'h-10' : 'h-11',
        props.disabled
            ? 'cursor-not-allowed bg-slate-50 text-slate-400'
            : 'cursor-pointer bg-white text-slate-700 hover:bg-slate-50',
        showRequiredAlert.value ? 'border-red-400' : '',
    ].join(' '),
);

const selectOption = (option) => {
    if (option.disabled) return;
    model.value = option.value;
    open.value = false;
    trigger.value?.focus();
    props.onChange?.(option.value);
};

const onTriggerKeydown = (event) => {
    if (event.key === 'Escape') {
        open.value = false;
        return;
    }
    if (open.value) return;
    if (['ArrowDown', 'Enter', ' '].includes(event.key)) {
        event.preventDefault();
        measure();
        open.value = true;
        nextTick(() => {
            const active =
                panel.value?.querySelector('[aria-selected="true"]') ??
                panel.value?.firstElementChild;
            active?.focus();
        });
    }
};

const onOptionKeydown = (event, option, options) => {
    if (event.key === 'Escape') {
        event.stopPropagation();
        open.value = false;
        trigger.value?.focus();
        return;
    }
    if (event.key === 'Tab') {
        open.value = false;
        return;
    }
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        selectOption(option);
        return;
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const enabled = options.filter((item) => !item.disabled);
        const index = enabled.indexOf(option);
        const next =
            event.key === 'ArrowDown'
                ? enabled[(index + 1) % Math.max(enabled.length, 1)]
                : enabled[(index - 1 + enabled.length) % Math.max(enabled.length, 1)];
        next?.focus();
    }
};

const onOutside = (event) => {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
};

const openWatcher = (value) => {
    if (value) document.addEventListener('mousedown', onOutside);
    else document.removeEventListener('mousedown', onOutside);
};

watch(open, openWatcher, { immediate: true });
onBeforeUnmount(() => document.removeEventListener('mousedown', onOutside));
</script>

<template>
    <div ref="root" class="relative">
        <button
            ref="trigger"
            type="button"
            :id="id"
            class="block w-full"
            :class="triggerClasses"
            :disabled="disabled"
            aria-haspopup="listbox"
            :aria-expanded="open"
            :aria-label="label || undefined"
            @click="measure(); open = !open"
            @keydown="onTriggerKeydown"
        >
            <span class="truncate" :class="selected ? 'text-slate-800' : 'text-slate-400'">
                {{ triggerText }}
            </span>
            <AppIcon
                name="chevron-down"
                :size="isSm ? 16 : 18"
                class="shrink-0 text-slate-400 transition-transform duration-200"
                :class="{ 'rotate-180': open }"
                decorative
            />
        </button>

        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                ref="panel"
                role="listbox"
                :aria-label="label || 'Pilihan opsi'"
                class="absolute z-50 max-h-60 w-full min-w-[11rem] overflow-y-auto rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg"
                :class="[
                    align === 'right' ? 'end-0' : 'start-0',
                    dropUp ? 'bottom-full mb-1.5' : 'top-full mt-1.5',
                ]"
            >
                <button
                    v-for="(option, index) in normalized"
                    :key="`${option.value}-${index}`"
                    type="button"
                    role="option"
                    :tabindex="option.disabled ? -1 : 0"
                    :aria-selected="option.value === model"
                    :disabled="option.disabled"
                    :aria-label="label || undefined"
                    class="flex w-full items-center justify-between gap-2 px-3.5 py-2 text-left text-sm transition-colors focus:outline-none focus-visible:bg-slate-50"
                    :class="[
                        option.value === model
                            ? 'bg-brand-blue/10 font-medium text-brand-blue'
                            : 'text-slate-700 hover:bg-slate-50',
                        option.disabled ? 'cursor-not-allowed text-slate-300' : '',
                    ]"
                    @click="selectOption(option)"
                    @keydown="onOptionKeydown($event, option, normalized)"
                >
                    <span class="truncate">{{ option.label }}</span>
                    <AppIcon
                        v-if="option.value === model"
                        name="check"
                        :size="16"
                        class="text-brand-blue"
                        decorative
                    />
                </button>
            </div>
        </Transition>

        <!-- Highlight merah field wajib yang masih kosong; penghalang utama tetap di validasi server. -->
        <p v-if="showRequiredAlert && !disabled" class="mt-1 text-xs text-red-500">
            {{ label ? `${label} wajib dipilih.` : 'Pilihan ini wajib diisi.' }}
        </p>
    </div>
</template>
