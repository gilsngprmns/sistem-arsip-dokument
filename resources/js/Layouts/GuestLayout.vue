<script setup>
import { institution } from '@/config/institution';
import { Link, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const year = new Date().getFullYear();
const page = usePage();
const SKELETON_MS = 1000;
const isLoading = ref(true);
let skeletonTimer = null;

const armSkeleton = () => {
    if (skeletonTimer) clearTimeout(skeletonTimer);
    skeletonTimer = setTimeout(() => {
        isLoading.value = false;
        skeletonTimer = null;
    }, SKELETON_MS);
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

// Logo kelurahan statis — tidak bisa diklik, hanya tampilan.
</script>

<template>
    <div class="min-h-screen bg-[#F4F7FB] text-slate-900">
        <div class="grid min-h-screen lg:grid-cols-[minmax(360px,0.85fr)_minmax(480px,1.15fr)]">
            <!-- Sidebar kiri — skeleton -->
            <aside
                v-if="isLoading"
                class="relative hidden overflow-hidden bg-brand-navy px-10 py-10 text-white lg:flex lg:flex-col lg:h-full lg:justify-between xl:px-16"
            >
                <div class="relative flex flex-col items-start gap-3 motion-safe:animate-pulse">
                    <div class="flex items-center gap-3">
                        <div class="h-11 w-11 rounded-xl bg-slate-600" />
                        <div class="space-y-1.5">
                            <div class="h-4 w-44 rounded bg-slate-600" />
                            <div class="h-3 w-52 rounded bg-slate-500" />
                        </div>
                    </div>
                </div>
                <!-- Skeleton badge -->
                <div class="relative motion-safe:animate-pulse">
                    <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-slate-700/50 border border-slate-600/50">
                        <div class="w-1.5 h-1.5 rounded-full bg-slate-500" />
                        <div class="h-3 w-28 rounded bg-slate-600" />
                    </div>
                </div>
                <div class="relative flex flex-1 flex-col items-center justify-center text-center motion-safe:animate-pulse">
                    <div class="h-4 w-40 rounded bg-slate-600" />
                    <div class="mt-4 space-y-2.5">
                        <div class="h-7 w-56 rounded bg-slate-500" />
                        <div class="h-7 w-48 rounded bg-slate-500" />
                    </div>
                    <div class="mt-4 space-y-2">
                        <div class="h-4 w-48 rounded bg-slate-500/70" />
                        <div class="h-4 w-36 rounded bg-slate-500/70" />
                    </div>
                </div>
                <!-- Skeleton footer -->
                <div class="relative mt-auto flex flex-col gap-3 pt-8 border-t border-slate-700/50 motion-safe:animate-pulse">
                    <div class="space-y-2">
                        <div class="flex items-center gap-1.5">
                            <div class="w-3 h-3 rounded bg-slate-600" />
                            <div class="h-2.5 w-28 rounded bg-slate-600" />
                        </div>
                        <div class="space-y-1.5">
                            <div class="h-2.5 w-full rounded bg-slate-500/70" />
                            <div class="h-2.5 w-full rounded bg-slate-500/70" />
                            <div class="h-2.5 w-full rounded bg-slate-500/70" />
                            <div class="h-2.5 w-2/3 rounded bg-slate-500/70" />
                        </div>
                    </div>
                    <div class="h-2.5 w-48 rounded bg-slate-500/70" />
                    <div class="h-2.5 w-36 rounded bg-slate-500/70" />
                </div>
            </aside>

            <!-- Sidebar kiri — asli -->
            <aside v-else class="relative hidden overflow-hidden bg-brand-navy px-10 py-10 text-white lg:flex lg:flex-col lg:h-full lg:justify-between xl:px-16">
                <!-- Background pattern -->
                <div class="absolute inset-0 opacity-20 [background-image:linear-gradient(rgba(255,255,255,.08)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.08)_1px,transparent_1px)] [background-size:34px_34px]" />

                <!-- Header: Logo + Status Badge -->
                <div class="relative z-10 flex flex-col items-start gap-3">
                    <div v-reveal class="relative">
                        <div class="inline-flex items-center gap-3">
                            <img
                                :src="institution.logoPath"
                                :alt="`Logo ${institution.name}`"
                                class="h-11 w-11 shrink-0 rounded-xl object-contain"
                            />
                            <span>
                                <span class="block text-sm font-bold tracking-tight">{{ institution.headerTitle }}</span>
                                <span class="block text-xs text-blue-200">{{ institution.headerSubtitle }}</span>
                            </span>
                        </div>
                    </div>
                    <!-- System Status Badge -->
                    <div v-reveal class="relative">
                        <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-slate-800/50 border border-slate-700/50 text-[10px] text-slate-300 uppercase tracking-wider">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                            Sistem Operasional
                        </span>
                    </div>
                </div>

                <!-- Center: Title + Mission (vertically centered) -->
                <div v-reveal :style="{ transitionDelay: '120ms' }" class="flex-1 flex flex-col justify-center items-center text-center">
                    <p class="mb-4 text-sm font-medium text-blue-200">{{ institution.accessNotice }}</p>
                    <h1 class="text-4xl font-bold leading-tight tracking-tight xl:text-5xl">
                        {{ institution.fullTitle }}
                    </h1>
                    <p class="mt-4 max-w-sm text-base leading-7 text-slate-300">
                        {{ institution.missionBrief }}
                    </p>
                </div>

                <!-- Footer: Security Notice + Help + Version -->
                <div v-reveal :style="{ transitionDelay: '240ms' }" class="relative z-10 flex flex-col gap-3 mt-auto pt-8 border-t border-blue-800/50">
                    <!-- Security Warning -->
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-1.5 text-blue-400/80">
                            <AppIcon name="lock" :size="12" class="w-3 h-3" decorative />
                            <span class="text-[10px] font-bold tracking-wider uppercase">Peringatan Keamanan</span>
                        </div>
                        <p class="text-[10px] text-blue-200/50 leading-relaxed text-justify max-w-sm">
                            Sistem ini bersifat rahasia dan terbatas. Segala bentuk aktivitas login dan sirkulasi arsip dipantau
                            dan dicatat secara sistem. Akses tidak sah akan dikenakan sanksi sesuai peraturan perundang-undangan.
                        </p>
                    </div>
                    <!-- Help Contact -->
                    <p class="text-[10px] text-blue-300/60">
                        Kendala akses login? Hubungi Admin Tata Usaha (Ruang IT).
                    </p>
                    <!-- Version -->
                    <p class="text-[10px] text-blue-400/50 font-semibold">
                        Sistem Informasi Manajemen Arsip (SIMA) • v1.0.0
                    </p>
                </div>

                <!-- Watermark: Database icon -->
                <AppIcon
                    name="backup"
                    class="absolute -bottom-12 -right-12 text-white w-56 h-56 opacity-[0.04]"
                    decorative
                />
            </aside>

            <!-- Panel kanan — skeleton -->
            <main v-if="isLoading" class="flex min-h-screen flex-col px-5 py-6 sm:px-10 lg:px-16 xl:px-24">
                <div class="flex justify-end">
                    <div class="h-6 w-16 rounded bg-slate-200" />
                </div>
                <div class="flex flex-1 items-center justify-center py-10">
                    <div class="w-full max-w-md">
                        <slot name="skeleton">
                            <div class="motion-safe:animate-pulse space-y-5">
                                <div class="h-7 w-44 rounded bg-slate-200" />
                                <div class="h-4 w-full rounded bg-slate-100" />
                                <div class="mt-6 space-y-4">
                                    <div class="h-10 rounded-lg bg-slate-100" />
                                    <div class="h-10 rounded-lg bg-slate-100" />
                                    <div class="h-10 rounded-lg bg-slate-100" />
                                </div>
                            </div>
                        </slot>
                    </div>
                </div>
                <div class="h-3 w-48 self-center rounded bg-slate-200" />
            </main>

            <!-- Panel kanan — asli -->
            <main v-else class="flex min-h-screen flex-col px-5 py-6 sm:px-10 lg:px-16 xl:px-24">
                <div class="flex justify-end">
                    <Link
                        href="/"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-slate-500 transition duration-200 hover:text-brand-blue focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue/40"
                    >
                        <AppIcon name="home" :size="16" :stroke-width="2" class="text-slate-500" decorative />
                        Beranda
                    </Link>
                </div>
                <div v-reveal :style="{ transitionDelay: '100ms' }" class="flex flex-1 items-center justify-center py-10">
                    <div class="w-full max-w-md">
                        <div class="mb-8 flex items-center gap-3 lg:hidden">
                            <img
                                :src="institution.logoPath"
                                :alt="`Logo ${institution.name}`"
                                class="h-10 w-10 shrink-0 rounded-xl object-contain"
                            />
                            <span>
                                <span class="block text-sm font-bold tracking-tight text-brand-navy">{{ institution.headerTitle }}</span>
                                <span class="block text-xs text-slate-500">{{ institution.headerSubtitle }}</span>
                            </span>
                        </div>
                        <slot />
                    </div>
                </div>
                <p class="text-center text-xs text-slate-400">
                    © {{ year }} {{ institution.name }}
                </p>
            </main>
        </div>

        <!-- Lightbox logo kelurahan — dinonaktifkan (statis saja) -->
    </div>
</template>
