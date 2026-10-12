<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name', 120)->nullable();
            $table->string('public_url')->nullable();
            $table->boolean('indexing_enabled')->default(false);
            $table->boolean('allow_ai_search')->default(true);
            $table->boolean('allow_ai_training')->default(false);
            $table->string('google_verification')->nullable();
            $table->string('bing_verification')->nullable();
            $table->string('social_image')->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamps();
        });
        Schema::create('seo_pages', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->string('kind')->default('content');
            $table->string('title', 180);
            $table->string('description', 500);
            $table->string('heading', 200);
            $table->text('intro');
            $table->json('sections');
            $table->json('faq');
            $table->string('status')->default('draft');
            $table->boolean('noindex')->default(false);
            $table->string('keyword', 200)->nullable();
            $table->string('social_title', 180)->nullable();
            $table->string('social_description', 500)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_pages');
        Schema::dropIfExists('seo_settings');
    }
};
