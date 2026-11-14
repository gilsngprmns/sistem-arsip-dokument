<script setup>
import axios from 'axios';
import { Html5Qrcode } from 'html5-qrcode';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    active: {
        type: Boolean,
        default: false,
    },
    mode: {
        type: String,
        default: 'webcam',
    },
});

const emit = defineEmits(['found']);

// Tipe hasil scan: 'arsip' | 'boks' | null
const foundResult = ref(null);
const foundType = ref(null);

const scannerId = 'qr-reader-region';
const statusMessage = ref('');
const statusType = ref('info');
const foundArsip = ref(null);
const isScanning = ref(false);
const fileInput = ref(null);

let html5QrCode = null;
let lastScannedCode = null;
let lastScannedAt = 0;

const setStatus = (message, type = 'info') => {
    statusMessage.value = message;
    statusType.value = type;
};

// QR boks berisi URL /scan/boks/{kode_boks}. Saat menemukan URL scan,
// lakukan POST ke API baru /api/boks/scan/{kode_boks}.
const lookupKode = async (decodedText) => {
    const raw = String(decodedText || '').trim();

    if (!raw) {
        return;
    }

    const now = Date.now();
    if (raw === lastScannedCode && now - lastScannedAt < 2500) {
        return;
    }

    lastScannedCode = raw;
    lastScannedAt = now;

    // Coba ekstrak kode boks dari URL scan boks: /scan/boks/KODE atau https://.../scan/boks/KODE
    const boksMatch = raw.match(/\/scan\/boks\/([^/?]+)/);

    if (boksMatch) {
        const kodeBoks = boksMatch[1];
        setStatus(`Mencari boks: ${kodeBoks}...`, 'info');
        try {
            const { data } = await axios.post(`/api/boks/scan/${encodeURIComponent(kodeBoks)}`, {});
            foundType.value = 'boks';
            foundArsip.value = null;
            foundResult.value = data;
            setStatus('Boks ditemukan.', 'success');
            emit('found', data);
        } catch (error) {
            foundType.value = null;
            foundResult.value = null;
            foundArsip.value = null;
            const message = error?.response?.data?.message || 'Kode boks tidak ditemukan';
            setStatus(message, 'error');
        }
        return;
    }

    // Fallback: anggap kode langsung = kode arsip
    setStatus(`Mencari arsip: ${raw}...`, 'info');
    try {
        const { data } = await axios.post('/api/arsip/scan', { kode_arsip: raw });
        foundType.value = 'arsip';
        foundResult.value = null;
        foundArsip.value = data;
        setStatus('Arsip ditemukan.', 'success');
        emit('found', data);
    } catch (error) {
        foundType.value = null;
        foundResult.value = null;
        foundArsip.value = null;
        const message = error?.response?.data?.message || 'Kode arsip tidak ditemukan';
        setStatus(message, 'error');
    }
};

const stopScanner = async () => {
    if (!html5QrCode) {
        isScanning.value = false;
        return;
    }

    try {
        if (html5QrCode.isScanning) {
            await html5QrCode.stop();
        }
        await html5QrCode.clear();
    } catch {
        // Kamera mungkin sudah berhenti.
    } finally {
        html5QrCode = null;
        isScanning.value = false;
    }
};

const getResponsiveQrbox = () => {
    const maxWidth = Math.min(220, (typeof window !== 'undefined' ? window.innerWidth : 375) - 40);
    return { width: maxWidth, height: maxWidth };
};

const startWebcam = async () => {
    await stopScanner();
    await nextTick();

    html5QrCode = new Html5Qrcode(scannerId);
    isScanning.value = true;
    setStatus('Arahkan kamera ke QR stiker pada berkas fisik.', 'info');
    foundArsip.value = null;

    try {
        await html5QrCode.start(
            { facingMode: 'environment' },
            {
                fps: 10,
                qrbox: getResponsiveQrbox(),
                aspectRatio: 1.777,
            },
            (decodedText) => {
                lookupKode(decodedText);
            },
            () => {
                // ignore frame miss
            },
        );
    } catch (error) {
        isScanning.value = false;
        setStatus(
            error?.message?.includes('Permission')
                ? 'Izin kamera ditolak. Izinkan kamera di browser atau gunakan tab Upload Gambar QR.'
                : 'Kamera tidak tersedia. Gunakan tab Upload Gambar QR.',
            'error',
        );
    }
};

