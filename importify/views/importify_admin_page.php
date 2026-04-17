<?php
if (!defined('ABSPATH')) { exit; }
if (!current_user_can('manage_options')) { wp_die(__('You do not have sufficient permissions.')); }

$api_key = get_option('importify_api_key');
$ic = get_option('importify_check');
if (!is_array($ic)) $ic = array();

/**
 * Material Symbols icons used (rendered via Google Fonts icon font).
 * The admin view hides all .material-symbols-outlined via `visibility: hidden`
 * until the font finishes loading and the `fonts-ready` class is added to <html>.
 * This avoids the "flash of raw ligature text" before the font has downloaded.
 *
 *   check_circle / verified_user / shopping_cart / link / code
 *   security / cloud_done / api / task_alt / arrow_forward / refresh
 *   dangerous / link_off / shield / timer_off / warning
 */

// Build checks array
$checks = array();
$ssl_ok = isset($ic['ssl_active']) && $ic['ssl_active'] === "true";
$checks[] = array('name'=>'SSL Certificate','desc'=>'Secure connection for API product importing','icon_pass'=>'verified_user','icon_fail'=>'dangerous','ok'=>$ssl_ok,'value'=>$ssl_ok?'Active':'Missing','type'=>$ssl_ok?'pass':'fail',
    'steps'=>array('Contact your hosting provider to install an SSL certificate.','Ensure your site loads with <code>https://</code>.','Update WordPress URL in Settings → General to use https://.'),'url'=>'https://help.importify.com/article/526/importify-woocommerce-store-connection-troubleshooting-guide');

$woo_ok = !empty($ic['woocomerce_installed']);
$wv = isset($ic['woo_version']) && $ic['woo_version'] ? 'v'.esc_html($ic['woo_version']) : ($woo_ok ? 'Installed' : '');
$checks[] = array('name'=>'WooCommerce','desc'=>'Core compatibility verified for dropshipping','icon_pass'=>'shopping_cart','icon_fail'=>'dangerous','ok'=>$woo_ok,'value'=>$woo_ok?$wv:'Not Installed','type'=>$woo_ok?'pass':'fail',
    'steps'=>array('Go to WordPress → Plugins → Add New.','Search for "WooCommerce" and click Install.','Activate the plugin and complete the setup wizard.'),'url'=>'https://help.importify.com/article/532/how-to-install-the-woocommerce-plugin');

$p = isset($ic['permalinks']) ? $ic['permalinks'] : '';
$p_ok = is_string($p) && strlen($p) > 0;
$checks[] = array('name'=>'Permalinks','desc'=>'Post name structure required for REST API','icon_pass'=>'link','icon_fail'=>'link_off','ok'=>$p_ok,'value'=>$p_ok?'Post name':'Plain','type'=>$p_ok?'pass':'fail',
    'steps'=>array('Go to WordPress → Settings → Permalinks.','Select "Post name" (recommended).','Click "Save Changes".'),'url'=>'https://help.importify.com/article/533/how-do-i-set-up-the-correct-permalink-structure-for-woocommerce-in-wordpress');

$php_ok = isset($ic['php_ok']) ? $ic['php_ok'] : version_compare(PHP_VERSION,'7.2','>=');
$checks[] = array('name'=>'PHP Version','desc'=>'Runtime environment compatibility','icon_pass'=>'code','icon_fail'=>'dangerous','ok'=>$php_ok,'value'=>esc_html(phpversion()),'type'=>$php_ok?'pass':'fail',
    'steps'=>array('Contact your hosting provider to upgrade PHP to 7.4+.','Most hosting dashboards allow PHP version switching.','PHP 8.0+ is recommended.'),'url'=>'');

$fw = !empty($ic['firewall_active']);
$fn = isset($ic['firewall_name']) ? esc_html($ic['firewall_name']) : 'Detected';
$checks[] = array('name'=>'Security Firewall','desc'=>'External import requests may be throttled','icon_pass'=>'security','icon_fail'=>'shield','ok'=>!$fw,'value'=>$fw?$fn:'No blocking plugins','type'=>$fw?'warn':'pass',
    'steps'=>array('Your security plugin may block Importify\'s API requests.','Add this IP to your firewall allowlist: <code>162.243.171.163</code>','If using Wordfence: Firewall → Manage → Allowlisted IPs.','After adding the IP, refresh this page.'),'url'=>'https://help.importify.com/article/526/importify-woocommerce-store-connection-troubleshooting-guide');

