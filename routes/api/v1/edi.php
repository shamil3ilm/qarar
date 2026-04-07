<?php

use App\Http\Controllers\Api\V1\Core\EdiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EDI / IDoc Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/edi
|
*/

Route::prefix('edi')->name('core.edi.')->group(function (): void {
    // Partners
    Route::get('/partners', [EdiController::class, 'indexPartners'])->name('partners.index');
    Route::post('/partners', [EdiController::class, 'storePartner'])->name('partners.store');
    Route::get('/partners/{id}', [EdiController::class, 'showPartner'])->name('partners.show');
    Route::put('/partners/{id}', [EdiController::class, 'updatePartner'])->name('partners.update');
    Route::delete('/partners/{id}', [EdiController::class, 'destroyPartner'])->name('partners.destroy');
    Route::get('/partners/{partnerId}/history', [EdiController::class, 'history'])->name('history');

    // Messages
    Route::get('/messages', [EdiController::class, 'indexMessages'])->name('messages.index');
    Route::get('/messages/{id}', [EdiController::class, 'showMessage'])->name('messages.show');
    Route::post('/messages/receive', [EdiController::class, 'receive'])->name('messages.receive');
    Route::post('/messages/send', [EdiController::class, 'send'])->name('messages.send');
    Route::post('/messages/{id}/process', [EdiController::class, 'process'])->name('messages.process');
    Route::post('/messages/{id}/reprocess', [EdiController::class, 'reprocess'])->name('messages.reprocess');
});
