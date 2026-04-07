<?php

declare(strict_types=1);

namespace App\Exceptions\ERP;

/**
 * Thrown when a tenant-scoped model is persisted without an organization_id.
 *
 * This is a programming error (HTTP 500), not a user-facing validation failure.
 * It indicates that a model was created outside an authenticated request context
 * without an explicit organization_id and without the BelongsToOrganization bypass.
 */
class MissingTenantException extends ErpException
{
    protected string $errorCode = 'MISSING_TENANT_CONTEXT';
    protected int $httpStatus   = 500;

    public static function forModel(string $modelClass): self
    {
        $short = class_basename($modelClass);

        return new self(
            "Cannot persist {$short} without organization_id. " .
            'Either authenticate the request, set organization_id explicitly, ' .
            'or use BelongsToOrganization::withoutTenantCheck().',
            ['model' => $modelClass],
        );
    }
}
