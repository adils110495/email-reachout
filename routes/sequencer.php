<?php

use App\Http\Controllers\Sequencer\AccountController;
use App\Http\Controllers\Sequencer\ActivityController;
use App\Http\Controllers\Sequencer\EnrollmentController;
use App\Http\Controllers\Sequencer\PasswordResetController;
use App\Http\Controllers\Sequencer\SequenceController;
use App\Http\Controllers\Sequencer\SequenceStepController;
use App\Http\Controllers\Sequencer\UnsubscribeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sequences - web routes (loaded with the "web" middleware group)
|--------------------------------------------------------------------------
| Only what did not exist before lives here: sequences, steps, enrollments, the
| activity timeline, unsubscribe, profile and password reset.
|
| Everything else a sequence needs is the existing module, in routes/web.php:
|   contacts -> Leads            lists -> Categories        templates -> Email Templates
|   sending accounts -> Mail Settings      sent emails -> Email Activity
|   imports + bulk actions -> Bulks        open / click tracking -> TrackingController
*/

// ── Public: called by recipients, no session ───────────────────────────────
Route::middleware('throttle:unsubscribe')->group(function () {
    // GET only shows a confirmation page (link scanners and prefetchers must not unsubscribe anyone);
    // the POST changes state. RFC 8058 one-click clients POST here directly.
    Route::get('/unsubscribe/{token}', [UnsubscribeController::class, 'show'])->name('sequencer.unsubscribe.show');
    Route::post('/unsubscribe/{token}', [UnsubscribeController::class, 'perform'])->name('sequencer.unsubscribe.perform');
});

// ── Password reset (guests) ────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
});

// ── Signed-in users only ───────────────────────────────────────────────────
Route::middleware('auth')->prefix('outreach')->name('outreach.')->group(function () {

    // Profile + personal defaults
    Route::get('/profile', [AccountController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [AccountController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [AccountController::class, 'password'])->middleware('throttle:6,1')->name('profile.password');
    Route::put('/profile/settings', [AccountController::class, 'settings'])->name('profile.settings');

    // Sequences
    Route::post('/sequences/{sequence}/activate', [SequenceController::class, 'activate'])->name('sequences.activate');
    Route::post('/sequences/{sequence}/pause', [SequenceController::class, 'pause'])->name('sequences.pause');
    Route::post('/sequences/{sequence}/resume', [SequenceController::class, 'resume'])->name('sequences.resume');
    Route::post('/sequences/{sequence}/archive', [SequenceController::class, 'archive'])->name('sequences.archive');
    Route::post('/sequences/{sequence}/duplicate', [SequenceController::class, 'duplicate'])->name('sequences.duplicate');
    Route::get('/sequences/{sequence}/analytics', [SequenceController::class, 'analytics'])->name('sequences.analytics');
    Route::resource('sequences', SequenceController::class);

    // Steps
    Route::get('/sequences/{sequence}/steps/create', [SequenceStepController::class, 'create'])->name('steps.create');
    Route::post('/sequences/{sequence}/steps', [SequenceStepController::class, 'store'])->name('steps.store');
    Route::get('/steps/{step}/edit', [SequenceStepController::class, 'edit'])->name('steps.edit');
    Route::put('/steps/{step}', [SequenceStepController::class, 'update'])->name('steps.update');
    Route::delete('/steps/{step}', [SequenceStepController::class, 'destroy'])->name('steps.destroy');
    Route::post('/steps/{step}/duplicate', [SequenceStepController::class, 'duplicate'])->name('steps.duplicate');
    Route::post('/steps/{step}/move', [SequenceStepController::class, 'move'])->name('steps.move');
    Route::get('/steps/{step}/preview', [SequenceStepController::class, 'preview'])->name('steps.preview');

    // Enrollments (leads in a sequence)
    Route::get('/sequences/{sequence}/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
    Route::post('/sequences/{sequence}/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
    Route::post('/sequences/{sequence}/enrollments/bulk', [EnrollmentController::class, 'bulk'])->name('enrollments.bulk');
    Route::post('/enrollments/{enrollment}/pause', [EnrollmentController::class, 'pause'])->name('enrollments.pause');
    Route::post('/enrollments/{enrollment}/resume', [EnrollmentController::class, 'resume'])->name('enrollments.resume');
    Route::post('/enrollments/{enrollment}/remove', [EnrollmentController::class, 'remove'])->name('enrollments.remove');
    Route::post('/enrollments/{enrollment}/retry', [EnrollmentController::class, 'retry'])->name('enrollments.retry');

    // Activity timeline
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
});
