<script setup>
import { institution } from '@/config/institution';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, onUnmounted, ref, watch } from 'vue';
import ImageLightbox from '@/Components/ImageLightbox.vue';

const props = defineProps({
    activeSeksiId: {
        type: [Number, String, null],
        default: null,
    },
    collapsed: {
        type: Boolean,
        default: false,
    },
    showClose: {
        type: Boolean,
        default: false,
    },
    collapsible: {
        type: Boolean,
        default: false,
    },
    sidebarId: {
        type: String,
        default: undefined,
    },
});

const emit = defineEmits(['close', 'navigate', 'toggle-collapse']);

const setExpanded = ref(false);
const publikExpanded = ref(false);

watch(
    () => props.activeSeksiId,
    (id) => {
        if (id !== null && id !== undefined && id !== '') {
            setExpanded.value = true;
        }
    },
    { immediate: true },
);

const page = usePage();

const seksiList = computed(() => page.props.seksiList ?? []);
const seksiCounts = computed(() => page.props.seksiCounts ?? { all: 0, by_seksi: {} });
const userName = computed(() => page.props.auth?.user?.name ?? 'Staf Pengarsipan');
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

// Seksi yang dikelola user (staff). Admin = null (tidak dibatasi).
const userSeksiId = computed(() => {
    const u = page.props.auth?.user;
    return u?.role !== 'admin' ? Number(u.seksi_id) : null;
});

const isSeksiDisabled = (id) => userSeksiId.value !== null && Number(id) !== userSeksiId.value;
// Avatar user (storage). Kosong = fallback SVG; tombol avatar hanya muncul saat ada foto.
const avatarSrc = computed(() => {
    const p = page.props.auth?.user?.avatar_path;
    return p ? `/storage/${p}` : '';
});

// Sidebar desktop TIDAK di-remount saat navigasi (AppLayout tak memberi :key),
// jadi state dropdown bertahan antar halaman. Auto-buka dropdown bila route
// saat ini adalah salah satu sub-item-nya — jadi item aktif selalu terlihat.
watch(
    () => page.url,
    () => {
        if (route().current('halaman-publik.*')) {
            publikExpanded.value = true;
        }
        if (route().current('arsip.index') && props.activeSeksiId) {
            setExpanded.value = true;
        }
    },
    { immediate: true },
);

// Transisi tinggi (height) sub-list: v-show saja terasa instan; ukur tinggi
// lalu transition agar buka/tutup halus (~300ms, sinkron dgn chevron).
const enterHooks = (el, done) => {
    el.style.height = el.scrollHeight + 'px';
    el.offsetHeight; // reflow
    el.style.height = 'auto';
    requestAnimationFrame(done);
};

const leaveHooks = (el, done) => {
    el.style.height = el.scrollHeight + 'px';
    el.offsetHeight;
    el.style.height = '0px';
    setTimeout(done, 240);
};

const seksiMeta = (nama) => {
    const lower = (nama || '').toLowerCase();

    if (lower.includes('pemerintahan')) {
        return {
            code: 'PEM',
            short: 'Pemerintahan',
            full: 'Seksi Pemerintahan',
            icon: 'building',
        };
    }
    if (lower.includes('ekonomi')) {
        return {
            code: 'EKB',
            short: 'Ekonomi',
            full: 'Seksi Ekonomi dan Pembangunan',
            icon: 'globe',
        };
    }
    if (lower.includes('kesejahteraan')) {
        return {
            code: 'KSR',
            short: 'Kesejahteraan Rakyat',
            full: 'Seksi Kesejahteraan Rakyat',
            icon: 'heart',
        };
    }

    return {
        code: 'SEK',
        short: nama,
        full: nama || 'Seksi',
        icon: 'folder',
    };
};

const isSeksiActive = (id) => String(props.activeSeksiId) === String(id);
const isAllDocumentsActive = computed(
    () => route().current('arsip.index') && !props.activeSeksiId,
);

const countForSeksi = (id) => {
    const map = seksiCounts.value.by_seksi || {};
    return map[id] ?? map[String(id)] ?? 0;
};

const onNavClick = () => {
    emit('navigate');
};

// Tooltip kustom pengganti atribut `title` native (bubble yang menabrak di rail 80px).
// Satu elemen bersama, dirender via <Teleport to="body"> + position:fixed agar lolos dari
// overflow rail aside DAN dari `transform` drawer mobile (containing block fixed).
// Hanya aktif di instans desktop (collapsed); instans mobile selalu collapsed=false.
const tip = ref({ show: false, text: '', top: 0, left: 0 });

