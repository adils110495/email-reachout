<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\BulkController;
use App\Http\Controllers\CashLeadController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailActivityController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\FinderController;
use App\Http\Controllers\GmbLeadController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MailSettingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\VerifierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ── Public ─────────────────────────────────────────────────────────────────

// Open-tracking pixel. Hit by the recipient's mail client, which has no
// session - it must stay outside the auth group or opens are never recorded.
Route::get('/t/o/{token}.gif', [TrackingController::class, 'open'])->name('track.open');

// Sign in / sign up. Guests only; a signed-in user is sent to the dashboard.
Route::middleware('guest')->group(function () {
    Route::get('/login',   [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',  [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
    Route::get('/signup',  [AuthController::class, 'showSignup'])->name('signup');
    Route::post('/signup', [AuthController::class, 'signup'])->middleware('throttle:10,1')->name('signup.attempt');
});

// ── Signed-in users only: everything below ─────────────────────────────────
Route::middleware('auth')->group(function () {

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');


// Dashboard — the landing page
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

// Finder — search the lead database and discover new addresses
Route::get('/finder',         [FinderController::class, 'index'])->name('finder.index');
Route::get('/finder/export',  [FinderController::class, 'export'])->name('finder.export');
Route::post('/finder/search', [FinderController::class, 'search'])->name('finder.search');
Route::post('/finder/save',   [FinderController::class, 'store'])->name('finder.store');

// Verifier — check deliverability
Route::get('/verifier',              [VerifierController::class, 'index'])->name('verifier.index');
Route::get('/verifier/export',       [VerifierController::class, 'export'])->name('verifier.export');
Route::post('/verifier/verify',      [VerifierController::class, 'verify'])->name('verifier.verify');
Route::post('/verifier/verify-many', [VerifierController::class, 'verifyMany'])->name('verifier.verify-many');
Route::post('/verifier/clear',       [VerifierController::class, 'clear'])->name('verifier.clear');
Route::delete('/verifier/{id}',      [VerifierController::class, 'destroy'])->name('verifier.destroy')->whereNumber('id');

// Bulks — CSV-driven verification / discovery
Route::get('/bulks',                  [BulkController::class, 'index'])->name('bulks.index');
Route::post('/bulks',                 [BulkController::class, 'store'])->name('bulks.store');
Route::get('/bulks/{id}',             [BulkController::class, 'show'])->name('bulks.show')->whereNumber('id');
Route::get('/bulks/{id}/status',      [BulkController::class, 'status'])->name('bulks.status')->whereNumber('id');
Route::get('/bulks/{id}/export',      [BulkController::class, 'export'])->name('bulks.export')->whereNumber('id');
Route::post('/bulks/{id}/cancel',     [BulkController::class, 'cancel'])->name('bulks.cancel')->whereNumber('id');
Route::post('/bulks/{id}/retry',      [BulkController::class, 'retry'])->name('bulks.retry')->whereNumber('id');
Route::delete('/bulks/{id}',          [BulkController::class, 'destroy'])->name('bulks.destroy')->whereNumber('id');

// Cash Leads — leads we expect to turn into paying customers
Route::get('/cash-leads',                  [CashLeadController::class, 'index'])->name('cash-leads.index');
Route::get('/cash-leads/export',           [CashLeadController::class, 'export'])->name('cash-leads.export');
Route::post('/cash-leads',                 [CashLeadController::class, 'store'])->name('cash-leads.store');
Route::put('/cash-leads/{id}',             [CashLeadController::class, 'update'])->name('cash-leads.update')->whereNumber('id');
Route::delete('/cash-leads/{id}',          [CashLeadController::class, 'destroy'])->name('cash-leads.destroy')->whereNumber('id');
Route::post('/cash-leads/from-lead/{id}',  [CashLeadController::class, 'fromLead'])->name('cash-leads.from-lead')->whereNumber('id');
Route::post('/cash-leads/from-gmb/{id}',   [CashLeadController::class, 'fromGmb'])->name('cash-leads.from-gmb')->whereNumber('id');

// Notifications — raised by background jobs, polled by the header bell
Route::get('/notifications',        [NotificationController::class, 'index'])->name('notifications.index');
Route::post('/notifications/read',  [NotificationController::class, 'read'])->name('notifications.read');

// GMB Leads — businesses with a Google Business Profile but no website
Route::get('/gmb-leads',             [GmbLeadController::class, 'index'])->name('gmb-leads.index');
Route::get('/gmb-leads/export',      [GmbLeadController::class, 'export'])->name('gmb-leads.export');
Route::post('/gmb-leads/search',     [GmbLeadController::class, 'search'])->name('gmb-leads.search');
Route::delete('/gmb-leads/{id}',     [GmbLeadController::class, 'destroy'])->name('gmb-leads.destroy')->whereNumber('id');

// Leads
Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');

// Search / find leads
Route::post('/search', [LeadController::class, 'search'])->name('leads.search');

// Manually add a lead
Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');

// Compose modal — fetch AI-generated subject + body (JSON)
Route::get('/leads/{id}/compose', [LeadController::class, 'compose'])->name('leads.compose');

// Scrape lead website in background to get real company/contact name
Route::get('/leads/{id}/scrape-contact', [LeadController::class, 'scrapeContact'])->name('leads.scrape-contact');

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

// Email Activity — opened / replied / not opened for every email we sent
Route::get('/email-activity',                [EmailActivityController::class, 'index'])->name('email-activity.index');
Route::post('/email-activity/check-replies', [EmailActivityController::class, 'checkReplies'])->name('email-activity.check-replies');


// Settings — Email Templates CRUD
Route::get('/settings/templates/export',    [EmailTemplateController::class, 'export'])->name('templates.export');
Route::get('/settings/templates',           [EmailTemplateController::class, 'index'])->name('templates.index');
Route::get('/settings/templates/create',    [EmailTemplateController::class, 'create'])->name('templates.create');
Route::post('/settings/templates',          [EmailTemplateController::class, 'store'])->name('templates.store');
Route::get('/settings/templates/{id}/edit', [EmailTemplateController::class, 'edit'])->name('templates.edit');
Route::put('/settings/templates/{id}',      [EmailTemplateController::class, 'update'])->name('templates.update');
Route::delete('/settings/templates/{id}',   [EmailTemplateController::class, 'destroy'])->name('templates.destroy');
Route::post('/settings/templates/{id}/toggle', [EmailTemplateController::class, 'toggleStatus'])->name('templates.toggle');

// Platforms CRUD
Route::get('/settings/platforms/export',   [PlatformController::class, 'export'])->name('platforms.export');
Route::get('/settings/platforms',          [PlatformController::class, 'index'])->name('platforms.index');
Route::post('/settings/platforms',         [PlatformController::class, 'store'])->name('platforms.store');
Route::put('/settings/platforms/{id}',     [PlatformController::class, 'update'])->name('platforms.update');
Route::delete('/settings/platforms/{id}',  [PlatformController::class, 'destroy'])->name('platforms.destroy');

// Categories CRUD
Route::get('/settings/categories/export',  [CategoryController::class, 'export'])->name('categories.export');
Route::get('/settings/categories',         [CategoryController::class, 'index'])->name('categories.index');
Route::post('/settings/categories',        [CategoryController::class, 'store'])->name('categories.store');
Route::put('/settings/categories/{id}',    [CategoryController::class, 'update'])->name('categories.update');
Route::delete('/settings/categories/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy');

// Addresses CRUD
Route::get('/settings/addresses/export',   [AddressController::class, 'export'])->name('addresses.export');
Route::get('/settings/addresses',          [AddressController::class, 'index'])->name('addresses.index');
Route::post('/settings/addresses',         [AddressController::class, 'store'])->name('addresses.store');
Route::put('/settings/addresses/{id}',     [AddressController::class, 'update'])->name('addresses.update');
Route::delete('/settings/addresses/{id}',  [AddressController::class, 'destroy'])->name('addresses.destroy');

// Mail Settings — SMTP (sending) and IMAP (Sent folder copy)
Route::get('/settings/mail',             [MailSettingController::class, 'index'])->name('mail-settings.index');
Route::put('/settings/mail/smtp',        [MailSettingController::class, 'updateSmtp'])->name('mail-settings.smtp');
Route::put('/settings/mail/imap',        [MailSettingController::class, 'updateImap'])->name('mail-settings.imap');
Route::post('/settings/mail/test/{type}', [MailSettingController::class, 'test'])->name('mail-settings.test');

// Branding — admin panel logo / icon and the logo in outgoing emails
Route::get('/settings/branding',          [BrandingController::class, 'index'])->name('branding.index');
Route::put('/settings/branding',          [BrandingController::class, 'update'])->name('branding.update');
Route::put('/settings/branding/footer',   [BrandingController::class, 'updateFooter'])->name('branding.footer');
Route::delete('/settings/branding/{key}', [BrandingController::class, 'reset'])->name('branding.reset');

// Templates JSON for compose modal dropdown
Route::get('/api/templates', fn() => response()->json(
    \App\Models\EmailTemplate::select('id','name','subject','body','attachments')->where('status', 'active')->latest()->get()
))->name('api.templates');

}); // end auth group
