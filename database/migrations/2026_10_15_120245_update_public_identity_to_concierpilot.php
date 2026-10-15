<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('seo_settings')->where('id', 1)->update([
            'site_name' => 'ConcierPilot',
            'public_url' => 'https://concierpilot.fr',
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The previous public identity is environment-specific and cannot be reconstructed.
    }
};
