/* ── AJAX filter regions ───────────────────────────────────────────────────
   Shared by the Email Templates / Platforms / Categories / Addresses lists.
   (Leads keeps its own copy: it also drives pagination, a bulk toolbar and a
   background poller, none of which belong here.)

   Opt a card in by marking it data-ajax-root:

       <div class="card" data-ajax-root>
           <input type="text" data-live-filter>                  <- hides rows on screen
           <input type="text" data-search-param="q">             <- server-side search
           <select class="select2" data-param="status">          <- server-side filter
           <div class="ajax-region">
               <div class="ajax-content"> …rows with data-search… </div>
           </div>
       </div>

   Changing a data-param select refetches the current URL with an
   X-Requested-With header; the server answers with just the .ajax-content
   partial, which is swapped in place instead of reloading the page. pushState
   keeps the URL shareable and the back button working. The live filter only
   hides rows already on screen, and is re-applied after every swap.

   Three things inside the swapped region are handled by delegation, because a
   listener bound at init time would not survive the swap:

     * pagination links       .ajax-region .pagination a
     * a rows-per-page select .ajax-region [data-param] (e.g. per_page)
     * a data-search-param input debounces, then filters server-side - use it
       instead of data-live-filter when the match must span every page, not
       just the rows currently rendered.
*/
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function initRoot(root) {
        const region = root.querySelector('.ajax-region');
        if (! region) return;

        const search = root.querySelector('[data-live-filter]');

        // Only the filter bar's own selects are bound (and Select2-enhanced)
        // here. Anything inside .ajax-region is replaced on every swap, so it is
        // handled by delegation further down - binding it here as well would
        // fire two loads per change.
        const selects = Array.prototype.filter.call(
            root.querySelectorAll('select[data-param]'),
            function (select) { return ! region.contains(select); },
        );

        // The loader is identical on every list, so build it here rather than
        // repeating the markup in each view. It sits over the table only, so
        // the filters above stay usable while a request is in flight.
        const loader = document.createElement('div');
        loader.className = 'ajax-loader';
        loader.hidden    = true;
        loader.innerHTML =
            '<div class="spinner-border text-primary" role="status">' +
            '<span class="visually-hidden">Loading…</span></div>';
        region.insertBefore(loader, region.firstChild);

        let request = null; // in-flight request, so a fast second change wins

        function applyLiveFilter() {
            const term = (search ? search.value : '').toLowerCase().trim();

            region.querySelectorAll('[data-search]').forEach(function (row) {
                const haystack = row.getAttribute('data-search') || '';
                row.classList.toggle('d-none', term !== '' && ! haystack.includes(term));
            });
        }

        function setLoading(on) {
            region.classList.toggle('is-loading', on);
            loader.hidden = ! on;
        }

        function load(url, push) {
            if (request) request.abort();

            const controller = new AbortController();
            request = controller;
            setLoading(true);

            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            })
                .then(function (r) {
                    if (! r.ok) throw new Error('Server error: ' + r.status);
                    return r.text();
                })
                .then(function (html) {
                    const wrap = region.querySelector('.ajax-content');
                    if (! wrap) return;

                    wrap.outerHTML = html;

                    if (push !== false) window.history.pushState({ ajaxUrl: url }, '', url);

                    // The record-count badge lives in the card header, outside the
                    // swapped node, so it would otherwise keep the old number.
                    // The partial carries the new total on its root.
                    const counter = root.querySelector('[data-ajax-total]');
                    const fresh   = region.querySelector('.ajax-content[data-total]');

                    if (counter && fresh) {
                        counter.textContent = Number(fresh.dataset.total || 0).toLocaleString();
                    }

                    // A freshly swapped table is unfiltered - re-apply the typed term.
                    applyLiveFilter();
                })
                .catch(function (err) {
                    // An aborted request was superseded - leave the loader up for the new one.
                    if (err.name === 'AbortError') return;
                    alert('Could not load this list. Please try again.');
                })
                .finally(function () {
                    if (request === controller) {
                        request = null;
                        setLoading(false);
                    }
                });
        }

        // Write one filter into the URL and reload the region. An empty value
        // drops the parameter entirely, so a cleared filter leaves a clean URL.
        function setParam(name, value) {
            const url = new URL(window.location.href);

            if (value) {
                url.searchParams.set(name, value);
            } else {
                url.searchParams.delete(name);
            }
            url.searchParams.delete('page'); // a narrower list starts at page 1

            load(url.toString(), true);
        }

        function applyFilter(select, chosen) {
            // select2:select carries the chosen item and is authoritative even if
            // the underlying <select> has not been written yet; otherwise read
            // the element (a plain native change).
            const value = (chosen === null || chosen === undefined ? select.value : chosen) || '';

            setParam(select.dataset.param, value);
        }

        if (search) search.addEventListener('keyup', applyLiveFilter);

        // Server-side search. Debounced so a typed word costs one request, not
        // one per keystroke; the in-flight abort in load() covers the rest.
        // Any number of inputs may carry data-search-param (e.g. the Min / Max
        // boxes of a range filter); each one is bound on its own.
        root.querySelectorAll('input[data-search-param]').forEach(function (serverSearch) {
            let timer = null;

            serverSearch.addEventListener('input', function () {
                window.clearTimeout(timer);
                timer = window.setTimeout(function () {
                    setParam(serverSearch.dataset.searchParam, serverSearch.value.trim());
                }, 400);
            });

            // Enter should not submit whatever form the input may sit in.
            serverSearch.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                window.clearTimeout(timer);
                setParam(serverSearch.dataset.searchParam, serverSearch.value.trim());
            });
        });

        // Pagination links live inside .ajax-content and are replaced on every
        // swap, so bind by delegation on the region that survives it.
        region.addEventListener('click', function (e) {
            const link = e.target.closest('.pagination a');
            if (! link || ! link.href) return;

            e.preventDefault();
            load(link.href, true);
        });

        // Same for a rows-per-page select rendered inside the table footer.
        region.addEventListener('change', function (e) {
            const select = e.target.closest('[data-param]');
            if (! select) return;

            applyFilter(select, null);
        });

        // Back / forward buttons replay the same AJAX load.
        window.addEventListener('popstate', function () {
            load(window.location.href, false);
        });

        // Lets a page refresh this list after it has changed the data behind it
        // - the Finder saves a lead and wants the table below to show it:
        //
        //     root.dispatchEvent(new CustomEvent('ajax-filters:reload'));
        //
        // Reloads the current URL without a pushState, so filters and page
        // number stay exactly where the user left them.
        root.addEventListener('ajax-filters:reload', function () {
            load(window.location.href, false);
        });

        const $         = window.jQuery;
        const hasSelect2 = !! ($ && $.fn && $.fn.select2);

        selects.forEach(function (select) {
            if (hasSelect2) {
                // Order matters. Select2 fires a `change` on init while it applies
                // the placeholder; binding first would let that init event clear
                // the filter the page was just loaded with. So: initialise first,
                // then bind. The try/catch keeps the binding happening even if
                // Select2 itself fails.
                try {
                    $(select).select2({
                        width: '100%',
                        // 0 = always show the search box, however short the list.
                        minimumResultsForSearch: 0,
                        placeholder: select.dataset.placeholder || '',
                        allowClear: false,
                    });

                    $(select).on('select2:select change', function (e) {
                        applyFilter(select, e && e.params && e.params.data ? e.params.data.id : null);
                    });

                    return;
                } catch (err) {
                    console.warn('Select2 unavailable - falling back to the native select.', err);
                }
            }

            select.addEventListener('change', function () { applyFilter(select, null); });
        });
    }

    ready(function () {
        document.querySelectorAll('[data-ajax-root]').forEach(initRoot);
    });
}());
