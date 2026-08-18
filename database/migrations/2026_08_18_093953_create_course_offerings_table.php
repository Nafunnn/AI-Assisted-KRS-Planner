<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('source_filename');
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->index(['user_id', 'imported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offerings');
    }
};
