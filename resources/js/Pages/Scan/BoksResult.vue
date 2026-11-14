<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BackLink from '@/Components/BackLink.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    boks: {
        type: Object,
        default: null,
    },
    dokumen: {
        type: Array,
        default: () => [],
    },
    previousUrl: {
        type: String,
        default: '',
    },
});

const page = usePage();
const statusClass = computed(() =>
    props.boks?.is_active
        ? 'bg-emerald-100 text-emerald-800'
        : 'bg-slate-100 text-slate-500',
);
const statusLabel = computed(() =>
    props.boks?.is_active ? 'Aktif' : 'Nonaktif',
);

// Flash messages
const flashSuccess = computed(() => page.props.flash?.success);
const flashVisible = ref(false);
let flashTimer = null;

watch(
    () => page.props.flash?.success,
    (v) => {
        clearTimeout(flashTimer);
        if (v) {
            flashVisible.value = true;
            flashTimer = setTimeout(() => { flashVisible.value = false; }, 5000);
        }
    },
    { immediate: true },
);

const flashDanger = computed(() => page.props.flash?.danger);
const flashDangerVisible = ref(false);
let flashDangerTimer = null;

watch(
    () => page.props.flash?.danger,
    (v) => {
        clearTimeout(flashDangerTimer);
        if (v) {
            flashDangerVisible.value = true;
            flashDangerTimer = setTimeout(() => { flashDangerVisible.value = false; }, 5000);
        }
    },
    { immediate: true },
);

onUnmounted(() => {
    clearTimeout(flashTimer);
    clearTimeout(flashDangerTimer);
});
</script>

<template>
    <Head title="Hasil Scan QR Boks" />
    <AppLayout>
        <!-- Flash notifications -->
        <div
            v-if="flashSuccess && flashVisible"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
        >
            {{ flashSuccess }}
        </div>

        <div
            v-if="flashDanger && flashDangerVisible"
            class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"
        >
            {{ flashDanger }}
        </div>

        <template #header>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Hasil Scan QR Boks</h1>
                    <p class="mt-1 text-sm text-slate-500">Informasi boks arsip dari kode QR yang dipindai.</p>
                </div>
                <BackLink :use-history="true" label="Kembali" />
            </div>
        </template>

        <div v-if="boks" class="space-y-6">
            <!-- Card utama -->
            <div v-reveal class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-blue/10">
                            <AppIcon name="box" :size="20" :stroke-width="2" class="text-brand-blue" decorative />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">{{ boks.kode_boks }}</h2>
                            <p class="text-xs text-slate-500">Hasil scan QR boks arsip</p>
                        </div>
                        <span class="ml-auto inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold">
                            {{ statusLabel }}
                        </span>
                    </div>
                </div>

                <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Kode Boks</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800">{{ boks.kode_boks }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Rak</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800">
                            {{ boks.rak?.kode_rak || '—' }}
                            <span class="ml-1 text-xs font-normal text-slate-500">{{ boks.rak?.nama_rak || '' }}</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">No. Baris</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800">{{ boks.nomor_baris || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">No. Boks</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800">{{ boks.nomor_boks || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Kapasitas</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800">
                            {{ boks.dokumen_arsip_count ?? 0 }} / {{ boks.kapasitas || '—' }} dokumen
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Keterangan</p>
                        <p class="mt-1 text-sm text-slate-700">{{ boks.keterangan || '—' }}</p>
                    </div>
                </div>
            </div>

            <!-- Daftar Dokumen di dalam Boks -->
            <div v-if="dokumen && dokumen.length > 0" v-reveal class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-slate-200 px-6 py-4 bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-blue/10">
                            <AppIcon name="file-text" :size="16" :stroke-width="2" class="text-brand-blue" decorative />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Daftar Dokumen</h2>
                            <p class="text-xs text-slate-500">{{ dokumen.length }} dokumen di dalam boks ini</p>
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-slate-100">
                    <Link
                        v-for="doc in dokumen"
                        :key="doc.id"
                        :href="route('arsip.show', doc.id)"
                        class="flex items-center gap-4 px-6 py-4 hover:bg-slate-50 transition group"
                    >
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100">
                            <AppIcon name="file-text" :size="18" :stroke-width="2" class="text-emerald-600" decorative />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-900 group-hover:text-brand-blue transition">
                                {{ doc.kode_arsip }}
                            </p>
                            <p class="text-sm text-slate-600 truncate">{{ doc.perihal || '—' }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">
                                <span v-if="doc.seksi?.nama_seksi">{{ doc.seksi.nama_seksi }}</span>
                            </p>
                        </div>
                        <div class="shrink-0">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="{
                                    'bg-emerald-100 text-emerald-800': doc.status_arsip === 'tersedia',
                                    'bg-amber-100 text-amber-800': doc.status_arsip === 'dipinjam',
                                    'bg-rose-100 text-rose-800': doc.status_arsip === 'hilang',
                                    'bg-slate-100 text-slate-600': !doc.status_arsip,
                                }"
                            >
                                {{ doc.status_arsip || '—' }}
                            </span>
                        </div>
                        <AppIcon name="chevron-right" :size="16" :stroke-width="2" class="text-slate-400 group-hover:text-brand-blue transition" decorative />
                    </Link>
                </div>
            </div>

            <!-- Empty state jika tidak ada dokumen -->
            <div v-else-if="boks" class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
                <AppIcon name="archive" :size="40" :stroke-width="1.5" class="mx-auto text-slate-300" decorative />
                <p class="mt-3 text-sm font-medium text-slate-700">Belum ada dokumen di boks ini</p>
                <p class="mt-1 text-xs text-slate-500">Dokumen akan muncul di sini setelah disimpan ke boks ini.</p>
            </div>
        </div>

        <div v-else class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
            <AppIcon name="box" :size="40" :stroke-width="1.5" class="mx-auto text-slate-300" decorative />
            <p class="mt-3 text-sm text-slate-500">Boks tidak ditemukan atau sudah tidak aktif.</p>
        </div>
    </AppLayout>
</template>
