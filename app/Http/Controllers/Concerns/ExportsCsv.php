<?php

namespace App\Http\Controllers\Concerns;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a module's records to the browser as a CSV download.
 *
 * Every module (Leads, Email Templates, Platforms, Categories, Addresses)
 * exposes its own "Export CSV" action, so the streaming boilerplate lives here
 * once instead of being repeated in each controller.
 */
trait ExportsCsv
{
    /**
     * @param  string    $filename  Base name; the date and ".csv" are appended.
     * @param  array     $columns   Header row.
     * @param  iterable  $rows      Records to write.
     * @param  callable  $mapper    Maps one record to an array of cell values.
     */
    protected function streamCsv(string $filename, array $columns, iterable $rows, callable $mapper): StreamedResponse
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($columns, $rows, $mapper) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $columns);

            foreach ($rows as $row) {
                fputcsv($handle, $mapper($row));
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
