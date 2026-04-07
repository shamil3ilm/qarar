<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Resolve route binding by UUID or integer ID (fallback).
     * Allows routes to accept either format, which simplifies testing.
     */
    public function resolveRouteBinding(mixed $value, $field = null): ?static
    {
        if ($field) {
            return $this->where($field, $value)->first();
        }

        // Try UUID first, then fall back to integer ID
        if (Str::isUuid((string) $value)) {
            return $this->where('uuid', $value)->first();
        }

        if (is_numeric($value)) {
            return $this->where('id', (int) $value)->first();
        }

        return null;
    }

    public static function findByUuid(string $uuid): ?static
    {
        if (!Str::isUuid($uuid)) {
            return null;
        }

        return static::where('uuid', $uuid)->first();
    }

    public static function findByUuidOrFail(string $uuid): static
    {
        if (!Str::isUuid($uuid)) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                'No query results for model [' . static::class . '] with UUID ' . $uuid
            );
        }

        return static::where('uuid', $uuid)->firstOrFail();
    }
}
