/**
 * Susun teks posisi fisik arsip dari data terstruktur:
 * Rak (nama/kode) · Boks (kode/nomor_baris/nomor_boks) · keterangan_posisi / catatan.
 * Return null bila tidak ada satupun bagian — pemakai menampilkan fallback sendiri.
 */
export function posisiLokasi(arsip) {
    const rak = arsip?.rak?.nama_rak || arsip?.rak?.kode_rak || '';
    const boks = arsip?.boks ? [arsip.boks.kode_boks, arsip.boks.nomor_baris, arsip.boks.nomor_boks].filter(Boolean).join(' / ') : null;
    const ekstra = [arsip?.keterangan_posisi, arsip?.catatan].filter(Boolean).join(' · ');
    const bagian = [rak, boks, ekstra].filter(Boolean);

    return bagian.length ? bagian.join(' · ') : null;
}
