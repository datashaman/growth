<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\McpAppHostController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/mcp-apps/project-dashboard', [McpAppHostController::class, 'showProjectDashboard'])
    ->name('mcp-apps.project-dashboard');
Route::post('/mcp-apps/project-dashboard/rpc', [McpAppHostController::class, 'rpc'])
    ->name('mcp-apps.project-dashboard.rpc');
