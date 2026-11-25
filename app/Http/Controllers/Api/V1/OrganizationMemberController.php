<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrganizationMemberRequest;
use App\Http\Resources\Api\V1\OrganizationMemberResource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrganizationMemberController extends Controller
{
    public function index(Request $request, string $organizationId)
    {
        $userOwnerMembership = $request->user()
            ->organizationMemberships()
            ->where('role', 'owner')
            ->where('status', 'active')
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        $organizationMemberships = $userOwnerMembership->organization->members()
            ->with(['user:id,name,email'])
            ->get();

        return response()->json([
            'message' => 'Organization members retrieved successfully',
            'data' => OrganizationMemberResource::collection($organizationMemberships)
        ]);
    }

    public function store(StoreOrganizationMemberRequest $request, string $organizationId)
    {
        // 1. Verify that the authenticated user is an active owner
        $ownerMembership = $request->user()
            ->organizationMemberships()
            ->where('organization_id', $organizationId)
            ->where('role', 'owner')
            ->where('status', 'active')
            ->firstOrFail();


        // 2. Retrieve the organization through the membership relationship
        $organization = $ownerMembership->organization;

        // 3. Find the existing registered user
        $invitedUser = User::where('email', $request->validated('email'))
            ->firstOrFail();

        // 4. Prevent self-invitation
        if ($invitedUser->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'email' => 'You cannot invite yourself',
            ]);
        }

        // 5. Prevent duplicate memberships
        $membershipExists = $organization->members()
            ->where('user_id', $invitedUser->id)
            ->exists();

        if ($membershipExists) {
            throw ValidationException::withMessages([
                'email' => 'This user already has a membership in this organization',
            ]);
        }

        // 6. Create the invitation
        $invitedMembership = $organization->members()->create([
            'user_id' => $invitedUser->id,
            'role' => 'member',
            'status' => 'invited',
            'joined_at' => null,
        ]);

        // 7. Return the newly created invitation
        return response()->json([
            'message' => 'User invitation created successfully',
            'data' => new OrganizationMemberResource(
                    $invitedMembership->load('user:id,name,email')
                ),
        ], 201);
    }
}
