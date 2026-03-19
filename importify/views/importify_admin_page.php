<?php
if (!defined('ABSPATH')) { exit; }
if (!current_user_can('manage_options')) { wp_die(__('You do not have sufficient permissions.')); }

$api_key = get_option('importify_api_key');
$ic = get_option('importify_check');
if (!is_array($ic)) $ic = array();

// Build checks array
$checks = array();
$ssl_ok = isset($ic['ssl_active']) && $ic['ssl_active'] === "true";
$checks[] = array('name'=>'SSL Certificate','desc'=>'Secure connection for API product importing','icon_pass'=>'check_circle','icon_fail'=>'dangerous','ok'=>$ssl_ok,'value'=>$ssl_ok?'Active':'Missing','type'=>$ssl_ok?'pass':'fail',
    'steps'=>array('Contact your hosting provider to install an SSL certificate.','Ensure your site loads with <code class="bg-white/10 px-2 py-0.5 rounded font-mono text-white">https://</code>.','Update WordPress URL in Settings → General to use https://.'),'url'=>'https://help.importify.com/article/526/importify-woocommerce-store-connection-troubleshooting-guide');

$woo_ok = !empty($ic['woocomerce_installed']);
$wv = isset($ic['woo_version']) && $ic['woo_version'] ? 'v'.esc_html($ic['woo_version']) : ($woo_ok ? 'Installed' : '');
$checks[] = array('name'=>'WooCommerce','desc'=>'Core compatibility verified for dropshipping','icon_pass'=>'check_circle','icon_fail'=>'dangerous','ok'=>$woo_ok,'value'=>$woo_ok?$wv:'Not Installed','type'=>$woo_ok?'pass':'fail',
    'steps'=>array('Go to WordPress → Plugins → Add New.','Search for "WooCommerce" and click Install.','Activate the plugin and complete the setup wizard.'),'url'=>'https://help.importify.com/article/532/how-to-install-the-woocommerce-plugin');

$p = isset($ic['permalinks']) ? $ic['permalinks'] : '';
$p_ok = is_string($p) && strlen($p) > 0;
$checks[] = array('name'=>'Permalinks','desc'=>'Post name structure required for REST API','icon_pass'=>'check_circle','icon_fail'=>'link_off','ok'=>$p_ok,'value'=>$p_ok?'Post name':'Plain','type'=>$p_ok?'pass':'fail',
    'steps'=>array('Go to WordPress → Settings → Permalinks.','Select "Post name" (recommended).','Click "Save Changes".'),'url'=>'https://help.importify.com/article/533/how-do-i-set-up-the-correct-permalink-structure-for-woocommerce-in-wordpress');

$php_ok = isset($ic['php_ok']) ? $ic['php_ok'] : version_compare(PHP_VERSION,'7.2','>=');
$checks[] = array('name'=>'PHP Version','desc'=>'Runtime environment compatibility','icon_pass'=>'check_circle','icon_fail'=>'dangerous','ok'=>$php_ok,'value'=>esc_html(phpversion()),'type'=>$php_ok?'pass':'fail',
    'steps'=>array('Contact your hosting provider to upgrade PHP to 7.4+.','Most hosting dashboards allow PHP version switching.','PHP 8.0+ is recommended.'),'url'=>'');

$fw = !empty($ic['firewall_active']);
$fn = isset($ic['firewall_name']) ? esc_html($ic['firewall_name']) : 'Detected';
$checks[] = array('name'=>'Security Firewall','desc'=>'External import requests may be throttled','icon_pass'=>'check_circle','icon_fail'=>'shield','ok'=>!$fw,'value'=>$fw?$fn:'No blocking plugins','type'=>$fw?'warn':'pass',
    'steps'=>array('Your security plugin may block Importify\'s API requests.','Add this IP to your firewall allowlist: <code class="bg-white/10 px-2 py-0.5 rounded font-mono text-white">162.243.171.163</code>','If using Wordfence: Firewall → Manage → Allowlisted IPs.','After adding the IP, refresh this page.'),'url'=>'https://help.importify.com/article/526/importify-woocommerce-store-connection-troubleshooting-guide');

