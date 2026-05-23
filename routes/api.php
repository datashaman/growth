<?php

use App\Http\Controllers\EvidenceAssetUploadController;
use App\Http\Controllers\MockupScreenshotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

// Visual-evidence upload — growth-sync posts a PR's screenshot gallery here.
// Guarded by the same Passport access token it already uses to record
// delivery links over the HTTP MCP transport.
Route::middleware('auth:api')
    ->post('/evidence-assets', [EvidenceAssetUploadController::class, 'store'])
    ->name('evidence-assets.store');

Route::middleware('auth:api')
    ->get('/mockup-shots/{mockup}/{revision}', [MockupScreenshotController::class, 'show'])
    ->name('api.mockup-shots.show');
