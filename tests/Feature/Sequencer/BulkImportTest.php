<?php

namespace Tests\Feature\Sequencer;

use App\Jobs\ProcessBulkJob;
use App\Models\Bulk;
use App\Models\Lead;
use App\Sequencer\Enums\ContactStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSequencerData;
use Tests\TestCase;

/** CSV import of leads, as a type of the existing Bulks module. */
class BulkImportTest extends TestCase
{
    use CreatesSequencerData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTransports();
        Storage::fake('local');
        $this->actingAs($this->makeUser());
    }

    private function upload(string $content, array $extra = []): Bulk
    {
        $this->post('/bulks', ['type' => 'import', 'file' => UploadedFile::fake()->createWithContent('leads.csv', $content)] + $extra)->assertRedirect();

        return Bulk::latest('id')->firstOrFail();
    }

    /** Run the queued job to the end, the way the worker would. */
    private function work(Bulk $bulk): Bulk
    {
        do {
            app()->call([new ProcessBulkJob($bulk->id), 'handle']);
        } while ($bulk->refresh()->isRunning());

        return $bulk;
    }

    private function confirm(Bulk $bulk, ?array $mapping = null)
    {
        Queue::fake();
        $response = $this->post("/bulks/{$bulk->id}/confirm", ['mapping' => $mapping ?? $bulk->options['mapping']]);
        Queue::fake([]);

        return $response;
    }

    public function test_upload_creates_a_draft_with_a_preview_before_anything_is_imported(): void
    {
        $this->makeLead(['email' => 'existing@x.test']);

        $bulk = $this->upload("first_name,last_name,email,company,website\nAnn,A,ann@x.test,Acme,acme.test\nBad,B,not-an-email,Beta,\nEve,E,existing@x.test,Gamma,\nAnn2,A,ANN@x.test,Acme,\n");

        $this->assertSame(Bulk::TYPE_IMPORT, $bulk->type);
        $this->assertSame(Bulk::STATUS_DRAFT, $bulk->status);
        $this->assertSame(4, $bulk->total_records);
        $this->assertSame(['first_name', 'last_name', 'email', 'company_name', 'website'], array_values($bulk->options['mapping']), 'Headers are auto-mapped.');

        $this->get("/bulks/{$bulk->id}")->assertOk()->assertSee('1 valid')->assertSee('2 duplicate')->assertSee('1 invalid')->assertSee('Import 4 rows');

        $this->assertSame(1, Lead::count(), 'Nothing imported until confirmed.');
    }

    public function test_confirming_queues_it_and_the_worker_imports_into_leads_and_the_category(): void
    {
        $existing = $this->makeLead(['email' => 'existing@x.test', 'company_name' => 'Old Co']);
        $category = $this->makeCategory('Imported');

        $bulk = $this->upload("first_name,email,company,plan\nAnn,ann@x.test,Acme,Pro\nBad,not-an-email,Beta,\nEve,existing@x.test,Gamma,\nCal,ann@x.test,Dup,\nDee,dee@x.test,Delta,Free\n", ['category_id' => $category->id]);

        Queue::fake();
        $this->post("/bulks/{$bulk->id}/confirm", ['mapping' => $bulk->options['mapping']])->assertRedirect("/bulks/{$bulk->id}");
        Queue::assertPushed(ProcessBulkJob::class, 1);
        $this->post("/bulks/{$bulk->id}/confirm", ['mapping' => $bulk->options['mapping']]);   // double submit
        Queue::assertPushed(ProcessBulkJob::class, 1);
        $this->assertSame(Bulk::STATUS_PENDING, $bulk->refresh()->status);
        $this->assertSame(1, Lead::count(), 'Processing happens on a worker, not in the request.');
        Queue::fake([]);

        $this->work($bulk);

        $this->assertSame(Bulk::STATUS_COMPLETED, $bulk->status);
        $this->assertSame(5, $bulk->processed_records);
        $this->assertSame(2, $bulk->successful_records);
        $this->assertSame(['imported' => 2, 'updated' => 0, 'duplicate' => 2, 'invalid' => 1], array_intersect_key(
            $this->getJson("/bulks/{$bulk->id}/status")->json('breakdown'), array_flip(['imported', 'updated', 'duplicate', 'invalid'])
        ));

        $ann = Lead::holdingAddress('ann@x.test')->firstOrFail();
        $this->assertSame('Acme', $ann->company_name);
        $this->assertSame('Pro', $ann->custom_fields['plan']);
        $this->assertSame(ContactStatus::Active, $ann->contact_status);
        $this->assertSame($category->id, $ann->category_id);
        $this->assertSame('Old Co', $existing->refresh()->company_name, 'Existing leads are not overwritten by default.');
        $this->assertSame(3, $category->leads()->count(), 'New leads and the matched existing one join the category.');

        $report = $this->get("/bulks/{$bulk->id}/export")->streamedContent();
        $this->assertStringContainsString('Invalid email address', $report);
        $this->assertStringContainsString('Duplicate email in file', $report);
    }

    public function test_update_existing_overwrites_with_non_blank_values_only(): void
    {
        $lead = $this->makeLead(['email' => 'upd@x.test', 'company_name' => 'Old', 'job_title' => 'Keeper']);

        $bulk = $this->upload("email,company,job_title\nupd@x.test,New Co,\n", ['update_existing' => 1]);
        $this->confirm($bulk);
        $this->work($bulk);

        $lead->refresh();
        $this->assertSame('New Co', $lead->company_name);
        $this->assertSame('Keeper', $lead->job_title, 'Blank cells never erase data.');
    }

    public function test_column_mapping_can_be_changed_and_email_is_required(): void
    {
        $bulk = $this->upload("Contact,Mail\nAnn,ann@x.test\n");

        $this->confirm($bulk, [0 => 'first_name', 1 => ''])->assertSessionHasErrors('mapping');
        $this->assertSame(Bulk::STATUS_DRAFT, $bulk->refresh()->status);

        $this->confirm($bulk, [0 => 'first_name', 1 => 'email'])->assertSessionHasNoErrors();
        $this->work($bulk);
        $this->assertSame('Ann', Lead::holdingAddress('ann@x.test')->value('first_name'));
    }

    public function test_semicolon_delimiters_bom_and_blank_lines_are_handled(): void
    {
        $bulk = $this->upload("\xEF\xBB\xBFemail;first_name\n\nann@x.test;Ann\n\nbob@x.test;Bob\n");

        $this->assertSame(';', $bulk->options['delimiter']);
        $this->assertSame(2, $bulk->total_records);
        $this->confirm($bulk);
        $this->work($bulk);
        $this->assertSame(2, Lead::count());
    }

    public function test_large_files_are_processed_in_resumable_slices(): void
    {
        $rows = ['email,first_name'];
        for ($i = 1; $i <= 2500; $i++) {
            $rows[] = "user{$i}@bulk.test,User{$i}";
        }
        $bulk = $this->upload(implode("\n", $rows)."\n");
        $this->confirm($bulk);

        $runs = 0;
        do {
            app()->call([new ProcessBulkJob($bulk->id), 'handle']);
            $runs++;
            $this->assertLessThan(10, $runs);
        } while ($bulk->refresh()->isRunning());

        $this->assertGreaterThanOrEqual(3, $runs, '1000 rows per slice means 2500 rows take at least three runs.');
        $this->assertSame(2500, Lead::count());
        $this->assertSame(2500, $bulk->successful_records);
    }

    public function test_only_csv_files_are_accepted_and_an_import_cannot_be_retried(): void
    {
        $this->post('/bulks', ['type' => 'import', 'file' => UploadedFile::fake()->create('evil.php', 1, 'application/x-php')])->assertSessionHasErrors('file');
        $this->post('/bulks', ['type' => 'import', 'file' => UploadedFile::fake()->createWithContent('e.csv', '')])->assertSessionHasErrors('file');

        $bulk = $this->upload("email\nann@x.test\n");
        $this->post("/bulks/{$bulk->id}/retry")->assertSessionHas('error');
    }

    public function test_importing_the_same_file_twice_creates_no_duplicates_and_keeps_unsubscribes(): void
    {
        foreach ([1, 2] as $_) {
            $bulk = $this->upload("email\nsame@x.test\n");
            $this->confirm($bulk);
            $this->work($bulk);
        }
        $this->assertSame(1, Lead::count());

        $lead = Lead::firstOrFail();
        $lead->forceFill(['contact_status' => ContactStatus::Unsubscribed])->save();

        $bulk = $this->upload("email,first_name\nsame@x.test,Back\n", ['update_existing' => 1]);
        $this->confirm($bulk);
        $this->work($bulk);
        $this->assertSame(ContactStatus::Unsubscribed, $lead->refresh()->contact_status, 'Consent withdrawn survives re-import.');
    }

    public function test_the_existing_verify_upload_still_works(): void
    {
        Queue::fake();
        $this->post('/bulks', ['type' => 'verify', 'file' => UploadedFile::fake()->createWithContent('v.csv', "a@x.test\nb@x.test\n"), 'column' => 1])->assertRedirect();

        $bulk = Bulk::firstOrFail();
        $this->assertSame('verify', $bulk->type);
        $this->assertSame(Bulk::STATUS_PENDING, $bulk->status);
        $this->assertSame(2, $bulk->items()->count());
        Queue::assertPushed(ProcessBulkJob::class);
    }
}
