<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\TenantResolver;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public Endpoints
Route::post('/auth/login', [AuthController::class, 'login']);

// Tenant-Scoped API
Route::middleware(['auth:sanctum', TenantResolver::class])->group(function () {

    // User Profile
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Calendar Data Feed (FullCalendar JSON)
    Route::get('/calendar/feed', [CalendarController::class, 'feed'])->name('api.calendar.feed');

    // Booking Actions
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);

    // Instructor Actions
    Route::middleware('role:instructor')->group(function () {
        Route::post('/bookings/{booking}/check-in', [BookingController::class, 'checkIn']);
        Route::post('/bookings/{booking}/evaluate', [BookingController::class, 'evaluate']);
    });
});
