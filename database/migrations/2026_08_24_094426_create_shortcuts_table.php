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
        Schema::create('shortcuts', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('url');
            $table->string('description')->nullable();
            $table->string('category')->nullable();
            $table->boolean('opens_in_new_tab')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shortcuts');
    }
};