$cf = !empty($ic['cloudflare_active']);
$checks[] = array('name'=>'Cloudflare','desc'=>'CDN and security proxy detection','icon_pass'=>'check_circle','icon_fail'=>'shield','ok'=>!$cf,'value'=>$cf?'Detected':'Not detected','type'=>$cf?'warn':'pass',
    'steps'=>array('Cloudflare may block Importify\'s API calls.','Go to Cloudflare → Security → WAF.','Allow IP: <code class="bg-white/10 px-2 py-0.5 rounded font-mono text-white">162.243.171.163</code>','Or create a Page Rule to bypass security for your API URL.'),'url'=>'https://help.importify.com/article/562/how-to-fix-woocommerce-api-issues-caused-by-cloudflare');

$le = get_option('importify_last_error');
$api_ok = empty($le);
$checks[] = array('name'=>'API Connection','desc'=>'Server communication with Importify','icon_pass'=>'check_circle','icon_fail'=>'timer_off','ok'=>$api_ok,'value'=>$api_ok?'Connected':'Timeout','type'=>$api_ok?'pass':'fail',
    'steps'=>array_filter(array('Ensure your site is accessible from the internet.','Check WooCommerce REST API is enabled.','Add IP <code class="bg-white/10 px-2 py-0.5 rounded font-mono text-white">162.243.171.163</code> to firewall allowlist.','Try deactivating and reactivating Importify.',$le?'Last error: <em class="text-slate-400">'.esc_html(substr($le,0,100)).'</em>':'')),'url'=>'https://help.importify.com/article/526/importify-woocommerce-store-connection-troubleshooting-guide');

