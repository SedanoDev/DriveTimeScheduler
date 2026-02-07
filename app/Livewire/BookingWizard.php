<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\InstructorAvailability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BookingWizard extends Component
{
    // Filters
    public $selectedDate;
    public $selectedInstructorId;
    public $selectedClassType = 'manual'; // manual/automatic

    // State
    public $availableSlots = [];
    public $selectedSlot = null; // {start, end, instructor_id, vehicle_id}
    public $bookingLockToken = null;

    // Computed properties for UI
    public $instructors;

    public function mount()
    {
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        // Load instructors for the dropdown
        $this->instructors = User::where('role', 'instructor')->get();
    }

    public function updated($propertyName)
    {
        if (in_array($propertyName, ['selectedDate', 'selectedInstructorId', 'selectedClassType'])) {
            $this->loadSlots();
            $this->cancelDraft(); // Reset selection if filters change
        }
    }

    public function loadSlots()
    {
        // Logic to calculate available slots based on:
        // 1. Instructor Availability (defined rules)
        // 2. Existing Bookings (instructor & vehicle)
        // 3. Vehicle availability matching the class type

        $this->availableSlots = [];

        if (!$this->selectedDate || !$this->selectedInstructorId) {
            return;
        }

        $date = Carbon::parse($this->selectedDate);
        $dayOfWeek = $date->dayOfWeek; // 0 (Sunday) - 6 (Saturday)

        // 1. Instructor Availability
        $availabilities = InstructorAvailability::where('instructor_id', $this->selectedInstructorId)
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time')
            ->get();

        if ($availabilities->isEmpty()) {
            return;
        }

        // 2. Instructor Bookings (Eager Load)
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();

        $instructorBookings = Booking::where('instructor_id', $this->selectedInstructorId)
            ->where('start_at', '<', $dayEnd)
            ->where('end_at', '>', $dayStart)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECK_IN])
            ->get();

        // 3. Vehicles & Their Bookings (Eager Load)
        $vehicles = Vehicle::where('type', $this->selectedClassType)
                           ->where('status', 'active')
                           ->get();

        if ($vehicles->isEmpty()) {
            return;
        }

        $vehicleIds = $vehicles->pluck('id');
        $vehicleBookings = Booking::whereIn('vehicle_id', $vehicleIds)
            ->where('start_at', '<', $dayEnd)
            ->where('end_at', '>', $dayStart)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECK_IN])
            ->get()
            ->groupBy('vehicle_id');

        foreach ($availabilities as $availability) {
            $start = Carbon::parse($this->selectedDate . ' ' . $availability->start_time);
            $end = Carbon::parse($this->selectedDate . ' ' . $availability->end_time);

            // Iterate in 1-hour slots
            $slotStart = $start->copy();
            while ($slotStart->lt($end)) {
                $slotEnd = $slotStart->copy()->addHour();

                if ($slotEnd->gt($end)) {
                    break;
                }

                // Check Instructor Overlap in Memory
                $isInstructorBooked = $instructorBookings->contains(function ($booking) use ($slotStart, $slotEnd) {
                    return $booking->start_at < $slotEnd && $booking->end_at > $slotStart;
                });

                if (!$isInstructorBooked) {
                    // Check Vehicle Availability
                    $availableVehicle = false;
                    foreach ($vehicles as $vehicle) {
                        $bookings = $vehicleBookings->get($vehicle->id, collect());

                        $isVehicleBooked = $bookings->contains(function ($booking) use ($slotStart, $slotEnd) {
                             return $booking->start_at < $slotEnd && $booking->end_at > $slotStart;
                        });

                        if (!$isVehicleBooked) {
                            $availableVehicle = true;
                            break;
                        }
                    }

                    if ($availableVehicle) {
                        $this->availableSlots[] = [
                            'start' => $slotStart->format('H:i'),
                            'end' => $slotEnd->format('H:i'),
                            'instructor_id' => $this->selectedInstructorId,
                        ];
                    }
                }

                $slotStart->addHour();
            }
        }
    }

    public function selectSlot($slotId)
    {
        // $slotId would contain encoded info: start|end|instructor_id
        // Parse slot
        // ...

        // 1. Attempt to acquire a lock (Redis/Cache)
        $lockKey = "booking_lock:{$this->selectedDate}:{$this->selectedInstructorId}:{$start_time}";
        $token = Str::random(16);

        if (Cache::add($lockKey, $token, 300)) { // Lock for 5 mins
            $this->bookingLockToken = $token;
            $this->selectedSlot = $slotData; // populate with details

            // Create a DRAFT record in DB if needed for strict auditing
            // or just rely on Cache lock for the wizard session
        } else {
            $this->addError('slot', 'This slot was just taken by another student.');
            $this->loadSlots(); // Refresh
        }
    }

    public function confirmBooking()
    {
        if (!$this->selectedSlot || !$this->bookingLockToken) {
            return;
        }

        DB::beginTransaction();
        try {
            // 1. Re-validate availability (Double check DB)
            // SELECT ... FOR UPDATE to prevent race conditions at DB level

            // Check User Credits
            $student = auth()->user();
            if ($student->credits < 1) {
                throw new \Exception("Insufficient credits.");
            }

            // Create Booking
            $booking = Booking::create([
                'school_id' => app('current_school_id'),
                'student_id' => $student->id,
                'instructor_id' => $this->selectedSlot['instructor_id'],
                'vehicle_id' => $this->selectedSlot['vehicle_id'], // Assigned algorithmically
                'start_at' => $this->selectedSlot['start'],
                'end_at' => $this->selectedSlot['end'],
                'status' => Booking::STATUS_CONFIRMED,
                'credits_cost' => 1,
            ]);

            // Deduct Credit
            $student->decrement('credits', 1);

            // Release Lock
            // Cache::forget($lockKey);

            DB::commit();

            // Emit success event for UI/Toast
            $this->dispatch('booking-confirmed', bookingId: $booking->id);
            $this->reset(['selectedSlot', 'bookingLockToken']);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->addError('booking', $e->getMessage());
        }
    }

    public function cancelDraft()
    {
        if ($this->bookingLockToken) {
            // Release lock
            // Cache::forget($lockKey);
            $this->bookingLockToken = null;
            $this->selectedSlot = null;
        }
    }

    public function render()
    {
        return view('livewire.booking-wizard');
    }
}
