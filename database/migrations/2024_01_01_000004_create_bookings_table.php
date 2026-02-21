<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();

            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->integer('credits_cost')->default(1);

            $table->string('status')->index(); // 'DRAFT', 'CONFIRMED', 'CHECK_IN', 'COMPLETED', 'CANCELLED'

            $table->text('cancellation_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('instructor_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'start_at', 'end_at']);
            $table->index(['school_id', 'instructor_id', 'start_at']);
            $table->index(['school_id', 'student_id']);
        });

        Schema::create('booking_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // The user holding the lock
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->dateTime('expires_at');
            $table->string('token')->index(); // Unique token for the lock session
            $table->timestamps();

            $table->index(['school_id', 'instructor_id', 'start_at', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_locks');
        Schema::dropIfExists('bookings');
    }
};
