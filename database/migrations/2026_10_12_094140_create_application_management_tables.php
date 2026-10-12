<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) { $table->boolean('is_admin')->default(false); $table->timestamp('disabled_at')->nullable(); });
        Schema::table('photos', function (Blueprint $table) { $table->unsignedBigInteger('size_bytes')->default(0); });
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->unsignedInteger('monthly_price_cents'); $table->json('limits'); $table->json('features'); $table->boolean('is_active')->default(true); $table->boolean('is_public')->default(false); $table->text('description')->nullable(); $table->timestamps();
        });
        Schema::create('plan_addons', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->unsignedInteger('monthly_price_cents'); $table->json('limit_bonus'); $table->json('features'); $table->boolean('is_active')->default(true); $table->text('description')->nullable(); $table->timestamps();
        });
        Schema::create('concierge_subscriptions', function (Blueprint $table) {
            $table->id(); $table->foreignId('concierge_id')->unique()->constrained(); $table->foreignId('subscription_plan_id')->constrained(); $table->string('status')->default('trialing'); $table->json('terms'); $table->json('limit_overrides')->nullable(); $table->json('feature_overrides')->nullable(); $table->unsignedInteger('price_override_cents')->nullable(); $table->date('starts_on'); $table->timestamp('expires_at')->nullable(); $table->text('notes')->nullable(); $table->timestamps();
        });
        Schema::create('application_settings', function (Blueprint $table) {
            $table->id(); $table->foreignId('default_plan_id')->constrained('subscription_plans'); $table->unsignedInteger('trial_days')->default(14); $table->boolean('registration_open')->default(true); $table->timestamps();
        });
        Schema::create('administration_events', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained(); $table->foreignId('concierge_id')->nullable()->constrained(); $table->string('description'); $table->json('details')->nullable(); $table->timestamps();
        });
    }
    public function down(): void {
        foreach (['administration_events','application_settings','concierge_subscriptions','plan_addons','subscription_plans'] as $table) { Schema::dropIfExists($table); }
        Schema::table('photos', function (Blueprint $table) { $table->dropColumn('size_bytes'); });
        Schema::table('users', function (Blueprint $table) { $table->dropColumn(['is_admin','disabled_at']); });
    }
};
