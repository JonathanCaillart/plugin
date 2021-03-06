<?php
/**
 * Copyright © Lyra Network and contributors.
 * This file is part of EpayNC plugin for WooCommerce. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @author    Geoffrey Crofte, Alsacréations (https://www.alsacreations.fr/)
 * @copyright Lyra Network and contributors
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL v2)
 */

/**
 * Plugin Name: EpayNC for WooCommerce
 * Description: This plugin links your WordPress WooCommerce shop to the payment gateway.
 * Author: Lyra Network
 * Contributors: Alsacréations (Geoffrey Crofte http://alsacreations.fr/a-propos#geoffrey)
 * Version: 1.9.4
 * Author URI: https://www.lyra.com/
 * License: GPLv2 or later
 * Requires at least: 3.5
 * Tested up to: 5.8
 * WC requires at least: 2.0
 * WC tested up to: 5.7
 *
 * Text Domain: woo-epaync-payment
 * Domain Path: /languages/
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('WC_EPAYNC_PLUGIN_URL', plugin_dir_url(__FILE__));

/* A global var to easily enable/disable features. */
global $epaync_plugin_features;

$epaync_plugin_features = array(
    'qualif' => false,
    'prodfaq' => true,
    'restrictmulti' => false,
    'shatwo' => true,
    'embedded' => true,
    'subscr' => true,
    'support' => true,

    'multi' => true,
    'choozeo' => false,
    'klarna' => false,
    'franfinance' => false
);

/* Check requirements. */
function woocommerce_epaync_activation()
{
    $all_active_plugins = get_option('active_plugins');
    if (is_multisite()) {
        $all_active_plugins = array_merge($all_active_plugins, wp_get_active_network_plugins());
    }

    $all_active_plugins = apply_filters('active_plugins', $all_active_plugins);

    if (! stripos(implode($all_active_plugins), '/woocommerce.php')) {
        deactivate_plugins(plugin_basename(__FILE__)); // Deactivate ourself.

        // Load translation files.
        load_plugin_textdomain('woo-epaync-payment', false, plugin_basename(dirname(__FILE__)) . '/languages');

        $message = sprintf(__('Sorry ! In order to use WooCommerce %s Payment plugin, you need to install and activate the WooCommerce plugin.', 'woo-epaync-payment'), 'EpayNC');
        wp_die($message, 'EpayNC for WooCommerce', array('back_link' => true));
    }
}
register_activation_hook(__FILE__, 'woocommerce_epaync_activation');

/* Delete all data when uninstalling plugin. */
function woocommerce_epaync_uninstallation()
{
    delete_option('woocommerce_epaync_settings');
    delete_option('woocommerce_epayncstd_settings');
    delete_option('woocommerce_epayncmulti_settings');
    delete_option('woocommerce_epayncchoozeo_settings');
    delete_option('woocommerce_epayncklarna_settings');
    delete_option('woocommerce_epayncfranfinance_settings');
    delete_option('woocommerce_epayncregroupedother_settings');
    delete_option('woocommerce_epayncsubscription_settings');
}
register_uninstall_hook(__FILE__, 'woocommerce_epaync_uninstallation');

