<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique(); // For subdomain or URL segment
            $table->string('domain')->nullable()->unique(); // Custom domain
            $table->json('branding_config')->nullable(); // Logo, colors
            $table->string('timezone')->default('UTC');
            $table->string('locale')->default('es');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
