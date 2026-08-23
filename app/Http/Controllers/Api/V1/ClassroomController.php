<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use App\Models\DaySend;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClassroomController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        // Admin kurumun tamamini gorur, ogretmen yalnizca atandigi siniflari.
        // Her iki sorgu da BelongsToInstitution global scope'undan geciyor.
        $query = $user->isAdmin()
            ? Classroom::query()
            : $user->classrooms()->getQuery();

        $classrooms = $query
            ->withCount('children')
            // Sinif kartindaki "Gonderildi" seridi icin: bugunun gonderimi
            // varsa damgasi, yoksa null. Bugun uygulama saat dilimine gore
            // alinir; ogretmenin yerel gunu gece yarisina yakin saatlerde
            // farklilasabilir.
            ->addSelect(['day_sent_at' => DaySend::query()
                ->select('sent_at')
                ->whereColumn('classroom_id', 'classrooms.id')
                ->whereDate('day', now()->toDateString())
                ->limit(1),
            ])
            ->orderBy('name')
            ->get();

        return ClassroomResource::collection($classrooms);
    }
}
