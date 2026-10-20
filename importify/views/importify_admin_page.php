<?php
if (!defined('ABSPATH')) { exit; }
if (!current_user_can('manage_options')) { wp_die(esc_html__('You do not have sufficient permissions.', 'importify')); }

/**
 * Importify settings page, 1.0.20 (Claude Design "Importify WooCommerce Plugin Settings").
 * Header, account strip, one card with seven checks, footer actions. System fonts and inline SVG icons.
 * Inputs: $importify_status (importify_get_status) and the importify_check option, both set by
 * importify_admin_menu_page_html().
 */

$api_key = get_option('importify_api_key');
$ic = get_option('importify_check');
if (!is_array($ic)) $ic = array();
$st = isset($importify_status) && is_array($importify_status) ? $importify_status : array();
$st = array_merge(array('account' => 'no_answer', 'message' => '', 'connected' => 'no', 'reachable' => '', 'http_code' => 0, 'plan' => '', 'account_email' => ''), $st);

$host = wp_parse_url(get_site_url(), PHP_URL_HOST);
$host = $host ? preg_replace('/^www\./i', '', $host) : get_site_url();
$fw_ip = '<code>162.243.171.163</code>';
$guide_connection = 'https://www.importify.com/help/importify-woocommerce-store-connection-troubleshooting-guide/';
// Importify reads the store with its keys: then nothing in front of the store is blocking it, and the
// firewall/Cloudflare detection is skipped (importify_admin_menu_page_html).
$reachable = ($st['account'] === 'ok' && $st['connected'] === 'yes' && $st['reachable'] !== 'no');

/* ── Icons (24px stroke paths from the design) ── */
$importify_icons = array(
    'ssl'  => 'M7 11V8a5 5 0 0110 0v3M5 11h14v10H5z',
    'woo'  => 'M3 4h2l2.4 11h10.2L20 7H6.2M9 20h.01M17 20h.01',
    'perm' => 'M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1',
    'php'  => 'M8 7l-5 5 5 5M16 7l5 5-5 5M13.5 5l-3 14',
    'fw'   => 'M12 3l8 3v6c0 4.5-3.4 8.2-8 9-4.6-.8-8-4.5-8-9V6z',
    'cf'   => 'M7 18h10.5a4 4 0 00.6-7.95A6 6 0 006.3 9.5 4.3 4.3 0 007 18z',
    'api'  => 'M9 3v5M15 3v5M6 8h12v3a6 6 0 01-12 0zM12 17v4',
);
$importify_pill_icons = array('pass' => 'M3.5 8.3l3 3 6-6.6', 'fail' => 'M4.5 4.5l7 7M11.5 4.5l-7 7', 'info' => 'M8 7.2v4.6M8 4.4v.1');

function importify_svg_external() {
    return '<svg width="11" height="11" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 2H2v8h8V7M7 2h3v3M10 2L5.5 6.5"/></svg>';
}

/* ── The seven checks: [id, name, state pass|fail|info, description, pill (null = Passed/Failed), steps, guide] ── */
$checks = array();

$ssl_ok = isset($ic['ssl_active']) && $ic['ssl_active'] === "true";
$checks[] = array('ssl', 'SSL Certificate', $ssl_ok ? 'pass' : 'fail',
    $ssl_ok ? sprintf('HTTPS is active on %s.', $host) : 'This site doesn’t load over HTTPS, which Importify needs to send products.', null,
    array('Contact your hosting provider to install an SSL certificate.', 'Make sure your site loads with <code>https://</code>.', 'Update the WordPress and Site Address in Settings → General to use https://.'),
    $guide_connection);

$woo_ok = !empty($ic['woocomerce_installed']);
$woo_version = !empty($ic['woo_version']) ? $ic['woo_version'] : '';
$checks[] = array('woo', 'WooCommerce', $woo_ok ? 'pass' : 'fail',
    $woo_ok ? ($woo_version ? sprintf('WooCommerce %s is installed and active.', $woo_version) : 'WooCommerce is installed and active.') : 'WooCommerce isn’t active on this site.', null,
    array('Go to Plugins → Add New.', 'Search for “WooCommerce” and click Install.', 'Activate it and complete the setup wizard, then re-run the checks.'),
    'https://www.importify.com/help/how-do-i-install-the-importify-app-on-my-e-commerce-store/');

