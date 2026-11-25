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

# Cancel Membership

    public function test_active_owner_can_cancel_a_pending_invitation(): void
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
        $invitedMembership = $organization->members()->create([
            'user_id' => $invitedMember->id,
            'role' => 'member',
            'status' => 'invited',
            'joined_at' => null
        ]);

        $this->actingAs($owner);
        $this->deleteJson('/api/v1/organizations/'.$organization->id.'/members/'.$invitedMembership->id)
            ->assertOk()
            ->assertJsonPath('message', 'Membership cancelled successfully');

        $this->assertDatabaseCount('organization_members', 1)
            ->assertDatabaseMissing('organization_members', [
                'id' => $invitedMembership->id,
                'user_id' => $invitedMember->id,
                'organization_id' => $organization->id,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $invitedMember->id,
        ]);
    }

    public function test_active_manager_cannot_cancel_a_pending_invitation(): void
    {
        $manager = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $manager->id,
            'role' => 'manager',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $invitedMember = User::factory()->create();
        $invitedMembership = $organization->members()->create([
            'user_id' => $invitedMember->id,
            'role' => 'member',
            'status' => 'invited',
            'joined_at' => null
        ]);

        $this->actingAs($manager);
        $this->deleteJson('/api/v1/organizations/'.$organization->id.'/members/'.$invitedMembership->id)
            ->assertNotFound();

        $this->assertDatabaseCount('organization_members', 2)
            ->assertDatabaseHas('organization_members', [
                'id' => $invitedMembership->id,
                'user_id' => $invitedMember->id,
                'organization_id' => $organization->id,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $invitedMember->id,
        ]);
    }

    public function test_suspended_owner_cannot_cancel_a_pending_invitation(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'suspended',
            'joined_at' => now()
        ]);

        $invitedMember = User::factory()->create();
        $invitedMembership = $organization->members()->create([
            'user_id' => $invitedMember->id,
            'role' => 'member',
            'status' => 'invited',
            'joined_at' => null
        ]);

        $this->actingAs($owner);
        $this->deleteJson('/api/v1/organizations/'.$organization->id.'/members/'.$invitedMembership->id)
            ->assertNotFound();

        $this->assertDatabaseCount('organization_members', 2)
            ->assertDatabaseHas('organization_members', [
                'id' => $invitedMembership->id,
                'user_id' => $invitedMember->id,
                'organization_id' => $organization->id,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $invitedMember->id,
        ]);
    }

    public function test_unrelated_user_cannot_cancel_a_pending_invitation(): void
    {
        $organization = Organization::factory()->create();

        $invitedMember = User::factory()->create();
        $invitedMembership = $organization->members()->create([
            'user_id' => $invitedMember->id,
            'role' => 'member',
            'status' => 'invited',
            'joined_at' => null
        ]);

        $unrelatedOrganization = Organization::factory()->create();
        $unrelatedUser = User::factory()->create();
        $unrelatedOrganizationOwnership = $unrelatedOrganization->members()->create([
            'user_id' => $unrelatedUser->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $this->actingAs($unrelatedUser);
        $this->deleteJson('/api/v1/organizations/'.$organization->id.'/members/'.$invitedMembership->id)
            ->assertNotFound();

        $this->assertDatabaseCount('organization_members', 2)
            ->assertDatabaseHas('organization_members', [
                'id' => $invitedMembership->id,
                'user_id' => $invitedMember->id,
                'organization_id' => $organization->id,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $invitedMember->id,
        ]);
    }

    public function test_guest_cannot_cancel_a_pending_invitation(): void
    {
        $organization = Organization::factory()->create();
        $invitedMember = User::factory()->create();
        $invitedMembership = $organization->members()->create([
            'user_id' => $invitedMember->id,
            'role' => 'member',
            'status' => 'invited',
            'joined_at' => null
        ]);

        $this->deleteJson('/api/v1/organizations/'.$organization->id.'/members/'.$invitedMembership->id)
            ->assertUnauthorized();

        $this->assertDatabaseCount('organization_members', 1)
            ->assertDatabaseHas('organization_members', [
                'id' => $invitedMembership->id,
                'user_id' => $invitedMember->id,
                'organization_id' => $organization->id,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $invitedMember->id,
        ]);
    }

    public function test_owner_cannot_cancel_an_active_or_suspended_membership(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $activeMember = User::factory()->create();
        $activeMembership = $organization->members()->create([
            'user_id' => $activeMember->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $suspendedMember = User::factory()->create();
        $suspendedMembership = $organization->members()->create([
            'user_id' => $suspendedMember->id,
            'role' => 'member',
            'status' => 'suspended',
            'joined_at' => now()
        ]);

        $this->actingAs($owner);
        $this->deleteJson('/api/v1/organizations/'.$organization->id.'/members/'.$activeMembership->id)
            ->assertNotFound();

        $this->deleteJson('/api/v1/organizations/'.$organization->id.'/members/'.$suspendedMembership->id)
            ->assertNotFound();

        $this->assertDatabaseCount('organization_members', 3)
            ->assertDatabaseHas('organization_members', [
                'id' => $activeMembership->id,
                'user_id' => $activeMember->id,
                'organization_id' => $organization->id,
            ])->assertDatabaseHas('organization_members', [
                'id' => $suspendedMembership->id,
                'user_id' => $suspendedMember->id,
                'organization_id' => $organization->id,
            ]);
    }

    public function test_cannot_cancel_membership_belonging_to_other_organization(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $otherOrganization = Organization::factory()->create();
        $otherOrganizationMember = User::factory()->create();
        $otherOrganizationMembership = $otherOrganization->members()->create([
            'user_id' => $otherOrganizationMember->id,
            'role' => 'member',
            'status' => 'invited',
            'joined_at' => null
        ]);

        $this->actingAs($owner);
        $this->deleteJson('/api/v1/organizations/'.$organization->id.'/members/'.$otherOrganizationMembership->id)
            ->assertNotFound();

        $this->assertDatabaseCount('organization_members', 2)
            ->assertDatabaseHas('organization_members', [
                'id' => $otherOrganizationMembership->id,
                'user_id' => $otherOrganizationMember->id,
                'organization_id' => $otherOrganization->id,
            ]);
    }

    public function test_cannot_cancel_non_existent_membership(): void
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
        $fakeMembershipId = 999999;
        $this->deleteJson('/api/v1/organizations/'.$organization->id.'/members/'.$fakeMembershipId)
            ->assertNotFound();
    }

# My Invitations

    public function test_user_can_retrieve_their_pending_invitations(): void
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

        $this->actingAs($owner);
        $inviteResponse = $this->postJson(
            '/api/v1/organizations/'.$organization->id.'/members',
            [
            'email' => $invitedMember->email
            ])->assertCreated();

        $invitationId = $inviteResponse->json('data.id');

        $this->actingAs($invitedMember);
        $response = $this->getJson('/api/v1/my/invitations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('message', 'Pending invitations retrieved successfully')
        ->assertJsonPath('data.0.id', $invitationId)
        ->assertJsonFragment([
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'role' => 'member',
            'status' => 'invited',
        ])
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'invited_at',
                ],
            ],
        ]);

        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $organization->id,
            'user_id' => $invitedMember->id,
            'role' => 'member',
            'status' => 'invited',
        ]);
        $this->assertNotFalse(
            strtotime($response->json('data.0.invited_at'))
        );

    }

    public function test_user_can_retrieve_only_their_own_pending_invitations(): void
    {
        $organizationA = Organization::factory()->create();
        $ownerA = User::factory()->create();
        $organizationA->members()->create([
            'user_id' => $ownerA->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $organizationB = Organization::factory()->create();
        $ownerB = User::factory()->create();
        $organizationB->members()->create([
            'user_id' => $ownerB->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $invitedMemberForOrganizationA = User::factory()->create();
        $this->actingAs($ownerA);
        $inviteResponseForOrganizationA = $this->postJson(
            '/api/v1/organizations/'.$organizationA->id.'/members',
            [
            'email' => $invitedMemberForOrganizationA->email
            ]
        )->assertCreated();

        $invitedMemberForOrganizationB = User::factory()->create();
        $this->actingAs($ownerB);
        $inviteResponseForOrganizationB = $this->postJson(
            '/api/v1/organizations/'.$organizationB->id.'/members',
            [
            'email' => $invitedMemberForOrganizationB->email
            ]
        )->assertCreated();


        $invitationIdForOrganizationA = $inviteResponseForOrganizationA->json('data.id');
        $invitationIdForOrganizationB = $inviteResponseForOrganizationB->json('data.id');

        $this->actingAs($invitedMemberForOrganizationA);
        $response = $this->getJson('/api/v1/my/invitations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('message', 'Pending invitations retrieved successfully')
        ->assertJsonPath('data.0.id', $invitationIdForOrganizationA)
        ->assertJsonFragment([
            'organization_id' => $organizationA->id,
            'organization_name' => $organizationA->name,
            'role' => 'member',
            'status' => 'invited',
        ])
        ->assertJsonMissing([
            'organization_id' => $organizationB->id,
        ])
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'invited_at',
                ],
            ],
        ]);

        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $organizationA->id,
            'user_id' => $invitedMemberForOrganizationA->id,
            'role' => 'member',
            'status' => 'invited',
        ]);
        $this->assertDatabaseHas('organization_members', [
            'id' => $invitationIdForOrganizationA,
            'user_id' => $invitedMemberForOrganizationA->id
        ]);
        $this->assertNotFalse(
            strtotime($response->json('data.0.invited_at'))
        );
        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $organizationB->id,
            'user_id' => $invitedMemberForOrganizationB->id,
            'role' => 'member',
            'status' => 'invited',
        ]);

    }

    public function test_user_retrieves_only_pending_invitations(): void
    {
        $invitedMember = User::factory()->create();

        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        $organizationC = Organization::factory()->create();

        $invitationFromOrgA = $organizationA->members()->create([
            'user_id' => $invitedMember->id, 'role' => 'member', 'status' => 'invited', 'joined_at' => null
        ]);

        $activeMembership = $organizationB->members()->create([
            'user_id' => $invitedMember->id, 'role' => 'member', 'status' => 'active', 'joined_at' => now()
        ]);

        $suspendedMembership = $organizationC->members()->create([
            'user_id' => $invitedMember->id, 'role' => 'member', 'status' => 'suspended', 'joined_at' => now()
        ]);

        $this->actingAs($invitedMember);
        $response = $this->getJson('/api/v1/my/invitations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('message', 'Pending invitations retrieved successfully')
        ->assertJsonFragment([
            'id' => $invitationFromOrgA->id,
            'organization_id' => $organizationA->id,
            'organization_name' => $organizationA->name,
            'role' => 'member',
            'status' => 'invited'
        ])
        ->assertJsonMissing([
            'id' => $activeMembership->id,
        ])
        ->assertJsonMissing([
            'id' => $suspendedMembership->id,
        ]);

        $this->assertDatabaseHas('organization_members', [
            'id' => $invitationFromOrgA->id,
            'user_id' => $invitedMember->id,
            'organization_id' => $organizationA->id,
            'role' => 'member',
            'status' => 'invited'

        ]);


    }

    public function test_user_with_no_pending_invitations_receives_empty_data(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $user->id, 'role' => 'member', 'status' => 'active', 'joined_at' => now()
        ]);

        $this->actingAs($user);
        $response = $this->getJson('/api/v1/my/invitations')
        ->assertOk()
        ->assertJsonPath('message', 'Pending invitations retrieved successfully')
        ->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('organization_members', [
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'status' => 'active',
        ]);
    }

    public function test_guest_cannot_retrieve_invitations():void
    {
        $response = $this->getJson('/api/v1/my/invitations')
        ->assertUnauthorized();
    }



    # END

}