/* Include gateway classes. */
function woocommerce_epaync_init()
{
    global $epaync_plugin_features;

    // Load translation files.
    load_plugin_textdomain('woo-epaync-payment', false, plugin_basename(dirname(__FILE__)) . '/languages');

    if (! class_exists('Epaync_Subscriptions_Loader')) { // Load subscriptions processing mecanism.
        require_once 'includes/subscriptions/epaync-subscriptions-loader.php';
    }

    if (! class_exists('WC_Gateway_Epaync')) {
        require_once 'class-wc-gateway-epaync.php';
    }

    if (! class_exists('WC_Gateway_EpayncStd')) {
        require_once 'class-wc-gateway-epayncstd.php';
    }

    if ($epaync_plugin_features['multi'] && ! class_exists('WC_Gateway_EpayncMulti')) {
        require_once 'class-wc-gateway-epayncmulti.php';
    }

    if ($epaync_plugin_features['choozeo'] && ! class_exists('WC_Gateway_EpayncChoozeo')) {
        require_once 'class-wc-gateway-epayncchoozeo.php';
    }

    if ($epaync_plugin_features['klarna'] && ! class_exists('WC_Gateway_EpayncKlarna')) {
        require_once 'class-wc-gateway-epayncklarna.php';
    }

    if ($epaync_plugin_features['franfinance'] && ! class_exists('WC_Gateway_EpayncFranfinance')) {
        require_once 'class-wc-gateway-epayncfranfinance.php';
    }

    if (! class_exists('WC_Gateway_EpayncRegroupedOther')) {
        require_once 'class-wc-gateway-epayncregroupedother.php';
    }

    if (! class_exists('WC_Gateway_EpayncOther')) {
        require_once 'class-wc-gateway-epayncother.php';
    }

    if ($epaync_plugin_features['subscr'] && ! class_exists('WC_Gateway_EpayncSubscription')) {
        require_once 'class-wc-gateway-epayncsubscription.php';
    }

    require_once 'includes/EpayncRequest.php';
    require_once 'includes/EpayncResponse.php';
    require_once 'includes/EpayncRest.php';
    require_once 'includes/EpayncRestTools.php';
    require_once 'includes/EpayncTools.php';
}
add_action('woocommerce_init', 'woocommerce_epaync_init');

/* Add our payment methods to woocommerce methods. */
function woocommerce_epaync_add_method($methods)
{
    global $epaync_plugin_features, $woocommerce;

    $methods[] = 'WC_Gateway_Epaync';
    $methods[] = 'WC_Gateway_EpayncStd';

    if ($epaync_plugin_features['multi']) {
        $methods[] = 'WC_Gateway_EpayncMulti';
    }

    if ($epaync_plugin_features['choozeo']) {
        $methods[] = 'WC_Gateway_EpayncChoozeo';
    }

    if ($epaync_plugin_features['klarna']) {
        $methods[] = 'WC_Gateway_EpayncKlarna';
    }

    if ($epaync_plugin_features['franfinance']) {
        $methods[] = 'WC_Gateway_EpayncFranfinance';
    }

    if ($epaync_plugin_features['subscr']) {
        $methods[] = 'WC_Gateway_EpayncSubscription';
    }

    $methods[] = 'WC_Gateway_EpayncRegroupedOther';

    // Since 2.3.0, we can display other payment means as submodules.
    if (version_compare($woocommerce->version, '2.3.0', '>=') && $woocommerce->cart) {
        $regrouped_other_payments = new WC_Gateway_EpayncRegroupedOther();

        if (! $regrouped_other_payments->regroup_other_payment_means()) {
            $payment_means = $regrouped_other_payments->get_available_options();
            if (is_array($payment_means) && ! empty($payment_means)) {
                foreach ($payment_means as $option) {
                    $methods[] = new WC_Gateway_EpayncOther($option['payment_mean'], $option['label']);
                }
            }
        }
    }

    return $methods;
}
add_filter('woocommerce_payment_gateways', 'woocommerce_epaync_add_method');

/* Add a link to plugin settings page from plugins list. */
function woocommerce_epaync_add_link($links, $file)
{
    global $epaync_plugin_features;

    $links[] = '<a href="' . epaync_admin_url('Epaync') . '">' . __('General configuration', 'woo-epaync-payment') . '</a>';
    $links[] = '<a href="' . epaync_admin_url('EpayncStd') . '">' . __('Standard payment', 'woo-epaync-payment') . '</a>';

    if ($epaync_plugin_features['multi']) {
        $links[] = '<a href="' . epaync_admin_url('EpayncMulti') . '">' . __('Payment in installments', 'woo-epaync-payment')
            . '</a>';
    }

    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'woocommerce_epaync_add_link', 10, 2);

