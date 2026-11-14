<?php

namespace App\Http\Controllers;

use App\Models\BoksArsip;
use Illuminate\Http\JsonResponse;

class BoksScanController extends Controller
{
    /**
     * Cari boks berdasarkan kode_boks dari hasil scan QR (POST API).
     * Mirip ArsipController::scanByKode() — hanya admin yang bisa scan.
     */
    public function __invoke(string $kodeBoks): JsonResponse
    {
        $boks = BoksArsip::query()
            ->where('kode_boks', $kodeBoks)
            ->where('is_active', true)
            ->first();

        if (! $boks) {
            return response()->json(
                ['message' => 'Kode boks tidak ditemukan'],
                404,
            );
        }

        $boks->load(['rak:id,kode_rak,nama_rak']);
        $boks->loadCount('dokumenArsip');

        return response()->json($boks);
    }
}
