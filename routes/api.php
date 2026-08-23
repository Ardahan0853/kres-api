<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClassroomChildController;
use App\Http\Controllers\Api\V1\ClassroomController;
use App\Http\Controllers\Api\V1\DaySendController;
use App\Http\Controllers\Api\V1\PhotoController;
use App\Http\Controllers\Api\V1\RecordController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::get('classrooms', [ClassroomController::class, 'index']);
        Route::get('classrooms/{classroom}/children', [ClassroomChildController::class, 'index']);

        // Toplu uc once yazilir ki ileride records/{record} eklendiginde
        // "batch" bir id sanilmasin.
        Route::post('records/batch', [RecordController::class, 'batch']);
        Route::post('records', [RecordController::class, 'store']);
        Route::delete('records/{recordId}', [RecordController::class, 'destroy']);

        // Dosyanin kendisi bu uclardan gecmez; imzali storage rotasina PUT edilir.
        Route::post('photos/upload-url', [PhotoController::class, 'uploadUrl']);
        Route::post('photos', [PhotoController::class, 'store']);

        // Route model binding kullanilmaz: bulunamayan sinif icin 404 yerine
        // 422 donmemiz gerekiyor, istemci 404'u "uc yok" diye yorumluyor.
        Route::post('classrooms/{classroomId}/day-send', [DaySendController::class, 'store']);
    });
});
