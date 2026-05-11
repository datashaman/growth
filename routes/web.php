<?php

use App\Http\Controllers\McpAppHostController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mcp-apps/project-dashboard', [McpAppHostController::class, 'showProjectDashboard'])
    ->name('mcp-apps.project-dashboard');
Route::post('/mcp-apps/project-dashboard/rpc', [McpAppHostController::class, 'rpc'])
    ->name('mcp-apps.project-dashboard.rpc');
