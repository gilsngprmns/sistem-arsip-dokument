<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import QrScanner from '@/Components/QrScanner.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

// Tab default = webcam, mengikuti perilaku lama (versi modal) saat pertama dibuka.
const tab = ref('webcam');
const foundArsip = ref(null);
const foundBoks = ref(null);

const switchTab = (value) => {
    tab.value = value;
    foundArsip.value = null; // hindari tombol "Buka Detail" stale setelah error/ganti tab
};

const onFound = (data) => {
    if (data?.kode_boks) {
        foundBoks.value = data;
        foundArsip.value = null;
    } else {
        foundArsip.value = data;
        foundBoks.value = null;
    }
};
</script>

<template>
    <Head title="Pindai QR Berkas" />
    <AppLayout>
        <template #skeleton-header>
            <div class="space-y-1">
                <div class="h-7 w-56 rounded bg-slate-200" />
                <div class="h-4 w-96 rounded bg-slate-200" />
            </div>
        </template>

        <template #skeleton>
            <div class="mx-auto max-w-2xl space-y-4">
                <div class="h-11 rounded-xl bg-slate-200" />
                <div class="h-64 rounded-2xl bg-slate-100" />
            </div>
        </template>

        <template #header>
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Pindai QR Label Berkas</h1>
                <p class="text-sm text-slate-500">Arahkan kamera ke QR stiker pada berkas fisik, atau unggah gambar QR dari komputer.</p>
            </div>
        </template>

        <div v-reveal class="mx-auto max-w-2xl space-y-4">
            <div class="grid grid-cols-2 rounded-xl bg-slate-100 p-1 text-sm font-medium">
                <button
                    type="button"
                    class="rounded-lg px-3 py-2 transition"
                    :class="tab === 'webcam' ? 'bg-white text-brand-blue shadow-sm' : 'text-slate-500'"
                    @click="switchTab('webcam')"
                >Webcam</button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-2 transition"
                    :class="tab === 'upload' ? 'bg-white text-brand-blue shadow-sm' : 'text-slate-500'"
                    @click="switchTab('upload')"
                >Upload Gambar QR</button>
            </div>

            <QrScanner :active="true" :mode="tab" @found="onFound" />

            <Link
                v-if="foundArsip?.id"
                :href="route('arsip.show', foundArsip.id)"
                class="flex items-center justify-center gap-2 rounded-xl bg-brand-blue px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
            >
                Buka Detail Arsip
                <AppIcon name="chevron-right" :size="16" :stroke-width="2" class="h-4 w-4" decorative />
            </Link>

            <Link
                v-if="foundBoks?.kode_boks"
                :href="route('scan.boks', foundBoks.kode_boks)"
                class="flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700"
            >
                Lihat Semua Dokumen di Boks
                <span class="ml-1 text-xs opacity-80">({{ foundBoks.dokumen_arsip_count ?? 0 }})</span>
                <AppIcon name="chevron-right" :size="16" :stroke-width="2" class="h-4 w-4" decorative />
            </Link>
        </div>
    </AppLayout>
</template>
