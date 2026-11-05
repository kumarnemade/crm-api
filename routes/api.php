<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LeadActivityController;
use App\Http\Controllers\LeadAssignmentController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\RepPerformanceController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('leads', [LeadController::class, 'index']);
    Route::post('leads', [LeadController::class, 'store']);
    Route::get('leads/{lead}', [LeadController::class, 'show'])->can('view', 'lead');
    Route::patch('leads/{lead}', [LeadController::class, 'update'])->can('update', 'lead');
    Route::post('leads/{lead}/assign', LeadAssignmentController::class)->can('assign', 'lead');
    Route::post('leads/{lead}/activities', LeadActivityController::class)->can('logActivity', 'lead');

    Route::get('reports/rep-performance', RepPerformanceController::class);
});
