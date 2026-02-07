<?php

namespace Tests\Feature\Livewire;

use App\Livewire\BookingWizard;
use App\Models\Booking;
use App\Models\InstructorAvailability;
use App\Models\School;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingWizardTest extends TestCase
{
    use RefreshDatabase;

    protected $school;
    protected $instructor;
    protected $student;
    protected $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a school
        $this->school = School::create([
            'name' => 'Test School',
            'slug' => 'test-school',
            'domain' => 'test.drivetime.com',
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);

        // Mock current school ID if necessary (depending on implementation details not fully visible)
        // Usually handled by middleware, but in tests we might need to be explicit if global scopes apply
        // We'll just ensure we pass school_id to factories/create.

        // Create an instructor
        $this->instructor = User::create([
            'school_id' => $this->school->id,
            'name' => 'Instructor John',
            'email' => 'instructor@example.com',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'role' => 'instructor',
            'credits' => 0,
        ]);

        // Create a student
        $this->student = User::create([
            'school_id' => $this->school->id,
            'name' => 'Student Jane',
            'email' => 'student@example.com',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'role' => 'student',
            'credits' => 10,
        ]);

        // Create a vehicle
        $this->vehicle = Vehicle::create([
            'school_id' => $this->school->id,
            'plate' => 'ABC-123',
            'model' => 'Test Car',
            'type' => 'manual',
            'status' => 'active',
        ]);

        $this->actingAs($this->student);
    }

    /** @test */
    public function it_loads_available_slots_correctly()
    {
        // Instructor available next Monday 09:00 - 12:00
        $date = Carbon::parse('next monday');

        InstructorAvailability::create([
            'school_id' => $this->school->id,
            'instructor_id' => $this->instructor->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        Livewire::test(BookingWizard::class)
            ->set('selectedDate', $date->format('Y-m-d'))
            ->set('selectedInstructorId', $this->instructor->id)
            ->set('selectedClassType', 'manual')
            ->call('loadSlots') // Call explicitly or via hook
            ->assertSet('availableSlots', [
                [
                    'start' => '09:00',
                    'end' => '10:00',
                    'instructor_id' => $this->instructor->id,
                ],
                [
                    'start' => '10:00',
                    'end' => '11:00',
                    'instructor_id' => $this->instructor->id,
                ],
                [
                    'start' => '11:00',
                    'end' => '12:00',
                    'instructor_id' => $this->instructor->id,
                ],
            ]);
    }

    /** @test */
    public function it_excludes_slots_booked_by_instructor()
    {
        $date = Carbon::parse('next monday');

        InstructorAvailability::create([
            'school_id' => $this->school->id,
            'instructor_id' => $this->instructor->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        // Booking 10-11
        Booking::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'instructor_id' => $this->instructor->id,
            'vehicle_id' => $this->vehicle->id,
            'start_at' => $date->copy()->setTime(10, 0),
            'end_at' => $date->copy()->setTime(11, 0),
            'status' => Booking::STATUS_CONFIRMED,
            'credits_cost' => 1,
        ]);

        Livewire::test(BookingWizard::class)
            ->set('selectedDate', $date->format('Y-m-d'))
            ->set('selectedInstructorId', $this->instructor->id)
            ->set('selectedClassType', 'manual')
            ->call('loadSlots')
            ->assertSet('availableSlots', [
                [
                    'start' => '09:00',
                    'end' => '10:00',
                    'instructor_id' => $this->instructor->id,
                ],
                // 10-11 skipped
                [
                    'start' => '11:00',
                    'end' => '12:00',
                    'instructor_id' => $this->instructor->id,
                ],
            ]);
    }

    /** @test */
    public function it_excludes_slots_where_no_vehicle_is_available()
    {
        $date = Carbon::parse('next monday');

        InstructorAvailability::create([
            'school_id' => $this->school->id,
            'instructor_id' => $this->instructor->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        // Another instructor uses the only manual vehicle 10-11
        $otherInstructor = User::create([
            'school_id' => $this->school->id,
            'name' => 'Instructor Bob',
            'email' => 'bob@example.com',
            'password' => 'password',
            'role' => 'instructor',
            'credits' => 0,
        ]);

        Booking::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'instructor_id' => $otherInstructor->id,
            'vehicle_id' => $this->vehicle->id,
            'start_at' => $date->copy()->setTime(10, 0),
            'end_at' => $date->copy()->setTime(11, 0),
            'status' => Booking::STATUS_CONFIRMED,
            'credits_cost' => 1,
        ]);

        Livewire::test(BookingWizard::class)
            ->set('selectedDate', $date->format('Y-m-d'))
            ->set('selectedInstructorId', $this->instructor->id)
            ->set('selectedClassType', 'manual')
            ->call('loadSlots')
            ->assertSet('availableSlots', [
                [
                    'start' => '09:00',
                    'end' => '10:00',
                    'instructor_id' => $this->instructor->id,
                ],
                // 10-11 skipped because vehicle is taken
                [
                    'start' => '11:00',
                    'end' => '12:00',
                    'instructor_id' => $this->instructor->id,
                ],
            ]);
    }

    /** @test */
    public function it_returns_empty_slots_if_no_availability()
    {
        $date = Carbon::parse('next monday');

        // No availability created

        Livewire::test(BookingWizard::class)
            ->set('selectedDate', $date->format('Y-m-d'))
            ->set('selectedInstructorId', $this->instructor->id)
            ->set('selectedClassType', 'manual')
            ->call('loadSlots')
            ->assertSet('availableSlots', []);
    }
}
