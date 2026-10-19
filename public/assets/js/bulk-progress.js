/**
 * Bulk send progress poller - the WhatsApp page and Email Activity.
 *
 * While the list's progress panel ([data-batch-running="1"], rendered by the
 * page's table partial) shows a running bulk send, poll its status URL and
 * swap the list in (ajax-filters.js) whenever it moves. Stops on its own: when
 * the send finishes, after three failed polls, and it skips polls while the
 * tab is hidden.
 */
(function () {
    'use strict';

    const root   = document.querySelector('[data-ajax-root]');
    const region = root && root.querySelector('.ajax-region');
    if (! region) return;

    let timer    = null;
    let failures = 0;

    function panel() {
        return region.querySelector('[data-batch-running]');
    }

    function stop() {
        window.clearInterval(timer);
        timer = null;
    }

    function tick() {
        const p = panel();
        if (! p || p.dataset.batchRunning !== '1') return stop();
        if (document.hidden) return;

        fetch(p.dataset.batchStatusUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) {
                if (! r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                failures = 0;
                // The swap brings a fresh panel; when the send has finished it
                // says so, and the next tick stops.
                if (String(data.done) !== p.dataset.batchDone || ! data.running) {
                    root.dispatchEvent(new CustomEvent('ajax-filters:reload'));
                }
            })
            .catch(function () {
                if (++failures >= 3) stop();
            });
    }

    function startIfRunning() {
        const p = panel();
        if (p && p.dataset.batchRunning === '1' && ! timer) {
            failures = 0;
            timer = window.setInterval(tick, 5000);
        }
    }

    // On load, and again whenever a swap brings in a running bulk send.
    new MutationObserver(startIfRunning).observe(region, { childList: true });
    startIfRunning();
})();
