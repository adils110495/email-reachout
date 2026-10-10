<?php

namespace App\Services;

use App\Models\Bulk;
use App\Models\BulkItem;
use App\Models\Category;
use App\Models\Lead;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The "import" type of Bulks: CSV -> leads.
 *
 *   upload   -> a draft Bulk with the file stored, header read, rows counted, columns auto-mapped
 *   preview  -> first rows with validation (invalid / duplicate) shown on the Bulk page
 *   confirm  -> mapping saved, status pending, ProcessBulkJob queued
 *   process  -> ProcessBulkJob calls processSlice() in bounded slices (checkpointed file
 *               offset) until done; every row becomes a bulk_item with its outcome, so the
 *               existing Bulk results table and CSV export are the import report.
 */
class BulkImportService
{
    /** Importable lead columns => accepted header spellings. */
    public const ALIASES = [
        'email' => ['email', 'e-mail', 'email address', 'emailaddress', 'mail', 'work email'],
        'first_name' => ['first_name', 'first name', 'firstname', 'given name', 'fname'],
        'last_name' => ['last_name', 'last name', 'lastname', 'surname', 'family name', 'lname'],
        'company_name' => ['company', 'company_name', 'company name', 'organization', 'organisation', 'business'],
        'website' => ['website', 'web site', 'url', 'domain', 'site'],
        'phone' => ['phone', 'phone number', 'telephone', 'mobile', 'tel'],
        'country' => ['country', 'nation'],
        'job_title' => ['job_title', 'job title', 'title', 'position', 'role'],
        'linkedin' => ['linkedin', 'linkedin url', 'linkedin_url'],
    ];

    public const ROWS_PER_RUN = 1000;

    public const SECONDS_PER_RUN = 60;

    // ── upload + preview ───────────────────────────────────────────────────

