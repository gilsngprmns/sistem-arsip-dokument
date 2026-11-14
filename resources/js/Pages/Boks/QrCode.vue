<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import BackLink from '@/Components/BackLink.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    boks: {
        type: Object,
        required: true,
    },
    qrPath: {
        type: String,
        default: '',
    },
    qrUrl: {
        type: String,
        default: '',
    },
});

const pct = (() => {
    const cap = props.boks?.kapasitas ?? 0;
    if (!cap) return 0;
    return Math.min(100, Math.round(((props.boks?.dokumen_arsip_count ?? 0) / cap) * 100));
})();

const barColor = pct >= 100 ? 'bg-rose-500' : pct >= 80 ? 'bg-amber-500' : 'bg-emerald-500';
</script>

<template>
    <Head :title="`QR Boks ${boks?.kode_boks}`" />

    <AppLayout>
        <template #skeleton-header>
            <div class="h-7 w-56 rounded bg-slate-200" />
        </template>

        <template #skeleton>
            <div class="mx-auto max-w-md space-y-4">
                <div class="h-12 w-full rounded-xl bg-slate-200" />
                <div class="aspect-square h-64 rounded-2xl bg-slate-100" />
            </div>
        </template>

        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold text-slate-900">QR Code Boks</h1>
                <BackLink :href="route('boks.index')" label="Kembali ke Daftar Boks" />
            </div>
        </template>

        <div v-reveal class="mx-auto max-w-md space-y-6">
            <!-- Info boks -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-500">Kode Boks</span>
                        <span class="text-sm font-semibold text-slate-900">{{ boks?.kode_boks }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-500">Rak</span>
                        <span class="text-sm font-semibold text-slate-900">
                            {{ boks?.rak?.kode_rak || '—' }}
                            <span class="text-xs font-normal text-slate-400">({{ boks?.rak?.nama_rak || '—' }})</span>
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-500">Baris / Nomor</span>
                        <span class="text-sm font-semibold text-slate-900">
                            {{ boks?.nomor_baris || '—' }} / {{ boks?.nomor_boks || '—' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-500">Kapasitas</span>
                        <span class="text-sm font-semibold text-slate-900">
                            {{ boks?.dokumen_arsip_count ?? 0 }} / {{ boks?.kapasitas ?? '—' }}
                        </span>
                    </div>
                    <div>
                        <div class="mb-1 flex items-center justify-between text-xs">
                            <span class="font-medium text-slate-500">Terisi</span>
                            <span class="font-semibold text-slate-700">{{ pct }}%</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                            <div
                                :class="[barColor, 'h-full rounded-full transition-all']"
                                :style="{ width: pct + '%' }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- QR Image -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-slate-700">QR Code</h2>
                <div class="flex justify-center rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <img
                        v-if="qrPath"
                        :src="`/storage/${qrPath}`"
                        :alt="`QR Code ${boks?.kode_boks}`"
                        class="h-48 w-48 object-contain"
                    />
                    <div v-else class="flex h-48 w-48 items-center justify-center text-sm text-slate-400">
                        QR belum tersedia
                    </div>
                </div>
                <p class="mt-3 text-center text-xs text-slate-400">
                    Scan untuk membuka: <span class="font-mono text-[10px]">{{ qrUrl }}</span>
                </p>
                <div class="mt-4 flex flex-wrap justify-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                        @click="window.print()"
                    >
                        <AppIcon name="print" :size="14" :stroke-width="2" class="h-4 w-4" decorative />
                        Cetak
                    </button>
                    <a
                        v-if="qrPath"
                        :href="`/storage/${qrPath}`"
                        :download="`qr-boks-${boks.kode_boks}.${qrPath.split('.').pop()}`"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        <AppIcon name="download" :size="14" :stroke-width="2" class="h-4 w-4" decorative />
                        Unduh {{ qrPath.endsWith('.svg') ? 'SVG' : 'PNG' }}
                    </a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
