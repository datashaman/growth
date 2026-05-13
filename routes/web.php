<?php

use App\Http\Controllers\McpAppHostController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::get('/mcp-apps/project-dashboard', [McpAppHostController::class, 'showProjectDashboard'])
    ->name('mcp-apps.project-dashboard');
Route::post('/mcp-apps/project-dashboard/rpc', [McpAppHostController::class, 'rpc'])
    ->name('mcp-apps.project-dashboard.rpc');

require __DIR__.'/settings.php';
