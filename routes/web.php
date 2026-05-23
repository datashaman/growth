<?php

use App\Http\Controllers\EvidenceAssetController;
use App\Http\Controllers\SpecMockupController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', 'dashboard')->name('home');

Route::livewire('invitations/{token}', 'pages::invitations.show')->name('invitations.show');

// Public, unauthenticated image-serving route — the stable URL embedded in
// per-PR evidence galleries. Outside the auth group on purpose: GitHub's camo
// proxy fetches it without credentials, and a plain PNG cannot script.
Route::get('evidence-assets/{evidenceAsset}', [EvidenceAssetController::class, 'show'])
    ->name('evidence-assets.show');

Route::middleware('auth')->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('intent', 'pages::intent')->name('intent');
    Route::livewire('requirements', 'pages::requirements.index')->name('requirements');
    Route::livewire('architecture', 'pages::architecture')->name('architecture');
    Route::livewire('architecture/elements/{element}', 'pages::architecture-elements.show')->name('architecture-elements.show');
    Route::livewire('verification', 'pages::verification')->name('verification');
    Route::livewire('plan', 'pages::plan')->name('plan');
    Route::livewire('roles', 'pages::roles')->name('roles');
    Route::livewire('evidence', 'pages::evidence')->name('evidence');
    Route::livewire('changes', 'pages::changes')->name('changes');
    Route::livewire('change-requests/{changeRequest}', 'pages::change-requests.show')->name('change-requests.show');
    Route::livewire('risks/{risk}', 'pages::risks.show')->name('risks.show');
    Route::livewire('work-items/{workItem}', 'pages::work-items.show')->name('work-items.show');
    Route::livewire('mockups/{mockup}', 'pages::mockups.show')->name('mockups.show');
    Route::get('mockups/{mockup}/raw', [SpecMockupController::class, 'raw'])->name('mockups.raw');
    Route::livewire('requirements/{requirement}', 'pages::requirements.show')->name('requirements.show');
    Route::livewire('anomalies/{anomaly}', 'pages::anomalies.show')->name('anomalies.show');
    Route::livewire('reviews', 'pages::reviews.index')->name('reviews');
    Route::livewire('reviews/{review}', 'pages::reviews.show')->name('reviews.show');
    Route::livewire('tool-invocations', 'pages::tool-invocations')->name('tool-invocations');
    Route::livewire('feedback', 'pages::feedback')->name('feedback');
    Route::livewire('feedback/{toolFeedback}', 'pages::feedback.show')->name('feedback.show');
    Route::livewire('notifications', 'pages::notifications')->name('notifications');
});

require __DIR__.'/settings.php';