function epaync_admin_url($id)
{
    global $woocommerce;

    $base_url = 'admin.php?page=wc-settings&tab=checkout&section=';
    $section = strtolower($id); // Method id in lower case.

    // Backward compatibility.
    if (version_compare($woocommerce->version, '2.1.0', '<')) {
        $base_url = 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=';
        $section = 'WC_Gateway_' . $id; // Class name as it is.
    } elseif (version_compare($woocommerce->version, '2.6.2', '<')) {
        $section = 'wc_gateway_' . $section; // Class name in lower case.
    }

    return admin_url($base_url . $section);
}

function woocommerce_epaync_order_payment_gateways($available_gateways)
{
    global $woocommerce;
    $index_other_not_grouped_gateways_ids = array();
    $index_other_grouped_gateway_id = null;
    $gateways_ids = array();
    $index_gateways_ids = 0;
    foreach ($woocommerce->payment_gateways()->payment_gateways as $gateway) {
        if ($gateway->id === 'epayncregroupedother') {
            $index_other_grouped_gateway_id = $index_gateways_ids;
        } elseif (strpos($gateway->id, 'epayncother_') === 0) {
            $index_other_not_grouped_gateways_ids[] = $index_gateways_ids;
        }

        $gateways_ids[] = $gateway->id;
        $index_gateways_ids ++;
    }

    // User created epaync not grouped other payment means lets reorder payment gatways as they appear in woocommerce backend.
    // And if only they are not already in last position.
    if (! empty($index_other_not_grouped_gateways_ids) && ($index_other_grouped_gateway_id !== reset($index_other_not_grouped_gateways_ids) - 1)) {
        $ordered_gateways_ids = array();
        for ($i = 0; $i < $index_other_grouped_gateway_id; $i++) {
            $ordered_gateways_ids[] = $gateways_ids[$i];
        }

        foreach ($index_other_not_grouped_gateways_ids as $index_not_grouped_other_id) {
            $ordered_gateways_ids[] = $gateways_ids[$index_not_grouped_other_id];
        }

        for ($i = $index_other_grouped_gateway_id + 1; $i < count($gateways_ids); $i++) {
            if (! in_array($i, $index_other_not_grouped_gateways_ids)) {
                $ordered_gateways_ids[] = $gateways_ids[$i];
            }
        }

        $ordered_gateways = array();
        foreach ($ordered_gateways_ids as $gateway_id) {
            if (isset($available_gateways[$gateway_id])) {
                $ordered_gateways[$gateway_id] = $available_gateways[$gateway_id];
            }
        }

        return $ordered_gateways;
    }

    return $available_gateways;
}
add_filter('woocommerce_available_payment_gateways', 'woocommerce_epaync_order_payment_gateways');

if (! function_exists('ly_saved_cards_link')) {
    function ly_saved_cards_link($menu_links)
    {
        // Add "My payment means".
        $menu_links = array_slice($menu_links, 0, count($menu_links) - 1, true)
        + array('ly_saved_cards' => __('My payment means', 'woo-epaync-payment'))
        + array_slice($menu_links, count($menu_links) - 1, NULL, true);

        return $menu_links;
    }
    add_filter('woocommerce_account_menu_items', 'ly_saved_cards_link', 40);
}

if (! function_exists('ly_add_saved_cards_endpoint_query_vars')) {
    function ly_add_saved_cards_endpoint_query_vars($query_vars)
    {
        $query_vars['ly_saved_cards'] = 'ly_saved_cards';

        return $query_vars;
    }
    add_filter('woocommerce_get_query_vars', 'ly_add_saved_cards_endpoint_query_vars');
}

if (! function_exists('ly_change_saved_cards_title')) {
    function ly_change_saved_cards_title($title)
    {
        return __('My payment means', 'woo-epaync-payment');
    }
    add_filter('woocommerce_endpoint_ly_saved_cards_title', 'ly_change_saved_cards_title');
}

if (! function_exists('ly_add_saved_cards_endpoint')) {
    function ly_add_saved_cards_endpoint()
    {
        // Add "ly_saved_cards" endpoint.
        add_rewrite_endpoint('ly_saved_cards', EP_ROOT | EP_PAGES);
    }
    add_action('init', 'ly_add_saved_cards_endpoint');
}

