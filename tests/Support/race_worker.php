<?php

/*
 * A separate OS process that boots the application and runs the send pipeline for the
 * given enrollment ids. Used by ConcurrencyTest to create REAL contention (distinct
 * processes, distinct MySQL connections) rather than simulated interleavings.
 *
 *   php tests/Support/race_worker.php <goFile> <sendLogFile> <id,id,...>
 *
 * It waits for <goFile> to appear so all workers start at the same instant.
 */

use App\Sequencer\Mail\EmailProviderManager;
use App\Sequencer\Services\SequenceEmailProcessor;
use App\Services\ImapService;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\FakeEmailProvider;
use Tests\Support\FakeImapService;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $goFile, $sendLog, $ids] = $argv;

// No real mail servers: SMTP is recorded to a shared file, IMAP (Sent-folder copy) is a no-op.
FakeEmailProvider::$logFile = $sendLog;
app(EmailProviderManager::class)->extend('smtp', fn () => new FakeEmailProvider);
app()->instance(ImapService::class, new FakeImapService);

$processor = app(SequenceEmailProcessor::class);

while (! file_exists($goFile)) {
    usleep(300);
}

foreach (array_filter(explode(',', $ids)) as $id) {
    try {
        echo $processor->process((int) $id)->value."\n";
    } catch (Throwable $e) {
        echo 'ERROR '.get_class($e).': '.$e->getMessage()."\n";
    }
}
