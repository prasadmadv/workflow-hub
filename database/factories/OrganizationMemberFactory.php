<?php

namespace Database\Factories;

use App\Models\OrganizationMember;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Organization;
use App\Models\User;
/**
 * @extends Factory<OrganizationMember>
 */
class OrganizationMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'role' => fake()->randomElement(['owner', 'manager', 'member']),
            'status' => fake()->randomElement(['invited', 'active', 'suspended']),
            'joined_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
