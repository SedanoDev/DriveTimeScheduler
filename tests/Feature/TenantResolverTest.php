<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\School;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\TenantResolver;

class TenantResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Define a route that uses the middleware
        Route::middleware(TenantResolver::class)->get('/test-tenant', function () {
            return response()->json([
                'message' => 'ok',
                'school_id' => app()->bound('current_school_id') ? app('current_school_id') : null,
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
            ]);
        });
    }

    /** @test */
    public function it_resolves_tenant_from_subdomain()
    {
        $school = School::create([
            'name' => 'Test School',
            'slug' => 'testschool',
            'domain' => 'testschool.example.com',
            'branding_config' => [],
            'timezone' => 'Europe/Madrid',
            'locale' => 'es',
        ]);

        $response = $this->get('http://testschool.example.com/test-tenant');

        $response->assertStatus(200)
            ->assertJson([
                'school_id' => $school->id,
                'timezone' => 'Europe/Madrid',
                'locale' => 'es',
            ]);

        $this->assertTrue(app()->bound('current_school'));
        $this->assertEquals($school->id, app('current_school')->id);
    }

    /** @test */
    public function it_aborts_if_school_not_found()
    {
        $response = $this->get('http://unknown.example.com/test-tenant');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_aborts_if_no_subdomain_provided()
    {
        // If host is "example.com", explode returns "example" as first part.
        // Since no school has slug "example", it should 404.

        $response = $this->get('http://example.com/test-tenant');

        $response->assertStatus(404);
    }
}