$cf = !empty($ic['cloudflare_active']);
$checks[] = array('name'=>'Cloudflare','desc'=>'CDN and security proxy detection','icon_pass'=>'cloud_done','icon_fail'=>'shield','ok'=>!$cf,'value'=>$cf?'Detected':'Not detected','type'=>$cf?'warn':'pass',
    'steps'=>array('Cloudflare may block Importify\'s API calls.','Go to Cloudflare → Security → WAF.','Allow IP: <code>162.243.171.163</code>','Or create a Page Rule to bypass security for your API URL.'),'url'=>'https://help.importify.com/article/562/how-to-fix-woocommerce-api-issues-caused-by-cloudflare');

$le = get_option('importify_last_error');
$api_ok = empty($le);
$checks[] = array('name'=>'API Connection','desc'=>'Server communication with Importify','icon_pass'=>'api','icon_fail'=>'timer_off','ok'=>$api_ok,'value'=>$api_ok?'Connected':'Timeout','type'=>$api_ok?'pass':'fail',
    'steps'=>array_filter(array('Ensure your site is accessible from the internet.','Check WooCommerce REST API is enabled.','Add IP <code>162.243.171.163</code> to firewall allowlist.','Try deactivating and reactivating Importify.',$le?'Last error: <em>'.esc_html(substr($le,0,100)).'</em>':'')),'url'=>'https://help.importify.com/article/526/importify-woocommerce-store-connection-troubleshooting-guide');

// ─────────────────────────────────────────────────────────────────────
// Preview overrides (debug helper, admin-gated by the manage_options
// check above). Lets support / designers see the error-state UI without
// actually triggering the underlying issue on the site.
//
// Append any combination to the plugin URL:
//   ?error1=preview  → SSL Certificate fails ("Missing")
//   ?error2=preview  → WooCommerce not installed
//   ?error3=preview  → Permalinks set to Plain
//   ?error4=preview  → PHP Version unsupported
//   ?error5=preview  → Security Firewall detected (warn)
//   ?error6=preview  → Cloudflare detected (warn)
//   ?error7=preview  → API Connection timeout
//   ?preview=all     → every check above at once
//   ?preview=mixed   → a curated realistic mix (SSL fail + Cloudflare warn)
// ─────────────────────────────────────────────────────────────────────
$importify_preview_map = array(
    0 => array('type' => 'fail', 'value' => 'Missing'),                  // SSL
    1 => array('type' => 'fail', 'value' => 'Not Installed'),            // WooCommerce
    2 => array('type' => 'fail', 'value' => 'Plain'),                    // Permalinks
    3 => array('type' => 'fail', 'value' => '5.6.40 (unsupported)'),     // PHP Version
    4 => array('type' => 'warn', 'value' => 'Wordfence Security'),       // Firewall
    5 => array('type' => 'warn', 'value' => 'Detected'),                 // Cloudflare
    6 => array('type' => 'fail', 'value' => 'Timeout'),                  // API Connection
);
$importify_preview_all   = (isset($_GET['preview']) && $_GET['preview'] === 'all');
$importify_preview_mixed = (isset($_GET['preview']) && $_GET['preview'] === 'mixed');
$importify_mixed_indexes = array(0, 5); // SSL + Cloudflare
$importify_any_preview = $importify_preview_all || $importify_preview_mixed;

for ($idx = 0; $idx < count($checks); $idx++) {
    $force = false;
    if ($importify_preview_all) {
        $force = true;
    } elseif ($importify_preview_mixed && in_array($idx, $importify_mixed_indexes, true)) {
        $force = true;
    } elseif (isset($_GET['error' . ($idx + 1)]) && $_GET['error' . ($idx + 1)] === 'preview') {
        $force = true;
        $importify_any_preview = true;
    }
    if ($force && isset($importify_preview_map[$idx])) {
        $checks[$idx]['type']  = $importify_preview_map[$idx]['type'];
        $checks[$idx]['value'] = $importify_preview_map[$idx]['value'];
        $checks[$idx]['ok']    = false;
    }
}

$has_issues = false; $issue_count = 0;
foreach ($checks as $c) { if ($c['type'] !== 'pass') { $has_issues = true; $issue_count++; } }
$btn = esc_attr(IMPORTIFY_API_URL.'/woocomerce/login-by-token?token='.$api_key);

/**
 * Choose the right pill class for a row's value.
 *  - fail/warn → coloured pill matching severity
 *  - pass with meaningful value like "Active"/"Connected"/"Post name"/"No blocking plugins" → success-tinted pill
 *  - pass with neutral value (version numbers) → neutral grey pill
 *  - "Not detected" (Cloudflare passed state) → muted grey
 */
