<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concierge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->string('title');
            $table->text('body');
            $table->string('url');
            $table->string('dedupe_key', 64);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['concierge_id', 'user_id', 'dedupe_key']);
            $table->index(['concierge_id', 'user_id', 'read_at']);
        });
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concierge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('email_events');
            $table->json('browser_events');
            $table->timestamps();
            $table->unique(['concierge_id', 'user_id']);
        });
        Schema::create('mission_recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concierge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained();
            $table->foreignId('template_id')->constrained('service_templates');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->string('kind')->default('service');
            $table->string('frequency');
            $table->unsignedSmallInteger('interval')->default(1);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_on');
            $table->string('time', 5);
            $table->unsignedSmallInteger('duration')->default(60);
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'next_on']);
        });
        Schema::create('recurrence_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concierge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mission_recurrence_id')->constrained()->cascadeOnDelete();
            $table->date('scheduled_on');
            $table->string('status')->default('pending');
            $table->json('snapshot');
            $table->foreignId('mission_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['mission_recurrence_id', 'scheduled_on']);
            $table->index(['concierge_id', 'status', 'scheduled_on']);
        });
        Schema::create('staff_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concierge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['concierge_id', 'user_id', 'starts_at']);
        });
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('priority')->default('normal');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('repair_mission_id')->nullable()->constrained('missions')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->json('resolution_photo_ids')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('shared_status')->nullable();
            $table->unsignedInteger('version')->default(1);
        });
        DB::table('incidents')->whereNotNull('shared_at')->update(['shared_status' => DB::raw('status')]);
        Schema::create('incident_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concierge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('action');
            $table->text('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_events');
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropConstrainedForeignId('repair_mission_id');
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn(['priority', 'resolution_notes', 'resolution_photo_ids', 'resolved_at', 'shared_status', 'version']);
        });
        foreach (['staff_availabilities', 'recurrence_occurrences', 'mission_recurrences', 'notification_preferences', 'business_notifications'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
