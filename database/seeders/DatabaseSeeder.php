<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Create demo organization
            $organization = Organization::firstOrCreate(
                ['slug' => 'workflow-hub-demo'],
                ['name' => 'WorkFlow Hub Demo']
            );

            // 2. Create demo users
            $owner = User::firstOrCreate(
                ['email' => 'owner@workflowhub.test'],
                [
                    'name' => 'Demo Owner',
                    'password' => Hash::make('password'),
                ]
            );

            $manager = User::firstOrCreate(
                ['email' => 'manager@workflowhub.test'],
                [
                    'name' => 'Demo Manager',
                    'password' => Hash::make('password'),
                ]
            );

            $member = User::firstOrCreate(
                ['email' => 'member@workflowhub.test'],
                [
                    'name' => 'Demo Member',
                    'password' => Hash::make('password'),
                ]
            );

            // 3. Organization memberships
            $organization->users()->syncWithoutDetaching([
                $owner->id => [
                    'role' => 'owner',
                    'status' => 'active',
                    'joined_at' => now(),
                ],
                $manager->id => [
                    'role' => 'manager',
                    'status' => 'active',
                    'joined_at' => now(),
                ],
                $member->id => [
                    'role' => 'member',
                    'status' => 'active',
                    'joined_at' => now(),
                ],
            ]);

            // 4. Create client
            $client = $organization->clients()->firstOrCreate(
                ['name' => 'Acme Technologies'],
                [
                    'contact_person' => 'John Smith',
                    'email' => 'john@acme.test',
                    'phone' => '9876543210',
                ]
            );

            // 5. Create client project
            $clientProject = $organization->projects()->firstOrCreate(
                ['name' => 'Client Portal'],
                [
                    'client_id' => $client->id,
                    'description' => 'Customer-facing client portal.',
                    'type' => 'client',
                    'status' => 'active',
                    'start_date' => now()->toDateString(),
                    'due_date' => now()->addMonths(3)->toDateString(),
                ]
            );

            // 6. Create internal project
            $internalProject = $organization->projects()->firstOrCreate(
                ['name' => 'Internal Platform'],
                [
                    'client_id' => null,
                    'description' => 'Internal workflow management platform.',
                    'type' => 'internal',
                    'status' => 'active',
                    'start_date' => now()->toDateString(),
                    'due_date' => now()->addMonths(2)->toDateString(),
                ]
            );

            // 7. Project memberships
            foreach ([$clientProject, $internalProject] as $project) {
                $project->members()->syncWithoutDetaching([
                    $owner->id,
                    $manager->id,
                    $member->id,
                ]);
            }

            // 8. Create tasks
            $tasks = [];

            foreach ([$clientProject, $internalProject] as $project) {
                foreach ([
                    ['Set up project structure', 'in_progress', 'high'],
                    ['Implement authentication', 'todo', 'critical'],
                    ['Review API documentation', 'in_review', 'medium'],
                    ['Configure development environment', 'done', 'low'],
                ] as [$title, $status, $priority]) {

                    $task = $project->tasks()->firstOrNew([
                        'title' => $title,
                    ]);

                    if (! $task->exists) {
                        $task->fill([
                            'description' => "Demo task: {$title}",
                            'status' => $status,
                            'priority' => $priority,
                            'estimated_hours' => 4,
                            'start_date' => now()->toDateString(),
                            'due_date' => now()->addDays(7)->toDateString(),
                            'completed_at' => $status === 'done' ? now() : null,
                        ]);

                        // Set creator before inserting the task
                        $task->createdBy()->associate($manager);
                        $task->save();
                    }

                    $tasks[] = $task;
                }
            }


            // 9. Task assignments, comments and activity logs
            foreach ($tasks as $task) {
                $task->assignees()->syncWithoutDetaching([
                    $member->id => [
                        'assigned_by' => $manager->id,
                    ],
                ]);

                // Create one demo comment per task
                $comment = $task->comments()->firstOrNew([
                    'body' => 'Working on this task. Progress will be updated soon.',
                ]);

                $comment->user()->associate($member);
                $comment->save();

                // Create activity log only if it doesn't already exist
                if (! $task->activityLogs()
                    ->where('action', 'created')
                    ->exists()) {
                    $log = ActivityLog::factory()->make([
                        'action' => 'created',
                    ]);

                    $log->user()->associate($manager);

                    $task->activityLogs()->save($log);
                }
            }
        });
    }
}
