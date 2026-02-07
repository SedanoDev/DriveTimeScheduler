<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\BookingWizard;
use App\Http\Middleware\TenantResolver;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application.
| These routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Public/Landing (Global)
Route::domain('drivetime.com')->group(function () {
    Route::get('/', function () {
        return view('welcome');
    });
    // Super Admin Routes
    Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', function () {
             return 'Super Admin Dashboard';
        })->name('admin.dashboard');
    });
});

// Tenant Routes (Subdomain)
Route::domain('{school}.drivetime.com')->middleware([TenantResolver::class])->group(function () {

    // Auth Routes (Login, Register specific to School)
    Route::get('/login', [AuthController::class, 'login'])->name('login');

    // Protected Routes
    Route::middleware(['auth:sanctum', 'verified'])->group(function () {

        // Student Area
        Route::middleware(['role:student'])->prefix('student')->name('student.')->group(function () {
            Route::get('/dashboard', function () {
                return view('student.dashboard');
            })->name('dashboard');

            // Booking Wizard (Livewire)
            Route::get('/book', BookingWizard::class)->name('book');

            // My Bookings
            Route::get('/my-bookings', [BookingController::class, 'index'])->name('bookings.index');
        });

        // Instructor Area
        Route::middleware(['role:instructor'])->prefix('instructor')->name('instructor.')->group(function () {
            Route::get('/schedule', [InstructorController::class, 'schedule'])->name('schedule');
            Route::post('/bookings/{booking}/complete', [BookingController::class, 'complete'])->name('bookings.complete');
        });

        // School Admin Area
        Route::middleware(['role:school_admin'])->prefix('admin')->name('school.admin.')->group(function () {
            Route::resource('vehicles', VehicleController::class);
            Route::resource('instructors', InstructorController::class);
            Route::resource('students', StudentController::class);
            Route::get('/settings', [SchoolSettingsController::class, 'edit'])->name('settings');
        });
    });
});
