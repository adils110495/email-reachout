/* ── Mobile sidebar behaviour ──────────────────────────────────────────────
   The theme already slides the sidebar in and out below 768px: deznav-init.js
   sets data-sidebar-style="overlay", and custom.js toggles .menu-toggle on
   #main-wrapper when the hamburger is tapped.

   What it does not ship is a way to dismiss it. The panel opens over the
   content at z-index 3 with nothing behind it, so on a phone the only way back
   is to find the hamburger again. This adds the three dismissals a drawer is
   expected to have:

     * tap the backdrop
     * follow a menu link (a leaf item - parents only expand)
     * press Escape

   It also locks page scrolling while the drawer is open, so a swipe moves the
   menu rather than the page behind it.

   Everything here is scoped to the overlay breakpoint. At desktop width
   .menu-toggle means "collapse to icons", which must keep working untouched.
*/
(function () {
    'use strict';

    var OVERLAY_MAX_WIDTH = 767.98;

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    ready(function () {
        var wrapper = document.getElementById('main-wrapper');
        var sidebar = document.querySelector('.deznav');

        if (!wrapper || !sidebar) return;

        // The drawer only exists below the overlay breakpoint. Above it,
        // .menu-toggle is the desktop "mini sidebar" state - leave it alone.
        function isOverlay() {
            return window.matchMedia('(max-width: ' + OVERLAY_MAX_WIDTH + 'px)').matches;
        }

        function isOpen() {
            return wrapper.classList.contains('menu-toggle');
        }

        function close() {
            if (!isOpen()) return;

            wrapper.classList.remove('menu-toggle');

            // custom.js drives the hamburger's animation off this class, so it
            // has to come off too or the icon stays in its "close" shape.
            var hamburger = document.querySelector('.hamburger');
            if (hamburger) hamburger.classList.remove('is-active');

            document.body.classList.remove('mobile-nav-open');
        }

        function syncScrollLock() {
            document.body.classList.toggle(
                'mobile-nav-open',
                isOverlay() && isOpen(),
            );
        }

        // The theme owns the open action, and it toggles the class on the same
        // click we are listening to. Reading the state on the next frame gets
        // the value after that toggle, not before it.
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.nav-control')) return;
            window.requestAnimationFrame(syncScrollLock);
        });

        // Backdrop. It is a ::before on #main-wrapper (see app-custom.css), so
        // there is no element of its own to bind - a tap outside the sidebar
        // and outside the hamburger is the backdrop by definition.
        document.addEventListener('click', function (e) {
            if (!isOverlay() || !isOpen()) return;
            if (e.target.closest('.deznav')) return;
            if (e.target.closest('.nav-control')) return;

            close();
        });

        // Following a link should dismiss the drawer. A parent item with
        // children only expands its submenu, so it must not.
        sidebar.addEventListener('click', function (e) {
            if (!isOverlay()) return;

            var link = e.target.closest('a');
            if (!link) return;
            if (link.classList.contains('has-arrow')) return;
            if (link.getAttribute('href') === 'javascript:void(0);') return;

            close();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOverlay() && isOpen()) close();
        });

        // Rotating to landscape can cross the breakpoint with the drawer open;
        // the desktop layout must not inherit a locked body.
        window.addEventListener('resize', function () {
            if (!isOverlay()) document.body.classList.remove('mobile-nav-open');
            else syncScrollLock();
        });
    });
}());
