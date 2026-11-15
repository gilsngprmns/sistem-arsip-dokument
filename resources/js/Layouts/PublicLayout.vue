<script setup>
import ImageLightbox from '@/Components/ImageLightbox.vue';
import { institution } from '@/config/institution';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

defineProps({
    canLogin: {
        type: Boolean,
        default: true,
    },
});

const year = new Date().getFullYear();
const mobileOpen = ref(false);
const page = usePage();

/* ============ Skeleton loading ====== */
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

const navItems = [
    { label: 'Beranda', routeName: 'home' },
    { label: 'Manfaat', routeName: 'fitur' },
    { label: 'Prosedur', routeName: 'alur-kerja' },
    { label: 'Bantuan', routeName: 'bantuan' },
];

const hasPhone = computed(() => Boolean(institution.phone?.trim()));
const hasEmail = computed(() => Boolean(institution.email?.trim()));

const linkClass = (routeName) => {
    const active = route().current(routeName);

    return active
        ? 'relative font-semibold text-brand-navy after:absolute after:-bottom-1 after:left-0 after:h-0.5 after:w-full after:rounded-full after:bg-brand-blue'
        : 'text-slate-500 transition duration-200 hover:text-brand-navy';
};

const mobileLinkClass = (routeName) =>
    route().current(routeName)
        ? 'rounded-lg bg-[#EEF3FB] px-3 py-2.5 text-sm font-semibold text-brand-navy'
        : 'rounded-lg px-3 py-2.5 text-sm text-slate-500 transition duration-200 hover:bg-slate-50 hover:text-brand-navy';