function epaync_my_account_endpoint_content()
{
    global $woocommerce;

    $cust_id = WC_Gateway_Epaync::get_customer_property($woocommerce->customer, 'id');

    $sub_module_saving_cards_ids = array('epayncstd', 'epayncsubscription');

    $customer_saved_cards = array();
    $column_card_brand =  false;
    foreach ($sub_module_saving_cards_ids as $id) {
        $saved_masked_pan = get_user_meta((int) $cust_id, $id.'_masked_pan', true);
        if ($saved_masked_pan) {
            $card_brand_pos = strpos($saved_masked_pan, '|');
            if ($card_brand_pos) {
                $column_card_brand = true;
                $customer_saved_cards[$id]['card_brand'] = substr($saved_masked_pan, 0, strpos($saved_masked_pan, '|'));
            }

            $expiry_start_pos = strpos($saved_masked_pan, '(');
            $expiry_end_pos = strpos($saved_masked_pan, ')');
            $customer_saved_cards[$id]['card_number'] = substr($saved_masked_pan, $card_brand_pos + 1, $expiry_start_pos - $card_brand_pos - 2);
            $customer_saved_cards[$id]['expiry'] = substr($saved_masked_pan, $expiry_start_pos + 1, $expiry_end_pos - $expiry_start_pos -1);
        }
    }

    if (! empty($customer_saved_cards)) {
        wp_register_style('epaync', WC_EPAYNC_PLUGIN_URL . 'assets/css/epaync.css', array(), WC_Gateway_Epaync::PLUGIN_VERSION);
        wp_enqueue_style('epaync');

        echo '<table id="ly_cards_table" class="shop_table" id ="epaync-customer-card" style="display:none">
                <thead>
                  <tr>';
        if ($column_card_brand) {
            echo '<th>' . __('Type', 'woo-epaync-payment') . '</th>';
        }

        echo      '<th>' . __('Means of payment', 'woo-epaync-payment') . '</th>
                  <th>' . __('Action', 'woo-epaync-payment') . '</th>
                  </tr>
                </thead>
                <tbody>
                </tbody>
              </table>';

        $table_body = '';
        foreach ($customer_saved_cards as $id => $card) {
            $table_body .= '<tr>';
            if ($column_card_brand) {
                $card_brand_logo = $card['card_brand'];
                $remote_logo = WC_Gateway_Epaync::LOGO_URL . strtolower($card['card_brand']) . '.png';
                if ($card['card_brand']) {
                    $card_brand_logo = '<img src=\"' . $remote_logo. '\""
                           + "alt=\"' . $card['card_brand'] . '\""
                           + "title=\"' . $card['card_brand'] . '\""
                           + "style=\"vertical-align: middle; margin: 0 10px 0 5px; max-height: 30px; display: unset;\">';
                }

                $table_body .= '<td>' . $card_brand_logo . '</td>';
            }

            $table_body .= '<td>' . $card['card_number'] . ' - ' . $card['expiry'] . '</td>';
            $table_body .= '<td><a href=\"javascript: void(0);\" onclick=\"epayncConfirmDelete(\'' . $id . '\')\">' . __('Delete', 'woo-epaync-payment') . '</a></td></tr>';
        }

        $delete_card_url = add_query_arg('wc-api', 'WC_Gateway_Epaync_Delete_Saved_Card', home_url('/'));

        echo '<script>
                  jQuery(document).ready(function(){
                      if (jQuery("table[id=\'ly_cards_table\']").length == 2) {
                          jQuery("table[id=\'ly_cards_table\']:last").remove();
                      }

                      if (jQuery("#ly_empty_cards_message").length) {
                          jQuery("#ly_empty_cards_message").remove();
                      }

                      jQuery("#ly_cards_table > tbody:last").append("' . $table_body . '");
                      jQuery("#ly_cards_table").show();
                  });

                  function epayncConfirmDelete(id) {
                      if (confirm("' . __('Are you sure you want to delete your saved means of payment? This action is not reversible!', 'woo-epaync-payment') . '")) {
                          jQuery("body").block({
                             message: null,
                             overlayCSS: {
                                 background: "#fff",
                                 opacity: 0.5
                             }
                          });

                          jQuery("div.blockUI.blockOverlay").css("cursor", "default");

                          jQuery.ajax({
                              method: "POST",
                              url: "' . $delete_card_url . '",
                              data: { "id": id },
                              success: function() {
                                  location.reload();
                              }
                          });
                      }
                  }
              </script>';
    } else {
        echo '<div id="ly_empty_cards_message" class="woocommerce-Message woocommerce-Message--info woocommerce-info" style="display:none">'
                  . __('You have no stored payment means.', 'woo-epaync-payment') .
             '</div>
             <script>
                 jQuery(document).ready(function(){
                     if (jQuery("#ly_cards_table").length || jQuery("div[id=\'ly_empty_cards_message\']").length == 2) {
                         jQuery("#ly_empty_cards_message").remove();
                     }

                     if (! jQuery("#ly_cards_table").length) {
                         jQuery("#ly_empty_cards_message").show();
                     }
                 });
             </script>';
    }
}
add_action('woocommerce_account_ly_saved_cards_endpoint', 'epaync_my_account_endpoint_content');

