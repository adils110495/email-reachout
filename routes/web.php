<?php

use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PlatformController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Dashboard
Route::get('/', [LeadController::class, 'index'])->name('leads.index');

// Search / find leads
Route::post('/search', [LeadController::class, 'search'])->name('leads.search');

// Manually add a lead
Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');

// Compose modal — fetch AI-generated subject + body (JSON)
Route::get('/leads/{id}/compose', [LeadController::class, 'compose'])->name('leads.compose');

// Send outreach email (accepts subject + body from compose modal)
Route::post('/send-email/{id}', [LeadController::class, 'sendEmail'])->name('leads.send-email');

// View single lead (JSON — for modal)
Route::get('/leads/{id}', [LeadController::class, 'show'])->name('leads.show');

// Show sent email details (JSON)
Route::get('/leads/{id}/sent-email', [LeadController::class, 'sentEmail'])->name('leads.sent-email');

// Download attachment
Route::get('/leads/attachment/download', [LeadController::class, 'downloadAttachment'])->name('leads.attachment.download');

// Edit & update single lead
Route::get('/leads/{id}/edit', [LeadController::class, 'edit'])->name('leads.edit');
Route::put('/leads/{id}', [LeadController::class, 'update'])->name('leads.update');

// Mark lead as sent manually
Route::post('/leads/{id}/mark-sent', [LeadController::class, 'markSent'])->name('leads.mark-sent');

// Delete single lead
Route::delete('/leads/{id}', [LeadController::class, 'destroy'])->name('leads.destroy');

// Bulk actions
Route::post('/leads/bulk-delete', [LeadController::class, 'bulkDelete'])->name('leads.bulk-delete');
Route::post('/leads/bulk-status', [LeadController::class, 'bulkStatus'])->name('leads.bulk-status');

// Export CSV
Route::get('/export', [LeadController::class, 'export'])->name('leads.export');

// Settings — Email Templates CRUD
Route::get('/settings/templates',           [EmailTemplateController::class, 'index'])->name('templates.index');
Route::get('/settings/templates/create',    [EmailTemplateController::class, 'create'])->name('templates.create');
Route::post('/settings/templates',          [EmailTemplateController::class, 'store'])->name('templates.store');
Route::get('/settings/templates/{id}/edit', [EmailTemplateController::class, 'edit'])->name('templates.edit');
Route::put('/settings/templates/{id}',      [EmailTemplateController::class, 'update'])->name('templates.update');
Route::delete('/settings/templates/{id}',   [EmailTemplateController::class, 'destroy'])->name('templates.destroy');
Route::post('/settings/templates/{id}/toggle', [EmailTemplateController::class, 'toggleStatus'])->name('templates.toggle');

// Platforms CRUD
Route::get('/settings/platforms',          [PlatformController::class, 'index'])->name('platforms.index');
Route::post('/settings/platforms',         [PlatformController::class, 'store'])->name('platforms.store');
Route::put('/settings/platforms/{id}',     [PlatformController::class, 'update'])->name('platforms.update');
Route::delete('/settings/platforms/{id}',  [PlatformController::class, 'destroy'])->name('platforms.destroy');

// Templates JSON for compose modal dropdown
Route::get('/api/templates', fn() => response()->json(
    \App\Models\EmailTemplate::select('id','name','subject','body')->where('status', 'active')->latest()->get()
))->name('api.templates');
