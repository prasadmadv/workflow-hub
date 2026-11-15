<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(
            Organization::class, 'organization_members'
        )->withPivot(['id', 'role', 'status', 'joined_at'])
        ->withTimestamps();
    }

    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(
            Project::class,
            'project_members'
        )->withTimestamps();
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(
            Task::class,
            'task_assignees',
            'user_id',
            'task_id'
        )
        ->withPivot(['id', 'assigned_by'])
        ->withTimestamps();
    }

    public function taskComments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

}
