<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Task;
use App\Models\ActivityLog;
class ActivityLogFactoryTest extends TestCase
{
    use RefreshDatabase;

        public function test_activity_log_can_be_attached_to_a_task(): void
    {
        $task = Task::factory()->create();

        $log = ActivityLog::factory()->make([
            'action' => 'created',
        ]);

        $task->activityLogs()->save($log);

        $this->assertDatabaseHas('activity_logs', [
            'id' => $log->id,
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'action' => 'created',
        ]);
    }

    public function test_activity_log_can_be_created_with_a_user(): void
    {
        $log = ActivityLog::factory()->withUser()->make();

        $this->assertNotNull($log->user_id);
        $this->assertNotNull($log->user);
    }
}
