<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Settings for simple key-value pairs (Hero Title, Subtitle, etc.)
        Schema::create('landing_page_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Leadership structure
        Schema::create('cms_leaders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title'); // Jabatan
            $table->string('initial')->nullable(); // For avatar if no image
            $table->string('image_path')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // Partners logos
        Schema::create('cms_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        // Hero carousel images
        Schema::create('cms_hero_images', function (Blueprint $table) {
            $table->id();
            $table->string('image_path');
            $table->string('title')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_hero_images');
        Schema::dropIfExists('cms_partners');
        Schema::dropIfExists('cms_leaders');
        Schema::dropIfExists('landing_page_settings');
    }
};
