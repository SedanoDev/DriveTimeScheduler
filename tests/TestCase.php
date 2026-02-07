<?php

namespace Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Livewire\LivewireServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends OrchestraTestCase
{
    // use LazilyRefreshDatabase; // Or just migrate in setUp

    protected function setUp(): void
    {
        parent::setUp();

        // Mock current_school_id if not set
        if (!app()->has('current_school_id')) {
            app()->instance('current_school_id', 1);
        }
    }

    protected function getPackageProviders($app)
    {
        return [
            LivewireServiceProvider::class,
            PermissionServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('app.key', 'base64:Hupx3yAySikrM2/edkZQNQHslgDWYfiBfCuSThJ5SK8=');

        // Set up view paths to include app resource views if needed
        $app['config']->set('view.paths', [
            __DIR__.'/views',
            resource_path('views'),
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
