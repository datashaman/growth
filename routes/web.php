<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', 'dashboard')->name('home');

Route::middleware('auth')->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('intent', 'pages::intent')->name('intent');
    Route::livewire('capabilities', 'pages::capabilities')->name('capabilities');
    Route::livewire('architecture', 'pages::architecture')->name('architecture');
    Route::livewire('verification', 'pages::verification')->name('verification');
    Route::livewire('plan', 'pages::plan')->name('plan');
    Route::livewire('evidence', 'pages::evidence')->name('evidence');
    Route::livewire('risks/{risk}', 'pages::risks.show')->name('risks.show');
});

require __DIR__.'/settings.php';
