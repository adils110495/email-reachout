<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ContactListController;
use App\Http\Controllers\Api\V1\EmailAccountController;
use App\Http\Controllers\Api\V1\EmailLogController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\SequenceController;
use App\Http\Controllers\Api\V1\SequenceStepController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API  -  /api/v1   (Laravel Sanctum bearer tokens; see docs/sequencer/api.md)
|--------------------------------------------------------------------------
*/

Route::name('api.')->group(function () {

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:api-login')->name('login');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('me');

        // Contacts
        Route::apiResource('contacts', ContactController::class);

        // Lists + membership
        Route::apiResource('lists', ContactListController::class);
        Route::get('/lists/{list}/contacts', [ContactListController::class, 'contacts'])->name('lists.contacts');
        Route::post('/lists/{list}/contacts', [ContactListController::class, 'addContacts'])->name('lists.contacts.add');
        Route::delete('/lists/{list}/contacts', [ContactListController::class, 'removeContacts'])->name('lists.contacts.remove');

        // Sequences + lifecycle
        Route::apiResource('sequences', SequenceController::class);
        Route::post('/sequences/{sequence}/activate', [SequenceController::class, 'activate'])->name('sequences.activate');
        Route::post('/sequences/{sequence}/pause', [SequenceController::class, 'pause'])->name('sequences.pause');
        Route::post('/sequences/{sequence}/resume', [SequenceController::class, 'resume'])->name('sequences.resume');
        Route::post('/sequences/{sequence}/archive', [SequenceController::class, 'archive'])->name('sequences.archive');
        Route::get('/sequences/{sequence}/analytics', [SequenceController::class, 'analytics'])->name('sequences.analytics');

        // Steps
        Route::get('/sequences/{sequence}/steps', [SequenceStepController::class, 'index'])->name('steps.index');
        Route::post('/sequences/{sequence}/steps', [SequenceStepController::class, 'store'])->name('steps.store');
        Route::post('/sequences/{sequence}/steps/reorder', [SequenceStepController::class, 'reorder'])->name('steps.reorder');
        Route::get('/steps/{step}', [SequenceStepController::class, 'show'])->name('steps.show');
        Route::match(['put', 'patch'], '/steps/{step}', [SequenceStepController::class, 'update'])->name('steps.update');
        Route::delete('/steps/{step}', [SequenceStepController::class, 'destroy'])->name('steps.destroy');
        Route::post('/steps/{step}/duplicate', [SequenceStepController::class, 'duplicate'])->name('steps.duplicate');

        // Enrollments
        Route::get('/sequences/{sequence}/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::post('/sequences/{sequence}/enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show'])->name('enrollments.show');
        Route::post('/enrollments/{enrollment}/pause', [EnrollmentController::class, 'pause'])->name('enrollments.pause');
        Route::post('/enrollments/{enrollment}/resume', [EnrollmentController::class, 'resume'])->name('enrollments.resume');
        Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');

        // Email logs + (read-only) accounts
        Route::get('/email-logs', [EmailLogController::class, 'index'])->name('logs.index');
        Route::get('/email-logs/{log}', [EmailLogController::class, 'show'])->name('logs.show');
        Route::get('/email-accounts', [EmailAccountController::class, 'index'])->name('accounts.index');
        Route::get('/email-accounts/{account}', [EmailAccountController::class, 'show'])->name('accounts.show');
    });
});
