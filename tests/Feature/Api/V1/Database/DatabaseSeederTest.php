<?php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_expected_demo_data(): void
    {
        Artisan::call('db:seed');

        $organization = Organization::where(
            'slug',
            'workflow-hub-demo'
        )->firstOrFail();

        $this->assertCount(3, $organization->users);
        $this->assertCount(1, $organization->clients);
        $this->assertCount(2, $organization->projects);

        $this->assertEquals(
            8,
            $organization->projects()
                ->withCount('tasks')
                ->get()
                ->sum('tasks_count')
        );
    }

    public function test_database_seeder_is_idempotent(): void
    {
        Artisan::call('db:seed');
        Artisan::call('db:seed');

        $organization = Organization::where(
            'slug',
            'workflow-hub-demo'
        )->firstOrFail();

        $this->assertCount(3, $organization->users);
        $this->assertCount(1, $organization->clients);
        $this->assertCount(2, $organization->projects);

        $this->assertDatabaseCount('tasks', 8);
        $this->assertDatabaseCount('task_comments', 8);
        $this->assertDatabaseCount('activity_logs', 8);
    }
}
