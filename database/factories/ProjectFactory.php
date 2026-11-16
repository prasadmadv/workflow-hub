<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'organization_id' => Organization::factory(),
            'client_id' => null,
            'name' => fake()->catchPhrase(),
            'description' => fake()->paragraph(),
            'type' => 'internal',
            'status' => fake()->randomElement([
                'planning',
                'active',
                'on_hold',
                'completed',
                'cancelled',
            ]),
            'start_date' => $startDate,
            'due_date' => fake()->dateTimeBetween($startDate, '+6 months'),
        ];
    }

    /**
     * Indicate that the project belongs to a client.
     */

    public function clientProject(): static
    {
        return $this
            ->state([
                'type' => 'client',
            ])
            ->afterCreating(function (Project $project) {
                if (! $project->client_id) {
                    $client = Client::factory()->create([
                        'organization_id' => $project->organization_id,
                    ]);

                    $project->client()->associate($client);
                    $project->save();
                }
            });
    }


    /**
     * Indicate that the project is internal.
     */
    public function internalProject(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'internal',
            'client_id' => null,
        ]);
    }
}
