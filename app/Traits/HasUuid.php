<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Assigns a stable, public-facing UUID to a model on creation.
 *
 * The primary key stays an auto-incrementing bigint (fast joins / indexes);
 * the `uuid` column is what gets exposed in URLs and API responses so internal
 * IDs are never leaked. Models using this trait must have a `uuid` column.
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->{$model->getUuidColumn()})) {
                $model->{$model->getUuidColumn()} = (string) Str::uuid();
            }
        });
    }

    public function getUuidColumn(): string
    {
        return 'uuid';
    }

    /**
     * Bind route-model resolution to the UUID instead of the numeric id.
     */
    public function getRouteKeyName(): string
    {
        return $this->getUuidColumn();
    }
}
