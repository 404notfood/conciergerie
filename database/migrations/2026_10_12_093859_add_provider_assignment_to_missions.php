<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('missions', function (Blueprint $table) { $table->string('assigned_company')->nullable(); }); }
    public function down(): void { Schema::table('missions', function (Blueprint $table) { $table->dropColumn('assigned_company'); }); }
};