$has_issues = false; $issue_count = 0;
foreach ($checks as $c) { if ($c['type'] !== 'pass') { $has_issues = true; $issue_count++; } }
$btn = esc_attr(IMPORTIFY_API_URL.'/woocomerce/login-by-token?token='.$api_key);
?>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script>
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                "primary": "#f15f5f",
                "accent-orange": "#ff8f3f",
                "success-green": "#22c55e",
                "warning-amber": "#f59e0b",
                "error-red": "#ef4444",
                "background-dark": "#0d0d1a",
                "surface-dark": "#111122",
            },
            fontFamily: { "display": ["Inter", "sans-serif"] },
            borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
        },
    },
}
</script>
<style>
    #importify-setup-wrap { background-color: #0d0d1a; background-image: radial-gradient(circle at 2px 2px, rgba(255,255,255,0.05) 1px, transparent 0); background-size: 24px 24px; min-height: 100vh; margin-left: -20px; margin-top: -10px; padding: 48px 24px 80px; position: relative; }
    .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1); }
    .glow-green { box-shadow: 0 0 15px rgba(34,197,94,0.4); }
    .glow-primary { box-shadow: 0 0 20px rgba(241,95,95,0.3); }
    .gradient-bg { background: linear-gradient(135deg, #2d1b69 0%, #1e3a5f 100%); }
    .imp-fix-panel { display: none; }
</style>

<div id="importify-setup-wrap" class="dark">
<div class="fixed top-0 left-0 w-full h-1 bg-gradient-to-r from-primary via-accent-orange to-primary/50 z-50"></div>
<div class="fixed -bottom-24 -left-24 size-96 bg-primary/10 blur-[120px] rounded-full pointer-events-none"></div>
<div class="fixed -top-24 -right-24 size-96 bg-blue-600/10 blur-[120px] rounded-full pointer-events-none"></div>

<div class="max-w-4xl mx-auto px-6 font-display text-slate-100">

<?php if (!$has_issues): ?>
<!-- ═══ STATE: ALL PASSED ═══ -->
<section class="space-y-8">
    <!-- Header Card -->
    <div class="relative overflow-hidden rounded-xl gradient-bg p-8 flex flex-col md:flex-row items-center justify-between gap-6 border border-white/10">
        <div class="flex items-center gap-5">
            <div class="size-14 bg-white/10 rounded-xl flex items-center justify-center backdrop-blur-md border border-white/20">
                <img src="<?php echo esc_url(IMPORTIFY_URL); ?>/assets/images/importify_icon.png" alt="" style="width:32px;height:32px;">
            </div>
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Importify</h1>
                <div class="flex items-center gap-2 mt-1">
                    <span class="size-2 bg-success-green rounded-full animate-pulse glow-green"></span>
                    <p class="text-success-green font-medium text-sm">Plugin activated successfully</p>
                </div>
            </div>
        </div>
        <div class="shrink-0">
            <a href="<?php echo $btn; ?>" target="_blank" class="bg-gradient-to-r from-primary to-accent-orange text-white px-6 py-3 rounded-lg font-bold text-sm glow-primary hover:opacity-90 transition-all inline-flex items-center gap-2 no-underline">
                Proceed to Importify Dashboard
                <span class="material-symbols-outlined text-lg">arrow_forward</span>
            </a>
        </div>
    </div>

    <!-- System Check -->
    <div class="glass rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-white/10 bg-white/5 flex items-center justify-between">
            <h2 class="text-lg font-semibold flex items-center gap-2">
                <span class="material-symbols-outlined text-success-green">verified</span>
                System Check - All Checks Passed
            </h2>
            <span class="text-xs text-slate-400 uppercase tracking-widest font-bold">v<?php echo esc_html(IMPORTIFY_VERSION); ?></span>
        </div>
        <div class="divide-y divide-white/5">
            <?php foreach ($checks as $c): ?>
            <div class="flex items-center justify-between p-6 hover:bg-white/5 transition-colors">
                <div class="flex items-center gap-4">
                    <div class="size-10 rounded-full bg-success-green/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-success-green">check_circle</span>
                    </div>
                    <div>
                        <p class="font-medium"><?php echo esc_html($c['name']); ?></p>
                        <p class="text-sm text-slate-400"><?php echo esc_html($c['desc']); ?></p>
                    </div>
                </div>
                <div class="px-3 py-1 rounded-full <?php echo $c['value'] === 'Active' || $c['value'] === 'Connected' || $c['value'] === 'Post name' ? 'bg-success-green/10 border border-success-green/30 text-success-green' : 'bg-white/10 border border-white/10 text-slate-300'; ?> text-xs font-bold uppercase">
                    <?php echo esc_html($c['value']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php else: ?>
<!-- ═══ STATE: ISSUES FOUND ═══ -->
<section class="space-y-8">
    <!-- Header Card -->
    <div class="relative overflow-hidden rounded-xl gradient-bg p-8 flex flex-col md:flex-row items-center justify-between gap-6 border border-white/10">
        <div class="flex items-center gap-5">
            <div class="size-14 bg-white/10 rounded-xl flex items-center justify-center backdrop-blur-md border border-white/20">
                <img src="<?php echo esc_url(IMPORTIFY_URL); ?>/assets/images/importify_icon.png" alt="" style="width:32px;height:32px;">
            </div>
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Importify</h1>
                <div class="flex items-center gap-2 mt-1">
                    <span class="size-2 bg-warning-amber rounded-full animate-pulse"></span>
                    <p class="text-warning-amber font-medium text-sm">Setup requires attention</p>
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-between px-2">
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-warning-amber text-3xl">warning</span>
            <h2 class="text-xl font-bold"><?php echo $issue_count; ?> issue<?php echo $issue_count > 1 ? 's' : ''; ?> preventing full automation</h2>
        </div>
    </div>

    <!-- System Check -->
    <div class="glass rounded-xl overflow-hidden">
        <div class="divide-y divide-white/5">
            <?php foreach ($checks as $i => $c):
                $is_pass = $c['type'] === 'pass';
                $is_fail = $c['type'] === 'fail';
                $is_warn = $c['type'] === 'warn';
                $icon = $is_pass ? $c['icon_pass'] : $c['icon_fail'];
                $icon_color = $is_pass ? 'text-success-green' : ($is_fail ? 'text-error-red' : 'text-warning-amber');
                $icon_bg = $is_pass ? 'bg-success-green/'.($has_issues?'10':'20') : ($is_fail ? 'bg-error-red/20' : 'bg-warning-amber/20');
                $pill_cls = $is_pass ? 'bg-success-green/'.($has_issues?'5':'10').' border-success-green/'.($has_issues?'20':'30').' text-success-green' : ($is_fail ? 'bg-error-red/20 border-error-red/40 text-error-red' : 'bg-warning-amber/20 border-warning-amber/40 text-warning-amber');
                $row_cls = $is_fail ? ' bg-error-red/5' : '';
                $row_cls .= $is_pass ? ' opacity-70' : '';
            ?>
            <div>
                <div class="flex items-center justify-between p-6<?php echo $row_cls; ?>">
                    <div class="flex items-center gap-4">
                        <div class="size-10 rounded-full <?php echo $icon_bg; ?> flex items-center justify-center">
                            <span class="material-symbols-outlined <?php echo $icon_color; ?><?php echo $is_pass && $has_issues ? '/60' : ''; ?>"><?php echo $icon; ?></span>
                        </div>
                        <div>
                            <p class="font-medium"><?php echo esc_html($c['name']); ?></p>
                            <p class="text-sm text-slate-400"><?php echo esc_html($c['desc']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="px-3 py-1 rounded-full border <?php echo $pill_cls; ?> text-xs font-bold uppercase">
                            <?php echo esc_html($c['value']); ?>
                        </div>
                        <?php if (!$is_pass): ?>
                            <button class="text-primary text-sm font-bold hover:underline" onclick="var p=document.getElementById('fix-<?php echo $i; ?>');if(p.style.display==='block'){p.style.display='none';this.textContent='How to fix';}else{p.style.display='block';this.textContent='Hide';}">How to fix</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!$is_pass && !empty($c['steps'])): ?>
                <div class="imp-fix-panel bg-warning-amber/5 border-y border-warning-amber/20 p-6 m-4 mt-0 rounded-lg" id="fix-<?php echo $i; ?>" <?php if ($i === array_key_first(array_filter($checks, function($x){return $x['type']!=='pass';}))) echo 'style="display:block;"'; ?>>
                    <h3 class="text-warning-amber font-bold text-sm uppercase tracking-wider mb-4">Fixing your <?php echo esc_html($c['name']); ?></h3>
                    <div class="space-y-4">
                        <?php $n=1; foreach ($c['steps'] as $step): if(empty($step)) continue; ?>
                        <div class="flex gap-4">
                            <span class="size-6 shrink-0 rounded-full bg-warning-amber/20 text-warning-amber text-xs flex items-center justify-center font-bold"><?php echo $n++; ?></span>
                            <p class="text-sm text-slate-300"><?php echo $step; ?></p>
                        </div>
                        <?php endforeach; ?>
                        <?php if (!empty($c['url'])): ?>
                        <a class="inline-flex items-center gap-1 text-primary text-sm font-bold mt-2 hover:gap-2 transition-all no-underline" href="<?php echo esc_url($c['url']); ?>" target="_blank">
                            View full guide <span class="material-symbols-outlined text-sm">arrow_forward</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Footer Actions -->
    <div class="flex flex-col md:flex-row items-center justify-center gap-4 pt-4">
        <a href="<?php echo $btn; ?>" target="_blank" class="w-full md:w-auto border border-white/20 hover:bg-white/5 text-slate-300 px-8 py-3 rounded-lg font-bold text-sm transition-all text-center no-underline">
            Skip and Proceed to Dashboard
        </a>
        <a href="" onclick="window.location.reload();return false;" class="w-full md:w-auto bg-primary text-white px-8 py-3 rounded-lg font-bold text-sm hover:bg-primary/90 transition-all inline-flex items-center justify-center gap-2 no-underline">
            <span class="material-symbols-outlined text-lg">refresh</span>
            Re-run System Checks
        </a>
    </div>
</section>
<?php endif; ?>

<p class="text-center mt-8 text-xs text-slate-600">Importify Plugin v<?php echo esc_html(IMPORTIFY_VERSION); ?></p>
</div>
</div>
