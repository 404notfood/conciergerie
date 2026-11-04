<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concierge_id')->constrained();
            $table->unsignedBigInteger('property_id');
            $table->string('name', 120);
            $table->string('source', 20);
            $table->text('url');
            $table->string('url_hash', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->unsignedInteger('event_count')->default(0);
            $table->timestamps();
            $table->unique(['concierge_id', 'id']);
            $table->unique(['concierge_id', 'property_id', 'url_hash'], 'calendar_feeds_property_url_unique');
            $table->index(['is_active', 'last_attempt_at']);
            $table->foreign(['concierge_id', 'property_id'])->references(['concierge_id', 'id'])->on('properties');
        });
        Schema::table('stays', function (Blueprint $table) {
            $table->unsignedBigInteger('calendar_feed_id')->nullable();
            $table->foreign(['concierge_id', 'calendar_feed_id'])->references(['concierge_id', 'id'])->on('calendar_feeds');
            $table->index('calendar_feed_id');
        });
    }

    public function down(): void
    {
        Schema::table('stays', function (Blueprint $table) {
            $table->dropForeign(['concierge_id', 'calendar_feed_id']);
            $table->dropIndex(['calendar_feed_id']);
            $table->dropColumn('calendar_feed_id');
        });
        Schema::dropIfExists('calendar_feeds');
    }
};
