<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrganizationController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/v1/register', [AuthController::class, 'register']);
Route::post('/v1/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function(){
    Route::post('/v1/logout', [AuthController::class, 'logout']);
    Route::post('/v1/organizations', [OrganizationController::class, 'store']);
    Route::get('/v1/organizations', [OrganizationController::class, 'index']);
    Route::get('/v1/organizations/{organization}', [OrganizationController::class, 'show']);
    Route::patch('/v1/organizations/{organization}', [OrganizationController::class, 'update']);
});