$p = isset($ic['permalinks']) && is_string($ic['permalinks']) ? $ic['permalinks'] : '';
$p_ok = strlen($p) > 0;
$checks[] = array('perm', 'Permalinks', $p_ok ? 'pass' : 'fail',
    $p_ok ? (($p === '/%postname%/' ? 'Post name' : 'Custom') . ' permalinks work with the WooCommerce REST API.') : 'Plain permalinks block the WooCommerce REST API.', null,
    array('Go to Settings → Permalinks.', 'Choose “Post name”. Any option except Plain works.', 'Click Save Changes, then re-run the checks.'),
    'https://www.importify.com/help/how-do-i-set-up-the-correct-permalink-structure-for-woocommerce-in-wordpress/');

$php_ok = isset($ic['php_ok']) ? (bool)$ic['php_ok'] : version_compare(PHP_VERSION, '7.2', '>=');
$php_v = implode('.', array_slice(explode('.', phpversion()), 0, 2));
$checks[] = array('php', 'PHP Version', $php_ok ? 'pass' : 'fail',
    $php_ok ? sprintf('PHP %s is running (7.2 or later required).', $php_v) : sprintf('PHP %s is too old (7.2 or later required).', $php_v), null,
    array('Contact your hosting provider to upgrade PHP.', 'Most hosting dashboards let you switch the PHP version.', 'PHP 8.0 or newer is recommended.'),
    '');

$fw = !empty($ic['firewall_active']);
$fw_name = !empty($ic['firewall_name']) ? $ic['firewall_name'] : 'A security plugin';
$checks[] = array('fw', 'Security Firewall', $fw ? 'info' : 'pass',
    $fw ? sprintf('%s detected. It may slow or block imports.', $fw_name) : ($reachable ? 'Importify reaches your store, so no firewall is blocking it.' : 'No security plugin that could block Importify was found.'),
    $fw ? 'Detected' : null,
    array('Open your security plugin’s allowlist (Wordfence: Firewall → Manage → Allowlisted IPs).', 'Add Importify’s IP: ' . $fw_ip, 'Save, then re-run the checks.'),
    $guide_connection);

$cf = !empty($ic['cloudflare_active']);
$checks[] = array('cf', 'Cloudflare', $cf ? 'info' : 'pass',
    $cf ? 'Cloudflare detected. Its bot protection can block Importify.' : ($reachable ? 'Importify reaches your store, so Cloudflare isn’t blocking it.' : 'Cloudflare isn’t in front of this site.'),
    $cf ? 'Detected' : null,
    array('In Cloudflare, open Security → WAF.', 'Allow Importify’s IP: ' . $fw_ip, 'Save the rule, then re-run the checks.'),
    'https://www.importify.com/help/how-to-fix-importify-connection-caused-by-cloudflare/');

// API Connection, from Importify's own answer (importify_get_status).
$le = get_option('importify_last_error');
$code = (int)$st['http_code'];
$reconnect_steps = array('Go to Plugins → Installed Plugins.', 'Click Deactivate under Importify.', 'Click Activate. The plugin reconnects to your Importify account.', 'Come back here and re-run the checks.');
if ($st['account'] === 'no_answer') {
    $api = array('fail', 'This site couldn’t reach Importify’s server.', 'No answer',
        array('Make sure your host allows outgoing connections to <code>app.importify.net</code>.', 'Try again in a few minutes with Re-run System Checks.'));
    $conn = array('fail', 'No answer');
} elseif ($st['account'] === 'not_found') {
    $api = array('fail', 'Importify couldn’t find an account for this store’s plugin key.', 'Account not found', $reconnect_steps);
    $conn = array('fail', 'Account not found');
} elseif ($st['connected'] !== 'yes') {
    $api = array('fail', 'Importify doesn’t have access to your WooCommerce store yet.', 'Not connected',
        array('Make sure the WooCommerce REST API is enabled.', 'Go to Plugins → Installed Plugins, click Deactivate under Importify, then Activate.', 'Come back here and re-run the checks.'));
    $conn = array('fail', 'Not connected');
} elseif ($st['reachable'] === 'no') {
    $blocked_steps = array('This usually means a firewall, security plugin or Cloudflare rule blocked the request.', 'Allow Importify’s IP ' . $fw_ip . ' in your firewall and Cloudflare (see the rows above).', 'If it still fails, ask your host to allow requests to <code>/wp-json/wc/v3</code>, then re-run the checks.');
    $api = $code > 0
        ? array('fail', sprintf('Your server refused Importify’s request with HTTP %d.', $code), sprintf('Blocked · HTTP %d', $code), $blocked_steps)
        : array('fail', 'Importify couldn’t reach your store.', 'Unreachable', $blocked_steps);
    $conn = array('fail', $code > 0 ? 'Blocked' : 'Unreachable');
} else {
    $api = array('pass', $st['account_email'] !== '' ? sprintf('Linked to the Importify account %s.', $st['account_email']) : 'Linked to your Importify account.', 'Connected', array());
    $conn = array('ok', 'Connected');
}
if ($le && $api[0] === 'fail') $api[3][] = 'Last error: <em>' . esc_html(substr($le, 0, 100)) . '</em>';
$checks[] = array('api', 'API Connection', $api[0], $api[1], $api[2], $api[3], $api[0] === 'fail' ? $guide_connection : '');

