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
# List members
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

# Invite member

    public function test_active_owner_can_invite_existing_registered_user(): void
    {
        $owner = User::factory()->create();
        $invitedMember = User::factory()->create();

        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $this->actingAs($owner);
        $this->postJson('/api/v1/organizations/'.$organization->id.'/members',[
            'email' => $invitedMember->email
        ])->assertCreated()
        ->assertJsonPath('message', 'User invitation created successfully')
        ->assertJsonFragment([
            'user_id' => $invitedMember->id,
            'email' => $invitedMember->email,
            'role' => 'member',
            'status' => 'invited',
        ]);

        $this->assertDatabaseHas('organization_members', [
            'user_id' => $invitedMember->id,
            'organization_id' => $organization->id,
            'role' => 'member',
            'status' => 'invited',
            'joined_at' => null
        ]);

    }

    public function test_active_manager_cannot_invite(): void
    {
        $manager = User::factory()->create();
        $invitedMember = User::factory()->create();

        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $this->actingAs($manager);
        $this->postJson('/api/v1/organizations/'.$organization->id.'/members',[
            'email' => $invitedMember->email
        ])->assertNotFound();

        $this->assertDatabaseMissing('organization_members', [
            'organization_id' => $organization->id,
            'user_id' => $invitedMember->id,
        ]);

    }

    public function test_suspended_owner_cannot_invite_existing_registered_user(): void
    {
        $suspenderOwner = User::factory()->create();
        $invitedMember = User::factory()->create();

        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $suspenderOwner->id,
            'role' => 'owner',
            'status' => 'suspended',
            'joined_at' => now()
        ]);

        $this->actingAs($suspenderOwner);
        $this->postJson('/api/v1/organizations/'.$organization->id.'/members',[
            'email' => $invitedMember->email
        ])->assertNotFound();

        $this->assertDatabaseMissing('organization_members', [
            'user_id' => $invitedMember->id,
            'organization_id' => $organization->id,
        ]);

    }


    public function test_active_owner_cannot_invite_themselves(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $this->actingAs($owner);
        $response = $this->postJson('/api/v1/organizations/'.$organization->id.'/members',[
            'email' => $owner->email
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseMissing('organization_members', [
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'role' => 'member'
        ]);

    }

    public function test_active_owner_cannot_invite_existing_organization_members(): void
    {
        $owner = User::factory()->create();
        $activeMember = User::factory()->create();
        $invitedMember = User::factory()->create();
        $suspendedMember = User::factory()->create();

        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $organization->members()->create([
            'user_id' => $activeMember->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $organization->members()->create([
            'user_id' => $invitedMember->id,
            'role' => 'member',
            'status' => 'invited',
            'joined_at' => null
        ]);

        $organization->members()->create([
            'user_id' => $suspendedMember->id,
            'role' => 'member',
            'status' => 'suspended',
            'joined_at' => now()
        ]);

        $this->actingAs($owner);


        $this->postJson('/api/v1/organizations/'.$organization->id.'/members',[
            'email' => $activeMember->email
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/v1/organizations/'.$organization->id.'/members',[
            'email' => $invitedMember->email
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/v1/organizations/'.$organization->id.'/members',[
            'email' => $suspendedMember->email
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('organization_members', [
            'user_id' => $activeMember->id,
            'organization_id' => $organization->id,
            'role' => 'member',
            'status' => 'active'
        ]);

        $this->assertDatabaseHas('organization_members', [
            'user_id' => $invitedMember->id,
            'organization_id' => $organization->id,
            'role' => 'member',
            'status' => 'invited',
            'joined_at' => null
        ]);

        $this->assertDatabaseHas('organization_members', [
            'user_id' => $suspendedMember->id,
            'organization_id' => $organization->id,
            'role' => 'member',
            'status' => 'suspended'
        ]);

        $this->assertDatabaseCount('organization_members', 4);

    }

    public function test_active_owner_cannot_invite_unregistered_user(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $this->actingAs($owner);
        $this->postJson('/api/v1/organizations/'.$organization->id.'/members',[
            'email' => 'unregistered_email@test.com'
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('organization_members', 1);

    }

    public function test_guest_cannot_invite_user(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $this->postJson('/api/v1/organizations/'.$organization->id.'/members',[
            'email' => 'unregistered_email@test.com'
        ])->assertUnauthorized();

        $this->assertDatabaseCount('organization_members', 1);

    }
}
