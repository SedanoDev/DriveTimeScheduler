<?php

namespace Tests\Unit\Models\Traits;

use App\Models\School;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BelongsToSchoolTest extends TestCase
{
    use RefreshDatabase;

    protected $schoolA;
    protected $schoolB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two schools
        $this->schoolA = School::create(['name' => 'School A', 'slug' => 'school-a']);
        $this->schoolB = School::create(['name' => 'School B', 'slug' => 'school-b']);
    }

    public function test_global_scope_filters_by_current_school()
    {
        // Create vehicles for each school
        $vehicleA = Vehicle::forceCreate(['school_id' => $this->schoolA->id, 'plate' => 'AAA-111', 'model' => 'Model A', 'type' => 'manual']);
        $vehicleB = Vehicle::forceCreate(['school_id' => $this->schoolB->id, 'plate' => 'BBB-222', 'model' => 'Model B', 'type' => 'automatic']);

        // Set current school to A
        $this->app->instance('current_school_id', $this->schoolA->id);

        $vehicles = Vehicle::all();
        $this->assertTrue($vehicles->contains($vehicleA));
        $this->assertFalse($vehicles->contains($vehicleB));
        $this->assertCount(1, $vehicles);

        // Set current school to B
        $this->app->instance('current_school_id', $this->schoolB->id);

        $vehicles = Vehicle::all();
        $this->assertFalse($vehicles->contains($vehicleA));
        $this->assertTrue($vehicles->contains($vehicleB));
        $this->assertCount(1, $vehicles);
    }

    public function test_creating_model_automatically_sets_school_id()
    {
        // Set current school to A
        $this->app->instance('current_school_id', $this->schoolA->id);

        $vehicle = Vehicle::create(['plate' => 'CCC-333', 'model' => 'Model C', 'type' => 'manual']);

        $this->assertEquals($this->schoolA->id, $vehicle->school_id);
    }

    public function test_global_scope_can_be_ignored()
    {
        // Create vehicles for each school
        $vehicleA = Vehicle::forceCreate(['school_id' => $this->schoolA->id, 'plate' => 'AAA-111', 'model' => 'Model A', 'type' => 'manual']);
        $vehicleB = Vehicle::forceCreate(['school_id' => $this->schoolB->id, 'plate' => 'BBB-222', 'model' => 'Model B', 'type' => 'automatic']);

        // Set current school to A
        $this->app->instance('current_school_id', $this->schoolA->id);

        $vehicles = Vehicle::withoutGlobalScope('school')->get();

        $this->assertTrue($vehicles->contains($vehicleA));
        $this->assertTrue($vehicles->contains($vehicleB));
        $this->assertCount(2, $vehicles);
    }
}