// Logo kelurahan bisa dilihat utuh: klik lambang di header → lightbox
// (gambar penuh, latar gelap). Blok teks di sebelahnya tetap link ke beranda.
const lightbox = ref({ show: false, src: '', label: '' });
const openLogoLightbox = () => {
    lightbox.value = { show: true, src: institution.logoPath, label: institution.name };
};
const closeLogoLightbox = () => {
    lightbox.value.show = false;
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50 text-slate-900">
        <!-- Header skeleton -->
        <header
            v-if="isLoading"
            class="sticky top-0 z-50 motion-safe:animate-pulse border-b border-gray-100 bg-white shadow-sm"
            role="status"
            aria-live="polite"
        >
            <span class="sr-only">Memuat navigasi…</span>
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-4">
                <div class="flex min-w-0 shrink-0 items-center gap-3">
                    <div class="h-10 w-10 shrink-0 rounded-xl bg-slate-200" />
                    <div>
                        <div class="h-4 w-24 rounded bg-slate-200" />
                        <div class="mt-1 h-3 w-32 rounded bg-slate-200" />
                    </div>
                </div>
                <div class="hidden items-center gap-4 md:flex md:gap-6">
                    <div class="flex items-center gap-5">
                        <div v-for="i in 4" :key="'nav-sk-' + i" class="h-4 w-14 rounded bg-slate-200" />
                    </div>
                    <div class="h-11 w-24 rounded-lg bg-slate-200" />
                </div>
            </div>
        </header>

        <!-- Header asli -->
        <header v-else class="sticky top-0 z-50 bg-white/85 backdrop-blur-md shadow-sm border-b border-gray-100">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-4 sm:gap-4 sm:px-6">
                <div class="flex min-w-0 shrink-0 items-center gap-3">
                    <button
                        type="button"
                        class="h-10 w-10 shrink-0 rounded-xl transition-transform focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue/40"
                        :aria-label="`Perbesar logo ${institution.name}`"
                        @click="openLogoLightbox()"
                    >
                        <img
                            :src="institution.logoPath"
                            :alt="`Logo ${institution.name}`"
                            class="h-full w-full rounded-xl object-contain"
                        />
                    </button>
                    <Link :href="route('home')" class="rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue/40">
                        <span class="min-w-0">
                            <span class="block text-sm font-bold tracking-tight text-brand-navy">
                                {{ institution.headerTitle }}
                            </span>
                            <span class="block truncate text-xs text-slate-500">
                                {{ institution.headerSubtitle }}
                            </span>
                        </span>
                    </Link>
                </div>

                <div class="flex items-center gap-4 md:gap-6">
                    <nav class="hidden items-center gap-5 text-sm md:flex">
                        <Link
                            v-for="item in navItems"
                            :key="item.routeName"
                            :href="route(item.routeName)"
                            :class="linkClass(item.routeName)"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>

                    <Link
                        v-if="canLogin && $page.props.auth.user"
                        :href="route('dashboard')"
                        class="inline-flex min-h-11 shrink-0 items-center rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white transition duration-200 hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue/40 focus-visible:ring-offset-2"
                    >
                        Buka Dashboard
                    </Link>
                    <Link
                        v-else-if="canLogin"
                        :href="route('login')"
                        class="inline-flex min-h-11 shrink-0 items-center rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white transition duration-200 hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue/40 focus-visible:ring-offset-2"
                    >
                        Masuk
                    </Link>

                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-slate-600 transition duration-200 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue/40 md:hidden"
                        :aria-expanded="mobileOpen"
                        aria-label="Buka menu navigasi"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <AppIcon
                            v-if="!mobileOpen"
                            name="menu"
                            :size="20"
                            :stroke-width="1.8"
                            class="h-5 w-5"
                            decorative
                        />
                        <AppIcon
                            v-else
                            name="close"
                            :size="20"
                            :stroke-width="1.8"
                            class="h-5 w-5"
                            decorative
                        />
                    </button>
                </div>
            </div>

            <nav
                v-if="mobileOpen"
                class="border-t border-slate-100 bg-white px-6 py-3 md:hidden"
            >
                <div class="flex flex-col gap-1">
                    <Link
                        v-for="item in navItems"
                        :key="item.routeName"
                        :href="route(item.routeName)"
                        :class="mobileLinkClass(item.routeName)"
                        @click="mobileOpen = false"
                    >
                        {{ item.label }}
                    </Link>
                </div>
            </nav>
        </header>

        <!-- Geometri skeleton dimiliki layout (pendekatan Fix #67): main mengisi
             sisa viewport, section skeleton (flex-1) menekan footer ke bawah lipatan
             tanpa menebak tinggi header. -->
        <main class="flex min-h-0 flex-1 flex-col">
            <div
                v-if="isLoading"
                role="status"
                aria-live="polite"
                class="flex flex-1 flex-col motion-safe:animate-pulse"
            >
                <slot name="skeleton" />
            </div>
            <template v-else>
                <slot />
            </template>
        </main>

        <!-- Footer skeleton — palet sama dengan sidebar admin: blok slate solid
             di atas navy, TANPA opasitas putih (yang menimbulkan kesan awan).
             Latar navy tetap solid; hanya isi yang berdenyut. -->
        <footer
            v-if="isLoading"
            class="border-t border-slate-800 bg-brand-navy"
            role="status"
            aria-live="polite"
        >
            <span class="sr-only">Memuat footer…</span>
            <div class="mx-auto w-full max-w-6xl px-6 py-6">
                <div class="grid gap-6 sm:grid-cols-3 motion-safe:animate-pulse">
                    <div>
                        <div class="h-4 w-32 rounded bg-slate-600" />
                        <div class="mt-2 h-3 w-full rounded bg-slate-500" />
                        <div class="mt-1 h-3 w-4/5 rounded bg-slate-500" />
                        <div class="mt-2 h-3 w-3/4 rounded bg-slate-500" />
                        <div class="mt-3 h-3 w-24 rounded bg-slate-600" />
                    </div>
                    <div>
                        <div class="h-4 w-28 rounded bg-slate-600" />
                        <div class="mt-2 h-3 w-full rounded bg-slate-500" />
                        <div class="mt-1 h-3 w-3/4 rounded bg-slate-500" />
                        <div class="mt-3 h-3 w-20 rounded bg-slate-600" />
                    </div>
                    <div>
                        <div class="h-4 w-24 rounded bg-slate-600" />
                        <div class="mt-2 h-3 w-2/3 rounded bg-slate-500" />
                        <div class="mt-1 h-3 w-1/2 rounded bg-slate-500" />
                    </div>
                </div>
                <div class="mt-4 border-t border-white/10 pt-3 motion-safe:animate-pulse">
                    <div class="h-3 w-3/4 rounded bg-slate-500" />
                </div>
            </div>
        </footer>

        <!-- Footer asli -->
        <footer v-else class="border-t border-slate-800 bg-brand-navy">
            <div class="mx-auto w-full max-w-6xl px-6 py-6">
                <div class="grid gap-6 text-sm text-slate-300 sm:grid-cols-3">
                    <div>
                        <p class="font-semibold text-white">{{ institution.name }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-400">
                            {{ institution.district }} · {{ institution.city }}
                        </p>
                        <p class="mt-2 text-xs leading-5">{{ institution.address }}</p>
                        <p class="mt-1 text-xs leading-5">Jam pelayanan: {{ institution.officeHours }}</p>
                        <a
                            :href="institution.mapsUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-2 inline-block text-xs font-semibold text-white transition duration-200 hover:underline"
                        >
                            Lihat lokasi di peta
                        </a>
                    </div>

                    <div>
                        <p class="font-semibold text-white">Kontak & bantuan</p>
                        <p class="mt-2 text-xs leading-5">{{ institution.adminRole }}</p>
                        <p v-if="hasPhone" class="mt-1 text-xs leading-5">
                            Telepon:
                            <a
                                :href="`tel:${institution.phone}`"
                                class="text-slate-300 transition duration-200 hover:text-white hover:underline"
                            >
                                {{ institution.phone }}
                            </a>
                        </p>
                        <p v-if="hasEmail" class="mt-1 text-xs leading-5">
                            Email:
                            <a
                                :href="`mailto:${institution.email}`"
                                class="text-slate-300 transition duration-200 hover:text-white hover:underline"
                            >
                                {{ institution.email }}
                            </a>
                        </p>
                        <Link
                            v-if="!route().current('bantuan')"
                            :href="route('bantuan')"
                            class="mt-2 inline-block text-xs text-slate-300 transition duration-200 hover:text-white hover:underline"
                        >
                            Halaman bantuan petugas
                        </Link>
                    </div>

                    <div>
                        <p class="font-semibold text-white">Informasi resmi</p>
                        <a
                            :href="institution.officialSite.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-2 inline-block text-xs font-semibold text-white transition duration-200 hover:underline"
                        >
                            {{ institution.officialSite.label }}
                        </a>
                        <p class="mt-2 text-xs leading-5 text-slate-400">
                            © {{ year }} {{ institution.name }}
                        </p>
                    </div>
                </div>

                <p
                    class="mt-4 border-t border-slate-700 pt-3 text-xs leading-5 text-slate-400"
                >
                    {{ institution.disclaimer }}
                </p>
            </div>
        </footer>

        <!-- Lightbox logo kelurahan: gambar utuh di tengah layar, latar gelap. -->
        <ImageLightbox
            :show="lightbox.show"
            :src="lightbox.src"
            :label="lightbox.label"
            @close="closeLogoLightbox()"
        />
    </div>
</template>