/* ── Preview overrides (support tool, admin-only): ?error1=preview … ?error7=preview, ?preview=all, ?preview=mixed ── */
$importify_preview_map = array(
    0 => array('fail', 'This site doesn’t load over HTTPS, which Importify needs to send products.', null),
    1 => array('fail', 'WooCommerce isn’t active on this site.', null),
    2 => array('fail', 'Plain permalinks block the WooCommerce REST API.', null),
    3 => array('fail', 'PHP 5.6 is too old (7.2 or later required).', null),
    4 => array('info', 'Wordfence Security detected. It may slow or block imports.', 'Detected'),
    5 => array('info', 'Cloudflare detected. Its bot protection can block Importify.', 'Detected'),
    6 => array('fail', 'Importify couldn’t find an account for this store’s plugin key.', 'Account not found'),
);
$importify_preview = isset($_GET['preview']) ? sanitize_key(wp_unslash($_GET['preview'])) : '';
$importify_any_preview = in_array($importify_preview, array('all', 'mixed'), true);
foreach ($checks as $idx => $c) {
    $flag = 'error' . ($idx + 1);
    $force = $importify_preview === 'all'
        || ($importify_preview === 'mixed' && in_array($idx, array(0, 5), true))
        || (isset($_GET[$flag]) && sanitize_key(wp_unslash($_GET[$flag])) === 'preview');
    if ($force) {
        $importify_any_preview = true;
        list($checks[$idx][2], $checks[$idx][3], $checks[$idx][4]) = $importify_preview_map[$idx];
        if ($idx === 6) { $checks[$idx][5] = $reconnect_steps; $checks[$idx][6] = $guide_connection; $conn = array('fail', 'Account not found'); }
    }
}

/* ── Counts and header copy ── */
$fails = 0; $infos = 0; $passed = 0; $first_fail = null;
foreach ($checks as $c) {
    if ($c[2] === 'fail') { $fails++; if ($first_fail === null) $first_fail = $c[0]; }
    elseif ($c[2] === 'info') $infos++;
    else $passed++;
}
$ok = ($fails === 0);
if ($ok) {
    $headline = 'Your store is ready for Importify';
    $sub = $infos ? 'All required checks passed. Blue items are worth a look but won’t stop imports.' : 'All 7 checks passed. You can start importing products.';
    $summary = $infos ? sprintf('%d passed · %d to review', $passed, $infos) : '7 of 7 passed';
} else {
    $headline = $fails === 1 ? '1 issue needs fixing before you import' : sprintf('%d issues need fixing before you import', $fails);
    $sub = 'Fix the red items, then re-run the checks.' . ($infos ? ' Blue items are worth a look but won’t stop imports.' : '');
    $summary = sprintf('%d passed · %d failed', $passed, $fails) . ($infos ? sprintf(' · %d to review', $infos) : '');
}

