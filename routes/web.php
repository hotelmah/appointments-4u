<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BlockedPeriodController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


Route::prefix('booking')->group(function () {
    Route::get('/', [BookingController::class, 'index']);
    Route::get('/{id}', [BookingController::class, 'show']);
    Route::post('/', [BookingController::class, 'store']);
    Route::put('/{id}', [BookingController::class, 'update']);
    Route::delete('/{id}', [BookingController::class, 'destroy']);

    // Search routes
    Route::get('/search', [BookingController::class, 'search']);
    Route::post('/advanced-search', [BookingController::class, 'advancedSearch']);

    // NEW: Availability and conflict checking routes
    Route::post('/check-availability', [BookingController::class, 'checkAvailability']);
    Route::post('/conflicts', [BookingController::class, 'getConflicts']);
    Route::get('/provider/{id}/statistics', [BookingController::class, 'getProviderStatistics']);

    // Status management routes
    Route::patch('/{id}/cancel', [BookingController::class, 'cancelAppointment']);
    Route::patch('/{id}/confirm', [BookingController::class, 'confirmAppointment']);
});


Route::prefix('blocked-periods')->group(function () {
    // CRUD routes
    Route::get('/', [BlockedPeriodController::class, 'index']);
    Route::get('/{id}', [BlockedPeriodController::class, 'show']);
    Route::post('/', [BlockedPeriodController::class, 'store']);
    Route::put('/{id}', [BlockedPeriodController::class, 'update']);
    Route::delete('/{id}', [BlockedPeriodController::class, 'destroy']);

    // Period-based queries
    Route::post('/for-period', [BlockedPeriodController::class, 'getForPeriod']);
    Route::post('/for-date', [BlockedPeriodController::class, 'getForDate']);
    Route::post('/check-date', [BlockedPeriodController::class, 'checkDateBlocked']);
    Route::post('/check-working-hours', [BlockedPeriodController::class, 'checkWorkingHours']);

    // Query routes
    Route::post('/get-filtered', [BlockedPeriodController::class, 'getFiltered']);
    Route::post('/get-by-criteria', [BlockedPeriodController::class, 'getByCriteria']);

    // Search routes
    Route::get('/search', [BlockedPeriodController::class, 'search']);
    Route::post('/advanced-search', [BlockedPeriodController::class, 'advancedSearch']);

    // CI3 compatibility routes
    Route::post('/find', [BlockedPeriodController::class, 'find']);
    Route::post('/value', [BlockedPeriodController::class, 'getValue']);
    Route::post('/values', [BlockedPeriodController::class, 'getValues']);

    // Utility routes
    Route::post('/check-overlaps', [BlockedPeriodController::class, 'checkOverlaps']);
});


require __DIR__ . '/auth.php';
