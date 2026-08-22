<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('krs_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('krs_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_section_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('active');
            $table->string('schedule_fingerprint')->nullable();
            $table->timestamps();

            $table->unique(['krs_plan_id', 'course_section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('krs_plan_items');
    }
};
