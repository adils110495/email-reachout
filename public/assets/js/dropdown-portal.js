/**
 * Dropdown portal - keeps page dropdowns from being clipped.
 *
 * Row-action menus sit inside .table-responsive (and cards), whose overflow
 * clips an open menu - worst with one or two rows, where there is no room
 * below the button. While open, the menu is moved to <body> so no scroller,
 * card or stacking context can clip or cover it. Bootstrap keeps its own
 * reference to the menu, so Popper still positions it against the button.
 * On close it goes back in place - unless an AJAX swap replaced the row.
 *
 * Applies to every dropdown inside the page content. Left alone: the header
 * menus, anything in a modal, and bootstrap-select pickers - their styles,
 * z-index or plugin logic depend on where the menu sits in the DOM.
 */
(function () {
    'use strict';

    var SCOPE   = '.content-body';
    var EXCLUDE = '.modal, .bootstrap-select';
    var ITEMS   = '.dropdown-item:not(.disabled):not(:disabled)';

    function isPortalToggle(toggle) {
        return toggle.closest(SCOPE) && ! toggle.closest(EXCLUDE);
    }

    document.addEventListener('show.bs.dropdown', function (e) {
        var toggle = e.target;
        if (! isPortalToggle(toggle)) return;

        var instance = bootstrap.Dropdown.getInstance(toggle);
        var menu     = instance && instance._menu;
        if (! menu || menu.parentElement === document.body) return;

        menu._portalHome   = menu.parentElement;
        menu._portalToggle = toggle;
        document.body.appendChild(menu);
    });

    document.addEventListener('hidden.bs.dropdown', function (e) {
        var instance = bootstrap.Dropdown.getInstance(e.target);
        var menu     = instance && instance._menu;
        if (! menu || ! menu._portalHome) return;

        if (menu._portalHome.isConnected) {
            menu._portalHome.appendChild(menu);
        } else {
            menu.remove(); // its row was replaced while the menu was open
        }
        menu._portalHome = menu._portalToggle = null;
    });

    // Bootstrap finds a menu's toggle by DOM position, which is wrong once the
    // menu lives under <body> (it would pick the first toggle on the page).
    // Handle Escape and the arrow keys for portalled menus here instead.
    document.addEventListener('keydown', function (e) {
        var menu = e.target.closest && e.target.closest('.dropdown-menu');
        if (! menu || ! menu._portalToggle) return;
        if (['Escape', 'ArrowUp', 'ArrowDown'].indexOf(e.key) === -1) return;

        e.preventDefault();
        e.stopPropagation();

        var toggle = menu._portalToggle;
        if (e.key === 'Escape') {
            bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
            toggle.focus();
            return;
        }

        var items = Array.prototype.filter.call(menu.querySelectorAll(ITEMS), function (el) {
            return el.offsetParent !== null; // visible only
        });
        if (! items.length) return;

        var i    = items.indexOf(e.target);
        var next = e.key === 'ArrowDown' ? i + 1 : i - 1;
        items[(next + items.length) % items.length].focus();
    }, true);
})();