function epaync_send_support_email_on_order($order)
{
    global $epaync_plugin_features;

    $std_payment_method = new WC_Gateway_EpayncStd();
    if (substr(WC_Gateway_EpayncStd::get_order_property($order, 'payment_method'), 0, strlen('epaync')) === 'epaync') {
        $user_info = get_userdata(1);
        $send_email_url = add_query_arg('wc-api', 'WC_Gateway_Epaync_Send_Email', home_url('/'));

        $epaync_email_send_msg = get_transient('epaync_email_send_msg');
        if ($epaync_email_send_msg) {
            echo $epaync_email_send_msg;

            delete_transient('epaync_email_send_msg');
        }

        $epaync_update_subscription_error_msg = get_transient('epaync_update_subscription_error_msg');

        if ($epaync_plugin_features['support']) {
        ?>
        <script type="text/javascript" src="<?php echo WC_EPAYNC_PLUGIN_URL; ?>/assets/js/support.js"></script>
        <contact-support
            shop-id="<?php echo $std_payment_method->get_general_option('site_id'); ?>"
            context-mode="<?php echo $std_payment_method->get_general_option('ctx_mode'); ?>"
            sign-algo="<?php echo $std_payment_method->get_general_option('sign_algo'); ?>"
            contrib="<?php echo EpayncTools::get_contrib(); ?>"
            integration-mode="<?php echo EpayncTools::get_integration_mode(); ?>"
            plugins="<?php echo EpayncTools::get_active_plugins(); ?>"
            title=""
            first-name="<?php echo $user_info->first_name; ?>"
            last-name="<?php echo $user_info->last_name; ?>"
            from-email="<?php echo get_option('admin_email'); ?>"
            to-email="<?php echo WC_Gateway_Epaync::SUPPORT_EMAIL; ?>"
            cc-emails=""
            phone-number=""
            language="<?php echo EpayncTools::get_support_component_language(); ?>"
            is-order="true"
            transaction-uuid="<?php echo EpayncTools::get_transaction_uuid($order); ?>"
            order-id="<?php echo WC_Gateway_EpayncStd::get_order_property($order, 'id'); ?>"
            order-number="<?php echo WC_Gateway_EpayncStd::get_order_property($order, 'id'); ?>"
            order-status=<?php echo WC_Gateway_EpayncStd::get_order_property($order, 'status'); ?>
            order-date="<?php echo WC_Gateway_EpayncStd::get_order_property($order, 'date_created'); ?>"
            order-amount="<?php echo WC_Gateway_EpayncStd::get_order_property($order, 'total') . ' ' . WC_Gateway_EpayncStd::get_order_property($order, 'currency'); ?>"
            cart-amount=""
            shipping-fees="<?php echo WC_Gateway_EpayncStd::get_order_property($order, 'shipping_total') . ' ' . WC_Gateway_EpayncStd::get_order_property($order, 'currency'); ?>"
            order-discounts="<?php echo EpayncTools::get_used_discounts($order); ?>"
            order-carrier="<?php echo WC_Gateway_EpayncStd::get_order_property($order, 'shipping_method'); ?>"></contact-support>
        <?php
            // Load css and add spinner.
            wp_register_style('epaync', WC_EPAYNC_PLUGIN_URL . 'assets/css/epaync.css', array(),  WC_Gateway_Epaync::PLUGIN_VERSION);
            wp_enqueue_style('epaync');
        }
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function() {
              <?php if ($epaync_plugin_features['support']) { ?>
                jQuery('contact-support').on('sendmail', function(e) {
                    jQuery('body').block({
                        message: null,
                        overlayCSS: {
                            background: '#fff',
                            opacity: 0.5
                        }
                    });

                    jQuery('div.blockUI.blockOverlay').css('cursor', 'default');

                    jQuery.ajax({
                        method: 'POST',
                        url: '<?php echo $send_email_url; ?>',
                        data: e.originalEvent.detail,
                        success: function(data) {
                            location.reload();
                        }
                    });
                });
        <?php
            }

            if ($epaync_update_subscription_error_msg) {
                delete_transient('epaync_update_subscription_error_msg');
        ?>
                jQuery('#lost-connection-notice').after('<div class="error notice is-dismissible"><p><?php echo addslashes($epaync_update_subscription_error_msg); ?></p><button type="button" class="notice-dismiss" onclick="this.parentElement.remove()"><span class="screen-reader-text"><?php echo esc_html__( 'Dismiss this notice.', 'woocommerce' )  ?></span></button></div>');
        <?php } ?>
            });
        </script>
        <?php
    }
}
// Add contact support link to order details page.
add_action('woocommerce_admin_order_data_after_billing_address', 'epaync_send_support_email_on_order');