const showTip = (event, text, always = false) => {
    if (!props.collapsed && !always) return; // expanded: label sudah terlihat
    const r = event.currentTarget.getBoundingClientRect();
    tip.value = {
        show: true,
        text,
        // anchor kanan item, clamp agar tidak meluber keluar viewport;
        // max-w tooltip ±220px + padding 8px → cadangan 228px.
        left: Math.max(8, Math.min(r.right + 10, window.innerWidth - 228)),
        top: Math.max(8, Math.min(r.top + r.height / 2 - 12, window.innerHeight - 44)),
    };
};

const hideTip = () => {
    tip.value.show = false;
};

onMounted(() => {
    // capture: menangkap scroll dari area menu di dalam sidebar (overflow-y-auto
    // ada di div konten, scroll event-nya tetap naik sampai window)
    window.addEventListener('scroll', hideTip, true);
    window.addEventListener('resize', hideTip);
});

onUnmounted(() => {
    window.removeEventListener('scroll', hideTip, true);
    window.removeEventListener('resize', hideTip);
});

watch(() => props.collapsed, hideTip); // reset saat rail berubah lebar

// Lightbox sederhana: gambar (logo/avatar) ditampilkan utuh di tengah layar.
// Render oleh <ImageLightbox> (Teleport ke body, Escape ditangani di sana);
// komponen ini hanya memegang state sumber gambar yang sedang dibuka.
const lightbox = ref({ show: false, src: '', label: '' });

const openLightbox = (src, label) => {
    if (!src) return;
    lightbox.value = { show: true, src, label };
};

const closeLightbox = () => {
    lightbox.value.show = false;
};

/* ============ Skeleton loading ============ */
const SKELETON_MS = 1000;
const isLoading = ref(true);
let skeletonTimer = null;

const armSkeleton = () => {
    if (skeletonTimer) clearTimeout(skeletonTimer);
    skeletonTimer = setTimeout(() => { isLoading.value = false; skeletonTimer = null; }, SKELETON_MS);
};

onMounted(armSkeleton);

watch(
    () => page.url,
    () => {
        if (isLoading.value) return;
        isLoading.value = true;
        armSkeleton();
    }
);

onBeforeUnmount(() => {
    if (skeletonTimer) clearTimeout(skeletonTimer);
});
</script>

