<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrganizationMemberResource;
use App\Http\Resources\Api\V1\OrganizationResource;
use App\Models\OrganizationMember;
use Illuminate\Http\Request;

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
}
