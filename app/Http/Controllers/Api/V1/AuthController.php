<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->string('email')->lower()->value())
            ->first();

        // Kullanici yok ile sifre yanlis ayni cevabi verir; e-posta sayimi yapilamasin.
        if ($user === null || ! Hash::check($request->string('password')->value(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['E-posta veya şifre hatalı.'],
            ]);
        }

        $token = $user->createToken($request->string('device_name')->value())->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('institution')),
        ]);
    }

    public function logout(Request $request): Response
    {
        // Yalnizca bu istekte kullanilan token silinir, kullanicinin diger
        // cihazlardaki oturumlari acik kalir.
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('institution'));
    }
}
