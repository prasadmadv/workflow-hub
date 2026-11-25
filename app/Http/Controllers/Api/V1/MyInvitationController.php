<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MyInvitationResource;
use Illuminate\Http\Request;

class MyInvitationController extends Controller
{
    public function index(Request $request)
    {

        $invitations = $request->user()
        ->organizationMemberships()
        ->where('status', 'invited')
        ->with('organization:id,name')
        ->get();

        return response()->json([
            'message' => 'Pending invitations retrieved successfully',
            'data' => MyInvitationResource::collection($invitations)
        ]);
    }
}
