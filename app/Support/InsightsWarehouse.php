<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class InsightsWarehouse
{
    /**
     * Determine whether the shared data warehouse has at least one record in the given table.
     */
    public function hasData(string $table = 'authorities'): bool
    {
        try {
            $connection = DB::connection('data');

            if (! $connection->getSchemaBuilder()->hasTable($table)) {
                return false;
            }

            return $connection->table($table)->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
