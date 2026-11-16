<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Project;
use App\Models\Task;
class ProjectFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_project_belongs_to_same_organization_as_client(): void
    {
        $project = Project::factory()
            ->clientProject()
            ->create();

        $this->assertEquals('client', $project->type);
        $this->assertNotNull($project->client_id);

        $this->assertEquals(
            $project->organization_id,
            $project->client->organization_id
        );
    }

    public function test_internal_project_has_no_client(): void
    {
        $project = Project::factory()
            ->internalProject()
            ->create();

        $this->assertEquals('internal', $project->type);
        $this->assertNull($project->client_id);
    }

    public function test_completed_task_has_done_status_and_completion_timestamp(): void
    {
        $task = Task::factory()->completed()->create();

        $this->assertEquals('done', $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_task_factory_generates_valid_status_and_priority_values(): void
    {
        $task = Task::factory()->create();

        $this->assertContains(
            $task->status,
            ['todo', 'in_progress', 'in_review', 'done']
        );

        $this->assertContains(
            $task->priority,
            ['low', 'medium', 'high', 'critical']
        );
    }
}