function importify_pill_class_for($c) {
    if ($c['type'] === 'fail') return 'importify-pill--fail';
    if ($c['type'] === 'warn') return 'importify-pill--warn';
    $strong_pass_values = array('Active', 'Connected', 'Post name', 'No blocking plugins');
    if (in_array($c['value'], $strong_pass_values, true)) return 'importify-pill--success';
    if ($c['value'] === 'Not detected') return 'importify-pill--muted';
    return 'importify-pill--neutral';
}
?>
<!-- Fonts: Manrope (headline) + Inter (body) + Material Symbols (icons, display=block for FOIT control) -->
<link href="https://fonts.googleapis.com" rel="preconnect">
<link crossorigin href="https://fonts.gstatic.com" rel="preconnect">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block" rel="stylesheet">

<script>
/* Reveal icons the instant the Material Symbols font is ready.
   Keeps ligature text invisible until the font loads → no "flash of check_circle text". */
(function () {
    if (document.fonts && document.fonts.load) {
        document.fonts.load('24px "Material Symbols Outlined"').then(function () {
            document.documentElement.classList.add('fonts-ready');
        }).catch(function () {
            document.documentElement.classList.add('fonts-ready');
        });
    } else {
        document.documentElement.classList.add('fonts-ready');
    }
    // Safety net: reveal anyway after 3s so icons never stay permanently hidden.
    setTimeout(function () { document.documentElement.classList.add('fonts-ready'); }, 3000);
})();
</script>

