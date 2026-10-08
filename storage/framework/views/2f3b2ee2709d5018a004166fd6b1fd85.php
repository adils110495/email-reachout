<?php $__env->startSection('title', 'Finder — AI Client Finder'); ?>
<?php $__env->startSection('page-title', 'Finder'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item active" aria-current="page">Finder</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>


<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header py-3">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-search me-2 text-primary"></i>Find Email Addresses</h4>
                    <p class="mb-0 fs-13">
                        Search a company website for published addresses, or work out the likely
                        address for a named person.
                    </p>
                </div>
            </div>

            <div class="card-body">
                
                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="modeDomainTab" data-mode="domain" type="button" role="tab"
                                aria-selected="true" aria-controls="finderForm">
                            <i class="bi bi-globe2 me-1"></i>Domain search
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="modePersonTab" data-mode="person" type="button" role="tab"
                                aria-selected="false" aria-controls="finderForm">
                            <i class="bi bi-person-badge me-1"></i>Person search
                        </button>
                    </li>
                </ul>

                <form id="finderForm" novalidate>
                    <input type="hidden" name="mode" id="finderMode" value="domain">

                    <div class="row g-3 align-items-start">
                        
                        <div class="col-12 col-md-3 d-none" id="personNameField">
                            <label class="form-label" for="finderName">Full name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="finderName" name="name"
                                   placeholder="e.g. Jamie Rivera" maxlength="120" autocomplete="off">
                            <div class="invalid-feedback" id="finderNameError"></div>
                        </div>

                        <div class="col-12 col-md-5" id="domainField">
                            <label class="form-label" for="finderDomain">Company domain <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="finderDomain" name="domain"
                                   placeholder="e.g. example.com" maxlength="255" autocomplete="off" required autofocus>
                            <div class="invalid-feedback" id="finderDomainError"></div>
                            <div class="form-text" id="finderHint">
                                The site's homepage and its contact page are scanned for published addresses.
                            </div>
                        </div>

                        
                        <div class="col-12 col-md-4" id="categoryField">
                            <label class="form-label" for="saveCategory">Save under category</label>
                            <select id="saveCategory" name="category_id" class="form-select">
                                <option value="">— None —</option>
                                <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($category->id); ?>"><?php echo e($category->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                            <div class="form-text">Found addresses are added to your leads automatically.</div>
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label d-none d-md-block">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100" id="finderSubmit">
                                <span class="spinner-border spinner-border-sm d-none me-1" id="finderSpinner"></span>
                                <i class="bi bi-lightning-charge-fill me-1" id="finderIcon"></i>Find
                            </button>
                        </div>
                    </div>
                </form>

                
                <div id="finderResult" class="mt-4 d-none"></div>
            </div>
        </div>
    </div>
</div>


<div class="row">
    <div class="col-xl-12">
        
        <div class="card" data-ajax-root>

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title">
                        <i class="bi bi-list-ul me-2 text-primary"></i>Finder Results
                        <span class="badge badge-primary light ms-1" data-ajax-total><?php echo e(number_format($results->total())); ?></span>
                    </h4>
                    <p class="mb-0 fs-13">
                        Every address the Finder has turned up — including the ones not saved as leads.
                    </p>
                </div>
                <div class="clearfix">
                    <a href="<?php echo e(route('finder.export', request()->query())); ?>" class="btn btn-light btn-sm m-1">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                    <a href="<?php echo e(route('leads.index')); ?>" class="btn btn-light btn-sm m-1">
                        <i class="bi bi-people me-1"></i>Lead Database
                    </a>
                </div>
            </div>

            
            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">

                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <label class="form-label" for="finderSearch">Search</label>
                        
                        <input type="text" id="finderSearch" class="form-control" data-search-param="q"
                               value="<?php echo e($filters['q']); ?>" placeholder="Email, domain, company or person…" autocomplete="off">
                    </div>

                    <div class="col-6 col-md-3 col-xl-2 mb-3">
                        <label class="form-label" for="finderStatus">Result</label>
                        <select id="finderStatus" class="form-select select2" data-param="status" data-placeholder="All Results">
                            <option value="">All Results</option>
                            <?php $__currentLoopData = \App\Models\EmailVerification::STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($value); ?>" <?php echo e($filters['status'] === $value ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-3 col-xl-2 mb-3">
                        
                        <label class="form-label" for="finderModeFilter">Found by</label>
                        <select id="finderModeFilter" class="form-select select2" data-param="mode" data-placeholder="Any Search">
                            <option value="">Any Search</option>
                            <option value="domain" <?php echo e($filters['mode'] === 'domain' ? 'selected' : ''); ?>>Domain search</option>
                            <option value="person" <?php echo e($filters['mode'] === 'person' ? 'selected' : ''); ?>>Person search</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-3 col-xl-2 mb-3">
                        <label class="form-label" for="finderSaved">Saved</label>
                        <select id="finderSaved" class="form-select select2" data-param="saved" data-placeholder="Any">
                            <option value="">Any</option>
                            <option value="yes" <?php echo e($filters['saved'] === 'yes' ? 'selected' : ''); ?>>Saved as lead</option>
                            <option value="no"  <?php echo e($filters['saved'] === 'no'  ? 'selected' : ''); ?>>Not saved</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-3 col-xl-2 mb-3">
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="<?php echo e(route('finder.index')); ?>" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            
            <div class="ajax-region">
                <?php echo $__env->make('finder._results', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>

        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(function () {
    'use strict';

    const form       = document.getElementById('finderForm');
    const modeInput  = document.getElementById('finderMode');
    const nameField     = document.getElementById('personNameField');
    const nameInput     = document.getElementById('finderName');
    const domainField   = document.getElementById('domainField');
    const categoryField = document.getElementById('categoryField');
    const domainIn   = document.getElementById('finderDomain');
    const hint       = document.getElementById('finderHint');
    const panel      = document.getElementById('finderResult');
    const submitBtn  = document.getElementById('finderSubmit');
    const spinner    = document.getElementById('finderSpinner');
    const icon       = document.getElementById('finderIcon');

    // Route templates and the CSRF token are resolved server-side so this
    // script never hardcodes a URL.
    const searchUrl      = <?php echo json_encode(route('finder.search'), 15, 512) ?>;
    const saveUrl        = <?php echo json_encode(route('finder.store'), 15, 512) ?>;
    const csrf           = document.querySelector('meta[name="csrf-token"]').content;
    const categorySelect = document.getElementById('saveCategory');

    // The Finder Results card below. A search or a save changes what it should
    // show, so it is refreshed in place rather than left stale until a reload.
    const resultsTable = document.querySelector("[data-ajax-root]");

    function refreshResultsTable() {
        if (resultsTable) resultsTable.dispatchEvent(new CustomEvent("ajax-filters:reload"));
    }

    const HINTS = {
        domain: "The site's homepage and its contact page are scanned for published addresses.",
        person: 'No site is scanned — the common corporate name patterns are generated and scored.',
    };

    // ── Mode switch ──────────────────────────────────────────────────────────
    document.querySelectorAll('[data-mode]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            const mode = tab.dataset.mode;

            document.querySelectorAll('[data-mode]').forEach(function (t) {
                const on = t === tab;
                t.classList.toggle('active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });

            modeInput.value = mode;
            nameField.classList.toggle('d-none', mode !== 'person');

            // Keep the row at exactly 12 columns in both modes: domain search is
            // 5 + 4 + 3, person search adds a 3-column name field and narrows the
            // two selects to match.
            const person = mode === 'person';
            domainField.className   = person ? 'col-12 col-md-3' : 'col-12 col-md-5';
            categoryField.className = person ? 'col-12 col-md-3' : 'col-12 col-md-4';

            hint.textContent = HINTS[mode];

            clearErrors();
            panel.classList.add('d-none');
            panel.innerHTML = '';
        });
    });

    // ── Helpers ──────────────────────────────────────────────────────────────
    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);
        return div.innerHTML;
    }

    function clearErrors() {
        [domainIn, nameInput].forEach(function (input) { input.classList.remove('is-invalid'); });
    }

    function showFieldError(input, errorId, message) {
        input.classList.add('is-invalid');
        document.getElementById(errorId).textContent = message;
    }

    function setLoading(on) {
        submitBtn.disabled = on;
        spinner.classList.toggle('d-none', ! on);
        icon.classList.toggle('d-none', on);
    }

    // Progress hints shown while a domain search works through the site.
    var hintTimers = [];

    function clearHints() {
        hintTimers.forEach(window.clearTimeout);
        hintTimers = [];
    }

    function showHint(text) {
        const hintEl = document.getElementById('finderSlowHint');
        if (! hintEl) return;

        hintEl.textContent = text;
        hintEl.classList.remove('d-none');
    }

    function render(html) {
        panel.innerHTML = html;
        panel.classList.remove('d-none');
    }

    function renderState(iconName, title, detail) {
        render(
            '<div class="empty-state">' +
                '<i class="bi ' + iconName + ' empty-state-icon"></i>' +
                '<p class="mb-1 fw-semibold">' + esc(title) + '</p>' +
                '<p class="fs-13 mb-0">' + esc(detail) + '</p>' +
            '</div>',
        );
    }

    function colourFor(status) {
        if (status === 'valid')   return 'success';
        if (status === 'invalid') return 'danger';
        if (status === 'risky')   return 'warning';
        return 'secondary';
    }

    // ── Results ──────────────────────────────────────────────────────────────
    function renderResults(data) {
        if (! data.candidates.length) {
            if (data.mode === 'domain' && ! data.scraped) {
                renderState('bi-wifi-off', 'That website could not be reached',
                    'It may be offline, blocking automated requests, or the domain may be wrong.');
            } else {
                renderState('bi-inbox', 'No addresses found',
                    'Nothing is published on this site. Try a person search if you know who you are looking for.');
            }
            return;
        }

        const heading = data.mode === 'person'
            ? esc(data.person) + ' at ' + esc(data.domain)
            : (data.company ? esc(data.company) + ' · ' + esc(data.domain) : esc(data.domain));

        const savedEmail = data.saved ? String(data.saved.email).toLowerCase() : null;

        const rows = data.candidates.map(function (c) {
            const alreadySaved = savedEmail === String(c.email).toLowerCase();
            const badges = [
                '<span class="badge badge-' + colourFor(c.status) + ' light">' + esc(c.status) + '</span>',
                c.guessed ? '<span class="badge badge-secondary light">guessed</span>' : '',
                c.role    ? '<span class="badge badge-dark light">generic</span>' : '',
                c.pattern ? '<span class="badge badge-primary light">' + esc(c.pattern) + '</span>' : '',
            ].join(' ');

            return '' +
                '<div class="result-row">' +
                    '<div class="result-main">' +
                        '<div class="result-addr">' + esc(c.email) + '</div>' +
                        '<div class="fs-13 text-muted">' + esc(c.reason) + '</div>' +
                        '<div class="mt-1 d-flex flex-wrap gap-1">' + badges + '</div>' +
                    '</div>' +
                    '<div class="d-flex align-items-center gap-3 flex-shrink-0">' +
                        '<div class="text-end">' +
                            '<div class="fs-13 text-muted mb-1">' + esc(c.score) + '%</div>' +
                            '<div class="score-meter" style="width:4.5rem">' +
                                '<span class="bg-' + colourFor(c.status) + '" style="width:' + esc(c.score) + '%"></span>' +
                            '</div>' +
                        '</div>' +
                        (alreadySaved
                            ? '<button type="button" class="btn btn-sm btn-success" disabled>' +
                                  '<i class="bi bi-check-lg me-1"></i>Saved' +
                              '</button>'
                            : '<button type="button" class="btn btn-sm btn-primary js-save-lead" ' +
                                      'data-email="' + esc(c.email) + '">' +
                                  '<i class="bi bi-plus-lg me-1"></i>Save' +
                              '</button>') +
                    '</div>' +
                '</div>';
        }).join('');

        // Says what was filed automatically, so "Saved" on a row the user never
        // pressed is explained rather than surprising.
        const savedNote = data.saved
            ? '<div class="alert alert-success d-flex align-items-start gap-2 py-2 mb-3" role="alert">' +
                  '<i class="bi bi-check-circle-fill mt-1"></i>' +
                  '<div class="fs-13">' + esc(data.saved.message) + '</div>' +
              '</div>'
            : '';

        render('' +
            '<div class="result-panel">' +
                '<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">' +
                    '<div>' +
                        '<div class="result-email">' + heading + '</div>' +
                        '<div class="fs-13 text-muted">' + data.candidates.length + ' address(es), best match first.</div>' +
                    '</div>' +
                '</div>' +
                savedNote +
                rows +
            '</div>');

        // Company name is carried into the saved lead when the site provided one.
        panel.dataset.domain  = data.domain;
        panel.dataset.company = data.company || '';
    }

    // ── Submit ───────────────────────────────────────────────────────────────
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearErrors();

        const mode   = modeInput.value;
        const domain = domainIn.value.trim();
        const name   = nameInput.value.trim();

        if (! domain) {
            showFieldError(domainIn, 'finderDomainError', 'Enter a company domain.');
            return;
        }

        if (mode === 'person' && ! name) {
            showFieldError(nameInput, 'finderNameError', 'Enter the person’s name.');
            return;
        }

        setLoading(true);
        render('<div class="inline-loading flex-column text-center">' +
                   '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Searching…</span></div>' +
                   '<span>' + (mode === 'person' ? 'Working out likely addresses…' : 'Scanning the website…') + '</span>' +
                   '<span class="fs-13 d-none" id="finderSlowHint"></span>' +
               '</div>');

        // A domain search reads the homepage, then the contact/careers paths,
        // then follows the links those pages contain - up to a minute on a slow
        // site. Say so, rather than leaving a bare spinner that looks stuck.
        clearHints();

        if (mode === 'domain') {
            hintTimers.push(window.setTimeout(function () {
                showHint('Checking the contact and careers pages…');
            }, 7000));

            hintTimers.push(window.setTimeout(function () {
                showHint('Following links on the pages found so far. This can take up to a minute.');
            }, 20000));
        }

        fetch(searchUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                mode: mode,
                domain: domain,
                name: name,
                // Carried so the automatic save files the lead under the chosen
                // category - a lead with none is invisible in the Leads module.
                category_id: categorySelect.value || null,
            }),
        })
            .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
            .then(function (res) {
                if (! res.ok || ! res.body.ok) {
                    // 422 from validation carries Laravel's errors bag.
                    const message = res.body.message
                        || (res.body.errors && Object.values(res.body.errors)[0][0])
                        || 'The lookup could not be completed.';
                    renderState('bi-exclamation-triangle', 'Search failed', message);
                    return;
                }

                renderResults(res.body);

                // The search filed a lead of its own - show it in the table below.
                refreshResultsTable();
            })
            .catch(function () {
                renderState('bi-wifi-off', 'Connection problem',
                    'The request did not reach the server. Check your connection and try again.');
            })
            .finally(function () {
                // Cancel any hint still pending - the panel has been replaced.
                clearHints();
                setLoading(false);
            });
    });

    // ── Save an address as a lead ────────────────────────────────────────────
    function saveLead(btn, payload) {
        const original = btn.innerHTML;

        btn.disabled  = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        })
            .then(function (r) { return r.json(); })
            .then(function (body) {
                if (! body.ok) throw new Error(body.message || 'Save failed');

                btn.className = 'btn btn-sm btn-success';
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Saved';
                btn.title     = body.message;

                refreshResultsTable();
            })
            .catch(function () {
                btn.disabled  = false;
                btn.className = 'btn btn-sm btn-danger';
                btn.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Retry';
                window.setTimeout(function () {
                    btn.className = 'btn btn-sm btn-primary';
                    btn.innerHTML = original;
                }, 2500);
            });
    }

    // Result panel buttons. Delegated: rebuilt on every search.
    panel.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-save-lead');
        if (! btn) return;

        saveLead(btn, {
            email:        btn.dataset.email,
            domain:       panel.dataset.domain,
            company_name: panel.dataset.company,
            category_id:  categorySelect ? categorySelect.value : null,
        });
    });

    // Results table buttons. Delegated on the region, which survives the AJAX
    // swap that replaces the rows.
    if (resultsTable) {
        resultsTable.addEventListener('click', function (e) {
            const btn = e.target.closest('.js-save-row');
            if (! btn) return;

            saveLead(btn, {
                email:        btn.dataset.email,
                domain:       btn.dataset.domain,
                company_name: btn.dataset.company || null,
                category_id:  categorySelect ? categorySelect.value : null,
            });
        });
    }
}());
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/finder/index.blade.php ENDPATH**/ ?>