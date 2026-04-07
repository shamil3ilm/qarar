<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Core\FeatureFlagController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Feature Flag Targeting Routes
|--------------------------------------------------------------------------
|
| Phased rollout controls: target features to specific users, branches,
| roles, or a percentage of users before org-wide release.
|
| All routes require authentication and the core.feature-flags.manage
| permission.
|
*/

Route::middleware(['auth:api', 'check.permission:core.feature-flags.manage'])
    ->prefix('feature-flags')
    ->name('core.feature-flags.')
    ->group(function () {

        // List all flags with targets for the authenticated org
        Route::get('/', [FeatureFlagController::class, 'index'])
            ->name('index');

        // Show a single flag with its targets
        Route::get('/{flagKey}', [FeatureFlagController::class, 'show'])
            ->name('show');

        // Add a targeting rule to a flag
        Route::post('/{flagKey}/targets', [FeatureFlagController::class, 'addTarget'])
            ->name('targets.add');

        // Remove a targeting rule
        Route::delete('/{flagKey}/targets/{targetId}', [FeatureFlagController::class, 'removeTarget'])
            ->name('targets.remove');

        // Set or update the percentage rollout for a flag
        Route::post('/{flagKey}/rollout-percentage', [FeatureFlagController::class, 'setPercentage'])
            ->name('rollout-percentage');

        // Check whether a flag is active for a specific user
        Route::get('/{flagKey}/check', [FeatureFlagController::class, 'checkForUser'])
            ->name('check');
    });
