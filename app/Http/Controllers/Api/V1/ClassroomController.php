<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use App\Models\DaySend;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

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
            // Gonderim onay ekrani "Bilgilendirilecek veli N" yazabilsin diye
            // gonderimden ONCE lazim. Kardesler ve cok velili cocuklar yuzunden
            // cocuk sayisindan turetilemez; DISTINCT veli sayilir.
            ->addSelect(['parent_count' => DB::table('child_parent')
                ->selectRaw('count(distinct child_parent.parent_id)')
                ->join('children', 'children.id', '=', 'child_parent.child_id')
                ->whereColumn('children.classroom_id', 'classrooms.id'),
            ])
            // Gunun birden fazla gonderimi olabilir (yeniden gonderim). Serit
            // EN SON bildirimin saatini gosterir: ogretmenin sordugu sey
            // "veli en son ne zaman haber aldi". Siralamasiz birakilirsa hangi
            // satirin dondugu surucuye kalirdi.
            ->addSelect(['day_sent_at' => DaySend::query()
                ->select('sent_at')
                ->whereColumn('classroom_id', 'classrooms.id')
                ->whereDate('day', now()->toDateString())
                ->orderByDesc('attempt')
                ->limit(1),
            ])
            ->orderBy('name')
            ->get();

        return ClassroomResource::collection($classrooms);
    }
}