/* ── Links ── */
$dashboard_url = $api_key ? IMPORTIFY_API_URL . '/woocomerce/login-by-token?token=' . rawurlencode($api_key) : 'https://app.importify.net/dashboard/';
$finder_url = $api_key ? $dashboard_url . '&to=product-finder' : $dashboard_url;
$links = array(
    'Chrome extension' => 'https://chromewebstore.google.com/detail/importify/mcldgfkcaapkhccpbhjjchamjfbpbgcp',
    'Product Finder' => $finder_url,
    'Help Center' => 'https://www.importify.com/help/',
);
$recheck_url = wp_nonce_url(admin_url('admin.php?page=importify&importify_recheck=1'), 'importify_recheck');
$step_tags = array('code' => array(), 'em' => array(), 'strong' => array());
?>
<div id="importify-setup-wrap" class="wrap">
<div class="imf-page">
    <hr class="wp-header-end"><?php /* WordPress places admin notices here, not inside our header */ ?>

    <?php if ($importify_any_preview): ?>
    <div class="imf-preview" role="note">
        <span>Preview mode: showing simulated data. <a href="<?php echo esc_url(remove_query_arg(array('preview', 'error1', 'error2', 'error3', 'error4', 'error5', 'error6', 'error7'))); ?>">Exit preview</a></span>
    </div>
    <?php endif; ?>

    <header class="imf-header">
        <?php if ($ok): ?>
        <span class="imf-badge imf-badge--ok"><svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3.5 8.3l3 3 6-6.6"/></svg>Importify plugin activated</span>
        <?php else: ?>
        <span class="imf-badge imf-badge--warn"><svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 1.8l6.6 11.7H1.4z"/><path d="M8 6.3v3.2M8 11.6v.1"/></svg>Setup requires attention</span>
        <?php endif; ?>
        <h1 class="imf-title"><?php echo esc_html($headline); ?></h1>
        <p class="imf-sub"><?php echo esc_html($sub); ?></p>
    </header>

    <div class="imf-strip">
        <span class="imf-strip__item">
            <span class="imf-strip__label">Store</span>
            <span class="imf-strip__store"><?php echo esc_html($host); ?></span>
            <span class="imf-conn imf-conn--<?php echo $conn[0] === 'ok' ? 'ok' : 'fail'; ?>"><?php echo esc_html($conn[1]); ?></span>
        </span>
        <?php if ($st['plan'] !== ''): ?>
        <span class="imf-strip__item">
            <span class="imf-strip__label">Plan</span>
            <span class="imf-plan"><?php echo esc_html($st['plan']); ?></span>
        </span>
        <?php endif; ?>
        <span class="imf-strip__spacer"></span>
        <nav class="imf-links" aria-label="Importify links">
            <?php foreach ($links as $label => $url): ?>
            <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($label); ?><?php echo importify_svg_external(); ?><span class="imf-sr">(opens in new tab)</span></a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="imf-card">
        <div class="imf-card__head">
            <span class="imf-card__title">System checks</span>
            <span class="imf-card__summary"><?php echo esc_html($summary); ?></span>
        </div>
        <?php foreach ($checks as $c):
            list($id, $name, $state, $desc, $pill, $steps, $guide) = $c;
            $fixable = ($state !== 'pass') && !empty($steps);
            $open = $fixable && $id === $first_fail;
            if ($pill === null) $pill = $state === 'fail' ? 'Failed' : 'Passed';
            $panel = 'importify-fix-' . $id;
        ?>
        <div class="imf-row<?php echo $state === 'pass' ? '' : ' imf-row--' . $state; ?>">
            <div class="imf-row__main">
                <div class="imf-row__text">
                    <span class="imf-tile"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?php echo esc_attr($importify_icons[$id]); ?>"/></svg></span>
                    <div class="imf-row__copy">
                        <h2 class="imf-row__name"><?php echo esc_html($name); ?></h2>
                        <p class="imf-row__desc"><?php echo esc_html($desc); ?></p>
                    </div>
                </div>
                <div class="imf-row__actions">
                    <span class="imf-pill<?php echo $state === 'pass' ? '' : ' imf-pill--' . $state; ?>"><svg width="11" height="11" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="<?php echo esc_attr($importify_pill_icons[$state]); ?>"/></svg><?php echo esc_html($pill); ?></span>
                    <?php if ($fixable): ?>
                    <button type="button" class="imf-fix-toggle" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr($panel); ?>">How to fix<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 4.5l3 3 3-3"/></svg></button>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($fixable): ?>
            <div class="imf-fix" id="<?php echo esc_attr($panel); ?>"<?php echo $open ? '' : ' hidden'; ?>>
                <ol class="imf-steps">
                    <?php foreach (array_values($steps) as $n => $step): ?>
                    <li><span class="imf-steps__n"><?php echo (int)$n + 1; ?></span><span><?php echo wp_kses($step, $step_tags); ?></span></li>
                    <?php endforeach; ?>
                </ol>
                <?php if ($guide): ?>
                <a class="imf-guide" href="<?php echo esc_url($guide); ?>" target="_blank" rel="noopener noreferrer">View full guide<?php echo importify_svg_external(); ?><span class="imf-sr">(opens in new tab)</span></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="imf-actions">
        <?php if ($ok): ?>
        <a class="imf-btn imf-btn--primary" href="<?php echo esc_url($dashboard_url); ?>" target="_blank" rel="noopener noreferrer">Proceed to Importify Dashboard<svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 7h8M7.5 3.5L11 7l-3.5 3.5"/></svg></a>
        <?php else: ?>
        <a class="imf-btn imf-btn--secondary" href="<?php echo esc_url($dashboard_url); ?>" target="_blank" rel="noopener noreferrer">Skip and Proceed to Dashboard</a>
        <a class="imf-btn imf-btn--primary" href="<?php echo esc_url($recheck_url); ?>" data-imf-recheck><svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M13.5 8A5.5 5.5 0 113 5M3 1.8V5h3.2"/></svg>Re-run System Checks</a>
        <?php endif; ?>
    </div>

    <p class="imf-version">Importify Plugin v<?php echo esc_html(IMPORTIFY_VERSION); ?></p>

</div>
</div>