function epaync_send_email()
{
    if (isset($_POST['submitter']) && $_POST['submitter'] === 'epaync_send_support') {
        $msg = '';
        if (isset($_POST['sender']) && isset($_POST['subject']) && isset($_POST['message'])) {
            $recipient = WC_Gateway_Epaync::SUPPORT_EMAIL;
            $subject = $_POST['subject'];
            $content = $_POST['message'];
            $headers = array('Content-Type: text/html; charset=UTF-8');

            if (wp_mail($recipient, $subject, $content, $headers)) {
                $msg = '<div class="inline updated"><p><strong>' . __('Thank you for contacting us. Your email has been successfully sent.', 'woo-epaync-payment') . '</strong></p></div>';
            } else {
                $msg = '<div class="inline error"><p><strong>' . __('An error has occurred. Your email was not sent.', 'woo-epaync-payment') . '</strong></p></div>';
            }
        } else {
            $msg = '<div class="inline error"><p><strong>' . __('Please make sure to configure all required fields.', 'woo-epaync-payment') . '</strong></p></div>';
        }

        set_transient('epaync_email_send_msg', $msg);
    }

    echo json_encode(array('success' => true));
    die();
}
// Send support email.
add_action('woocommerce_api_wc_gateway_epaync_send_email', 'epaync_send_email');

/* Retrieve blog_id from post when this is an IPN URL call. */
if (is_multisite() && key_exists('vads_hash', $_POST) && $_POST['vads_hash']
    && key_exists('vads_ext_info_blog_id', $_POST) && $_POST['vads_ext_info_blog_id']) {
    global $wpdb, $current_blog, $current_site;

    $blog = $_POST['vads_ext_info_blog_id'];
    switch_to_blog((int) $blog);

    // Set current_blog global var.
    $current_blog = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $wpdb->blogs WHERE blog_id = %s", $blog)
    );

    // Set current_site global var.
    $network_fnc = function_exists('get_network') ? 'get_network' : 'wp_get_network';
    $current_site = $network_fnc($current_blog->site_id);
    $current_site->blog_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT blog_id FROM $wpdb->blogs WHERE domain = %s AND path = %s",
            $current_site->domain,
            $current_site->path
        )
    );

    $current_site->site_name = get_site_option('site_name');
    if (! $current_site->site_name) {
        $current_site->site_name = ucfirst($current_site->domain);
    }
}
