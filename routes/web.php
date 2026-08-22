<?php

use App\Http\Controllers\AiChatController;
use App\Http\Controllers\Friends\FriendshipController;
use App\Http\Controllers\Krs\Admin\CourseOfferingController as AdminCourseOfferingController;
use App\Http\Controllers\Krs\CourseOfferingController;
use App\Http\Controllers\Krs\KrsExportController;
use App\Http\Controllers\Krs\KrsPlanCompareController;
use App\Http\Controllers\Krs\KrsPlanController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('krs')->name('krs.')->group(function () {
        Route::get('/', [CourseOfferingController::class, 'index'])->name('index');
        Route::get('/offerings/{offering}', [CourseOfferingController::class, 'show'])->name('offerings.show');
        Route::post('/offerings/{offering}/plans', [KrsPlanController::class, 'store'])->name('plans.store');
        Route::get('/planner/{offering}', [KrsPlanController::class, 'latest'])->name('planner.latest');
        Route::get('/planner/{offering}/{plan}', [KrsPlanController::class, 'planner'])->name('planner');
        Route::patch('/plans/{plan}', [KrsPlanController::class, 'update'])->name('plans.update');
        Route::delete('/plans/{plan}', [KrsPlanController::class, 'destroy'])->name('plans.destroy');
        Route::post('/plans/{plan}/items', [KrsPlanController::class, 'toggleItem'])->name('plans.items.toggle');
        Route::post('/plans/{plan}/copy', [KrsPlanController::class, 'copyFrom'])->name('plans.copy');
        Route::get('/compare', KrsPlanCompareController::class)->name('plans.compare');
        Route::get('/plans/{plan}/export/pdf', [KrsExportController::class, 'pdf'])->name('plans.export.pdf');

        Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
            Route::get('/offerings', [AdminCourseOfferingController::class, 'index'])->name('offerings.index');
            Route::post('/offerings/import', [AdminCourseOfferingController::class, 'store'])->name('offerings.store');
            Route::get('/offerings/{offering}', [AdminCourseOfferingController::class, 'show'])->name('offerings.show');
            Route::post('/offerings/{offering}/sync/preview', [AdminCourseOfferingController::class, 'previewSync'])->name('offerings.sync.preview');
            Route::post('/offerings/{offering}/sync', [AdminCourseOfferingController::class, 'sync'])->name('offerings.sync');
            Route::post('/offerings/{offering}/publish', [AdminCourseOfferingController::class, 'publish'])->name('offerings.publish');
            Route::post('/offerings/{offering}/unpublish', [AdminCourseOfferingController::class, 'unpublish'])->name('offerings.unpublish');
            Route::delete('/offerings/{offering}', [AdminCourseOfferingController::class, 'destroy'])->name('offerings.destroy');
        });
    });

    Route::prefix('friends')->name('friends.')->group(function () {
        Route::get('/', [FriendshipController::class, 'index'])->name('index');
        Route::post('/', [FriendshipController::class, 'store'])->name('store');
        Route::post('/{friendship}/accept', [FriendshipController::class, 'accept'])->name('accept');
        Route::post('/{friendship}/decline', [FriendshipController::class, 'decline'])->name('decline');
        Route::delete('/{friendship}', [FriendshipController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('ai')->name('ai.')->group(function () {
        Route::post('/chat', [AiChatController::class, 'send'])->name('chat.send');
        Route::get('/conversations', [AiChatController::class, 'index'])->name('conversations.index');
        Route::delete('/conversations/{conversation}', [AiChatController::class, 'destroy'])->name('conversations.destroy');
    });
});

require __DIR__.'/settings.php';
