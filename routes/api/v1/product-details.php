<?php

use App\Http\Controllers\Api\V1\Inventory\ProductDetailController;
use Illuminate\Support\Facades\Route;

Route::prefix('products/{product}')->group(function () {
    Route::get('/details', [ProductDetailController::class, 'show']);

    // Specifications
    Route::get('/specifications', [ProductDetailController::class, 'specifications']);
    Route::put('/specifications', [ProductDetailController::class, 'syncSpecifications']);

    // Images
    Route::get('/images', [ProductDetailController::class, 'images']);
    Route::post('/images', [ProductDetailController::class, 'addImage']);
    Route::delete('/images/{image}', [ProductDetailController::class, 'removeImage']);
    Route::put('/images/reorder', [ProductDetailController::class, 'reorderImages']);

    // Documents
    Route::get('/documents', [ProductDetailController::class, 'documents']);
    Route::post('/documents', [ProductDetailController::class, 'addDocument']);
    Route::delete('/documents/{document}', [ProductDetailController::class, 'removeDocument']);

    // Videos
    Route::get('/videos', [ProductDetailController::class, 'videos']);
    Route::post('/videos', [ProductDetailController::class, 'addVideo']);
    Route::delete('/videos/{video}', [ProductDetailController::class, 'removeVideo']);

    // Relations
    Route::get('/relations', [ProductDetailController::class, 'relations']);
    Route::put('/relations', [ProductDetailController::class, 'setRelations']);

    // Reviews
    Route::get('/reviews', [ProductDetailController::class, 'reviews']);
    Route::post('/reviews', [ProductDetailController::class, 'submitReview']);
    Route::post('/reviews/{review}/approve', [ProductDetailController::class, 'approveReview']);
    Route::post('/reviews/{review}/reject', [ProductDetailController::class, 'rejectReview']);

    // Price History
    Route::get('/price-history', [ProductDetailController::class, 'priceHistory']);

    // Certifications
    Route::get('/certifications', [ProductDetailController::class, 'certifications']);
    Route::post('/certifications', [ProductDetailController::class, 'addCertification']);
    Route::put('/certifications/{certification}', [ProductDetailController::class, 'updateCertification']);
});
