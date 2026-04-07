<?php

use App\Http\Controllers\Api\V1\Document\DocumentController;
use App\Http\Controllers\Api\V1\Document\DocumentFolderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Document Vault Routes
|--------------------------------------------------------------------------
|
| Routes for the document vault module including folders, documents,
| versioning, sharing, and digital signatures.
|
*/

// Document Folders
Route::prefix('document-folders')->group(function () {
    Route::get('/', [DocumentFolderController::class, 'index'])
        ->name('document-folders.index');

    Route::post('/', [DocumentFolderController::class, 'store'])
        ->middleware('check.permission:documents.folders.create')
        ->name('document-folders.store');

    Route::get('/{documentFolder}', [DocumentFolderController::class, 'show'])
        ->name('document-folders.show');

    Route::put('/{documentFolder}', [DocumentFolderController::class, 'update'])
        ->name('document-folders.update');

    Route::delete('/{documentFolder}', [DocumentFolderController::class, 'destroy'])
        ->name('document-folders.destroy');
});

// Documents
Route::prefix('documents')->group(function () {
    Route::get('/', [DocumentController::class, 'index'])
        ->name('documents.index');

    Route::post('/', [DocumentController::class, 'store'])
        ->middleware('check.permission:documents.files.create')
        ->name('documents.store');

    // Signature verification (does not require a specific document)
    Route::post('/verify-signature', [DocumentController::class, 'verifySignature'])
        ->name('documents.verify-signature');

    Route::get('/{document}', [DocumentController::class, 'show'])
        ->name('documents.show');

    Route::put('/{document}', [DocumentController::class, 'update'])
        ->name('documents.update');

    Route::delete('/{document}', [DocumentController::class, 'destroy'])
        ->name('documents.destroy');

    // Download
    Route::get('/{document}/download', [DocumentController::class, 'download'])
        ->name('documents.download');

    // Versioning
    Route::get('/{document}/versions', [DocumentController::class, 'versions'])
        ->name('documents.versions');

    Route::post('/{document}/versions', [DocumentController::class, 'uploadVersion'])
        ->name('documents.versions.store');

    // Sharing
    Route::get('/{document}/shares', [DocumentController::class, 'shares'])
        ->name('documents.shares');

    Route::post('/{document}/shares', [DocumentController::class, 'share'])
        ->name('documents.shares.store');

    Route::post('/{document}/shares/{share}/revoke', [DocumentController::class, 'revokeShare'])
        ->name('documents.shares.revoke');

    // Signatures
    Route::get('/{document}/signatures', [DocumentController::class, 'signatures'])
        ->name('documents.signatures');

    Route::post('/{document}/signatures', [DocumentController::class, 'sign'])
        ->name('documents.signatures.store');

    // Activity log
    Route::get('/{document}/activities', [DocumentController::class, 'activities'])
        ->name('documents.activities');

    // Move to folder
    Route::post('/{document}/move', [DocumentController::class, 'move'])
        ->name('documents.move');
});
