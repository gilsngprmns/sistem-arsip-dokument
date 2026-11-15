<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumen_arsip', function (Blueprint $table) {
            $table->boolean('is_encrypted')->default(false)->after('file_path');
            $table->string('file_hash', 64)->nullable()->after('is_encrypted');
            $table->timestamp('encrypted_at')->nullable()->after('file_hash');
        });
    }

    public function down(): void
    {
        Schema::table('dokumen_arsip', function (Blueprint $table) {
            $table->dropColumn(['is_encrypted', 'file_hash', 'encrypted_at']);
        });
    }
};
