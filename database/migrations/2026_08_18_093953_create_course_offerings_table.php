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
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('term');
            $table->string('source_filename');
            $table->unsignedInteger('catalog_version')->default(1);
            $table->timestamp('imported_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('published_at');
            $table->index('term');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offerings');
    }
};
