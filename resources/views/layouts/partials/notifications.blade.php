{{-- Notification polling + toasts. Background jobs write to app_notifications;
     this polls every few seconds, lights up the bell and pops a toast for each
     notification that arrived since the page loaded. --}}
<div class="toast-container position-fixed top-0 end-0 p-3" id="notifToasts" style="z-index:1100;"></div>

<script>
(function () {
    'use strict';

    const URL_LIST = @json(route('notifications.index'));
    const URL_READ = @json(route('notifications.read'));
    const CSRF     = @json(csrf_token());
    const POLL_MS  = 8000;

    const badge = document.getElementById('notifBadge');
    const list  = document.getElementById('notifList');
    const toasts = document.getElementById('notifToasts');
    if (! badge || ! list) return;

    const ICONS = {
        success: ['bi-check-circle-fill', 'text-success'],
        error:   ['bi-exclamation-triangle-fill', 'text-danger'],
        info:    ['bi-info-circle-fill', 'text-primary'],
    };

    let lastSeenId = null; // null until the first poll, so old notifications don't all toast on page load

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function post(body) {
        return fetch(URL_READ, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(body || {}),
        });
    }

    function renderItem(n) {
        const icon = ICONS[n.type] || ICONS.info;
        const link = n.url ? ' href="' + esc(n.url) + '"' : '';
        return '<a' + link + ' class="d-flex gap-2 px-3 py-2 border-bottom text-decoration-none text-body notif-item'
            + (n.read ? '' : ' bg-light') + '" data-id="' + n.id + '">'
            + '<i class="bi ' + icon[0] + ' ' + icon[1] + ' mt-1"></i>'
            + '<div class="flex-grow-1"><div class="fw-semibold">' + esc(n.title) + '</div>'
            + (n.message ? '<div class="fs-13 text-muted">' + esc(n.message) + '</div>' : '')
            + '<div class="fs-12 text-muted">' + esc(n.time) + '</div></div></a>';
    }

    function showToast(n) {
        const icon = ICONS[n.type] || ICONS.info;
        const el = document.createElement('div');
        el.className = 'toast align-items-center';
        el.setAttribute('role', 'alert');
        el.innerHTML = '<div class="d-flex"><div class="toast-body"><i class="bi ' + icon[0] + ' ' + icon[1] + ' me-2"></i>'
            + '<strong>' + esc(n.title) + '</strong>'
            + (n.message ? '<div class="fs-13 mt-1">' + esc(n.message) + '</div>' : '')
            + (n.url ? '<a class="fs-13" href="' + esc(n.url) + '">View</a>' : '')
            + '</div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
        toasts.appendChild(el);
        const t = new bootstrap.Toast(el, { delay: 10000 });
        el.addEventListener('hidden.bs.toast', function () { el.remove(); });
        t.show();
    }

    function poll() {
        fetch(URL_LIST, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (! data) return;

                badge.textContent = data.unread > 99 ? '99+' : data.unread;
                badge.classList.toggle('d-none', data.unread === 0);

                list.innerHTML = data.items.length
                    ? data.items.map(renderItem).join('')
                    : '<div class="text-center text-muted fs-13 py-4">No notifications yet.</div>';

                const newest = data.items.length ? data.items[0].id : 0;

                if (lastSeenId !== null) {
                    data.items
                        .filter(function (n) { return n.id > lastSeenId && ! n.read; })
                        .reverse()
                        .forEach(function (n) {
                            showToast(n);
                            // Lets a page react to a job finishing (e.g. re-enable its button).
                            document.dispatchEvent(new CustomEvent('app:notification', { detail: n }));
                        });
                }

                lastSeenId = Math.max(lastSeenId || 0, newest);
            })
            .catch(function () { /* transient network error - try again next tick */ });
    }

    document.getElementById('notifMarkAll').addEventListener('click', function () {
        post({}).then(poll);
    });

    // Opening a notification marks just that one read.
    list.addEventListener('click', function (e) {
        const item = e.target.closest('.notif-item');
        if (item) post({ id: item.dataset.id });
    });

    poll();
    setInterval(poll, POLL_MS);
})();
</script>