<template>
    <aside
        :id="sidebarId"
        :aria-busy="isLoading"
        class="flex h-screen shrink-0 flex-col overflow-hidden bg-brand-navy text-white transition-[width] duration-300 ease-in-out"
        :class="collapsed ? 'w-[80px]' : 'w-[260px]'"
    >
        <!-- SKELETON LOADING -->
        <div
            v-if="isLoading"
            role="status"
            aria-live="polite"
            class="flex h-screen flex-col overflow-hidden bg-brand-navy text-white"
            :class="collapsed ? 'w-[80px]' : 'w-[260px]'"
        >
            <span class="sr-only">Memuat menu navigasi…</span>
            <!-- Header: logo + teks placeholder -->
            <div class="shrink-0 border-b border-white/10 py-3 motion-safe:animate-pulse" :class="collapsed ? 'px-2' : 'px-4'">
                <div class="flex" :class="collapsed ? 'flex-col items-center gap-2' : 'items-center gap-3'">
                    <div class="h-9 w-9 shrink-0 rounded-full bg-slate-500" />
                    <div v-show="!collapsed" class="min-w-0 flex-1">
                        <div class="h-4 w-32 rounded bg-slate-600" />
                        <div class="mt-1 h-3 w-40 rounded bg-slate-500" />
                    </div>
                </div>
            </div>
            <!-- Menu: grup placeholder -->
            <div class="min-h-0 flex-1 overflow-hidden py-3 motion-safe:animate-pulse" :class="collapsed ? 'px-2' : 'px-3'">
                <div v-for="i in 4" :key="'skeleton-grp-' + i" class="flex flex-col gap-1" :class="i < 4 ? 'mb-4' : ''">
                    <div class="mb-1.5 px-3">
                        <div class="h-3 w-16 rounded bg-slate-500" />
                    </div>
                    <div class="rounded-lg py-2" :class="collapsed ? 'px-2' : 'px-3'">
                        <div class="h-5 w-5 rounded bg-slate-500" :class="!collapsed && 'mr-2.5'" />
                    </div>
                    <div class="rounded-lg py-2" :class="collapsed ? 'px-2' : 'px-3'">
                        <div class="h-5 w-5 rounded bg-slate-500" :class="!collapsed && 'mr-2.5'" />
                    </div>
                </div>
            </div>
            <!-- Bottom: profil + logout -->
            <div class="shrink-0 border-t border-white/10 py-3 motion-safe:animate-pulse" :class="collapsed ? 'px-2' : 'px-3'">
                <div class="flex" :class="collapsed ? 'flex-col items-center gap-1' : 'items-center gap-2.5'">
                    <div class="h-8 w-8 shrink-0 rounded-full bg-slate-500" />
                    <div v-show="!collapsed" class="min-w-0 flex-1">
                        <div class="h-3 w-24 rounded bg-slate-500" />
                    </div>
                </div>
                <div class="mt-2 h-5 w-full rounded bg-slate-600" />
            </div>
        </div>

        <!-- KONTEN ASLI -->
        <template v-else>
        <div
            class="shrink-0 border-b border-white/10 py-3 transition-[padding] duration-300"
            :class="collapsed ? 'px-2' : 'px-4'"
        >
            <div
                class="flex"
                :class="collapsed ? 'flex-col items-center gap-2' : 'items-center gap-3'"
            >
                <button
                    type="button"
                    class="h-9 w-9 shrink-0 rounded-full bg-white p-0.5 transition-colors hover:bg-white/90 focus:outline-none focus:ring-2 focus:ring-brand-blue/40"
                    :aria-label="`Perbesar logo ${institution.name}`"
                    @click="openLightbox(institution.logoPath, institution.name)"
                >
                    <img
                        :src="institution.logoPath"
                        :alt="`Logo ${institution.name}`"
                        class="h-full w-full rounded-full object-contain"
                    />
                </button>
                <div
                    v-show="!collapsed"
                    class="min-w-0 flex-1 overflow-hidden"
                >
                    <div class="truncate text-sm font-bold tracking-tight">{{ institution.systemName }}</div>
                    <div class="truncate text-[11px] text-slate-300">{{ institution.name }}</div>
                </div>
                <button
                    v-if="showClose"
                    type="button"
                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-300 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                    aria-label="Tutup menu"
                    @click="emit('close')"
                >
                    <AppIcon name="close" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
                </button>
                <button
                    v-else-if="collapsible"
                    type="button"
                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-300 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                    :aria-label="collapsed ? 'Perluas menu' : 'Ciutkan menu'"
                    :aria-expanded="!collapsed"
                    :aria-controls="sidebarId"
                    @mouseenter="showTip($event, collapsed ? 'Perluas menu' : 'Ciutkan menu', true)"
                    @mousemove="showTip($event, collapsed ? 'Perluas menu' : 'Ciutkan menu', true)"
                    @mouseleave="hideTip()"
                    @focus="showTip($event, collapsed ? 'Perluas menu' : 'Ciutkan menu', true)"
                    @blur="hideTip()"
                    @click="emit('toggle-collapse')"
                >
                    <AppIcon
                        name="chevron-left"
                        :size="16"
                        :stroke-width="1.8"
                        :class="['h-4 w-4 transition-transform duration-300', collapsed ? 'rotate-180' : '']"
                        decorative
                    />
                </button>
            </div>
        </div>

        <!-- Konten menu: satu-satunya area yang bisa scroll. Header (atas)
             dan pin profil/Keluar (bawah) tetap mengunci di tempat. -->
        <div
            class="min-h-0 flex-1 overflow-y-auto py-3 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
            :class="collapsed ? 'px-2' : 'px-3'"
        >
            <!-- GRUP MAIN -->
            <div class="flex flex-col gap-1">
                <p
                    v-show="!collapsed"
                    class="mb-1.5 px-3 text-xs font-bold uppercase tracking-wider text-slate-400"
                >
                    Main
                </p>

                <!-- Dashboard -->
                <Link
                    :href="route('dashboard')"
                    class="flex items-center rounded-lg py-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                    :class="[
                        route().current('dashboard')
                            ? 'bg-white/10 font-medium text-white'
                            : 'text-slate-300 hover:bg-white/5 hover:text-white',
                        collapsed ? 'justify-center px-2' : 'gap-2.5 px-3',
                    ]"
                    :aria-label="collapsed ? 'Dashboard' : undefined"
                    @mouseenter="showTip($event, 'Dashboard')"
                    @mousemove="showTip($event, 'Dashboard')"
                    @mouseleave="hideTip()"
                    @focus="showTip($event, 'Dashboard')"
                    @blur="hideTip()"
                    @click="onNavClick"
                >
                    <AppIcon
                        name="dashboard"
                        :size="20"
                        :stroke-width="1.8"
                        :class="['h-5 w-5 shrink-0', route().current('dashboard') ? 'text-emerald-400' : 'text-slate-400']"
                        decorative
                    />
                    <span v-show="!collapsed" class="truncate">Dashboard</span>
                </Link>

                <!-- Semua Dokumen + chevron -->
                <div class="flex items-center gap-0.5">
                    <Link
                        :href="route('arsip.index')"
                        class="flex min-w-0 flex-1 items-center rounded-lg py-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                        :class="[
                            isAllDocumentsActive
                                ? 'bg-white/10 font-medium text-white'
                                : 'text-slate-300 hover:bg-white/5 hover:text-white',
                            collapsed ? 'justify-center px-2' : 'justify-between gap-2 px-3',
                        ]"
                        :aria-label="collapsed ? 'Semua Dokumen' : undefined"
                        @mouseenter="showTip($event, 'Semua Dokumen')"
                        @mousemove="showTip($event, 'Semua Dokumen')"
                        @mouseleave="hideTip()"
                        @focus="showTip($event, 'Semua Dokumen')"
                        @blur="hideTip()"
                        @click="onNavClick"
                    >
                        <span class="flex min-w-0 items-center" :class="collapsed ? '' : 'gap-2.5'">
                            <AppIcon
                                name="archive"
                                :size="20"
                                :stroke-width="1.8"
                                :class="['h-5 w-5 shrink-0', isAllDocumentsActive ? 'text-emerald-400' : 'text-slate-400']"
                                decorative
                            />
                            <span v-show="!collapsed" class="truncate">Semua Dokumen</span>
                        </span>
                        <span
                            v-show="!collapsed"
                            class="shrink-0 text-[11px] text-slate-400"
                        >
                            {{ seksiCounts.all }}
                        </span>
                    </Link>
                    <button
                        v-show="!collapsed"
                        type="button"
                        class="inline-flex h-8 w-7 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                        :aria-label="setExpanded ? 'Tutup daftar seksi' : 'Buka daftar seksi'"
                        :aria-expanded="setExpanded"
                        @click.stop="setExpanded = !setExpanded"
                    >
                        <AppIcon
                            name="chevron-down"
                            :size="16"
                            :stroke-width="1.8"
                            :class="['h-4 w-4 transition-transform duration-300', setExpanded ? 'rotate-180' : '']"
                            decorative
                        />
                    </button>
                </div>

                <!-- SUB-SEKSI: leading lines.
                     Berada DI DALAM ALUR (in-flow): saat terbuka, seksi
                     mendorong semua grup menu di bawahnya turun dan
                     bottom pin ikut turun; aside yang overflow-y-auto
                     mengurus gulirnya. Tidak lagi layer floating. -->
                <transition @enter="enterHooks" @leave="leaveHooks">
                <div
                    v-show="!collapsed && setExpanded"
                    class="my-1 ml-4 space-y-1 overflow-hidden border-l border-slate-600/50 py-1 pl-4 transition-[height] duration-300 ease-in-out"
                >
                    <div
                        v-for="seksi in seksiList"
                        :key="seksi.id"
                        class="flex items-center rounded-lg py-2 px-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                        :class="[
                            isSeksiActive(seksi.id) && !isSeksiDisabled(seksi.id)
                                ? 'bg-white/10 font-medium text-white'
                                : isSeksiDisabled(seksi.id)
                                    ? 'cursor-not-allowed opacity-40'
                                    : 'text-slate-300 hover:bg-white/5 hover:text-white',
                        ]"
                        :aria-disabled="isSeksiDisabled(seksi.id) ? true : undefined"
                        @mouseenter="!isSeksiDisabled(seksi.id) && showTip($event, seksiMeta(seksi.nama_seksi).full)"
                        @mousemove="!isSeksiDisabled(seksi.id) && showTip($event, seksiMeta(seksi.nama_seksi).full)"
                        @mouseleave="hideTip()"
                        @focus="!isSeksiDisabled(seksi.id) && showTip($event, seksiMeta(seksi.nama_seksi).full)"
                        @blur="hideTip()"
                    >
                        <Link
                            :href="isSeksiDisabled(seksi.id) ? undefined : route('arsip.index', { seksi_id: seksi.id })"
                            :tabindex="isSeksiDisabled(seksi.id) ? -1 : 0"
                            class="flex items-center w-full min-w-0 rounded-lg focus:outline-none"
                            @click="!isSeksiDisabled(seksi.id) && onNavClick()"
                        >
                            <AppIcon
                                :name="seksiMeta(seksi.nama_seksi).icon || 'folder'"
                                :size="20"
                                :stroke-width="1.8"
                                :class="['h-5 w-5 shrink-0', isSeksiActive(seksi.id) && !isSeksiDisabled(seksi.id) ? 'text-emerald-400' : 'text-slate-400']"
                                decorative
                            />
                            <span v-show="!collapsed" class="ml-2 min-w-0 flex-1">
                                <span class="truncate">{{ seksiMeta(seksi.nama_seksi).short }}</span>
                                <span v-if="!isSeksiDisabled(seksi.id)" class="ml-1.5 text-[11px] text-slate-400">
                                    {{ countForSeksi(seksi.id) }}
                                </span>
                            </span>
                        </Link>
                    </div>
                </div>
                </transition>
            </div>

            <!-- GRUP OPERASIONAL -->
            <div class="mt-4 flex flex-col gap-1">
                <p
                    v-show="!collapsed"
                    class="mb-1.5 px-3 text-xs font-bold uppercase tracking-wider text-slate-400"
                >
                    Operasional
                </p>
                <div v-if="collapsed" class="mx-2 my-2 border-t border-white/10" />

                <Link
                    :href="route('arsip.create')"
                    class="flex items-center rounded-lg py-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                    :class="[
                        route().current('arsip.create')
                            ? 'bg-white/10 font-medium text-white'
                            : 'text-slate-300 hover:bg-white/5 hover:text-white',
                        collapsed ? 'justify-center px-2' : 'gap-2.5 px-3',
                    ]"
                    :aria-label="collapsed ? 'Tambah Dokumen' : undefined"
                    @mouseenter="showTip($event, 'Tambah Dokumen')"
                    @mousemove="showTip($event, 'Tambah Dokumen')"
                    @mouseleave="hideTip()"
                    @focus="showTip($event, 'Tambah Dokumen')"
                    @blur="hideTip()"
                    @click="onNavClick"
                >
                    <AppIcon
                        name="file-plus"
                        :size="20"
                        :stroke-width="1.8"
                        :class="['h-5 w-5 shrink-0', route().current('arsip.create') ? 'text-emerald-400' : 'text-slate-400']"
                        decorative
                    />
                    <span v-show="!collapsed" class="truncate">Tambah Dokumen</span>
                </Link>

                <Link
                    :href="route('scan.index')"
                    class="flex items-center rounded-lg py-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                    :class="[
                        route().current('scan.*')
                            ? 'bg-white/10 font-medium text-white'
                            : 'text-slate-300 hover:bg-white/5 hover:text-white',
                        collapsed ? 'justify-center px-2' : 'gap-2.5 px-3',
                    ]"
                    :aria-label="collapsed ? 'Pindai QR Berkas' : undefined"
                    @mouseenter="showTip($event, 'Pindai QR Berkas')"
                    @mousemove="showTip($event, 'Pindai QR Berkas')"
                    @mouseleave="hideTip()"
                    @focus="showTip($event, 'Pindai QR Berkas')"
                    @blur="hideTip()"
                    @click="onNavClick"
                >
                    <AppIcon
                        name="scan"
                        :size="20"
                        :stroke-width="1.8"
                        :class="['h-5 w-5 shrink-0', route().current('scan.*') ? 'text-emerald-400' : 'text-slate-400']"
                        decorative
                    />
                    <span v-show="!collapsed" class="truncate">Pindai QR Berkas</span>
                </Link>
            </div>

            <!-- GRUP ADMIN: LOKASI ARSIP -->
            <div v-if="isAdmin" class="mt-4 flex flex-col gap-1">
                <p
                    v-show="!collapsed"
                    class="mb-1.5 px-3 text-xs font-bold uppercase tracking-wider text-slate-400"
                >
                    Lokasi Arsip
                </p>
                <div v-if="collapsed" class="mx-2 my-2 border-t border-white/10" />

                <Link
                    :href="route('rak.index')"
                    class="flex items-center rounded-lg py-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                    :class="[
                        route().current('rak.*')
                            ? 'bg-white/10 font-medium text-white'
                            : 'text-slate-300 hover:bg-white/5 hover:text-white',
                        collapsed ? 'justify-center px-2' : 'gap-2.5 px-3',
                    ]"
                    :aria-label="collapsed ? 'Rak Arsip' : undefined"
                    @mouseenter="showTip($event, 'Rak Arsip')"
                    @mousemove="showTip($event, 'Rak Arsip')"
                    @mouseleave="hideTip()"
                    @focus="showTip($event, 'Rak Arsip')"
                    @blur="hideTip()"
                    @click="onNavClick"
                >
                    <AppIcon
                        name="rak"
                        :size="20"
                        :stroke-width="1.8"
                        :class="['h-5 w-5 shrink-0', route().current('rak.*') ? 'text-emerald-400' : 'text-slate-400']"
                        decorative
                    />
                    <span v-show="!collapsed" class="truncate">Rak</span>
                </Link>

                <Link
                    :href="route('boks.index')"
                    class="flex items-center rounded-lg py-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                    :class="[
                        route().current('boks.*')
                            ? 'bg-white/10 font-medium text-white'
                            : 'text-slate-300 hover:bg-white/5 hover:text-white',
                        collapsed ? 'justify-center px-2' : 'gap-2.5 px-3',
                    ]"
                    :aria-label="collapsed ? 'Boks Arsip' : undefined"
                    @mouseenter="showTip($event, 'Boks Arsip')"
                    @mousemove="showTip($event, 'Boks Arsip')"
                    @mouseleave="hideTip()"
                    @focus="showTip($event, 'Boks Arsip')"
                    @blur="hideTip()"
                    @click="onNavClick"
                >
                    <AppIcon
                        name="box"
                        :size="20"
                        :stroke-width="1.8"
                        :class="['h-5 w-5 shrink-0', route().current('boks.*') ? 'text-emerald-400' : 'text-slate-400']"
                        decorative
                    />
                    <span v-show="!collapsed" class="truncate">Boks</span>
                </Link>
            </div>

            <!-- GRUP ADMIN: ADMINISTRASI -->
            <div v-if="isAdmin" class="mt-4 flex flex-col gap-1">
                <p
                    v-show="!collapsed"
                    class="mb-1.5 px-3 text-xs font-bold uppercase tracking-wider text-slate-400"
                >
                    Administrasi
                </p>
                <div v-if="collapsed" class="mx-2 my-2 border-t border-white/10" />

                <Link
                    :href="route('users.index')"
                    class="flex items-center rounded-lg py-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                    :class="[
                        route().current('users.*')
                            ? 'bg-white/10 font-medium text-white'
                            : 'text-slate-300 hover:bg-white/5 hover:text-white',
                        collapsed ? 'justify-center px-2' : 'gap-2.5 px-3',
                    ]"
                    :aria-label="collapsed ? 'Manajemen Pengguna' : undefined"
                    @mouseenter="showTip($event, 'Manajemen Pengguna')"
                    @mousemove="showTip($event, 'Manajemen Pengguna')"
                    @mouseleave="hideTip()"
                    @focus="showTip($event, 'Manajemen Pengguna')"
                    @blur="hideTip()"
                    @click="onNavClick"
                >
                    <AppIcon
                        name="users"
                        :size="20"
                        :stroke-width="1.8"
                        :class="['h-5 w-5 shrink-0', route().current('users.*') ? 'text-emerald-400' : 'text-slate-400']"
                        decorative
                    />
                    <span v-show="!collapsed" class="truncate">Manajemen Pengguna</span>
                </Link>

                <Link
                    :href="route('activity.index')"
                    class="flex items-center rounded-lg py-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                    :class="[
                        route().current('activity.*')
                            ? 'bg-white/10 font-medium text-white'
                            : 'text-slate-300 hover:bg-white/5 hover:text-white',
                        collapsed ? 'justify-center px-2' : 'gap-2.5 px-3',
                    ]"
                    :aria-label="collapsed ? 'Log Aktivitas' : undefined"
                    @mouseenter="showTip($event, 'Log Aktivitas')"
                    @mousemove="showTip($event, 'Log Aktivitas')"
                    @mouseleave="hideTip()"
                    @focus="showTip($event, 'Log Aktivitas')"
                    @blur="hideTip()"
                    @click="onNavClick"
                >
                    <AppIcon
                        name="activity"
                        :size="20"
                        :stroke-width="1.8"
                        :class="['h-5 w-5 shrink-0', route().current('activity.*') ? 'text-emerald-400' : 'text-slate-400']"
                        decorative
                    />
                    <span v-show="!collapsed" class="truncate">Log Aktivitas</span>
                </Link>

                <Link
                    :href="route('backup.index')"
                    class="flex items-center rounded-lg py-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                    :class="[
                        route().current('backup.*')
                            ? 'bg-white/10 font-medium text-white'
                            : 'text-slate-300 hover:bg-white/5 hover:text-white',
                        collapsed ? 'justify-center px-2' : 'gap-2.5 px-3',
                    ]"
                    :aria-label="collapsed ? 'Cadangkan & Pulihkan' : undefined"
                    @mouseenter="showTip($event, 'Cadangkan & Pulihkan')"
                    @mousemove="showTip($event, 'Cadangkan & Pulihkan')"
                    @mouseleave="hideTip()"
                    @focus="showTip($event, 'Cadangkan & Pulihkan')"
                    @blur="hideTip()"
                    @click="onNavClick"
                >
                    <AppIcon
                        name="backup"
                        :size="20"
                        :stroke-width="1.8"
                        :class="['h-5 w-5 shrink-0', route().current('backup.*') ? 'text-emerald-400' : 'text-slate-400']"
                        decorative
                    />
                    <span v-show="!collapsed" class="truncate">Cadangkan & Pulihkan</span>
                </Link>

                <!-- Halaman Publik — toggle + sub-list -->
                <button
                    type="button"
                    class="flex items-center rounded-lg py-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                    :class="[
                        route().current('halaman-publik.*')
                            ? 'bg-white/10 font-medium text-white'
                            : 'text-slate-300 hover:bg-white/5 hover:text-white',
                        collapsed ? 'justify-center px-2' : 'gap-2.5 px-3',
                    ]"
                    :aria-label="collapsed ? 'Halaman Publik' : undefined"
                    :aria-expanded="!collapsed && publikExpanded"
                    @mouseenter="showTip($event, 'Halaman Publik')"
                    @mousemove="showTip($event, 'Halaman Publik')"
                    @mouseleave="hideTip()"
                    @focus="showTip($event, 'Halaman Publik')"
                    @blur="hideTip()"
                    @click="publikExpanded = !publikExpanded"
                >
                    <AppIcon
                        name="layout"
                        :size="20"
                        :stroke-width="1.8"
                        :class="['h-5 w-5 shrink-0', route().current('halaman-publik.*') ? 'text-emerald-400' : 'text-slate-400']"
                        decorative
                    />
                    <span v-show="!collapsed" class="min-w-0 flex-1 truncate text-left">Halaman Publik</span>
                    <AppIcon
                        v-show="!collapsed"
                        name="chevron-down"
                        :size="16"
                        :stroke-width="1.8"
                        :class="['h-4 w-4 shrink-0 transition-transform duration-300', publikExpanded ? 'rotate-180' : '']"
                        decorative
                    />
                </button>

                <!-- Sub-list Halaman Publik -->
                <transition @enter="enterHooks" @leave="leaveHooks">
                <div
                    v-show="!collapsed && publikExpanded"
                    class="my-1 ml-4 space-y-1 overflow-hidden border-l border-slate-600/50 py-1 pl-4 transition-[height] duration-300 ease-in-out"
                >
                    <Link
                        :href="route('halaman-publik.beranda')"
                        class="flex items-center rounded-lg py-2 px-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                        :class="
                            route().current('halaman-publik.beranda')
                                ? 'bg-white/10 font-medium text-white'
                                : 'text-slate-300 hover:bg-white/5 hover:text-white'
                        "
                        @click="onNavClick"
                    >
                        <AppIcon
                            name="carousel"
                            :size="20"
                            :stroke-width="1.8"
                            :class="['h-5 w-5 shrink-0', route().current('halaman-publik.beranda') ? 'text-emerald-400' : 'text-slate-400']"
                            decorative
                        />
                        <span class="ml-2 min-w-0 flex-1 truncate">Foto Carousel</span>
                    </Link>

                    <Link
                        :href="route('halaman-publik.manfaat')"
                        class="flex items-center rounded-lg py-2 px-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                        :class="
                            route().current('halaman-publik.manfaat')
                                ? 'bg-white/10 font-medium text-white'
                                : 'text-slate-300 hover:bg-white/5 hover:text-white'
                        "
                        @click="onNavClick"
                    >
                        <AppIcon
                            name="manfaat"
                            :size="20"
                            :stroke-width="1.8"
                            :class="['h-5 w-5 shrink-0', route().current('halaman-publik.manfaat') ? 'text-emerald-400' : 'text-slate-400']"
                            decorative
                        />
                        <span class="ml-2 min-w-0 flex-1 truncate">Kartu Manfaat</span>
                    </Link>

                    <Link
                        :href="route('halaman-publik.alur-kerja')"
                        class="flex items-center rounded-lg py-2 px-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                        :class="
                            route().current('halaman-publik.alur-kerja')
                                ? 'bg-white/10 font-medium text-white'
                                : 'text-slate-300 hover:bg-white/5 hover:text-white'
                        "
                        @click="onNavClick"
                    >
                        <AppIcon
                            name="alur"
                            :size="20"
                            :stroke-width="1.8"
                            :class="['h-5 w-5 shrink-0', route().current('halaman-publik.alur-kerja') ? 'text-emerald-400' : 'text-slate-400']"
                            decorative
                        />
                        <span class="ml-2 min-w-0 flex-1 truncate">Langkah Prosedur</span>
                    </Link>

                    <Link
                        :href="route('halaman-publik.faq')"
                        class="flex items-center rounded-lg py-2 px-2 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                        :class="
                            route().current('halaman-publik.faq')
                                ? 'bg-white/10 font-medium text-white'
                                : 'text-slate-300 hover:bg-white/5 hover:text-white'
                        "
                        @click="onNavClick"
                    >
                        <AppIcon
                            name="faq"
                            :size="20"
                            :stroke-width="1.8"
                            :class="['h-5 w-5 shrink-0', route().current('halaman-publik.faq') ? 'text-emerald-400' : 'text-slate-400']"
                            decorative
                        />
                        <span class="ml-2 min-w-0 flex-1 truncate">FAQ Bantuan</span>
                    </Link>
                </div>
                </transition>
            </div>
        </div>

        <!-- PIN BAWAH: mengunci di dasar sidebar (di luar area scroll),
             jadi profil + Keluar selalu terlihat tanpa ikut ter-scroll. -->
        <!-- Padding horizontal disamakan dengan area menu (px-2 rail / px-3 lebar)
             agar kolom kiri profil, avatar, dan Keluar sejajar dengan ikon menu. -->
        <div
            class="flex shrink-0 flex-col gap-3 border-t border-white/10 py-3"
            :class="collapsed ? 'px-2' : 'px-3'"
        >
            <!-- Profil + Logout -->
            <div class="border-t border-slate-800 pt-3">
                <!-- Baris profil: avatar (lightbox bila ada foto) + nama + gear → profile.edit.
                     Rail: kolom vertikal, gear di bawah avatar dengan tooltip. -->
                <!-- Geometri sama dengan item menu: rail = center (px-2),
                     lebar = icon+gap+label dari px-3; gear menempel kanan. -->
                <div
                    class="flex rounded-lg py-1 transition-colors"
                    :class="[
                        collapsed ? 'flex-col items-center gap-1 px-2' : 'items-center gap-2.5 px-3',
                        route().current('profile.*') ? 'bg-white/10' : '',
                    ]"
                >
                    <button
                        v-if="avatarSrc"
                        type="button"
                        class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/10 transition-colors hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-brand-blue/40"
                        :aria-label="`Perbesar avatar ${userName}`"
                        @click="openLightbox(avatarSrc, userName)"
                    >
                        <img
                            :src="avatarSrc"
                            :alt="userName"
                            class="h-8 w-8 rounded-full object-cover"
                        />
                        <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full border border-brand-navy bg-emerald-400" />
                    </button>
                    <div
                        v-else
                        class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/10"
                    >
                        <AppIcon
                            name="avatar"
                            :size="14"
                            :stroke-width="1.8"
                            class="h-3.5 w-3.5 text-slate-200"
                            decorative
                        />
                        <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full border border-brand-navy bg-emerald-400" />
                    </div>

                    <div v-show="!collapsed" class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-white">{{ userName }}</div>
                    </div>

                    <Link
                        :href="route('profile.edit')"
                        class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-brand-blue/40"
                        :class="route().current('profile.*') ? 'text-emerald-400' : 'text-slate-400'"
                        :aria-label="collapsed ? 'Profil & pengaturan' : undefined"
                        @mouseenter="showTip($event, 'Profil & pengaturan')"
                        @mousemove="showTip($event, 'Profil & pengaturan')"
                        @mouseleave="hideTip()"
                        @focus="showTip($event, 'Profil & pengaturan')"
                        @blur="hideTip()"
                        @click="onNavClick"
                    >
                        <AppIcon
                            name="gear"
                            :size="20"
                            :stroke-width="1.8"
                            class="h-5 w-5"
                            decorative
                        />
                    </Link>
                </div>

                <!-- w-full wajib: Link as="button" dirender <button> (replaced
                     element) yang lebarnya menyusut jadi pas isi — tanpa ini
                     justify-center rail hanya memusatkan ikon DI DALAM tombol
                     sempit yang menempel kiri. w-full menyamakannya dengan
                     item menu (<a> block-level yang meregang penuh). -->
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="mt-1.5 flex w-full items-center rounded-lg py-1.5 text-xs text-slate-400 transition-colors hover:bg-white/5 hover:text-rose-300 focus:outline-none focus:ring-2 focus:ring-brand-blue/30"
                    :class="collapsed ? 'justify-center px-2' : 'gap-2.5 px-3'"
                    :aria-label="collapsed ? 'Keluar' : undefined"
                    @mouseenter="showTip($event, 'Keluar')"
                    @mousemove="showTip($event, 'Keluar')"
                    @mouseleave="hideTip()"
                    @focus="showTip($event, 'Keluar')"
                    @blur="hideTip()"
                    @click="onNavClick"
                >
                    <!-- Ikon sama besar dengan item menu (h-5 w-5) agar kolom
                         kiri sejajar di semua mode. -->
                    <AppIcon
                        name="logout"
                        :size="20"
                        :stroke-width="1.8"
                        class="h-5 w-5 shrink-0"
                        decorative
                    />
                    <span v-show="!collapsed">Keluar</span>
                </Link>
            </div>
        </div>
        </template>

        <!-- Tooltip bersama: dipindah ke <body> agar lolos dari overflow rail dan dari
             `transform` drawer mobile (containing block position:fixed). Hanya ter-render
             saat collapsed (instans desktop); instans mobile selalu collapsed=false. -->
        <Teleport to="body">
            <div
                v-if="tip.show"
                class="pointer-events-none fixed z-[60] w-max max-w-[220px] break-words rounded-lg bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-white shadow-lg ring-1 ring-white/10"
                :style="{ top: tip.top + 'px', left: tip.left + 'px' }"
            >{{ tip.text }}</div>
        </Teleport>

        <!-- Lightbox logo/avatar: gambar utuh di tengah layar, latar gelap.
             Klik overlay (di luar gambar) atau Escape menutup. -->
        <ImageLightbox
            :show="lightbox.show"
            :src="lightbox.src"
            :label="lightbox.label"
            @close="closeLightbox()"
        />
    </aside>
</template>
