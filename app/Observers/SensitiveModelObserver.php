<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\Core\SensitiveAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SensitiveModelObserver
{
    /**
     * Handle the model "retrieved" event.
     *
     * Logs a read entry to sensitive_access_logs whenever a sensitive model
     * instance is fetched from the database.  Skipped during console commands
     * and when no authenticated user is present to avoid noise from scheduled
     * tasks and seeders.
     */
    public function retrieved(Model $model): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        if (!auth()->check()) {
            return;
        }

        // Skip eager-loaded models that are being hydrated as part of a parent query.
        // We only want to log when the model is the primary resource of the request,
        // not every related model pulled in via with().
        $requestPath = request()?->path() ?? '';
        $modelShortName = class_basename($model);
        $segments = explode('/', strtolower($requestPath));
        $modelSlug = strtolower(Str::plural($modelShortName)); // e.g. Employee -> employees
        if (!in_array($modelSlug, $segments)) {
            return;
        }

        app(SensitiveAccessService::class)->logAccess(
            modelType: get_class($model),
            modelId:   (int) $model->getKey(),
            action:    'read',
        );
    }
}
