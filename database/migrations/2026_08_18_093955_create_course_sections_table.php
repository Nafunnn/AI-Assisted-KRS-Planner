<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('group_code');
            $table->string('time_period', 2);
            $table->timestamps();

            $table->unique(['course_id', 'group_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_sections');
    }
};
