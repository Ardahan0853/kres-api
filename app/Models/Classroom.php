<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['institution_id', 'name'])]
class Classroom extends Model
{
    use BelongsToInstitution, HasUuids;

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(Child::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
