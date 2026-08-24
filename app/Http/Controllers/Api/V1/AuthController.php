<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\StoreSessionLogRequest;
use App\Http\Resources\SessionLogResource;
use App\Http\Resources\UserResource;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
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

    /**
     * Giris kaydi (denetim izi).
     *
     * Cevrimdisi giris sunucudan gecmedigi icin ogretmen bagliandiginda bunu
     * ayrica bildirir. started_at OLAYIN ani, sunucunun aldigi an degil:
     * 09:00'da cevrimdisi giren ogretmen 11:00'da baglansa da 09:00 gorunur.
     *
     * id istemciden gelir ve idempotency anahtaridir; kuyruk ayni istegi
     * tekrar gonderebilir.
     */
    public function sessionLog(StoreSessionLogRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $existing = SessionLog::find($data['id']);

        if ($existing !== null) {
            // Ayni id baska bir ogretmene aitse kaydi DONMEYIZ; donmek onun
            // giris saatini sizdirirdi.
            if ($existing->user_id !== $user->getKey()) {
                abort(409, 'Bu oturum kaydı id değeri kullanımda.');
            }

            return (new SessionLogResource($existing))->response()->setStatusCode(200);
        }

        // Baska kurumda duruyorsa da sizdirmadan reddedilir.
        if (SessionLog::withoutGlobalScopes()->whereKey($data['id'])->exists()) {
            abort(409, 'Bu oturum kaydı id değeri kullanımda.');
        }

        try {
            $log = SessionLog::create([
                'id' => $data['id'],
                'user_id' => $user->getKey(),
                'started_at' => $data['started_at'],
                'offline' => $data['offline'] ?? false,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Ayni kayit es zamanli iki istekle geldi.
            $raced = SessionLog::find($data['id']);

            if ($raced === null || $raced->user_id !== $user->getKey()) {
                abort(409, 'Bu oturum kaydı id değeri kullanımda.');
            }

            return (new SessionLogResource($raced))->response()->setStatusCode(200);
        }

        return (new SessionLogResource($log))->response()->setStatusCode(201);
    }
}
