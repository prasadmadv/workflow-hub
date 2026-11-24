<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user?->id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'role' => $this->role,
            'status' => $this->status,
            'joined_at' => $this->joined_at
        ];
    }
}