<div id="importify-setup-wrap">
    <div class="importify-container">

    <?php if ($importify_any_preview): ?>
    <div class="importify-preview-banner" role="note">
        <span class="material-symbols-outlined material-symbols-outlined--filled">science</span>
        <span>Preview mode — showing simulated data. <a href="<?php echo esc_url(remove_query_arg(array('preview','error1','error2','error3','error4','error5','error6','error7'))); ?>">Exit preview</a></span>
    </div>
    <?php endif; ?>

    <?php if (!$has_issues): ?>
    <!-- ═══ STATE: ALL PASSED ═══ -->

    <!-- Header -->
    <div class="importify-header">
        <div class="importify-status-badge importify-status-badge--success">
            <span class="material-symbols-outlined material-symbols-outlined--filled">check_circle</span>
            Importify plugin activated
        </div>
        <h1>You&rsquo;re all set.</h1>
        <p>Your site passed every check. Head to the dashboard to <a href="<?php echo $btn; ?>" target="_blank" rel="noopener noreferrer">start importing products</a>.</p>
    </div>

    <!-- Main card -->
    <div class="importify-card">
        <div class="importify-card__stripe"></div>
        <div class="importify-card__body">

            <div class="importify-check-list">
                <?php foreach ($checks as $c): $pill_cls = importify_pill_class_for($c); ?>
                <div class="importify-check-row importify-check-row--pass">
                    <div class="importify-check-row__main">
                        <div class="importify-check-left">
                            <div class="importify-check-icon importify-check-icon--pass">
                                <span class="material-symbols-outlined material-symbols-outlined--filled"><?php echo esc_attr($c['icon_pass']); ?></span>
                            </div>
                            <div class="importify-check-text">
                                <h3 class="importify-check-text__name"><?php echo esc_html($c['name']); ?></h3>
                                <p class="importify-check-text__desc"><?php echo esc_html($c['desc']); ?></p>
                            </div>
                        </div>
                        <div class="importify-check-right">
                            <span class="importify-pill <?php echo $pill_cls; ?>"><?php echo esc_html($c['value']); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="importify-card__footer">
                <div class="importify-card__footer-note">
                    <span class="material-symbols-outlined material-symbols-outlined--filled">task_alt</span>
                    <span>All systems ready. You can start importing now.</span>
                </div>
                <a href="<?php echo $btn; ?>" target="_blank" rel="noopener noreferrer" class="importify-btn importify-btn--primary">
                    Proceed to Importify Dashboard
                    <span class="material-symbols-outlined">arrow_forward</span>
                </a>
            </div>

        </div>
    </div>

    <?php else: ?>
    <!-- ═══ STATE: ISSUES FOUND ═══ -->

    <!-- Header -->
    <div class="importify-header">
        <div class="importify-status-badge importify-status-badge--warning">
            <span class="material-symbols-outlined material-symbols-outlined--filled">warning</span>
            Setup requires attention
        </div>
        <h1>A few things to sort out.</h1>
        <p><?php echo $issue_count; ?> issue<?php echo $issue_count > 1 ? 's' : ''; ?> preventing full automation. Each one has a fix — follow the steps below.</p>
    </div>

    <!-- Main card -->
    <div class="importify-card">
        <div class="importify-card__stripe importify-card__stripe--warning"></div>
        <div class="importify-card__body">

            <div class="importify-check-list">
                <?php
                $first_non_pass_index = null;
                foreach ($checks as $i => $c) { if ($c['type'] !== 'pass') { $first_non_pass_index = $i; break; } }
                foreach ($checks as $i => $c):
                    $is_pass = $c['type'] === 'pass';
                    $is_fail = $c['type'] === 'fail';
                    $is_warn = $c['type'] === 'warn';
                    $icon_name = $is_pass ? $c['icon_pass'] : $c['icon_fail'];
                    $icon_cls  = $is_pass ? 'importify-check-icon--pass' : ($is_fail ? 'importify-check-icon--fail' : 'importify-check-icon--warn');
                    $row_cls = $is_fail ? ' importify-check-row--fail' : ($is_warn ? ' importify-check-row--warn' : ' importify-check-row--pass');
                    $pill_cls = importify_pill_class_for($c);
                ?>
                <div class="importify-check-row<?php echo $row_cls; ?>">
                    <div class="importify-check-row__main">
                        <div class="importify-check-left">
                            <div class="importify-check-icon <?php echo $icon_cls; ?>">
                                <span class="material-symbols-outlined material-symbols-outlined--filled"><?php echo esc_attr($icon_name); ?></span>
                            </div>
                            <div class="importify-check-text">
                                <h3 class="importify-check-text__name"><?php echo esc_html($c['name']); ?></h3>
                                <p class="importify-check-text__desc"><?php echo esc_html($c['desc']); ?></p>
                            </div>
                        </div>
                        <div class="importify-check-right">
                            <span class="importify-pill <?php echo $pill_cls; ?>"><?php echo esc_html($c['value']); ?></span>
                            <?php if (!$is_pass): ?>
                                <button type="button" class="importify-btn--link" data-fix-toggle="fix-<?php echo $i; ?>">How to fix</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!$is_pass && !empty($c['steps'])):
                        $is_first_open = ($i === $first_non_pass_index);
                    ?>
                    <div class="importify-fix-panel<?php echo $is_first_open ? ' importify-fix-panel--open' : ''; ?>" id="fix-<?php echo $i; ?>">
                        <h3>Fixing your <?php echo esc_html($c['name']); ?></h3>
                        <div class="importify-fix-steps">
                            <?php $n = 1; foreach ($c['steps'] as $step): if (empty($step)) continue; ?>
                            <div class="importify-fix-step">
                                <span class="importify-fix-step__num"><?php echo $n++; ?></span>
                                <p><?php echo wp_kses($step, array('code'=>array(),'em'=>array(),'strong'=>array())); ?></p>
                            </div>
                            <?php endforeach; ?>
                            <?php if (!empty($c['url'])): ?>
                            <a class="importify-fix-link" href="<?php echo esc_url($c['url']); ?>" target="_blank" rel="noopener noreferrer">
                                View full guide
                                <span class="material-symbols-outlined">arrow_forward</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="importify-footer-actions">
                <a href="<?php echo $btn; ?>" target="_blank" rel="noopener noreferrer" class="importify-btn importify-btn--secondary">
                    Skip and Proceed to Dashboard
                </a>
                <a href="#" onclick="window.location.reload();return false;" class="importify-btn importify-btn--primary">
                    <span class="material-symbols-outlined">refresh</span>
                    Re-run System Checks
                </a>
            </div>

        </div>
    </div>

    <?php endif; ?>

    <p class="importify-version">Importify Plugin v<?php echo esc_html(IMPORTIFY_VERSION); ?></p>

    </div><!-- /.importify-container -->
</div><!-- /#importify-setup-wrap -->

<script>
/* How-to-fix toggle — delegated so it survives any DOM re-render. */
(function () {
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-fix-toggle]');
        if (!trigger) return;
        var panel = document.getElementById(trigger.getAttribute('data-fix-toggle'));
        if (!panel) return;
        var isOpen = panel.classList.toggle('importify-fix-panel--open');
        trigger.textContent = isOpen ? 'Hide' : 'How to fix';
    });
})();
</script>
