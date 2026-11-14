<script setup>
import Modal from '@/Components/Modal.vue';
import { institution } from '@/config/institution';
import { computed, ref } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    boks: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const busy = ref(false);

const qrUrl = computed(() => {
    if (!props.boks?.qr_path) {
        return null;
    }
    return `/storage/${props.boks.qr_path}`;
});

// Ekstensi file QR — konsisten untuk :download dan label tombol
const qrExt = computed(() => {
    if (!props.boks?.qr_path) return 'png';
    return props.boks.qr_path.split('.').pop().toLowerCase();
});

// Cetak label stiker boks via iframe — sama pola seperti Arsip
const printLabel = () => {
    if (!props.boks) return;

    const existing = document.getElementById('siparsip-print-frame');
    if (existing) {
        existing.remove();
    }

    const iframe = document.createElement('iframe');
    iframe.id = 'siparsip-print-frame';
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.cssText =
        'position:fixed;right:0;bottom:0;width:0;height:0;border:0;opacity:0;pointer-events:none;';
    document.body.appendChild(iframe);

    const frameDoc = iframe.contentDocument || iframe.contentWindow?.document;
    if (!frameDoc) {
        alert('Browser tidak mendukung cetak label. Coba peramban lain atau unduh stiker PNG.');
        iframe.remove();
        return;
    }

    const html = labelDocumentHtml();
    frameDoc.open();
    frameDoc.write(html);
    frameDoc.close();

    const frameWindow = iframe.contentWindow;
    let printed = false;

    const cleanup = () => {
        setTimeout(() => {
            if (iframe.parentNode) iframe.remove();
        }, 1000);
    };

    const triggerPrint = () => {
        if (printed) return;
        printed = true;
        try {
            frameWindow.focus();
            frameWindow.print();
        } catch (error) {
            console.error(error);
            alert('Gagal membuka dialog cetak. Silakan unduh stiker PNG sebagai alternatif.');
        } finally {
            cleanup();
        }
    };

    const img = frameDoc.querySelector('img');
    if (img && !img.complete) {
        img.onload = triggerPrint;
        img.onerror = triggerPrint;
        setTimeout(triggerPrint, 2000);
    } else {
        setTimeout(triggerPrint, 300);
    }
};

const labelDocumentHtml = () => {
    const b = props.boks;
    const qr = qrUrl.value
        ? `<img src="${escapeHtml(qrUrl.value)}" alt="QR ${escapeHtml(b.kode_boks)}" />`
        : `<div class="qr-missing">QR belum tersedia</div>`;

    return `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <title>Label Boks ${escapeHtml(b.kode_boks)}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
      width: 100%;
      height: 100%;
      overflow: hidden;
    }
    @page {
      size: 100mm 62mm;
      margin: 0;
    }
    body {
      font-family: "Segoe UI", Tahoma, Arial, sans-serif;
      background: #fff;
      color: #0B1F3A;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .label {
      width: 100mm;
      height: 62mm;
      max-height: 62mm;
      border: 1.2pt solid #0B1F3A;
      overflow: hidden;
      page-break-inside: avoid;
      break-inside: avoid;
      page-break-after: avoid;
      display: flex;
      flex-direction: column;
    }
    .head {
      background: #0B1F3A;
      color: #fff;
      text-align: center;
      padding: 2.2mm 2mm;
      flex-shrink: 0;
    }
    .head h1 {
      font-size: 8.5pt;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      line-height: 1.15;
    }
    .head p {
      margin-top: 0.6mm;
      font-size: 6.5pt;
      color: #cbd5e1;
      line-height: 1.15;
    }
    .body {
      display: flex;
      gap: 2.5mm;
      padding: 2.5mm;
      align-items: center;
      flex: 1;
      min-height: 0;
      overflow: hidden;
    }
    .qr {
      width: 24mm;
      height: 24mm;
      border: 1pt solid #94a3b8;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f8fafc;
      flex-shrink: 0;
    }
    .qr img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    .qr-missing {
      font-size: 6.5pt;
      color: #94a3b8;
      text-align: center;
      padding: 1.5mm;
    }
    .meta {
      flex: 1;
      min-width: 0;
      min-height: 0;
      border-left: 1pt solid #e2e8f0;
      padding-left: 2.5mm;
      overflow: hidden;
    }
    .kode {
      font-size: 10pt;
      font-weight: 700;
      line-height: 1.15;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .lokasi {
      margin-top: 0.6mm;
      font-size: 7pt;
      color: #64748b;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .kapasitas {
      margin-top: 1.2mm;
      font-size: 7.5pt;
      font-weight: 700;
      color: #475569;
    }
    .foot {
      border-top: 1pt dashed #cbd5e1;
      background: #f8fafc;
      text-align: center;
      padding: 1.4mm 2mm;
      font-size: 6.5pt;
      color: #64748b;
      flex-shrink: 0;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
  </style>
</head>
<body>
  <div class="label">
    <div class="head">
      <h1>Kelurahan Grogol Selatan</h1>
      <p>Kota Administrasi Jakarta Selatan · Tata Usaha &amp; Kearsipan</p>
    </div>
    <div class="body">
      <div class="qr">${qr}</div>
      <div class="meta">
        <div class="kode">${escapeHtml(b.kode_boks)}</div>
        <div class="lokasi">
          Rak ${escapeHtml(b.rak?.kode_rak || '—')}
          ${b.rak?.nama_rak ? ' · ' + escapeHtml(b.rak.nama_rak) : ''}
        </div>
        <div class="lokasi">
          Baris ${escapeHtml(b.nomor_baris || '—')} · Boks ${escapeHtml(b.nomor_boks || '—')}
        </div>
        <div class="kapasitas">
          ${b.dokumen_arsip_count ?? 0} / ${b.kapasitas || '—'} dokumen
        </div>
      </div>
    </div>
    <div class="foot">${institution.qrFooter} · ${institution.systemName}</div>
  </div>
</body>
</html>`;
};

