<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedTinyInteger('sks');
            $table->string('class_type', 1);
            $table->timestamps();

            $table->unique(['course_offering_id', 'code', 'class_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
