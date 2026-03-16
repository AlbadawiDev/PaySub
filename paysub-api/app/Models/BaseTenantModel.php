<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

abstract class BaseTenantModel extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenantId = config('app.comercio_id');

            if ($tenantId === null) {
                return;
            }

            $model = $query->getModel();
            $table = $model->getTable();

            if (self::tableHasColumn($table, 'id_comercio')) {
                $query->where($table . '.id_comercio', $tenantId);
            }
        });
    }

    public function scopeForCurrentTenant(Builder $query): Builder
    {
        $tenantId = config('app.comercio_id');

        if ($tenantId !== null && self::tableHasColumn($this->getTable(), 'id_comercio')) {
            $query->where($this->getTable() . '.id_comercio', $tenantId);
        }

        return $query;
    }

    private static function tableHasColumn(string $table, string $column): bool
    {
        static $columns = [];

        if (!isset($columns[$table])) {
            try {
                $columns[$table] = array_flip(Schema::getColumnListing($table));
            } catch (\Throwable $exception) {
                $columns[$table] = [];
            }
        }

        return isset($columns[$table][$column]);
    }
}
