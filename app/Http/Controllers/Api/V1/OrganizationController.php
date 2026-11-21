<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrganizationRequest;
use App\Http\Resources\Api\V1\OrganizationResource;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'organization';
        return $baseSlug . '-'. strtolower((string)Str::ulid());
    }

    public function store(StoreOrganizationRequest $request)
    {

        $name = $request->validated('name');

        $organization = DB::transaction(function() use($request, $name)
        {

            // create org
            $organization = Organization::create([
                'name' => $name,
                'slug' => $this->generateUniqueSlug($name)
            ]);

            // create org_memeber
            $membership = $organization->members()->create([
                'user_id' => $request->user()->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now()
            ]);

            $organization->setAttribute('membership', [
                'role' => $membership->role,
                'status' => $membership->status,
            ]);

            return $organization;

        });

        // return response
        return response()->json([
            'message' => 'Organization created successfully',
            'data' => OrganizationResource::make($organization)
        ], 201);
    }

    public function index(Request $request)
    {
        $organizations = $request->user()
        ->organizations()
        ->wherePivot('status', 'active')
        ->get();

        $organizations->each(function ($organization)
        {
            $organization->setAttribute('membership', [
                'role' => $organization->pivot->role,
                'status' => $organization->pivot->status,
            ]);
        });

        return response()->json([
            'message' => 'Organizations retrieved successfully',
            'data' => OrganizationResource::collection($organizations),
        ]);
    }

    public function show(Request $request)
    {
        $organization = $request->user()
            ->organizations()
            ->where('organizations.id', $request->route('organization'))
            ->wherePivot('status','active')
            ->firstOrFail();

        $organization->setAttribute('membership', [
            'status' => $organization->pivot->status,
            'role' => $organization->pivot->role,
        ]);

        return response()->json([
            'message' => 'Organization retrieved successfully',
            'data' => OrganizationResource::make($organization)
        ]);
    }
}
