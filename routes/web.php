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
    Route::livewire('work-items/create', 'pages::work-items.create')->name('work-items.create');
    Route::livewire('work-items/{workItem}', 'pages::work-items.show')->name('work-items.show');
    Route::livewire('work-items/{workItem}/edit', 'pages::work-items.edit')->name('work-items.edit');
    Route::livewire('requirements/create', 'pages::requirements.create')->name('requirements.create');
    Route::livewire('requirements/{requirement}', 'pages::requirements.show')->name('requirements.show');
    Route::livewire('requirements/{requirement}/edit', 'pages::requirements.edit')->name('requirements.edit');
    Route::livewire('anomalies/{anomaly}', 'pages::anomalies.show')->name('anomalies.show');
    Route::livewire('reviews/{review}', 'pages::reviews.show')->name('reviews.show');
});

require __DIR__.'/settings.php';
