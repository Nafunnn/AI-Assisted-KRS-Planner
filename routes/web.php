<?php

use App\Http\Controllers\AiChatController;
use App\Http\Controllers\Krs\CourseOfferingController;
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
    });

    Route::prefix('ai')->name('ai.')->group(function () {
        Route::post('/chat', [AiChatController::class, 'send'])->name('chat.send');
        Route::get('/conversations', [AiChatController::class, 'index'])->name('conversations.index');
        Route::delete('/conversations/{conversation}', [AiChatController::class, 'destroy'])->name('conversations.destroy');
    });
});

require __DIR__.'/settings.php';
