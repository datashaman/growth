<?php

use App\Http\Controllers\SpecMockupController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', 'dashboard')->name('home');

Route::livewire('invitations/{token}', 'pages::invitations.show')->name('invitations.show');

Route::middleware('auth')->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('intent', 'pages::intent')->name('intent');
    Route::livewire('requirements', 'pages::requirements.index')->name('requirements');
    Route::livewire('architecture', 'pages::architecture')->name('architecture');
    Route::livewire('verification', 'pages::verification')->name('verification');
    Route::livewire('plan', 'pages::plan')->name('plan');
    Route::livewire('evidence', 'pages::evidence')->name('evidence');
    Route::livewire('changes', 'pages::changes')->name('changes');
    Route::livewire('change-requests/{changeRequest}', 'pages::change-requests.show')->name('change-requests.show');
    Route::livewire('risks/{risk}', 'pages::risks.show')->name('risks.show');
    Route::livewire('work-items/{workItem}', 'pages::work-items.show')->name('work-items.show');
    Route::livewire('mockups/{mockup}', 'pages::mockups.show')->name('mockups.show');
    Route::get('mockups/{mockup}/raw', [SpecMockupController::class, 'raw'])->name('mockups.raw');
    Route::livewire('requirements/{requirement}', 'pages::requirements.show')->name('requirements.show');
    Route::livewire('anomalies/{anomaly}', 'pages::anomalies.show')->name('anomalies.show');
    Route::livewire('reviews/{review}', 'pages::reviews.show')->name('reviews.show');
    Route::livewire('tool-invocations', 'pages::tool-invocations')->name('tool-invocations');
    Route::livewire('feedback', 'pages::feedback')->name('feedback');
});

require __DIR__.'/settings.php';
