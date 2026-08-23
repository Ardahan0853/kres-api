<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Institution extends Model
{
    use HasUuids;

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(Child::class);
    }
}