    public function createDraft(UploadedFile $file, ?Category $category, bool $updateExisting, ?string $name = null): Bulk
    {
        $path = $file->store('bulk-uploads', 'local');
        $absolute = Storage::disk('local')->path($path);
        $this->stripBom($absolute);

        $handle = fopen($absolute, 'r');
        $delimiter = $this->detectDelimiter($handle);
        $header = fgetcsv($handle, 0, $delimiter) ?: [];
        $offset = (int) ftell($handle);

        if (count(array_filter($header, fn ($h) => trim((string) $h) !== '')) === 0) {
            fclose($handle);
            Storage::disk('local')->delete($path);

            throw ValidationException::withMessages(['file' => 'The CSV file is empty or has no header row.']);
        }

        $rows = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row !== [null]) {
                $rows++;
            }
        }
        fclose($handle);

        return Bulk::create([
            'name' => $name ?: pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME),
            'type' => Bulk::TYPE_IMPORT,
            'category_id' => $category?->id,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'status' => Bulk::STATUS_DRAFT,
            'total_records' => $rows,
            'options' => [
                'delimiter' => $delimiter,
                'header' => array_map(fn ($h) => mb_substr(trim((string) $h), 0, 100), $header),
                'mapping' => $this->guessMapping($header),
                'update_existing' => $updateExisting,
                'offset' => $offset,
                'row' => 0,
            ],
        ]);
    }

    /**
     * First rows with validation, so problems are visible before importing.
     *
     * @return array{headers: list<string>, rows: list<array>, stats: array<string,int>, total: int}
     */
    public function preview(Bulk $bulk): array
    {
        $options = $bulk->options;
        $limit = (int) config('sequencer.import.preview_rows', 20);
        $emailIndex = array_search('email', (array) $options['mapping'], true);

        $handle = fopen(Storage::disk('local')->path($bulk->file_path), 'r');
        fseek($handle, (int) $options['offset']);
        $sample = [];
        while (count($sample) < $limit && ($row = fgetcsv($handle, 0, $options['delimiter'])) !== false) {
            if ($row !== [null]) {
                $sample[] = $row;
            }
        }
        fclose($handle);

        $stats = ['valid' => 0, 'invalid' => 0, 'duplicate' => 0];
        $seen = [];
        $rows = [];

        foreach ($sample as $i => $row) {
            $email = $emailIndex !== false ? mb_strtolower(trim((string) ($row[$emailIndex] ?? ''))) : '';
            $problem = null;

            if (! $this->validEmail($email)) {
                $problem = 'invalid';
            } elseif (isset($seen[$email]) || Lead::holdingAddress($email)->exists()) {
                $problem = 'duplicate';
            }

            $stats[$problem ?? 'valid']++;
            $seen[$email] = true;
            $rows[] = ['cells' => $row, 'problem' => $problem, 'number' => $i + 1];
        }

        return ['headers' => $options['header'], 'rows' => $rows, 'stats' => $stats, 'total' => $bulk->total_records];
    }

    /**
     * @param  array<int, string|null>  $mapping  column index => field ("email", ..., "custom") or blank to ignore
     *
     * @throws ValidationException
     */
    public function confirm(Bulk $bulk, array $mapping, ?bool $updateExisting = null): Bulk
    {
        if ($bulk->status !== Bulk::STATUS_DRAFT) {
            return $bulk;   // double submit: already queued
        }

        $allowed = [...array_keys(self::ALIASES), 'custom'];
        $clean = [];
        foreach ($mapping as $index => $field) {
            if ($field && in_array($field, $allowed, true)) {
                $clean[(int) $index] = $field;
            }
        }

        if (! in_array('email', $clean, true)) {
            throw ValidationException::withMessages(['mapping' => 'Map one column to "email" before importing.']);
        }

        $bulk->update([
            'status' => Bulk::STATUS_PENDING,
            'options' => array_merge($bulk->options, [
                'mapping' => $clean,
                'update_existing' => $updateExisting ?? (bool) ($bulk->options['update_existing'] ?? false),
            ]),
        ]);

        return $bulk;
    }

    // ── processing (called by ProcessBulkJob) ──────────────────────────────

    /** Import the next slice of rows. Returns true when the whole file is done. */
    public function processSlice(Bulk $bulk): bool
    {
        $options = $bulk->options;
        $mapping = (array) $options['mapping'];
        $headers = (array) $options['header'];
        $category = $bulk->category_id ? Category::find($bulk->category_id) : null;
        $deadline = microtime(true) + self::SECONDS_PER_RUN;
        $rowNumber = (int) ($options['row'] ?? 0);
        $count = 0;

        $handle = fopen(Storage::disk('local')->path($bulk->file_path), 'r');
        fseek($handle, (int) $options['offset']);

        while ($count < self::ROWS_PER_RUN && microtime(true) < $deadline) {
            $row = fgetcsv($handle, 0, $options['delimiter']);
            if ($row === false) {
                break;
            }
            if ($row === [null]) {
                continue;   // blank line
            }

            $rowNumber++;
            $count++;
            $this->importRow($bulk, $category, $this->rowToData($row, $mapping, $headers), $rowNumber, (bool) $options['update_existing']);
        }

        $done = feof($handle) || $rowNumber >= $bulk->total_records;
        $offset = (int) ftell($handle);
        fclose($handle);

        $bulk->update(['options' => array_merge($options, ['offset' => $offset, 'row' => $rowNumber])]);

        return $done;
    }

    private function importRow(Bulk $bulk, ?Category $category, array $data, int $rowNumber, bool $updateExisting): void
    {
        $email = $data['email'] ?? '';
        $item = ['bulk_id' => $bulk->id, 'input' => mb_substr($email ?: '(row '.$rowNumber.')', 0, 255), 'extra' => $data['company_name'] ?? null, 'meta' => ['row' => $rowNumber] + $data];

        if (! $this->validEmail($email)) {
            BulkItem::create($item + ['status' => BulkItem::STATUS_FAILED, 'result_status' => 'invalid', 'message' => 'Invalid email address']);

            return;
        }

        if (BulkItem::where('bulk_id', $bulk->id)->where('input', $email)->exists()) {
            BulkItem::create($item + ['status' => BulkItem::STATUS_DONE, 'result_status' => 'duplicate', 'message' => 'Duplicate email in file']);

            return;
        }

        $lead = Lead::holdingAddress($email)->first();

        if (! $lead) {
            $lead = Lead::create(array_filter($data, fn ($v) => $v !== null && $v !== '') + [
                'status' => Lead::STATUS_NEW,
                'category_id' => $category?->id,
            ]);
            $category?->addLeads([$lead->id]);
            BulkItem::create($item + ['status' => BulkItem::STATUS_DONE, 'result_status' => 'imported', 'result_value' => (string) $lead->id, 'message' => 'New lead']);

            return;
        }

        $category?->addLeads([$lead->id]);

        if ($updateExisting) {
            $updates = array_filter(array_diff_key($data, ['email' => true]), fn ($v) => $v !== null && $v !== '');
            if (isset($updates['custom_fields'])) {
                $updates['custom_fields'] = array_merge((array) $lead->custom_fields, $updates['custom_fields']);
            }
            $lead->update($updates);
            BulkItem::create($item + ['status' => BulkItem::STATUS_DONE, 'result_status' => 'updated', 'result_value' => (string) $lead->id, 'message' => 'Existing lead updated']);

            return;
        }

        BulkItem::create($item + ['status' => BulkItem::STATUS_DONE, 'result_status' => 'duplicate', 'result_value' => (string) $lead->id, 'message' => 'Lead already exists (not changed)']);
    }

    /**
     * @param  array<int, string>  $mapping
     * @param  list<string>  $headers
     */
    private function rowToData(array $row, array $mapping, array $headers): array
    {
        $data = [];
        $custom = [];

        foreach ($mapping as $index => $field) {
            $value = trim((string) ($row[$index] ?? ''));
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');   // drop invalid byte sequences

            if ($field === 'custom') {
                if ($value !== '') {
                    $custom[Str::slug($headers[$index] ?? 'field_'.$index, '_')] = mb_substr($value, 0, 500);
                }

                continue;
            }

            $data[$field] = $value === '' ? null : mb_substr($value, 0, 255);
        }

        if (isset($data['email'])) {
            $data['email'] = mb_strtolower($data['email']);
        }

        if ($custom) {
            $data['custom_fields'] = $custom;
        }

        return $data;
    }

    public function validEmail(string $email): bool
    {
        return $email !== '' && mb_strlen($email) <= 191 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** @return array<int, string> column index => field */
    public function guessMapping(array $header): array
    {
        $mapping = [];
        $used = [];

        foreach ($header as $index => $name) {
            $normal = mb_strtolower(trim((string) $name));

            foreach (self::ALIASES as $field => $aliases) {
                if (! isset($used[$field]) && in_array($normal, $aliases, true)) {
                    $mapping[$index] = $field;
                    $used[$field] = true;

                    continue 2;
                }
            }

            if ($normal !== '') {
                $mapping[$index] = 'custom';     // unknown columns become custom fields; can be ignored
            }
        }

        return $mapping;
    }

    private function stripBom(string $absolute): void
    {
        $handle = fopen($absolute, 'r');
        $bom = fread($handle, 3);
        fclose($handle);

        if ($bom === "\xEF\xBB\xBF") {
            file_put_contents($absolute, substr((string) file_get_contents($absolute), 3));
        }
    }

    /** @param  resource  $handle */
    private function detectDelimiter($handle): string
    {
        $line = (string) fgets($handle);
        rewind($handle);

        $best = ',';
        $max = 0;
        foreach ([',', ';', "\t", '|'] as $candidate) {
            if (($count = substr_count($line, $candidate)) > $max) {
                $max = $count;
                $best = $candidate;
            }
        }

        return $best;
    }
}
