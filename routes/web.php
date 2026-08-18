<?php

use App\Http\Controllers\Krs\CourseOfferingController;
use App\Http\Controllers\Krs\KrsAiController;
use App\Http\Controllers\Krs\KrsExportController;
use App\Http\Controllers\Krs\KrsPlanController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('krs')->name('krs.')->group(function () {
        Route::get('/', [CourseOfferingController::class, 'index'])->name('index');
        Route::post('/offerings/import', [CourseOfferingController::class, 'store'])->name('offerings.store');
        Route::get('/offerings/{offering}', [CourseOfferingController::class, 'show'])->name('offerings.show');
        Route::delete('/offerings/{offering}', [CourseOfferingController::class, 'destroy'])->name('offerings.destroy');
        Route::post('/offerings/{offering}/plans', [KrsPlanController::class, 'store'])->name('plans.store');
        Route::get('/planner/{offering}', [KrsPlanController::class, 'latest'])->name('planner.latest');
        Route::get('/planner/{offering}/{plan}', [KrsPlanController::class, 'planner'])->name('planner');
        Route::patch('/plans/{plan}', [KrsPlanController::class, 'update'])->name('plans.update');
        Route::delete('/plans/{plan}', [KrsPlanController::class, 'destroy'])->name('plans.destroy');
        Route::post('/plans/{plan}/items', [KrsPlanController::class, 'toggleItem'])->name('plans.items.toggle');
        Route::get('/plans/{plan}/export/pdf', [KrsExportController::class, 'pdf'])->name('plans.export.pdf');
        Route::post('/plans/{plan}/ai/auto-schedule', [KrsAiController::class, 'autoSchedule'])->name('plans.ai.auto-schedule');
        Route::post('/plans/{plan}/ai/review', [KrsAiController::class, 'review'])->name('plans.ai.review');
    });
});

require __DIR__.'/settings.php';
