<?php

namespace Tests\Feature\Livewire;

use App\Livewire\BookingWizard;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingWizardTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_handles_exceptions_during_booking_confirmation()
    {
        // 1. Arrange: Create School and bind context
        // Manually create models to ensure reliability in partial environment
        $school = School::create([
            'name' => 'Test School',
            'slug' => 'test-school',
            'branding_config' => [],
            'timezone' => 'UTC',
        ]);

        // Bind the current school ID as expected by the application
        app()->instance('current_school_id', $school->id);

        // Create Instructor
        $instructor = User::create([
            'name' => 'Instructor',
            'email' => 'instructor@test.com',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'school_id' => $school->id,
            'role' => 'instructor',
            'credits' => 0,
        ]);

        // Create Student
        $student = User::create([
            'name' => 'Student',
            'email' => 'student@test.com',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'school_id' => $school->id,
            'role' => 'student',
            'credits' => 10,
        ]);

        $this->actingAs($student);

        // 2. Act: Call confirmBooking with invalid vehicle_id
        // This invalid ID (999999) should trigger a Foreign Key Constraint Violation
        // because the 'vehicle_id' column is constrained.
        $invalidVehicleId = 999999;

        $slotData = [
            'instructor_id' => $instructor->id,
            'vehicle_id' => $invalidVehicleId,
            'start' => now()->addDay()->toDateTimeString(),
            'end' => now()->addDay()->addHour()->toDateTimeString(),
        ];

        Livewire::test(BookingWizard::class)
            ->set('selectedSlot', $slotData)
            ->set('bookingLockToken', 'valid-token') // Simulate a valid lock token
            ->call('confirmBooking')
            ->assertHasErrors(['booking']) // Assert that the exception was caught and added as an error
            ->assertNotDispatched('booking-confirmed');

        // 3. Assert: Verify DB state
        // Ensure no booking was created (Rollback successful)
        $this->assertDatabaseCount('bookings', 0);

        // Ensure credits were NOT deducted (Rollback successful)
        $this->assertEquals(10, $student->fresh()->credits);
    }
}
