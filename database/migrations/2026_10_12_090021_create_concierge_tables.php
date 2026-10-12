<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concierges', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('concierge_id')->constrained();
            $t->string('name');
            $t->string('email');
            $t->string('phone')->nullable();
            $t->string('kind')->default('individual');
            $t->timestamps();
            $t->unique(['concierge_id', 'id']);
            $t->unique(['concierge_id', 'email']);
        });
        Schema::create('memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('concierge_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('role');
            $t->unsignedBigInteger('client_id')->nullable();
            $t->string('team')->nullable();
            $t->string('company')->nullable();
            $t->timestamps();
            $t->unique(['concierge_id', 'user_id']);
            $t->foreign(['concierge_id', 'client_id'])->references(['concierge_id', 'id'])->on('clients');
        });
        Schema::create('properties', function (Blueprint $t) {
            $t->id();
            $t->foreignId('concierge_id')->constrained();
            $t->unsignedBigInteger('client_id');
            $t->string('name');
            $t->string('address');
            $t->string('kind');
            $t->text('instructions')->nullable();
            $t->text('access_code')->nullable();
            $t->boolean('offline_access')->default(false);
            $t->timestamps();
            $t->unique(['concierge_id', 'id']);
            $t->foreign(['concierge_id', 'client_id'])->references(['concierge_id', 'id'])->on('clients');
        });
        Schema::create('service_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('concierge_id')->constrained();
            $t->string('name');
            $t->json('checklist');
            $t->timestamps();
            $t->unique(['concierge_id', 'id']);
        });
        Schema::create('missions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('concierge_id')->constrained();
            $t->unsignedBigInteger('property_id');
            $t->unsignedBigInteger('assigned_user_id')->nullable();
            $t->string('title');
            $t->string('kind')->default('service');
            $t->dateTime('scheduled_at');
            $t->unsignedInteger('duration')->default(60);
            $t->string('status')->default('planned');
            $t->unsignedInteger('version')->default(1);
            $t->json('checklist');
            $t->text('instructions')->nullable();
            $t->timestamps();
            $t->unique(['concierge_id', 'id']);
            $t->index(['concierge_id', 'scheduled_at']);
            $t->foreign(['concierge_id', 'property_id'])->references(['concierge_id', 'id'])->on('properties');
            $t->foreign(['concierge_id', 'assigned_user_id'])->references(['concierge_id', 'user_id'])->on('memberships');
        });
        Schema::create('reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('concierge_id')->constrained();
            $t->unsignedBigInteger('mission_id');
            $t->foreignId('author_id')->constrained('users');
            $t->uuid('operation_id');
            $t->text('notes');
            $t->json('answers');
            $t->string('status')->default('submitted');
            $t->foreignId('reviewer_id')->nullable()->constrained('users');
            $t->text('feedback')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['concierge_id', 'id']);
            $t->unique(['concierge_id', 'author_id', 'operation_id']);
            $t->foreign(['concierge_id', 'mission_id'])->references(['concierge_id', 'id'])->on('missions');
        });
        Schema::create('photos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('concierge_id')->constrained();
            $t->unsignedBigInteger('report_id');
            $t->foreignId('author_id')->constrained('users');
            $t->string('path');
            $t->string('stage');
            $t->timestamp('captured_at')->nullable();
            $t->timestamps();
            $t->unique(['concierge_id', 'id']);
            $t->foreign(['concierge_id', 'report_id'])->references(['concierge_id', 'id'])->on('reports');
        });
        Schema::create('incidents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('concierge_id')->constrained();
            $t->unsignedBigInteger('mission_id');
            $t->foreignId('author_id')->constrained('users');
            $t->uuid('operation_id');
            $t->text('description');
            $t->string('status')->default('open');
            $t->text('client_message')->nullable();
            $t->json('shared_photo_ids')->nullable();
            $t->foreignId('shared_by')->nullable()->constrained('users');
            $t->timestamp('shared_at')->nullable();
            $t->timestamps();
            $t->unique(['concierge_id', 'author_id', 'operation_id']);
            $t->foreign(['concierge_id', 'mission_id'])->references(['concierge_id', 'id'])->on('missions');
        });
        Schema::create('stays', function (Blueprint $t) {
            $t->id();
            $t->foreignId('concierge_id')->constrained();
            $t->unsignedBigInteger('property_id');
            $t->string('title');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->string('source')->default('manual');
            $t->string('external_uid')->nullable();
            $t->string('kind')->default('stay');
            $t->timestamps();
            $t->unique(['concierge_id', 'property_id', 'source', 'external_uid']);
            $t->foreign(['concierge_id', 'property_id'])->references(['concierge_id', 'id'])->on('properties');
        });
        Schema::create('intervention_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('concierge_id')->constrained();
            $t->unsignedBigInteger('property_id');
            $t->foreignId('author_id')->constrained('users');
            $t->string('title');
            $t->text('description');
            $t->string('status')->default('pending');
            $t->unsignedBigInteger('mission_id')->nullable();
            $t->text('decision')->nullable();
            $t->timestamps();
            $t->foreign(['concierge_id', 'property_id'])->references(['concierge_id', 'id'])->on('properties');
            $t->foreign(['concierge_id', 'mission_id'])->references(['concierge_id', 'id'])->on('missions');
        });
        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('concierge_id')->constrained();
            $t->unsignedBigInteger('client_id');
            $t->foreignId('author_id')->constrained('users');
            $t->text('body');
            $t->timestamps();
            $t->foreign(['concierge_id', 'client_id'])->references(['concierge_id', 'id'])->on('clients');
        });
        Schema::create('activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('concierge_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('description');
            $t->unsignedBigInteger('mission_id')->nullable();
            $t->timestamps();
            $t->foreign(['concierge_id', 'mission_id'])->references(['concierge_id', 'id'])->on('missions');
        });
    }

    public function down(): void
    {
        foreach (['activities', 'messages', 'intervention_requests', 'stays', 'incidents', 'photos', 'reports', 'missions', 'service_templates', 'properties', 'memberships', 'clients', 'concierges'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
