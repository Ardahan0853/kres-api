<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstitution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gun icinde tutulan kayit: yoklama, yemek, uyku, tuvalet, not, fotograf.
 *
 * institution_id bilerek fillable degildir; BelongsToInstitution trait'i onu
 * giris yapmis kullanicidan yazar. Boylece govdeden kurum gecirilemez.
 */
#[Fillable(['id', 'classroom_id', 'child_id', 'user_id', 'type', 'value', 'recorded_at'])]
class Record extends Model
{
    use BelongsToInstitution, HasUuids;

    public const TYPE_ATTENDANCE = 'attendance';

    public const TYPE_MEAL = 'meal';

    public const TYPE_NAP = 'nap';

    public const TYPE_TOILET = 'toilet';

    public const TYPE_NOTE = 'note';

    public const TYPE_PHOTO = 'photo';

    /** @return list<string> */
    public static function types(): array
    {
        return [
            self::TYPE_ATTENDANCE,
            self::TYPE_MEAL,
            self::TYPE_NAP,
            self::TYPE_TOILET,
            self::TYPE_NOTE,
            self::TYPE_PHOTO,
        ];
    }

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
