<?php

namespace Tests\Feature\Api\V1\Organizations;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

# 1. Save Organization

    public function test_authenticated_user_can_create_an_organization(): void
    {

        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/v1/organizations', [
            'name' => 'ACME TECH',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Organization created successfully')
            ->assertJsonPath('data.name', 'ACME TECH');

        $response->assertJsonPath(
            'data.slug',
            fn ($slug) => is_string($slug) && $slug !== ''
        );

        $this->assertDatabaseHas('organizations', [
            'name' => 'ACME TECH',
        ]);

    }

    public function test_creator_automatically_becomes_active_owner(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->postJson('/api/v1/organizations', [ 'name' => 'ACME TECH' ]);
        $response->assertCreated();

        $this->assertDatabaseHas('organization_members', [
            'user_id' => $user->id,
            'organization_id' => $response->json('data.id'),
            'role' => 'owner',
            'status' => 'active',
        ]);
    }


    public function test_invalid_organization_name_returns_422(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $invalidData = [
            [],
            [ 'name' => '' ],
            [ 'name' => str_repeat('a', 256) ],
            [ 'name' => ['invalid'] ]
        ];

        foreach($invalidData as $data)
        {
            $response = $this->postJson( '/api/v1/organizations', $data );
            $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
        }

        $this->assertDatabaseCount('organizations', 0);
    }


    public function test_guest_cannot_create_an_organization(): void
    {
        $response = $this->postJson('/api/v1/organizations', [ 'name' => 'ACME TECH' ]);
        $response->assertUnauthorized();
        $this->assertDatabaseCount('organizations', 0);
        $this->assertDatabaseCount('organization_members', 0);
    }

    public function test_failed_membership_creation_rolls_back_organization_creation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        DB::statement("
            CREATE TRIGGER fail_organization_membership
            BEFORE INSERT ON organization_members
            BEGIN
                SELECT RAISE(ABORT, 'Simulated membership failure');
            END;
        ");

        $this->withoutExceptionHandling();

        try {
            $this->postJson('/api/v1/organizations', [
                'name' => 'ACME TECH',
            ]);

            $this->fail('Expected membership creation to fail.');
        } catch (\Illuminate\Database\QueryException $exception) {

            $this->assertStringContainsString(
                'Simulated membership failure',
                $exception->getMessage()
            );

            $this->assertDatabaseCount('organizations', 0);
            $this->assertDatabaseCount('organization_members', 0);
        }
    }

# 2. Retrieve Organizations listing

    public function test_user_can_list_their_active_organizations(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->postJson('/api/v1/organizations', [ 'name' => 'ACME TECH']);
        $response->assertCreated();

        $this->assertDatabaseCount('organizations', 1)
            ->assertDatabaseCount('organization_members', 1);

        $organizationsResponse = $this->getJson('/api/v1/organizations');
        $organizationsResponse->assertOk()
            ->assertJsonPath('message', 'Organizations retrieved successfully')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'ACME TECH')
            ->assertJsonPath('data.0.membership.role', 'owner')
            ->assertJsonPath('data.0.membership.status', 'active');

    }

    public function test_invited_and_suspended_organizations_are_excluded(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $invitedOrganization = Organization::factory()->create();
        $suspendedOrganization = Organization::factory()->create();

        $invitedOrganization->members()->create([
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'invited'
        ]);

        $suspendedOrganization->members()->create([
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'suspended'
        ]);

        $response = $this->getJson('/api/v1/organizations');
        $response->assertOk()
            ->assertJsonCount(0,'data');

    }

    public function test_user_cannot_see_another_users_organizations(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $firstOrganization = Organization::factory()->create(['name' => 'First Organization']);
        $secondOrganization = Organization::factory()->create(['name' => 'Second Organization']);

        $firstOrganization->members()->create([
            'user_id' => $user1->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $secondOrganization->members()->create([
            'user_id' => $user2->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($user1);
        $response = $this->getJson('/api/v1/organizations');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'First Organization' );
    }

    public function test_user_with_no_organizations_receives_empty_array(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->getJson('/api/v1/organizations');
        $response->assertOk()
            ->assertJsonCount(0, 'data');

    }

    public function test_guest_cannot_list_organizations(): void
    {
        $response = $this->getJson('/api/v1/organizations');
        $response->assertUnauthorized();
    }

# 3. Show
    public function test_authenticated_active_member_can_retrieve_their_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->create([
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()
        ]);

        $this->actingAs($user);
        $response = $this->getJson('/api/v1/organizations/'.$organization->id);
        $response->assertOk()
            ->assertJsonPath('message', 'Organization retrieved successfully')
            ->assertJsonPath('data.id', $organization->id)
            ->assertJsonPath('data.name', $organization->name)
            ->assertJsonPath('data.membership.status', 'active')
            ->assertJsonPath('data.membership.role', 'owner');
    }

    public function test_non_member_cannot_retrieve_other_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $this->actingAs($user);
        $response = $this->getJson('/api/v1/organizations/'.$organization->id);
        $response->assertNotFound();
    }

    public function test_invited_member_cannot_retrieve_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->members()->create([
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'invited'
        ]);

        $this->actingAs($user);
        $response = $this->getJson('/api/v1/organizations/'.$organization->id);
        $response->assertNotFound();
    }

    public function test_suspended_member_cannot_retrieve_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $organization->members()->create([
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'suspended'
        ]);

        $this->actingAs($user);
        $response = $this->getJson('/api/v1/organizations/'.$organization->id);
        $response->assertNotFound();
    }

    public function test_guest_cannot_retrieve_an_organization(): void
    {
        $organization = Organization::factory()->create();
        $response = $this->getJson('/api/v1/organizations/'.$organization->id);
        $response->assertUnauthorized();
    }

