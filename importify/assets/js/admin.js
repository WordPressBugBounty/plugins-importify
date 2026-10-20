/* Importify settings page (1.0.20). */

/* "How to fix" toggle: delegated so it survives any DOM re-render. */
(function () {
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('#importify-setup-wrap .imf-fix-toggle');
        if (!trigger) return;
        var panel = document.getElementById(trigger.getAttribute('aria-controls'));
        if (!panel) return;
        var open = trigger.getAttribute('aria-expanded') !== 'true';
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        panel.hidden = !open;
    });
})();

/* Re-run System Checks: show the loading state while the page reloads with fresh checks. */
(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#importify-setup-wrap [data-imf-recheck]');
        if (!btn || btn.getAttribute('aria-busy') === 'true') return;
        btn.setAttribute('aria-busy', 'true');
        btn.setAttribute('aria-disabled', 'true');
        btn.innerHTML = '<span class="imf-spinner" aria-hidden="true"></span>Checking…';
    });
})();
