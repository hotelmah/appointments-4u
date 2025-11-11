<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BookingController;
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

require __DIR__ . '/auth.php';
