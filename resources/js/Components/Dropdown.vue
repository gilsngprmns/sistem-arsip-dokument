<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    width: {
        type: String,
        default: '48',
    },
    contentClasses: {
        type: String,
        default: 'py-1 bg-white',
    },
    autoFlip: {
        type: Boolean,
        default: false,
    },
});

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));

const widthClass = computed(() => {
    return {
        48: 'w-48',
    }[props.width.toString()];
});

const alignmentClasses = computed(() => {
    const edge =
        props.align === 'left'
            ? 'start-0'
            : props.align === 'right'
                ? 'end-0'
                : '';
    if (dropUp.value) {
        const origin =
            props.align === 'left'
                ? 'ltr:origin-bottom-left rtl:origin-bottom-right'
                : props.align === 'right'
                    ? 'ltr:origin-bottom-right rtl:origin-bottom-left'
                    : 'origin-bottom';
        return `${edge} ${origin}`.trim();
    }
    if (props.align === 'left') {
        return 'ltr:origin-top-left rtl:origin-top-right start-0';
    } else if (props.align === 'right') {
        return 'ltr:origin-top-right rtl:origin-top-left end-0';
    } else {
        return 'origin-top';
    }
});

const open = ref(false);
const dropUp = ref(false);
const triggerRef = ref(null);

// Perkiraan tinggi menu (2-3 item + padding + gap) untuk cek muat tidak di viewport
const EST_MENU_HEIGHT = 200;

const measure = () => {
    if (!props.autoFlip || !triggerRef.value) return;
    const rect = triggerRef.value.getBoundingClientRect();
    dropUp.value = rect.bottom + EST_MENU_HEIGHT > window.innerHeight;
};

const toggle = () => {
    measure();
    open.value = !open.value;
};
</script>

<template>
    <div class="relative">
        <div ref="triggerRef" @click="toggle">
            <slot name="trigger" />
        </div>

        <!-- Full Screen Dropdown Overlay -->
        <div
            v-show="open"
            class="fixed inset-0 z-40"
            @click="toggle"
        ></div>

        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-show="open"
                class="absolute z-50 rounded-md shadow-lg"
                :class="[dropUp ? 'bottom-full mb-2' : 'mt-2', widthClass, alignmentClasses]"
                style="display: none"
                @click="open = false"
            >
                <div
                    class="rounded-md ring-1 ring-black ring-opacity-5"
                    :class="contentClasses"
                >
                    <slot name="content" />
                </div>
            </div>
        </Transition>
    </div>
</template>