# 4. Update
    public function test_active_member_can_update_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Old Organization Name']);
        $organization->members()->create([
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active'
        ]);

        $this->actingAs($user);
        $response = $this->patchJson('/api/v1/organizations/'.$organization->id, [
            'name' => 'Updated Organization Name'
        ])->assertOk()
        ->assertJsonPath('message', 'Organization updated successfully')
        ->assertJsonPath('data.id', $organization->id)
        ->assertJsonPath('data.name', 'Updated Organization Name')
        ->assertJsonPath(
            'data.membership.role',
            'owner'
        )
        ->assertJsonPath(
            'data.membership.status',
            'active'
        );

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Updated Organization Name',
        ]);
    }

    public function test_empty_body_leaves_organization_unchanged(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Old Organization Name']);
        $organization->members()->create([
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active'
        ]);

        $this->actingAs($user);
        $response = $this->patchJson('/api/v1/organizations/'.$organization->id, [])->assertOk()
        ->assertJsonPath('message', 'Organization updated successfully')
        ->assertJsonPath('data.id', $organization->id)
        ->assertJsonPath('data.name', 'Old Organization Name')
        ->assertJsonPath(
            'data.membership.role',
            'owner'
        )
        ->assertJsonPath(
            'data.membership.status',
            'active'
        );

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Old Organization Name',
        ]);
    }

    public function test_empty_name_returns_validation_error(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Old Organization Name']);
        $organization->members()->create([
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active'
        ]);

        $this->actingAs($user);
        $response = $this->patchJson('/api/v1/organizations/'.$organization->id, ['name' => ''])
        ->assertUnprocessable();

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Old Organization Name',
        ]);
    }

    public function test_invalid_name_types_and_length_returns_validation_error(): void
    {
        $user = User::factory()->create();

        $organization = Organization::factory()->create(['name' => 'Old Organization Name']);
        $organization->members()->create([
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active'
        ]);

        $this->actingAs($user);
        $data = [
            [], 123, $user, str_repeat('a',266)
        ];
        foreach($data as $name)
        {
            $response = $this->patchJson('/api/v1/organizations/'.$organization->id, ['name' => $name])
            ->assertUnprocessable();
        }

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Old Organization Name',
        ]);
    }

    public function test_guest_cannot_update_organization(): void
    {
        $organization = Organization::factory()->create(['name' => 'Old Organization Name']);
        $response = $this->patchJson('/api/v1/organizations/'.$organization->id)
        ->assertUnauthorized();
    }

    public function test_non_member_cannot_update_other_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Old Organization Name']);

        $this->actingAs($user);
        $response = $this->patchJson(
            '/api/v1/organizations/'.$organization->id,
            ['name' => 'Updated Organization Name']
        )->assertNotFound();

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Old Organization Name',
        ]);
    }

    // 8	Invited member cannot update	404
    public function test_invited_and_suspended_member_cannot_update_organization(): void
    {
        $invitedUser = User::factory()->create();
        $suspendedUser = User::factory()->create();

        $organization = Organization::factory()->create(['name' => 'Old Organization Name']);
        $organization->members()->create([
            'user_id' => $invitedUser->id,
            'role' => 'owner',
            'status' => 'invited'
        ]);
        $organization->members()->create([
            'user_id' => $suspendedUser->id,
            'role' => 'owner',
            'status' => 'suspended'
        ]);

        $this->actingAs($invitedUser);
        $response = $this->patchJson('/api/v1/organizations/'.$organization->id, [
            'name' => 'Updated Organization Name'
        ])->assertNotFound();

        $this->actingAs($suspendedUser);
        $response = $this->patchJson('/api/v1/organizations/'.$organization->id, [
            'name' => 'Updated Organization Name'
        ])->assertNotFound();

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Old Organization Name',
        ]);
    }

    // 10	Nonexistent organization returns 404	404
public function test_non_existent_organization_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $response = $this->patchJson('/api/v1/organizations/'.random_int(1,100), [
            'name' => 'Updated Organization Name'
        ])->assertNotFound();
    }
}