const onFileSelected = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;

    await stopScanner();
    await nextTick();

    html5QrCode = new Html5Qrcode(scannerId);
    setStatus('Memindai gambar...', 'info');
    foundArsip.value = null;

    try {
        const decodedText = await html5QrCode.scanFile(file, true);
        await lookupKode(decodedText);
    } catch {
        setStatus('Tidak ada QR terbaca pada gambar. Coba foto yang lebih jelas.', 'error');
    } finally {
        event.target.value = '';
    }
};

watch(
    () => [props.active, props.mode],
    async ([active, mode]) => {
        if (!active) {
            await stopScanner();
            statusMessage.value = '';
            foundArsip.value = null;
            return;
        }

        if (mode === 'webcam') {
            await startWebcam();
            return;
        }

        await stopScanner();
        setStatus('Pilih file gambar QR dari komputer.', 'info');
        foundArsip.value = null;
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    stopScanner();
});
</script>

<template>
    <div class="space-y-4">
        <div
            :id="scannerId"
            class="overflow-hidden rounded-xl bg-slate-900"
            :class="mode === 'webcam' && active ? 'min-h-[260px] w-full' : 'hidden'"
        />

        <div
            v-if="mode === 'upload'"
            class="flex min-h-[220px] flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 text-center"
        >
            <AppIcon name="image" :size="40" :stroke-width="1.5" class="mb-3 h-10 w-10 text-slate-400" decorative />
            <p class="text-sm font-medium text-slate-600">Pilih file gambar QR dari komputer</p>
            <input
                ref="fileInput"
                type="file"
                accept="image/*"
                class="mt-4 block w-full max-w-xs text-sm text-slate-500 file:mr-3 file:rounded-md file:border-0 file:bg-brand-blue file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white"
                @change="onFileSelected"
            />
        </div>

        <p
            v-if="statusMessage"
            class="rounded-lg px-3 py-2 text-center text-sm"
            :class="{
                'bg-slate-100 text-slate-600': statusType === 'info',
                'bg-emerald-50 text-emerald-700': statusType === 'success',
                'bg-rose-50 text-rose-700': statusType === 'error',
            }"
        >
            {{ statusMessage }}
        </p>

        <!-- Hasil scan arsip -->
        <div
            v-if="foundArsip"
            class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950"
        >
            <p class="font-bold text-emerald-800">{{ foundArsip.kode_arsip }}</p>
            <p class="mt-1">{{ foundArsip.nomor_surat }}</p>
            <p class="mt-1 text-emerald-800/80">{{ foundArsip.perihal }}</p>
            <p class="mt-2 capitalize">
                Status:
                <span class="font-semibold">{{ foundArsip.status_arsip }}</span>
                <span v-if="foundArsip.seksi"> • {{ foundArsip.seksi.nama_seksi }}</span>
            </p>
        </div>

        <!-- Hasil scan boks -->
        <div
            v-if="foundResult"
            class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950"
        >
            <p class="font-bold text-emerald-800">{{ foundResult.kode_boks }}</p>
            <p class="mt-1 text-emerald-800/80">
                Rak: {{ foundResult.rak?.kode_rak || '—' }}
                <span v-if="foundResult.rak?.nama_rak"> ({{ foundResult.rak.nama_rak }})</span>
            </p>
            <p class="mt-1 text-emerald-800/80">
                No. Baris: {{ foundResult.nomor_baris || '—' }} · No. Boks: {{ foundResult.nomor_boks || '—' }}
            </p>
            <p class="mt-2">
                <span class="font-semibold">Kapasitas:</span>
                {{ foundResult.dokumen_arsip_count ?? 0 }} / {{ foundResult.kapasitas || '—' }} dokumen
            </p>
        </div>
    </div>
</template>
