<?php

namespace Tests\Feature\Livewire;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\School;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Booking;
use App\Livewire\BookingWizard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BookingWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_booking_rollback_on_error()
    {
        // 1. Setup School
        $school = School::create([
            'name' => 'Test School',
            'slug' => 'test-school',
            'branding_config' => [],
            'timezone' => 'UTC',
        ]);

        // Bind current_school_id globally for the test execution
        app()->instance('current_school_id', $school->id);

        // 2. Setup Student
        $student = User::create([
            'name' => 'Student User',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
            'credits' => 10,
            'school_id' => $school->id,
        ]);

        // 3. Setup Instructor
        $instructor = User::create([
            'name' => 'Instructor User',
            'email' => 'instructor@example.com',
            'password' => bcrypt('password'),
            'role' => 'instructor',
            'school_id' => $school->id,
        ]);

        // 4. Setup Vehicle
        $vehicle = Vehicle::create([
            'school_id' => $school->id,
            'plate' => 'TEST-123',
            'model' => 'Toyota Corolla',
            'type' => 'manual',
        ]);

        // 5. Prepare Component State
        $start = Carbon::tomorrow()->setHour(10)->setMinute(0)->setSecond(0);
        $end = $start->copy()->addHour();

        $selectedSlot = [
            'instructor_id' => $instructor->id,
            'vehicle_id' => $vehicle->id,
            'start' => $start->toDateTimeString(),
            'end' => $end->toDateTimeString(),
        ];

        $token = Str::random(16);

        // 6. Register a model event listener to simulate a failure during the transaction
        // We use 'created' event which fires after the record is inserted but before the transaction is committed
        Booking::created(function ($booking) {
            throw new \Exception('Simulated Database Error');
        });

        // 7. Execute & Assert
        Livewire::actingAs($student)
            ->test(BookingWizard::class)
            ->set('selectedSlot', $selectedSlot)
            ->set('bookingLockToken', $token)
            ->call('confirmBooking')
            ->assertHasErrors(['booking' => 'Simulated Database Error']);

        // 8. Verify Rollback
        // The booking should not exist
        $this->assertEquals(0, Booking::count(), 'Booking should be rolled back');

        // The student credits should remain 10 (deduction rolled back)
        $this->assertEquals(10, $student->fresh()->credits, 'Credits should not be deducted');
    }
}
