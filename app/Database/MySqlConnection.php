<?php

namespace App\Database;

use DateTimeInterface;
use Illuminate\Database\MySqlConnection as BaseMySqlConnection;
use Illuminate\Support\Carbon;

/**
 * MySQL connection that binds every date in the app timezone - the zone the MySQL session
 * runs in (config/database.php) - whatever zone the PHP object carries.
 *
 * The query builder formats a bound DateTime in its own zone, so `where('sent_at', '<=', $t)`
 * with $t in UTC would compare against IST values and be off by 5:30.
 */
class MySqlConnection extends BaseMySqlConnection
{
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = Carbon::instance($value)->setTimezone(config('app.timezone'));
            }
        }

        return parent::prepareBindings($bindings);
    }
}
