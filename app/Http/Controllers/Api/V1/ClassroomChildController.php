<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChildResource;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClassroomChildController extends Controller
{
    public function index(Request $request, Classroom $classroom): AnonymousResourceCollection
    {
        $user = $request->user();

        // Global scope zaten kurum disini eleyip 404 uretir, ama route model
        // binding'in auth'tan once calistigi durumlara karsi acikca da bakariz.
        abort_unless($classroom->institution_id === $user->institution_id, 404);

        abort_if(
            ! $user->isAdmin() && ! $user->classrooms()->whereKey($classroom->getKey())->exists(),
            403,
            'Bu sınıfa atanmış değilsiniz.'
        );

        $children = $classroom->children()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return ChildResource::collection($children);
    }
}
