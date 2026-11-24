<?php

namespace Tests\Feature\Api\V1\Organizations;

use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;


class OrganizationMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_owner_can_list_organization_members(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $ownerMembership = $organization->members()->create([
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $member = User::factory()->create();
        $memberMembership = $organization->members()->create([
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $this->actingAs($user);
        $response = $this->getJson('/api/v1/organizations/'.$organization->id.'/members')
        ->assertOk()
        ->assertJsonPath('message', 'Organization members retrieved successfully')
        ->assertJsonCount(2, 'data');

        $response->assertJsonFragment([
            'id' => $ownerMembership->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $response->assertJsonFragment([
            'id' => $memberMembership->id,
            'user_id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => 'member',
            'status' => 'active',
        ]);
    }

    public function test_active_manager_cannot_list_organization_members(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $user->id,
            'role' => 'manager',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $this->actingAs($user);
        $this->getJson('/api/v1/organizations/'.$organization->id.'/members')
        ->assertNotFound();

    }

    public function test_suspended_owner_cannot_list_organization_members(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'suspended',
            'joined_at' => now()
        ]);

        $this->actingAs($user);
        $this->getJson('/api/v1/organizations/'.$organization->id.'/members')
        ->assertNotFound();

    }

    public function test_unrelated_user_cannot_list_organization_members(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $unrelatedUser = User::factory()->create();
        $this->actingAs($unrelatedUser);
        $this->getJson('/api/v1/organizations/'.$organization->id.'/members')
        ->assertNotFound();
    }

    public function test_guest_cannot_list_organization_members(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $this->getJson('/api/v1/organizations/'.$organization->id.'/members')
        ->assertUnauthorized();
    }

    public function test_owner_can_list_invited_and_suspended_organization_members(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $invitedMember = User::factory()->create();
        $organization->members()->create([
            'user_id' => $invitedMember->id,
            'role' => 'member',
            'status' => 'invited'
        ]);

        $suspendedMember = User::factory()->create();
        $organization->members()->create([
            'user_id' => $suspendedMember->id,
            'role' => 'member',
            'status' => 'suspended',
            'joined_at' => now()
        ]);

        $this->actingAs($owner);
        $this->getJson('/api/v1/organizations/'.$organization->id.'/members')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonFragment([
            'user_id' => $invitedMember->id,
            'status' => 'invited',
            'joined_at' => null
        ])
        ->assertJsonFragment([
            'user_id' => $suspendedMember->id,
            'status' => 'suspended'
        ])
        ->assertJsonFragment([
            'user_id' => $owner->id,
            'status' => 'active'
        ]);
    }

}
