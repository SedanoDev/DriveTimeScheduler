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
    const ERROR_NO_CREDITS = 'You do not have enough credits to book a lesson. Please purchase more credits to continue.';
    const ERROR_SLOT_TAKEN = 'This time slot is no longer available. Please choose another time.';
    const ERROR_GENERIC = 'We could not complete your booking. Please try again or contact support.';

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
        // Fail-fast validation
        if (auth()->check() && auth()->user()->credits < 1) {
            $this->addError('booking', self::ERROR_NO_CREDITS);
        }

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

        // This is a simplified mock implementation
        $this->availableSlots = [];
        // Real implementation would iterate 8am-8pm, check availability, etc.
    }

    public function selectSlot($slotId)
    {
        // Fail-fast validation
        if (auth()->check() && auth()->user()->credits < 1) {
            $this->addError('booking', self::ERROR_NO_CREDITS);
            return;
        }

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
            $this->addError('booking', self::ERROR_SLOT_TAKEN);
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

            if ($e->getMessage() === 'Insufficient credits.') {
                $this->addError('booking', self::ERROR_NO_CREDITS);
            } else {
                $this->addError('booking', self::ERROR_GENERIC);
            }
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
