<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Kurum izolasyonu.
 *
 * Kurum kimligi asla URL'den veya request body'den okunmaz; yalnizca giris
 * yapmis kullanicinin institution_id degerinden gelir. Bu sayede bir kurumun
 * kullanicisi baska kurumun kaydini ne okuyabilir ne de olusturabilir.
 *
 * Kimlik dogrulanmamis baglamda (seeder, konsol, tinker) scope devre disi
 * kalir; tum uc noktalar Sanctum ile korundugu icin bu durum API'ye acilmaz.
 */
trait BelongsToInstitution
{
    protected static function bootBelongsToInstitution(): void
    {
        static::addGlobalScope('institution', function (Builder $query) {
            $user = Auth::user();

            if ($user === null) {
                return;
            }

            $query->where(
                $query->getModel()->qualifyColumn('institution_id'),
                $user->institution_id
            );
        });

        static::creating(function (Model $model) {
            $user = Auth::user();

            if ($user !== null && $model->getAttribute('institution_id') === null) {
                $model->setAttribute('institution_id', $user->institution_id);
            }
        });
    }
}
