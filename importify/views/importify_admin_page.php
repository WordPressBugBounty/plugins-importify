<?php

// Prevent direct file access
if (!defined('ABSPATH')) {
  exit;
}

if (!current_user_can('manage_options')) {
  wp_die(__('You do not have sufficient permissions to access this page.'));
}

$api_key = get_option('importify_api_key');
$error = get_option('importify_error');
$error_message = get_option('importify_error_message');

$importify_check = get_option('importify_check');

$show_error_design = false;

if($importify_check['ssl_active'] == "false" || strlen($importify_check['permalinks']) < 1 || $importify_check['woocomerce_installed'] == FALSE  || $importify_check['firewall_active'] == TRUE)
{
	$show_error_design = true;
}

$button_prop = IMPORTIFY_API_URL.'/woocomerce/login-by-token?token='.$api_key;


?>
<div id="importify_page">
  <?php
    if($show_error_design)
    {
      ?>
      <div class="container mx-auto p-4 max-w-4xl">
        <div class="card bg-white p-8 mb-4">
            <div class="flex flex-col items-center mb-6">
                <img src="<?php echo esc_url(IMPORTIFY_URL);?>/assets/images/importifyLogo.png" alt="Importify Logo" class="logo">
                <p class="text-center success-message mb-2">
                    Plugin activated successfully
                    <i data-feather="check-circle" class="checkmark inline"></i>
                </p>
                <p class="text-center text-gray-600 mb-6">
                    Importify has been activated, but we need to address a few issues before you can start using it.
                </p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Component</th>
                        <th>Status</th>
                        <th>How to Fix</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                      if($importify_check['woocomerce_installed'] == FALSE)
                      {?>
                    <tr>
                        <td>WooCommerce plugin</td>
                        <td class="status-critical">Missing</td>
                        <td><a href="https://help.importify.com/article/532/how-to-install-the-woocommerce-plugin" class="text-blue-600 hover:underline" target="_blank">Install the WooCommerce Plugin</a></td>
                    </tr>
                    <?php
                      }
                      if($importify_check['ssl_active'] == "false")
                      {
                    ?>
                    <tr>
                        <td>SSL certificate</td>
                        <td class="status-warning">Not installed</td>
                        <td><a href="https://help.importify.com/article/526/importify-woocommerce-store-connection-troubleshooting-guide" class="text-blue-600 hover:underline" target="_blank">Set Up SSL</a></td>
                    </tr>
                    <?php
                      }
                      if(strlen($importify_check['permalinks']) < 1)
                      {
                    ?>
                    <tr>
                        <td>Permalinks Setting</td>
                        <td class="status-warning">Issue detected</td>
                        <td><a href="https://help.importify.com/article/533/how-do-i-set-up-the-correct-permalink-structure-for-woocommerce-in-wordpress" class="text-blue-600 hover:underline" target="_blank">Change Permalinks structure to "Post name"</a></td>
                    </tr>
                    <?php
                    }
                      if($importify_check['firewall_active'])
                      {
                    ?>
                      <tr>
                        <td>Security Plugins</td>
                        <td class="status-critical">Importify ip blocked</td>
                        <td><a href="https://help.importify.com/article/526/importify-woocommerce-store-connection-troubleshooting-guide" class="text-blue-600 hover:underline" target="_blank">Configure Firewall</a></td>
                    </tr>
                    <?php
                      }
                    ?>
                </tbody>
            </table>
        </div>
        
        <div class="text-center mt-4">
            <a href="<?php echo esc_attr($button_prop);?>" target='_blank' class="dashboard-btn inline-block">
                Skip and Proceed to Dashboard
            </a>
        </div>
        
        <p class="small-text text-center">
            <a href='https://help.importify.com/article/526/importify-woocommerce-store-connection-troubleshooting-guide' target="_blank">* These issues must be resolved before you can fully use the Importify app.</a>
        </p>
    </div>
      <?php
    }
    else
    {
      ?>
      <div class="container mx-auto p-4 max-w-2xl">
          <div class="card bg-white p-8 mb-4">
              <div class="flex flex-col items-center mb-6">
                  <img src="<?php echo esc_url(IMPORTIFY_URL);?>/assets/images/importifyLogo.png" alt="Importify Logo" class="logo">
                  <p class="text-center success-message mb-2 text-xl">
                      Plugin activated successfully
                      <i data-feather="check-circle" class="checkmark inline"></i>
                  </p>
                  <p class="text-center text-gray-600 mb-6">
                      Great news! Importify has been activated and is ready to use. No issues were detected during the setup process.
                  </p>
              </div>
          </div>
          
          <div class="text-center mt-4">
              <a href="<?php echo esc_attr($button_prop);?>" target='_blank' class="dashboard-btn inline-block">
                  Proceed to Importify Dashboard
              </a>
          </div>
      </div>
      <?php
    }
    ?>
  <script>
      feather.replace()
  </script>
</div>