const escapeHtml = (value) =>
    String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');

const loadImage = (src) =>
    new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = src;
    });

// Download stiker boks sebagai PNG — mengikuti pola Arsip
const downloadPng = async () => {
    if (!props.boks || !qrUrl.value || busy.value) return;

    busy.value = true;

    try {
        const b = props.boks;
        const scale = 2;
        const width = 560;
        const height = 340;
        const canvas = document.createElement('canvas');
        canvas.width = width * scale;
        canvas.height = height * scale;
        const ctx = canvas.getContext('2d');
        ctx.scale(scale, scale);

        // Latar
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);
        ctx.strokeStyle = '#0B1F3A';
        ctx.lineWidth = 3;
        ctx.strokeRect(1.5, 1.5, width - 3, height - 3);

        // Header navy
        ctx.fillStyle = '#0B1F3A';
        ctx.fillRect(0, 0, width, 64);
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 16px Segoe UI, Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('KELURAHAN GROGOL SELATAN', width / 2, 28);
        ctx.font = '12px Segoe UI, Arial, sans-serif';
        ctx.fillStyle = '#cbd5e1';
        ctx.fillText('Kota Administrasi Jakarta Selatan · Tata Usaha & Kearsipan', width / 2, 48);

        // QR
        const qrBox = { x: 24, y: 84, size: 160 };
        ctx.fillStyle = '#f8fafc';
        ctx.fillRect(qrBox.x, qrBox.y, qrBox.size, qrBox.size);
        ctx.strokeStyle = '#94a3b8';
        ctx.lineWidth = 1.5;
        ctx.strokeRect(qrBox.x, qrBox.y, qrBox.size, qrBox.size);

        try {
            const qrImg = await loadImage(qrUrl.value);
            ctx.drawImage(qrImg, qrBox.x + 8, qrBox.y + 8, qrBox.size - 16, qrBox.size - 16);
        } catch {
            ctx.fillStyle = '#94a3b8';
            ctx.font = '12px Segoe UI, Arial, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('QR gagal dimuat', qrBox.x + qrBox.size / 2, qrBox.y + qrBox.size / 2);
        }

        // Meta
        const textX = 210;
        ctx.textAlign = 'left';
        ctx.fillStyle = '#0B1F3A';
        ctx.font = 'bold 22px Segoe UI, Arial, sans-serif';
        ctx.fillText(b.kode_boks, textX, 110);

        ctx.fillStyle = '#64748b';
        ctx.font = '13px Segoe UI, Arial, sans-serif';
        const lokasiTeks = `Rak ${b.rak?.kode_rak || '—'}`;
        ctx.fillText(lokasiTeks, textX, 138);

        const barisBoksTeks = `Baris: ${b.nomor_baris || '—'}  Boks: ${b.nomor_boks || '—'}`;
        ctx.fillText(barisBoksTeks, textX, 160);

        const isiCount = b.dokumen_arsip_count ?? 0;
        const capVal = b.kapasitas || '—';
        ctx.font = 'bold 13px Segoe UI, Arial, sans-serif';
        ctx.fillText(`${isiCount} / ${capVal} dokumen`, textX, 188);

        // Footer
        ctx.fillStyle = '#f8fafc';
        ctx.fillRect(0, height - 42, width, 42);
        ctx.strokeStyle = '#cbd5e1';
        ctx.beginPath();
        ctx.setLineDash([4, 4]);
        ctx.moveTo(0, height - 42);
        ctx.lineTo(width, height - 42);
        ctx.stroke();
        ctx.setLineDash([]);
        ctx.fillStyle = '#64748b';
        ctx.font = '12px Segoe UI, Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(`${institution.qrFooter} · ${institution.systemName}`, width / 2, height - 18);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        if (!blob) throw new Error('Gagal membuat PNG');

        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${b.kode_boks}-label-stiker.png`;
        link.click();
        URL.revokeObjectURL(url);
    } catch (error) {
        console.error(error);
        alert('Gagal mengunduh label stiker. Coba muat ulang halaman.');
    } finally {
        busy.value = false;
    }
};

const cap = computed(() => props.boks?.kapasitas ?? 0);
const isi = computed(() => props.boks?.dokumen_arsip_count ?? 0);
const pct = computed(() => cap.value > 0 ? Math.min(100, Math.round((isi.value / cap.value) * 100)) : 0);
const barColor = computed(() => pct.value >= 100 ? 'bg-rose-500' : pct.value >= 80 ? 'bg-amber-500' : 'bg-emerald-500');
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div v-if="boks">
            <div class="relative overflow-hidden border-b border-slate-200 bg-gradient-to-r from-brand-navy to-[#16355c] px-6 py-5 text-white">
                <div class="pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full bg-white/5" />
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <img
                            :src="institution.logoPath"
                            :alt="`Logo ${institution.name}`"
                            class="h-11 w-11 shrink-0 rounded-full bg-white object-contain p-0.5 shadow-sm"
                        />
                        <div>
                            <p class="text-xs font-medium text-sky-200/90">
                                {{ institution.systemName }}
                            </p>
                            <h2 class="mt-0.5 text-lg font-bold tracking-tight">
                                QR Label - {{ boks.kode_boks }}
                            </h2>
                            <p class="mt-0.5 text-xs text-slate-300">
                                Download gambar atau cetak label QR boks
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/30"
                        aria-label="Tutup"
                        @click="emit('close')"
                    >
                        <AppIcon name="close" :size="20" :stroke-width="2" class="h-5 w-5" decorative />
                    </button>
                </div>
            </div>

            <div class="bg-[#EEF2F7] px-6 py-6">
                <p class="mb-3 text-center text-[11px] font-medium uppercase tracking-wider text-slate-500">
                    QR Code Preview
                </p>

                <div class="mx-auto flex max-w-md flex-col items-center gap-4">
                    <!-- QR Code Image -->
                    <div class="rounded-2xl border border-slate-300 bg-white p-4 shadow-md shadow-slate-200/80">
                        <img
                            v-if="qrUrl"
                            :src="qrUrl"
                            :alt="`QR Code ${boks.kode_boks}`"
                            class="h-48 w-48 object-contain"
                        />
                        <div v-else class="flex h-48 w-48 items-center justify-center text-sm text-slate-400">
                            QR belum tersedia
                        </div>
                    </div>

                    <!-- Info Box -->
                    <div class="w-full rounded-xl border border-slate-200 bg-white p-4">
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Kode Boks</span>
                                <span class="font-semibold text-slate-900">{{ boks.kode_boks }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Rak</span>
                                <span class="font-semibold text-slate-900">{{ boks.rak?.kode_rak || '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">No. Baris</span>
                                <span class="font-semibold text-slate-900">{{ boks.nomor_baris || '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">No. Boks</span>
                                <span class="font-semibold text-slate-900">{{ boks.nomor_boks || '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Kapasitas</span>
                                <span class="font-semibold text-slate-900">{{ isi }} / {{ cap || '—' }}</span>
                            </div>
                            <div class="w-full">
                                <div class="mb-1 flex justify-between text-xs">
                                    <span class="text-slate-500">Terisi</span>
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

                    <!-- Footer -->
                    <div class="w-full rounded-xl border-t border-dashed border-slate-200 bg-slate-50 px-4 py-2 text-center">
                        <p class="text-[10px] font-medium text-slate-500">
                            {{ institution.qrFooter }} · {{ institution.systemName }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-200 bg-white px-6 py-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs leading-relaxed text-slate-500">
                        Download gambar atau cetak label QR code.
                    </p>
                    <div class="flex flex-wrap items-center justify-end gap-2.5">
                        <a
                            v-if="qrUrl"
                            :href="qrUrl"
                            :download="`qr-boks-${boks.kode_boks}.${qrExt}`"
                            class="group inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-brand-navy shadow-sm transition hover:border-brand-navy/30 hover:bg-slate-50"
                        >
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-brand-navy transition group-hover:bg-brand-navy group-hover:text-white">
                                <AppIcon name="download" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
                            </span>
                            <span class="text-left leading-tight">
                                <span class="block text-sm">Unduh {{ qrExt.toUpperCase() }}</span>
                                <span class="block text-[10px] font-medium text-slate-400">Siap tempel</span>
                            </span>
                        </a>
                        <button
                            type="button"
                            class="group inline-flex items-center gap-2 rounded-xl bg-brand-navy px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-brand-navy/25 transition hover:bg-[#122a4a]"
                            @click="printLabel"
                        >
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 transition group-hover:bg-white/25">
                                <AppIcon name="print" :size="16" :stroke-width="1.8" class="h-4 w-4" decorative />
                            </span>
                            <span class="text-left leading-tight">
                                <span class="block text-sm">Cetak</span>
                                <span class="block text-[10px] font-medium text-sky-200/80">1 halaman label</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>
