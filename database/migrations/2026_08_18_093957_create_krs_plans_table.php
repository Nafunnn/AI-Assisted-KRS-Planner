<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('krs_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_offering_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->boolean('is_shared_with_friends')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'course_offering_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('krs_plans');
    }
};
