<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_section_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot_number');
            $table->string('day');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('raw');
            $table->timestamps();

            $table->index(['course_section_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_schedules');
    }
};
