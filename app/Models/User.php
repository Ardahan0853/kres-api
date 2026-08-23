<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['institution_id', 'name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_TEACHER = 'teacher';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
}
