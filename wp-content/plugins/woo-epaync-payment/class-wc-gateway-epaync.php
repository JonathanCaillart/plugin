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

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class WC_Gateway_Epaync extends WC_Payment_Gateway
{
    const GATEWAY_CODE = 'EpayNC';
    const GATEWAY_NAME = 'EpayNC';
    const BACKOFFICE_NAME = 'EpayNC';
    const GATEWAY_URL = 'https://epaync.nc/vads-payment/';
    const REST_URL = 'https://epaync.nc/api-payment/';
    const STATIC_URL = 'https://epaync.nc/static/';
    const LOGO_URL = 'https://epaync.nc/static/latest/images/type-carte/';
    const SITE_ID = '12345678';
    const KEY_TEST = '1111111111111111';
    const KEY_PROD = '2222222222222222';
    const CTX_MODE = 'TEST';
    const SIGN_ALGO = 'SHA-256';
    const LANGUAGE = 'fr';

    const CMS_IDENTIFIER = 'WooCommerce_2.x-5.x';
    const SUPPORT_EMAIL = 'sav@csb.nc';
    const PLUGIN_VERSION = '1.9.4';
    const GATEWAY_VERSION = 'V2';

    protected $admin_page;
    protected $admin_link;
    protected $reset_admin_link;

    protected $general_settings = array();
    protected $general_form_fields = array();
    protected $notices = array();

    public function __construct()
    {
        $this->id = 'epaync';
        $this->has_fields = false;
        $this->method_title = self::GATEWAY_NAME . ' - ' . __('General configuration', 'woo-epaync-payment');

        // Init common vars.
        $this->epaync_init();

        // Load the form fields.
        $this->init_form_fields();

        // Load the module settings.
        $this->init_settings();

        $this->title = __('General configuration', 'woo-epaync-payment');
        $this->enabled = false;
        $this->testmode = ($this->get_general_option('ctx_mode') == 'TEST');
        $this->debug = ($this->get_general_option('debug') == 'yes') ? true : false;

        if ($this->epaync_is_section_loaded()) {
            // Reset common admin form action.
            add_action('woocommerce_settings_start', array($this, 'epaync_reset_admin_options'));

            // Adding style to admin form action.
            add_action('admin_head-woocommerce_page_' . $this->admin_page, array($this, 'epaync_admin_head_style'));

            // Adding JS to admin form action.
            add_action('admin_head-woocommerce_page_' . $this->admin_page, array($this, 'epaync_admin_head_script'));

            // Update admin form action.
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        // Return from payment gateway action.
        add_action('woocommerce_api_wc_gateway_epaync', array($this, 'epaync_notify_response'));

        // Filter to allow order status override.
        add_filter('woocommerce_payment_complete_order_status', array($this, 'epaync_complete_order_status'), 10, 2);

        // Customize email.
        add_action('woocommerce_email_after_order_table', array($this, 'epaync_add_order_email_payment_result'), 10, 3);

        // Print our notices when payment was sucessful.
        add_action('woocommerce_before_template_part', array($this, 'epaync_notices'), 10, 4);

        // Delete saved means of payment and saved identifier.
        add_action('woocommerce_api_wc_gateway_epaync_delete_saved_card', array($this, 'epaync_delete_saved_card'));
    }

    protected function epaync_is_section_loaded()
    {
        $current_section = isset($_GET['section']) ? $_GET['section'] : null;
        return ($current_section === $this->id) || (strtolower($current_section) === strtolower(get_class($this)));
    }


    protected function epaync_init()
    {
        $this->logger = new WC_Logger();

        // Init API.
        $this->epaync_request = new EpayncRequest();

        if ($this->epaync_is_section_loaded()) {
            $this->admin_page = $_GET['page'];

            $this->admin_link = admin_url('admin.php?page=' . $_GET['page'] . '&tab=' . $_GET['tab'] . '&section=' . $_GET['section']);

            $this->reset_admin_link = add_query_arg('noheader', '', add_query_arg('reset', '', $this->admin_link));
            $this->reset_admin_link = wp_nonce_url($this->reset_admin_link, $_GET['page']);
        }
    }

    public function epaync_admin_head_style()
    {
        ?>
        <style>
            .epaync p.description {
                color: #0073aa !important;
                font-style: normal !important;
            }

            #woocommerce_epaync_url_check + p.description span.url {
                color: #23282d !important;
                font-size: 16px;
                font-weight: bold;
            }

            #woocommerce_epaync_url_check + p.description span.desc {
                color: red !important;
            }

            #woocommerce_epaync_url_check + p.description img {
                vertical-align: middle;
                margin-right: 5px;
            }

            #woocommerce_epaync_rest_check_url + p.description span.url {
                color: #23282d !important;
                font-size: 16px;
                font-weight: bold;
            }

            #woocommerce_epaync_rest_check_url + p.description span.desc {
                color: red !important;
                display: inline-block;
            }

            #woocommerce_epaync_rest_check_url + p.description img {
                margin-right: 5px;
            }
        </style>
        <?php
    }

    public function epaync_admin_head_script()
    {
        ?>
        <script type="text/javascript">
        //<!--
            jQuery(function() {
                epayncUpdateCategoryDisplay();
            });

            function epayncUpdateCategoryDisplay() {
                var commonCategory = jQuery('#<?php echo esc_attr($this->get_field_key('common_category')); ?> option:selected').val();
                var categoryMapping = jQuery('#<?php echo esc_attr($this->get_field_key('additional_options')); ?>').next().next().find('tr:nth-child(2)');

                if (commonCategory === 'CUSTOM_MAPPING') {
                    categoryMapping.show();
                } else {
                    categoryMapping.hide();
                }
            }
        //-->
        </script>
        <?php
    }

    /**
     * Admin panel options.
     */
    public function admin_options()
    {
        if (! $this->is_supported_currency()) {
            echo '<div class="inline error"><p><strong>' . __('Gateway disabled', 'woo-epaync-payment') . ': ' . sprintf(__('%s does not support your store currency.', 'woo-epaync-payment'), self::GATEWAY_NAME) . '</strong></p></div>';
        }

        if (get_transient($this->id . '_settings_reset')) {
            delete_transient($this->id . '_settings_reset');

            echo '<div class="inline updated"><p><strong>' . sprintf(__('Your %s module configuration is successfully reset.', 'woo-epaync-payment'), self::GATEWAY_NAME) . '</strong></p></div>';
        }

        $epaync_email_send_msg = get_transient('epaync_email_send_msg');
        if ($epaync_email_send_msg) {
            echo $epaync_email_send_msg;

            delete_transient('epaync_email_send_msg');
        }
        ?>

        <script type="text/javascript" src="<?php echo WC_EPAYNC_PLUGIN_URL . '/assets/js/support.js' ?>"></script>
        <br />
        <h3><?php echo self::GATEWAY_NAME; ?></h3>
        <p><?php echo sprintf(__('The module works by sending users to %s in order to select their payment mean and enter their payment information.', 'woo-epaync-payment'), self::GATEWAY_NAME); ?></p>

        <?php foreach ($this->notices as $notice) { ?>
            <p style="background: none repeat scroll 0 0 #FFFFE0; border: 1px solid #E6DB55; margin: 0 0 20px; padding: 10px; font-weight: bold;"><?php echo $notice; ?></p>
        <?php } ?>

        <section class="epaync">
            <table class="form-table">
                <?php $this->generate_settings_html(); // Generate the HTML For the settings form. ?>
            </table>
        </section>

        <a href="<?php echo $this->reset_admin_link; ?>"><?php _e('Reset configuration', 'woo-epaync-payment');?></a>

        <?php
    }

    public function epaync_reset_admin_options()
    {
        // If not reset action do nothing.
        if (! isset($_GET['reset'])) {
            return;
        }

        // Check if correct link.
        if (! $this->epaync_is_section_loaded()) {
            return;
        }

        delete_option('woocommerce_' . $this->id . '_settings');

        // Transcient flag to display reset message.
        set_transient($this->id . '_settings_reset', true);

        wp_redirect($this->admin_link);
        exit();
    }

    protected function get_supported_languages()
    {
        $langs = array();

        foreach (EpayncApi::getSupportedLanguages() as $code => $label) {
            $langs[$code] = __($label, 'woo-epaync-payment');
        }

        return $langs;
    }

    protected function get_validation_modes($is_general_settings = false)
    {
        $modes = array(
            '-1' => sprintf(__('%s general configuration', 'woo-epaync-payment'), self::GATEWAY_NAME),
            ' ' => sprintf(__('%s Back Office configuration', 'woo-epaync-payment'), self::BACKOFFICE_NAME),
            '0' => __('Automatic', 'woo-epaync-payment'),
            '1' => __('Manual', 'woo-epaync-payment')
        );

        if ($is_general_settings) {
            unset($modes['-1']);
        }

        return $modes;
    }

    protected function get_gateway_categories($no_select_opt = true)
    {
        $categories = array(
            'FOOD_AND_GROCERY' => __('Food and grocery', 'woo-epaync-payment'),
            'AUTOMOTIVE' => __('Automotive', 'woo-epaync-payment'),
            'ENTERTAINMENT' => __('Entertainment', 'woo-epaync-payment'),
            'HOME_AND_GARDEN' => __('Home and garden', 'woo-epaync-payment'),
            'HOME_APPLIANCE' => __('Home appliance', 'woo-epaync-payment'),
            'AUCTION_AND_GROUP_BUYING' => __('Auction and group buying', 'woo-epaync-payment'),
            'FLOWERS_AND_GIFTS' => __('Flowers and gifts', 'woo-epaync-payment'),
            'COMPUTER_AND_SOFTWARE' => __('Computer and software', 'woo-epaync-payment'),
            'HEALTH_AND_BEAUTY' => __('Health and beauty', 'woo-epaync-payment'),
            'SERVICE_FOR_INDIVIDUAL' => __('Service for individual', 'woo-epaync-payment'),
            'SERVICE_FOR_BUSINESS' => __('Service for business', 'woo-epaync-payment'),
            'SPORTS' => __('Sports', 'woo-epaync-payment'),
            'CLOTHING_AND_ACCESSORIES' => __('Clothing and accessories', 'woo-epaync-payment'),
            'TRAVEL' => __('Travel', 'woo-epaync-payment'),
            'HOME_AUDIO_PHOTO_VIDEO' => __('Home audio, photo, video', 'woo-epaync-payment'),
            'TELEPHONY' => __('Telephony', 'woo-epaync-payment')
        );

        if ($no_select_opt) {
            return $categories;
        } else {
            return array_merge(
                array(
                    '' => '---',
                    'CUSTOM_MAPPING' => __('(Use category mapping below)', 'woo-epaync-payment'),
                ),
                $categories
            );
        }
    }

    protected function get_method_title_field_description()
    {
        return __('Method title to display on payment means page.', 'woo-epaync-payment');
    }

    protected function get_method_description_field_description()
    {
        return __('This controls the description which the user sees during checkout.', 'woo-epaync-payment');
    }

    /**
     * Get description function.
     *
     * @access public
     * @return string
     */

    public function get_description()
    {
        $description = $this->get_option('description');

        if (is_array($description)) {
            $description = isset($description[get_locale()]) && $description[get_locale()] ? $description[get_locale()] : $description['en_US'];
        }

        return apply_filters('woocommerce_gateway_description', $description, $this->id);
    }

    /**
     * Initialise gateway settings form fields.
     */
    public function init_form_fields()
    {
        global $woocommerce, $epaync_plugin_features;

        // Get log folder.
        if (function_exists('wc_get_log_file_path')) {
            $log_folder = dirname(wc_get_log_file_path('epaync')) . '/';
        } else {
            $log_folder = $woocommerce->plugin_path() . '/logs/';
        }

        $log_folder = str_replace('\\', '/', $log_folder);

        // Get relative path.
        $base_dir = str_replace('\\', '/', ABSPATH);
        if (strpos($log_folder, $base_dir) === 0) {
            $log_folder = str_replace($base_dir, '', $log_folder);
        } else {
            $base_dir = str_replace('\\', '/', dirname(ABSPATH));
            $log_folder = str_replace($base_dir, '..', $log_folder);
        }

        // Get documentation links.
        $docs = '';
        $minor = substr(self::PLUGIN_VERSION, 0, strrpos(self::PLUGIN_VERSION, '.'));
        $doc_pattern = plugin_dir_path(__FILE__) . 'installation_doc/';
        $doc_pattern .= self::GATEWAY_CODE . '_' . self::CMS_IDENTIFIER . '_v' . $minor . '*.pdf';
        $filenames = glob($doc_pattern);

        if (! empty($filenames)) {
            $languages = array(
                'fr' => 'Français',
                'en' => 'English',
                'de' => 'Deutsch',
                'es' => 'Español'
                // Complete when other languages are managed.
            );

            $docs = __('Click to view the module configuration documentation: ', 'woo-epaync-payment');

            foreach ($filenames as $filename) {
                $base_filename = basename($filename, '.pdf');
                $lang = substr($base_filename, -2); // Extract language code.

                $docs .= '<a style="margin-left: 10px; text-decoration: none; text-transform: uppercase;" href="' . WC_EPAYNC_PLUGIN_URL
                . 'installation_doc/' . $base_filename . '.pdf" target="_blank">' . $languages[$lang] . '</a>';
            }
        }

        $this->form_fields = array(
            // Module information.
            'module_details' => array(
                'title' => __('MODULE DETAILS', 'woo-epaync-payment'),
                'type' => 'title'
            ),
            'developed_by' => array(
                'title' => __('Developed by', 'woo-epaync-payment'),
                'type' => 'text',
                'description' => '<b><a href="https://www.lyra.com/" target="_blank">Lyra Network</a></b>',
                'css' => 'display: none;'
            ),
            'contact' => array(
                'title' => __('Contact us', 'woo-epaync-payment'),
                'type' => 'text',
                'description' => '<b>' . EpayncApi::formatSupportEmails(self::SUPPORT_EMAIL) . '</b>',
                'css' => 'display: none;'
            ),
            'contrib_version' => array(
                'title' => __('Module version', 'woo-epaync-payment'),
                'type' => 'text',
                'description' => self::PLUGIN_VERSION,
                'css' => 'display: none;'
            ),
            'platform_version' => array(
                'title' => __('Gateway version', 'woo-epaync-payment'),
                'type' => 'text',
                'description' => self::GATEWAY_VERSION,
                'css' => 'display: none;'
            ),
            'doc_link' => array(
                'title' => $docs,
                'type' => 'label',
                'css' => 'font-weight: bold; color: red; cursor: auto !important; text-transform: uppercase;'
            ),
            'support_component' => array(
                'type' => 'support_component'
            ),

            'base_settings' => array(
                'title' => __('BASE SETTINGS', 'woo-epaync-payment'),
                'type' => 'title'
            ),
            'debug' => array(
                'title' => __('Logs', 'woo-epaync-payment'),
                'label' => __('Enable / disable', 'woo-epaync-payment'),
                'type' => 'checkbox',
                'default' => 'yes',
                'description' => sprintf(__('Enable / disable module logs. The log file will be inside <code>%s</code>.', 'woo-epaync-payment'), $log_folder),
            ),

            // Payment gateway access params.
            'payment_gateway_access' => array(
                'title' => __('PAYMENT GATEWAY ACCESS', 'woo-epaync-payment'),
                'type' => 'title'
            ),
            'site_id' => array(
                'title' => __('Shop ID', 'woo-epaync-payment'),
                'type' => 'text',
                'default' => self::SITE_ID,
                'description' => sprintf(__('The identifier provided by %s.', 'woo-epaync-payment'), self::GATEWAY_NAME),
                'custom_attributes' => array('autocomplete' => 'off')
            ),
            'key_test' => array(
                'title' => __('Key in test mode', 'woo-epaync-payment'),
                'type' => 'text',
                'default' => self::KEY_TEST,
                'description' => sprintf(__('Key provided by %s for test mode (available in %s Back Office).', 'woo-epaync-payment'), self::GATEWAY_NAME, self::BACKOFFICE_NAME),
                'custom_attributes' => array('autocomplete' => 'off')
            ),
            'key_prod' => array(
                'title' => __('Key in production mode', 'woo-epaync-payment'),
                'type' => 'text',
                'default' => self::KEY_PROD,
                'description' => sprintf(__('Key provided by %s (available in %s Back Office after enabling production mode).', 'woo-epaync-payment'), self::GATEWAY_NAME, self::BACKOFFICE_NAME),
                'custom_attributes' => array('autocomplete' => 'off')
            ),
            'ctx_mode' => array(
                'title' => __('Mode', 'woo-epaync-payment'),
                'type' => 'select',
                'default' => self::CTX_MODE,
                'options' => array(
                    'TEST' => __('TEST', 'woo-epaync-payment'),
                    'PRODUCTION' => __('PRODUCTION', 'woo-epaync-payment')
                ),
                'description' => __('The context mode of this module.', 'woo-epaync-payment'),
                'class' => 'wc-enhanced-select'
            ),
            'sign_algo' => array(
                'title' => __('Signature algorithm', 'woo-epaync-payment'),
                'type' => 'select',
                'default' => self::SIGN_ALGO,
                'options' => array(
                    EpayncApi::ALGO_SHA1 => 'SHA-1',
                    EpayncApi::ALGO_SHA256 => 'HMAC-SHA-256'
                ),
                'description' => sprintf(__('Algorithm used to compute the payment form signature. Selected algorithm must be the same as one configured in the %s Back Office.<br /><b>The HMAC-SHA-256 algorithm should not be activated if it is not yet available in the %s Back Office, the feature will be available soon.</b>', 'woo-epaync-payment'), self::BACKOFFICE_NAME, self::BACKOFFICE_NAME),
                'class' => 'wc-enhanced-select'
            ),
            'url_check' => array(
                'title' => __('Instant Payment Notification URL', 'woo-epaync-payment'),
                'type' => 'text',
                'description' => '<span class="url">' . add_query_arg('wc-api', 'WC_Gateway_Epaync', network_home_url('/')) . '</span><br />' .
                    '<img src="' . esc_url(WC_EPAYNC_PLUGIN_URL . 'assets/images/warn.png') . '"><span class="desc">' . sprintf(__('URL to copy into your %s Back Office > Settings > Notification rules.', 'woo-epaync-payment'), self::BACKOFFICE_NAME) . '</span>',
                'css' => 'display: none;'
            ),
            'platform_url' => array(
                'title' => __('Payment page URL', 'woo-epaync-payment'),
                'type' => 'text',
                'default' => self::GATEWAY_URL,
                'description' => __('Link to the payment page.', 'woo-epaync-payment'),
                'css' => 'width: 350px;'
            ),

            // Add REST API key fields.
            'rest_settings' => array(
                'title' => __('REST API keys', 'woo-epaync-payment'),
                'type' => 'title',
                'description' => sprintf(__('REST API keys are available in your %s Back Office (menu: Settings > Shops > REST API keys).<br><br>Configure this section if you are using order operations from WooCommerce Back Office or if you are using embedded payment fields or popin modes.', 'woo-epaync-payment'), self::BACKOFFICE_NAME)
             ),
             'test_private_key' => array(
                 'title' => __('Test password', 'woo-epaync-payment'),
                 'type' => 'password',
                 'default' => '',
                 'custom_attributes' => array('autocomplete' => 'off')
             ),
             'prod_private_key' => array(
                 'title' => __('Production password', 'woo-epaync-payment'),
                 'type' => 'password',
                 'default' => '',
                 'custom_attributes' => array('autocomplete' => 'off')
             ),
             'rest_url' => array(
                'title' => __('API REST server URL', 'woo-epaync-payment'),
                'type' => 'text',
                'default' => self::REST_URL,
                'css' => 'width: 350px;'
             ),
            'embedded_fields_keys_settings' => array(
                'title' => '',
                'type' => 'title',
                'description' => sprintf(__('Configure this section only if you are using embedded payment fields or popin modes.', 'woo-epaync-payment'), self::BACKOFFICE_NAME)
            ),
             'test_public_key' => array(
                 'title' => __('Public test key', 'woo-epaync-payment'),
                 'type' => 'text',
                 'default' => '',
                 'custom_attributes' => array('autocomplete' => 'off')
             ),
             'prod_public_key' => array(
                 'title' => __('Public production key', 'woo-epaync-payment'),
                 'type' => 'text',
                 'default' => '',
                 'custom_attributes' => array('autocomplete' => 'off')
             ),
             'test_return_key' => array(
                 'title' => __('HMAC-SHA-256 test key', 'woo-epaync-payment'),
                 'type' => 'password',
                 'default' => '',
                 'custom_attributes' => array('autocomplete' => 'off')
             ),
             'prod_return_key' => array(
                 'title' => __('HMAC-SHA-256 production key', 'woo-epaync-payment'),
                 'type' => 'password',
                 'default' => '',
                 'custom_attributes' => array('autocomplete' => 'off')
             ),
            'static_url' => array(
                'title' => __('JavaScript client URL', 'woo-epaync-payment'),
                'type' => 'text',
                'default' => self::STATIC_URL,
                'css' => 'width: 350px;'
            ),
            'rest_check_url' => array(
                'title' => __('API REST Notification URL', 'woo-epaync-payment'),
                'type' => 'text',
                'default' => '',
                'description' => '<span class="url">' . add_query_arg('wc-api', 'WC_Gateway_Epaync_Notify_Rest', network_home_url('/')). '</span><br />' .
                '<img src="' . esc_url(WC_EPAYNC_PLUGIN_URL . 'assets/images/warn.png') . '"><span class="desc">' . sprintf(__('URL to copy into your %s Back Office > Settings > Notification rules.<br>In multistore mode, notification URL is the same for all the stores.', 'woo-epaync-payment'), self::GATEWAY_NAME). '</span>',
                'css' => 'display: none;'
            ),

            // Payment page params.
            'payment_page' => array(
                'title' => __('PAYMENT PAGE', 'woo-epaync-payment'),
                'type' => 'title'
            ),
            'language' => array(
                'title' => __('Default language', 'woo-epaync-payment'),
                'type' => 'select',
                'default' => self::LANGUAGE,
                'options' => $this->get_supported_languages(),
                'description' => __('Default language on the payment page.', 'woo-epaync-payment'),
                'class' => 'wc-enhanced-select'
            ),
            'available_languages' => array(
                'title' => __('Available languages', 'woo-epaync-payment'),
                'type' => 'multiselect',
                'default' => array(),
                'options' => $this->get_supported_languages(),
                'description' => __('Languages available on the payment page. If you do not select any, all the supported languages will be available.', 'woo-epaync-payment'),
                'class' => 'wc-enhanced-select'
            ),
            'capture_delay' => array(
                'title' => __('Capture delay', 'woo-epaync-payment'),
                'type' => 'text',
                'default' => '',
                'description' => sprintf(__('The number of days before the bank capture (adjustable in your %s Back Office).', 'woo-epaync-payment'), self::BACKOFFICE_NAME)
            ),
            'validation_mode' => array(
                'title' => __('Validation mode', 'woo-epaync-payment'),
                'type' => 'select',
                'default' => '',
                'options' => $this->get_validation_modes(true),
                'description' => sprintf(__('If manual is selected, you will have to confirm payments manually in your %s Back Office.', 'woo-epaync-payment'), self::BACKOFFICE_NAME),
                'class' => 'wc-enhanced-select'
            ),

            // Selective 3DS.
            'selective_3ds' => array(
                'title' => __('CUSTOM 3DS', 'woo-epaync-payment'),
                'type' => 'title'
            ),
            '3ds_min_amount' => array(
                'title' => __('Manage 3DS', 'woo-epaync-payment'),
                'type' => 'text',
                'default' => '',
                'description' => __('Amount below which customer could be exempt from strong authentication. Needs subscription to «Selective 3DS1» or «Frictionless 3DS2» options. For more information, refer to the module documentation.', 'woo-epaync-payment')
            ),

            // Return to store params.
            'return_options' => array(
                'title' => __('RETURN OPTIONS', 'woo-epaync-payment'),
                'type' => 'title'
            ),
            'redirect_enabled' => array(
                'title' => __('Automatic redirection', 'woo-epaync-payment'),
                'label' => __('Enable / disable', 'woo-epaync-payment'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('If enabled, the buyer is automatically redirected to your site at the end of the payment.', 'woo-epaync-payment')
            ),
            'redirect_success_timeout' => array(
                'title' => __('Redirection timeout on success', 'woo-epaync-payment'),
                'type' => 'text',
                'default' => '5',
                'description' => __('Time in seconds (0-300) before the buyer is automatically redirected to your website after a successful payment.', 'woo-epaync-payment')
            ),
            'redirect_success_message' => array(
                'title' => __('Redirection message on success', 'woo-epaync-payment'),
                'type' => 'multilangtext',
                'default' => array(
                    'en_US' => 'Redirection to shop in a few seconds...',
                    'en_GB' => 'Redirection to shop in a few seconds...',
                    'fr_FR' => 'Redirection vers la boutique dans quelques instants...',
                    'de_DE' => 'Weiterleitung zum Shop in Kürze...',
                    'es_ES' => 'Redirección a la tienda en unos momentos...'
                ),
                'description' => __('Message displayed on the payment page prior to redirection after a successful payment.', 'woo-epaync-payment'),
                'css' => 'width: 35em;'
            ),
            'redirect_error_timeout' => array(
                'title' => __('Redirection timeout on failure', 'woo-epaync-payment'),
                'type' => 'text',
                'default' => '5',
                'description' => __('Time in seconds (0-300) before the buyer is automatically redirected to your website after a declined payment.', 'woo-epaync-payment')
            ),
            'redirect_error_message' => array(
                'title' => __('Redirection message on failure', 'woo-epaync-payment'),
                'type' => 'multilangtext',
                'default' => array(
                    'en_US' => 'Redirection to shop in a few seconds...',
                    'en_GB' => 'Redirection to shop in a few seconds...',
                    'fr_FR' => 'Redirection vers la boutique dans quelques instants...',
                    'de_DE' => 'Weiterleitung zum Shop in Kürze...',
                    'es_ES' => 'Redirección a la tienda en unos momentos...'
                ),
                'description' => __('Message displayed on the payment page prior to redirection after a declined payment.', 'woo-epaync-payment'),
                'css' => 'width: 35em;'
            ),
            'return_mode' => array(
                'title' => __('Return mode', 'woo-epaync-payment'),
                'type' => 'select',
                'default' => 'GET',
                'options' => array(
                    'GET' => 'GET',
                    'POST' => 'POST'
                ),
                'description' => __('Method that will be used for transmitting the payment result from the payment page to your shop.', 'woo-epaync-payment'),
                'class' => 'wc-enhanced-select'
            ),
            'order_status_on_success' => array(
                'title' => __('Order Status', 'woo-epaync-payment'),
                'type' => 'select',
                'default' => 'default',
                'options' => self::get_success_order_statuses(true),
                'description' => __('Defines the status of orders paid with this payment mode.', 'woo-epaync-payment'),
                'class' => 'wc-enhanced-select'
            ),

            // Additional options.
            'additional_options' => array(
                'title' => __('ADDITIONAL OPTIONS', 'woo-epaync-payment'),
                'type' => 'title',
                'description' => __('Configure this section if you are using advanced risk assessment module or if you are proposing payment means requiring cart data (Franfinance or Klarna).', 'woo-epaync-payment')
            ),
            'common_category' => array(
                'custom_attributes' => array(
                    'onchange' => 'epayncUpdateCategoryDisplay()'
                ),
                'title' => __('Category mapping', 'woo-epaync-payment'),
                'type' => 'select',
                'options' => $this->get_gateway_categories(false),
                'description' => __('Use the same category for all products.', 'woo-epaync-payment'),
                'default' => '',
                'class' => 'wc-enhanced-select'
            ),
        );

        $columns['label'] = array(
            'title' => __('Product category', 'woo-epaync-payment'),
            'width' => '154px'
        );

        $columns['category'] = array(
            'title' => sprintf(__('%s category', 'woo-epaync-payment'), self::BACKOFFICE_NAME),
            'width' => '154px'
        );

        $this->form_fields['category_mapping'] = array(
            'type' => 'category_mapping',
            'columns' => $columns,
            'description' => sprintf(__('Match each product category with a %s product category. <br /><b>Entries marked with * are newly added and must be configured.</b>', 'woo-epaync-payment'), self::BACKOFFICE_NAME)
        );

        if ($epaync_plugin_features['qualif']) {
            // Tests will be made on qualif, no test mode available.
            unset($this->form_fields['key_test']);

            $this->form_fields['ctx_mode']['disabled'] = true;
        }

        if ($epaync_plugin_features['shatwo']) {
            // HMAC-SHA-256 already available, update field description.
            $desc = preg_replace('#<br /><b>[^<>]+</b>#', '', $this->form_fields['sign_algo']['description']);
            $this->form_fields['sign_algo']['description'] = $desc;
        }

        if (! $docs) {
            unset($this->form_fields['doc_link']);
        }

        if (! $epaync_plugin_features['support']) {
            unset($this->form_fields['support_component']);
        }

        // Save general form fields.
        foreach ($this->form_fields as $k => $v) {
            $this->general_form_fields[$k] = $v;
        }
    }

    protected function init_general_settings()
    {
        $this->general_settings = get_option('woocommerce_epaync_settings', null);

        // If there are no settings defined, use defaults.
        if (! is_array($this->general_settings) || empty($this->general_settings)) {
            $this->general_settings = array();

            foreach ($this->general_form_fields as $k => $v) {
                $this->general_settings[$k] = isset($v['default']) ? $v['default'] : '';
            }
        }
    }

    public function get_general_option($key, $empty_value = null)
    {
        if (empty($this->general_settings)) {
            $this->init_general_settings();
        }

        // Get empty string if unset.
        if (! isset($this->general_settings[$key])) {
            $this->general_settings[$key] = '';
        }

        if (! is_null($empty_value) && ($this->general_settings[$key] === '')) {
            $this->general_settings[$key] = $empty_value;
        }

        return $this->general_settings[$key];
    }

    public function generate_support_component_html($key, $data)
    {
        $user_info = get_userdata(1);
        $send_email_url = add_query_arg('wc-api', 'WC_Gateway_Epaync_Send_Email', home_url('/'));

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <contact-support
                    shop-id="<?php echo $this->get_general_option('site_id'); ?>"
                    context-mode="<?php echo $this->get_general_option('ctx_mode'); ?>"
                    sign-algo="<?php echo $this->get_general_option('sign_algo'); ?>"
                    contrib="<?php echo EpayncTools::get_contrib(); ?>"
                    integration-mode="<?php echo EpayncTools::get_integration_mode(); ?>"
                    plugins="<?php echo EpayncTools::get_active_plugins(); ?>"
                    title=""
                    first-name="<?php echo $user_info->first_name; ?>"
                    last-name="<?php echo $user_info->last_name; ?>"
                    from-email="<?php echo get_option('admin_email'); ?>"
                    to-email="<?php echo self::SUPPORT_EMAIL; ?>"
                    cc-emails=""
                    phone-number=""
                    language="<?php echo EpayncTools::get_support_component_language(); ?>"></contact-support>
            </th>
        </tr>

        <?php
        // Load css and add spinner.
        wp_register_style('epaync', WC_EPAYNC_PLUGIN_URL . 'assets/css/epaync.css', array(), self::PLUGIN_VERSION);
        wp_enqueue_style('epaync');
        ?>

        <script type="text/javascript">
            jQuery(document).ready(function() {
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
            });
        </script>
        <?php

        return ob_get_clean();
    }

    public function generate_label_html($key, $data)
    {
        $defaults = array(
            'title' => '',
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'label',
            'description' => ''
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
        if ($data != null) {
        ?>
            <tr valign="top">
                <td class="forminp" colspan="2" style="padding-left: 0;">
                    <fieldset>
                        <label class="<?php echo esc_attr($data['class']); ?>" style="<?php echo esc_attr($data['css']); ?>"><?php echo wp_kses_post($data['title']); ?></label>
                        <p class="description"><?php echo wp_kses_post($data['description']); ?></p>
                    </fieldset>
                </td>
            </tr>
        <?php
        }

        return ob_get_clean();
    }

    public function generate_multilangtext_html($key, $data)
    {
        global $wp_version;

        $data['title'] = isset($data['title']) ? $data['title'] : '';
        $data['disabled'] = empty($data['disabled']) ? false : true;
        $data['class'] = isset($data['class']) ? ' ' . $data['class'] : '';
        $data['css'] = isset($data['css']) ? $data['css'] : '';
        $data['placeholder'] = isset($data['placeholder']) ? $data['placeholder'] : '';
        $data['type'] = isset($data['type']) ? $data['type'] : 'array';
        $data['desc_tip'] = isset($data['desc_tip']) ? $data['desc_tip'] : false;
        $data['description'] = isset($data['description']) ? $data['description'] : '';
        $data['default'] = isset($data['default']) ? $data['default'] : array('en_US' => '');

        $languages = get_available_languages();
        foreach ($languages as $lang) {
            if (! isset($data['default'][$lang])) {
                $data['default'][$lang] = $data['default']['en_US'];
            }
        }

        $field = $this->plugin_id . $this->id . '_' . $key;
        $value = (array) stripslashes_deep($this->get_option($key));

        // Set input default value.
        $default_input_value = isset($value[get_locale()]) ? $value[get_locale()] : $data['default'][get_locale()];
        $default_input_value = esc_attr($default_input_value);

        ob_start();
        ?>

        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field); ?>_text"><?php echo wp_kses_post($data['title']); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <input class="input-text regular-input<?php echo esc_attr($data['class']); ?>" type="text"
                            name="<?php echo esc_attr($field) . '[text]'; ?>" id="<?php echo esc_attr($field) . '_text'; ?>" style="<?php echo esc_attr($data['css']); ?>"
                            value="<?php echo $default_input_value; ?>" placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?>>

                    <?php
                    if (version_compare($wp_version, '4.0.0', '>=')) {
                        $select = wp_dropdown_languages(array(
                            'name' => esc_attr($field) . '[lang]',
                            'id' => esc_attr($field) . '_lang',
                            'selected' => get_locale(), // Default selected is current admin locale.
                            'languages' => $languages,
                            'translations' => array(),
                            'show_available_translations' => false,
                            'echo' => false
                        ));

                        echo str_replace('<select', '<select style="width: auto; height: auto; vertical-align: top;"', $select);
                    } else {
                        $languages = array();
                    }

                    $languages[] = 'en_US';
                    foreach ($languages as $lang) {
                        $v = isset($value[$lang]) ? $value[$lang] : $data['default'][$lang]; ?>
                        <input type="hidden" id="<?php echo esc_attr($field) . '_' . $lang; ?>"
                                name="<?php echo esc_attr($field) . '[' . $lang . ']'; ?>"
                                value="<?php echo esc_attr($v); ?>">
                    <?php
                    }
                    ?>

                    <p class="description"><?php echo wp_kses_post($data['description']); ?></p>
                </fieldset>
            </td>
        </tr>

        <script type="text/javascript">
            jQuery(document).ready(function() {
                var key = '#<?php echo esc_attr($field); ?>';
                jQuery(key + '_lang').bind('change', function() {
                    var sl = jQuery(key + '_lang').val() || 'en_US';
                    var value = jQuery(key + '_' + sl).val();
                    jQuery(key + '_text').val(value);
                });

                jQuery(key + '_text').bind('change', function() {
                    var sl = jQuery(key + '_lang').val() || 'en_US';
                    var value = jQuery(key + '_text').val();
                    jQuery(key + '_' + sl).val(value);
                });
            });
        </script>
        <?php

        return ob_get_clean();
    }

    /**
     * Generate text input HTML.
     *
     * @access public
     * @param mixed $key
     * @param mixed $data
     * @since 1.0.0
     * @return string
     */
    public function generate_table_html($key, $data)
    {
        global $woocommerce;

        $html = '';

        $data['title'] = isset($data['title']) ? $data['title'] : '';
        $data['disabled'] = empty($data['disabled']) ? false : true;
        $data['class'] = isset($data['class']) ? $data['class'] : '';
        $data['css'] = isset($data['css']) ? $data['css'] : '';
        $data['placeholder'] = isset($data['placeholder']) ? $data['placeholder'] : '';
        $data['type'] = isset($data['type']) ? $data['type'] : 'text';
        $data['desc_tip'] = isset($data['desc_tip']) ? $data['desc_tip'] : false;
        $data['description'] = isset($data['description']) ? $data['description'] : '';
        $data['columns'] = isset($data['columns']) ? (array) $data['columns'] : array();

        // Description handling.
        if ($data['desc_tip'] === true) {
            $description = '';
            $tip = $data['description'];
        } elseif (! empty($data['desc_tip'])) {
            $description = $data['description'];
            $tip = $data['desc_tip'];
        } elseif (! empty($data['description'])) {
            $description = $data['description'];
            $tip = '';
        } else {
            $description = $tip = '';
        }

        $options = $this->get_option($key);
        if ($key === 'extra_payment_means' && ! empty($options)) {
            foreach ($options as $key_card => $option_card) {
                $cards = EpayncApi::getSupportedCardTypes();
                if (isset($cards[$option_card['code']])) {
                    unset($options[$key_card]);
                }
            }
        }

        $field_name = esc_attr($this->plugin_id . $this->id . '_' . $key);

        $html .= '<tr valign="top">' . "\n";
        $html .= '<th scope="row" class="titledesc">';
        $html .= '<label for="' . esc_attr($this->plugin_id . $this->id . '_' . $key) . '">' . wp_kses_post($data['title']) . '</label>';

        if ($tip) {
            $html .= '<img class="help_tip" data-tip="' . esc_attr($tip) . '" src="' . $woocommerce->plugin_url() . '/assets/images/help.png" height="16" width="16" />';
        }

        $html .= '</th>' . "\n";
        $html .= '<td class="forminp">' . "\n";
        $html .= '<fieldset><legend class="screen-reader-text"><span>' . wp_kses_post($data['title']) . '</span></legend>' . "\n";

        $html .= '<input id="' . $field_name . '_btn" class="' . $field_name . '_btn '. esc_attr($data['class']) . '"' . (! empty($options) ? ' style="display: none;"' : '') . ' type="button" value="' . __('Add', 'woo-epaync-payment') . '">';
        $html .= '<table id="' . $field_name . '_table" class="'. esc_attr($data['class']) . '"' . (empty($options) ? ' style="display: none;"' : '') . ' cellpadding="10" cellspacing="0" >';

        $html .= '<thead><tr>';
        $record = array();
        foreach ($data['columns'] as $code => $column) {
            $record[$code] = '';
            $html .= '<th class="' . $code . '" style="width: ' . $column['width'] . '; padding: 0px;">' . $column['title'] . '</th>';
        }

        $html .= '<th style="width: auto; padding: 0px;"></th>';
        $html .= '</tr></thead>';

        $html .= '<tbody>';
        $html .= '<tr id="' . $field_name . '_add">
                    <td colspan="' . count($data['columns']) . '"></td>
                    <td style="padding: 0px;"><input class="' . $field_name . '_btn" type="button" value="' . __('Add') . '"></td>
                  </tr>';
        $html .= '</tbody></table>';

        $html .= "\n" . '<script type="text/javascript">';
        $html .= "\n" . 'jQuery(".' . $field_name . '_btn").click(function() {
                            epayncAddOption("' . $field_name . '", ' . json_encode($record) . ');
                         })';

        if (! empty($options)) {
            // Add already inserted lines.
            foreach ($options as $code => $option) {
                $html .= "\n" . 'epayncAddOption("' . $field_name . '", ' . json_encode($option) . ', "' . $code . '");';
            }
        }

        $html .= "\n" . '</script>';

        if ($description) {
            $html .= ' <p class="description">' . wp_kses_post($description) . '</p>' . "\n";
        }

        $html .= '</fieldset>';
        $html .= '</td>' . "\n";
        $html .= '</tr>' . "\n";

        return $html;
    }

    public function validate_multilangtext_field($key, $value = null)
    {
        $name = $this->plugin_id . $this->id . '_' . $key;
        $old_value = $this->get_option($key);
        $new_value = ! is_null($value) ? $value : (key_exists($name, $_POST) ? $_POST[$name] : '');

        if (isset($new_value) && is_array($new_value) && ! empty($new_value)) {
            unset($new_value['text']);
            unset($new_value['lang']);

            $languages = get_available_languages();
            $languages[] = 'en_US'; // En_US locale is always available for WP.
            foreach ($languages as $lang) {
                if (! isset($new_value[$lang]) || ! $new_value[$lang]) {
                    $new_value[$lang] = $old_value[$lang];
                }
            }

            return $new_value;
        }

        return $old_value;
    }

    /**
     * Validate multiselect field.
     *
     * @return array
     */
    public function validate_multiselect_field($key, $value = null)
    {
        $name = $this->plugin_id . $this->id . '_' . $key;
        $new_value = ! is_null($value) ? $value : (key_exists($name, $_POST) ? $_POST[$name] : array(''));

        if (isset($new_value) && is_array($new_value) && in_array('', $new_value)) {
            return array('');
        }

        return parent::validate_multiselect_field($key, $value);
    }

    public function validate_ctx_mode_field($key, $value = null)
    {
        global $epaync_plugin_features;

        $name = $this->plugin_id . $this->id . '_' . $key;
        $new_value = ! is_null($value) ? $value : (key_exists($name, $_POST) ? $_POST[$name] : null);

        if (! $new_value && $epaync_plugin_features['qualif']) {
            // When using qualif for testing, mode is always PRODUCTION.
            return 'PRODUCTION';
        }

        return parent::validate_select_field($key, $value);
    }

    public function validate_3ds_min_amount_field($key, $value = null)
    {
        if (empty($value)) {
            return '';
        }

        $new_value = parent::validate_text_field($key, $value);

        $new_value = str_replace(',', '.', $new_value);
        if (! is_numeric($new_value) || ($new_value < 0)) { // Invalid value, restore old.
            return $this->get_option($key);
        }

        return $new_value;
    }

    public function generate_category_mapping_html($key, $data)
    {
        global $woocommerce;

        $html = '';

        $data['title'] = isset($data['title']) ? $data['title'] : '';
        $data['disabled'] = empty($data['disabled']) ? false : true;
        $data['class'] = isset($data['class']) ? $data['class'] : '';
        $data['css'] = isset($data['css']) ? $data['css'] : '';
        $data['placeholder'] = isset($data['placeholder']) ? $data['placeholder'] : '';
        $data['type'] = isset($data['type']) ? $data['type'] : 'text';
        $data['desc_tip'] = isset($data['desc_tip']) ? $data['desc_tip'] : false;
        $data['description'] = isset($data['description']) ? $data['description'] : '';
        $data['columns'] = isset($data['columns']) ? (array) $data['columns'] : array();
        $data['default'] = isset($data['default']) ? (array) $data['default'] : array();

        // Description handling.
        if ($data['desc_tip'] === true) {
            $description = '';
            $tip = $data['description'];
        } elseif (! empty($data['desc_tip'])) {
            $description = $data['description'];
            $tip = $data['desc_tip'];
        } elseif (! empty($data['description'])) {
            $description = $data['description'];
            $tip = '';
        } else {
            $description = $tip = '';
        }

        $field_name = esc_attr($this->plugin_id . $this->id . '_' . $key);

        $html .= '<tr valign="top">' . "\n";
        $html .= '<th scope="row" class="titledesc">';
        $html .= '<label for="' . esc_attr($this->plugin_id . $this->id . '_' . $key) . '">' . wp_kses_post($data['title']) . '</label>';

        if ($tip) {
            $html .= '<img class="help_tip" data-tip="' . esc_attr($tip) . '" src="' . $woocommerce->plugin_url() . '/assets/images/help.png" height="16" width="16" />';
        }

        $html .= '</th>' . "\n";
        $html .= '<td class="forminp">' . "\n";
        $html .= '<fieldset><legend class="screen-reader-text"><span>' . wp_kses_post($data['title']) . '</span></legend>' . "\n";

        $html .= '<table id="' . $field_name . '_table" class="'. esc_attr($data['class']) . '" cellpadding="10" cellspacing="0" >';

        $html .= '<thead><tr>';
        foreach ($data['columns'] as $code => $column) {
            $html .= '<th class="' . $code . '" style="width: ' . $column['width'] . '; padding: 0px;">' . $column['title'] . '</th>';
        }

        $html .= '<th style="width: auto; padding: 0px;"></th>';
        $html .= '</tr></thead>';

        $html .= '<tbody>';

        $cat_args = array(
            'orderby'    => 'id',
            'order'      => 'asc',
            'hide_empty' => false,
        );

        $categories = get_terms('product_cat', $cat_args);
        $options = $this->get_option($key);

        if (! is_array($options) || empty($options)) {
            $options = array();
        }

        foreach ($categories as $category) {
            if ($category->parent == 0) {
                $code = $category->term_id;

                if (! isset($options[$code]) || ! is_array($options[$code])) {
                    $options[$code]['category'] = 'FOOD_AND_GROCERY';
                    $options[$code]['new'] = true;
                } else {
                    $options[$code]['new'] = false;
                }

                $options[$code]['label'] = $category->name;
            }
        }

        foreach ($options as $code => $option) {
            $html .= '<tr>';
            $html .= '<td style="padding: 5px;"><label style="display: inline;">';
            $html .= $option['label'] . ($option['new'] == true ? '<span style="color: red;">*</span> ' : '');
            $html .= '</label></td>';

            $html .= '<td style="padding: 5px;"><select name="' . $field_name . '[' . $code . '][category]"
                      value="' . (isset($option['category']) ? $option['category'] : '') .'">';
            foreach ($this->get_gateway_categories() as $key => $value) {
                $selected = ($key === $option['category']) ? 'selected="selected"' : '';
                $html .= '<option value="' . $key . '" ' . $selected . '>' . $value . '</option>';
            }

            $html .= '</select></td>';

            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        if ($description) {
            $html .= ' <p class="description">' . wp_kses_post($description) . '</p>' . "\n";
        }

        $html .= '</fieldset>';
        $html .= '</td>' . "\n";
        $html .= '</tr>' . "\n";

        return $html;
    }

    public function validate_category_mapping_field($key, $value = null)
    {
        $name = $this->plugin_id . $this->id . '_' . $key;
        $value = $value ? $value : (key_exists($name, $_POST) ? $_POST[$name] : array());

        foreach ($value as $code => $option) {
            // Clean string.
            $fnc = function_exists('wc_clean') ? 'wc_clean' : 'woocommerce_clean';
            $value[$code] = array_map('esc_attr', array_map($fnc, (array) $option));
        }

        return $value;
    }
    /**
     * Check if this gateway is available for the current currency.
     */
    protected function is_supported_currency()
    {
        $currency = EpayncApi::findCurrencyByAlphaCode(get_woocommerce_currency());
        if ($currency == null) {
            return false;
        }

        return true;
    }

    /**
     * Check for notify response.
     **/
    public function epaync_notify_response()
    {
        global $woocommerce;

        @ob_clean();

        $raw_response = (array) stripslashes_deep($_REQUEST);

        $epaync_response = new EpayncResponse(
            $raw_response,
            $this->get_general_option('ctx_mode'),
            $this->get_general_option('key_test'),
            $this->get_general_option('key_prod'),
            $this->get_general_option('sign_algo')
        );

        $from_server = $epaync_response->get('hash') != null;

        if (! $epaync_response->isAuthentified()) {
            $this->log('Authentication failed: received invalid response with parameters: ' . print_r($raw_response, true));
            $this->log('Signature algorithm selected in module settings must be the same as one selected in gateway Back Office.');

            if ($from_server) {
                $this->log('IPN URL PROCESS END');
                die($epaync_response->getOutputForGateway('auth_fail'));
            } else {
                // Fatal error, empty cart.
                $woocommerce->cart->empty_cart();
                $this->add_notice(__('An error has occurred in the payment process.', 'woo-epaync-payment'), 'error');

                $this->log('RETURN URL PROCESS END');

                $cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : $woocommerce->cart->get_cart_url();
                $iframe = $epaync_response->get('action_mode') === 'IFRAME';
                $this->epaync_redirect($cart_url, $iframe);
            }
        } else {
            header('HTTP/1.1 200 OK');

            $this->epaync_manage_notify_response($epaync_response);
        }
    }

    /**
     * Valid payment process : update order, send mail, ...
     **/
    public function epaync_manage_notify_response($epaync_response, $from_server_rest = false)
    {
        global $woocommerce, $epaync_plugin_features;

        // Clear all response messages.
        $this->clear_notices();

        $order_id = $epaync_response->get('order_id');
        $from_server = $epaync_response->get('hash') != null || $from_server_rest;
        $iframe = $epaync_response->get('action_mode') == 'IFRAME';

        // Cart URL.
        $cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : $woocommerce->cart->get_cart_url();

        // Get ext_info parameter.
        $key = $epaync_response->getExtInfo('order_key');

        $order = new WC_Order((int) $order_id);

        // If gateway doesn't return vads_ext_info_order_key, skip ckecking order key.
        if (! self::get_order_property($order, 'id') || ($key && (self::get_order_property($order, 'order_key') !== $key))) {
            $this->log("Error: order #$order_id not found or key does not match received invoice ID.");

            if ($from_server) {
                $this->log('IPN URL PROCESS END');
                die($epaync_response->getOutputForGateway('order_not_found'));
            } else {
                // Fatal error, empty cart.
                $woocommerce->cart->empty_cart();
                $this->add_notice(__('An error has occurred in the payment process.', 'woo-epaync-payment'), 'error');

                $this->log('RETURN URL PROCESS END');
                $this->epaync_redirect($cart_url, $iframe);
            }
        }

        if (! $from_server && $this->testmode && $epaync_plugin_features['prodfaq']) {
            $msg = __('<p><u>GOING INTO PRODUCTION</u></p>You want to know how to put your shop into production mode, please read chapters « Proceeding to test phase » and « Shifting the shop to production mode » in the documentation of the module.', 'woo-epaync-payment');

            $this->add_notice($msg);
        }

        // Checkout payment URL to allow re-order.
        $checkout_url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : $woocommerce->cart->get_checkout_url();

        // Backward compatibility.
        if (version_compare($woocommerce->version, '2.1.0', '<')) {
            $checkout_url = $cart_url = $order->get_cancel_order_url();
        }

        // Use the selected susbscriptions handler.
        $method = self::get_order_property($order, 'payment_method');
        $subscriptions_handler = self::subscriptions_handler($method);

        if ($this->is_new_order($order, $epaync_response->get('trans_id'), $epaync_response->get('sequence_number'))) {
            // Order not processed yet or a failed payment (re-order).

            // Add order note.
            self::epaync_add_order_note($epaync_response, $order);

            // Delete old saved transaction details.
            delete_post_meta((int) $order_id, 'Transaction ID');
            delete_post_meta((int) $order_id, 'Card number');
            delete_post_meta((int) $order_id, 'Means of payment');
            delete_post_meta((int) $order_id, 'Card expiry');
            delete_post_meta((int) $order_id, 'Sequence number');

            // Store transaction details.
            update_post_meta((int) $order_id, 'Transaction ID', $epaync_response->get('trans_id'));
            update_post_meta((int) $order_id, 'Card number', $epaync_response->get('card_number'));
            update_post_meta((int) $order_id, 'Means of payment', $epaync_response->get('card_brand'));
            update_post_meta((int) $order_id, 'Sequence number', $epaync_response->get('sequence_number'));

            // Store authorized amount.
            if ($authorized_amount = $epaync_response->get('authorized_amount')) {
                delete_post_meta((int) $order_id, 'Authorized amount');
                update_post_meta((int) $order_id, 'Authorized amount', self::display_amount($authorized_amount, $epaync_response->get('currency')));
            }

            // Store installments number/config.
            if (($installments_number = $epaync_response->get('payment_option_code')) && is_numeric($installments_number)) {
                delete_post_meta((int) $order_id, 'Installments number');
                update_post_meta((int) $order_id, 'Installments number', $installments_number);
            }

            $expiry = '';
            if ($epaync_response->get('expiry_month') && $epaync_response->get('expiry_year')) {
                $expiry = str_pad($epaync_response->get('expiry_month'), 2, '0', STR_PAD_LEFT) . '/' . $epaync_response->get('expiry_year');
            }

            update_post_meta((int) $order_id, 'Card expiry', $expiry);

            if (self::is_successful_action($epaync_response)) {
                if ($epaync_response->isPendingPayment()) {
                    // Payment is pending.
                    $this->log("Payment is pending, make order #$order_id in on-hold status.");

                    $order->update_status('on-hold');
                } else {
                    // Payment completed.
                    $this->log("Payment successfull, let's complete order #$order_id.");

                    $order->payment_complete();
                }

                // Try to save identifier if any.
                $this->epaync_save_identifier($order, $epaync_response);

                if ($subscriptions_handler) {
                    // Try to save subscritption info if any.
                    $this->epaync_save_recurrence($order, $epaync_response);

                    $subscriptions_handler->process_subscription($order, $epaync_response);
                }

                if ($from_server) {
                    $this->log("Payment processed successfully by IPN URL call for order #$order_id.");
                    $this->log('IPN URL PROCESS END');

                    die($epaync_response->getOutputForGateway('payment_ok'));
                } else {
                    $this->log("Warning! IPN URL call has not worked. Payment completed by return URL call for order #$order_id.");

                    if ($this->testmode) {
                        $ipn_url_warn = sprintf(__('The automatic validation has not worked. Have you correctly set up the notification URL in the %s Back Office?', 'woo-epaync-payment'), self::BACKOFFICE_NAME);
                        $ipn_url_warn .= '<br />';
                        $ipn_url_warn .= __('For understanding the problem, please read the documentation of the module : <br />&nbsp;&nbsp;&nbsp;- Chapter &laquo; To read carefully before going further &raquo;<br />&nbsp;&nbsp;&nbsp;- Chapter &laquo; Notification URL settings &raquo;', 'woo-epaync-payment');

                        $this->add_notice($ipn_url_warn, 'error');
                    }

                    $this->log('RETURN URL PROCESS END');
                    $this->epaync_redirect($this->get_return_url($order), $iframe);
                }
            } else {
                $order->update_status('failed');
                $this->log("Payment failed or cancelled for order #$order_id. {$epaync_response->getLogMessage()}");

                if ($subscriptions_handler) {
                    // Try to manage subscription if any.
                    $subscriptions_handler->process_subscription($order, $epaync_response);
                }

                if ($from_server) {
                    $this->log('IPN URL PROCESS END');
                    die($epaync_response->getOutputForGateway('payment_ko'));
                } else {
                    $this->log('RETURN URL PROCESS END');

                    if (! $epaync_response->isCancelledPayment()) {
                        $this->add_notice(__('Your payment was not accepted. Please, try to re-order.', 'woo-epaync-payment'), 'error');
                        $this->epaync_redirect($cart_url, $iframe);
                    } else {
                        $this->epaync_redirect($checkout_url, $iframe);
                    }
                }
            }
        } else {
            $this->log("Order #$order_id is already saved.");

            // Case of new recurrence on an active subscription with our method.
            if ($epaync_response->get('recurrence_number') && $subscriptions_handler) {
                // IPN URL called for each recurrence creation on gateway.
                $this->log("New recurrence created for order #$order_id. Let subscriptions handler do the work.");

                $subscriptions_handler->process_subscription_renewal($order, $epaync_response);

                if (self::is_successful_action($epaync_response)) {
                    $this->log("Payment recurrence processed successfully by IPN URL call for order #$order_id.");
                    echo($epaync_response->getOutputForGateway('payment_ok'));
                } else {
                    echo($epaync_response->getOutputForGateway('payment_ko'));
                }

                $this->log('IPN URL PROCESS END');
                die();
            } elseif (($epaync_response->get('identifier_status') === 'UPDATED')
                /* Case of method change from WooCommerce or subscription creation on gateway Back Office. */
                || ((($epaync_response->get('recurrence_status') === 'CREATED') || ($epaync_response->get('recurrence_number') === '1'))
                        && ($subscriptions_handler = self::subscriptions_handler('epayncsubscription')))) {
                // Means of payment updated on payment gateway.
                $this->log("Updating subscription payment means on gateway for order #$order_id.");

                // View subscription URL (in case of changing payment method of an existing subscription).
                $subsc_redirect_url = false;
                if ($subsc_id = $epaync_response->getExtInfo('subsc_id')) {
                    $subsc_redirect_url = $subscriptions_handler->get_view_order_url($subsc_id);
                }

                if (self::is_successful_action($epaync_response)) {
                    // Means of payment successfully updated on payment gateway.
                    $this->log("Means of payment successfully updated for order #$order_id. Save new means of payment info.");

                    // Workarround to manage subscription creation on gateway Back Office.
                    // Delete workarround when subscription IPN is fixed.
                    $force_update = $epaync_response->get('recurrence_number') === '1';

                    if ($force_update && ($method !== 'epayncsubscription')) {
                        // Update payment method in order.
                        $epaync_subscription = new WC_Gateway_EpayncSubscription();
                        if (method_exists($order, 'set_payment_method')) {
                            $order->set_payment_method($epaync_subscription);
                        } else {
                            $order->payment_method = $epaync_subscription->id;
                            $order->payment_method_title = $epaync_subscription->get_title();
                        }

                        $order->save();
                    }

                    // Try to save identifier if any.
                    $this->epaync_save_identifier($order, $epaync_response, $force_update);

                    // Try to save subscritption info if any.
                    $this->epaync_save_recurrence($order, $epaync_response, $force_update);

                    $subscriptions_handler->process_subscription($order, $epaync_response);

                    // Try to save subscription recurrences.
                    if ($force_update) {
                        $subscriptions_handler->process_subscription_renewal($order, $epaync_response);
                    }

                    if ($from_server) {
                        $this->log('IPN URL PROCESS END');
                        die($epaync_response->getOutputForGateway('payment_ok_already_done'));
                    } else {
                        $this->add_notice(__('Payment method updated.', 'woocommerce-subscriptions'));

                        $this->log('RETURN URL PROCESS END');
                        $this->epaync_redirect($subsc_redirect_url ? $subsc_redirect_url : $this->get_return_url($order), $iframe);
                    }
                } else {
                    if ($from_server) {
                        $this->log('IPN URL PROCESS END');
                        die($epaync_response->getOutputForGateway('payment_ok_already_done'));
                    } else {
                        $this->log('RETURN URL PROCESS END');

                        if (! $epaync_response->isCancelledPayment()) {
                            $this->add_notice(__('The payment method can not be changed for that subscription.', 'woocommerce-subscriptions'), 'error');

                            $this->epaync_redirect($subsc_redirect_url ? $subsc_redirect_url : $cart_url, $iframe);
                        } else {
                            $this->epaync_redirect($subsc_redirect_url ? $subsc_redirect_url : $checkout_url, $iframe);
                        }
                    }
                }
            }

            if ($from_server && (self::get_order_property($order, 'status') === 'on-hold')) {
                switch (true) {
                    case $epaync_response->isCancelledPayment():
                        $this->log("Order #$order_id is in a pending status and payment is cancelled. It may be a payment expiration. Do nothing.");
                        echo($epaync_response->getOutputForGateway('payment_ko_already_done'));
                        break;
                    case $epaync_response->isPendingPayment():
                        $this->log("Order #$order_id is in a pending status and stays in the same status. Do nothing.");
                        echo($epaync_response->getOutputForGateway('payment_ok_already_done'));
                        break;
                    case self::is_successful_action($epaync_response):
                        $this->log("Order #$order_id is in a pending status and payment is accepted. Complete order payment.");
                        $order->payment_complete();

                        echo($epaync_response->getOutputForGateway('payment_ok'));
                        break;
                    default:
                        $this->log("Order #$order_id is in a pending status and payment failed. Cancel order.");

                        // Add order note.
                        self::epaync_add_order_note($epaync_response, $order);

                        $order->update_status('failed');
                        echo($epaync_response->getOutputForGateway('payment_ko'));
                        break;
                }

                $this->log('IPN URL PROCESS END');
                die();
            } elseif (self::is_successful_action($epaync_response) && key_exists(self::get_order_property($order, 'status'), self::get_success_order_statuses(false, $subscriptions_handler))) {
                $status = $epaync_response->isPendingPayment() ? 'pending' : 'successful';
                $this->log("Payment $status confirmed for order #$order_id.");

                // Order success registered and payment success received.
                if ($from_server) {
                    $this->log('IPN URL PROCESS END');
                    die($epaync_response->getOutputForGateway('payment_ok_already_done'));
                } else {
                    $this->log('RETURN URL PROCESS END');
                    $this->epaync_redirect($this->get_return_url($order), $iframe);
                }
            } elseif (! self::is_successful_action($epaync_response) && (self::get_order_property($order, 'status') === 'failed' || self::get_order_property($order, 'status') === 'cancelled')) {
                $this->log("Payment failed confirmed for order #$order_id.");

                // Order failure registered and payment error received.
                if ($from_server) {
                    $this->log('IPN URL PROCESS END');
                    die($epaync_response->getOutputForGateway('payment_ko_already_done'));
                } else {
                    $this->log('RETURN URL PROCESS END');

                    if (! $epaync_response->isCancelledPayment()) {
                        $this->add_notice(__('Your payment was not accepted. Please, try to re-order.', 'woo-epaync-payment'), 'error');
                        $this->epaync_redirect($cart_url, $iframe);
                    } else {
                        $this->epaync_redirect($checkout_url, $iframe);
                    }
                }
            } else {
                $this->log("Error! Invalid payment result received for already saved order #$order_id. Payment result : {$epaync_response->getTransStatus()}, Order status : {self::get_order_property($order, 'status')}.");

                // Registered order status not match payment result.
                if ($from_server) {
                    $this->log('IPN URL PROCESS END');
                    die($epaync_response->getOutputForGateway('payment_ko_on_order_ok'));
                } else {
                    // Fatal error, empty cart.
                    $woocommerce->cart->empty_cart();
                    $this->add_notice(__('An error has occurred in the payment process.', 'woo-epaync-payment'), 'error');

                    $this->log('RETURN URL PROCESS END');
                    $this->epaync_redirect($cart_url, $iframe);
                }
            }
        }
    }

    public function epaync_complete_order_status($status, $order_id)
    {
        $order = new WC_Order((int)$order_id);

        if ((strpos(self::get_order_property($order, 'payment_method'), 'epaync') === 0)
            && ($this->get_general_option('order_status_on_success') != 'default')) {
            return $this->get_general_option('order_status_on_success');
        }

        return $status;
    }

    private function is_new_order($order, $trs_id, $seq_nb)
    {
        if (self::get_order_property($order, 'status') === 'pending') {
            return true;
        }

        if (self::get_order_property($order, 'status') === 'failed'
            || self::get_order_property($order, 'status') === 'cancelled') {
            if (get_post_meta((int) self::get_order_property($order, 'id'), 'Transaction ID', true) !== $trs_id) {
                return  true;
            } elseif (get_post_meta((int) self::get_order_property($order, 'id'), 'Sequence number', true) !== $seq_nb) {
                return true;
            }
        }

        return false;
    }

    protected function log($msg)
    {
        if (! $this->debug) {
            return;
        }

        $this->logger->add('epaync', $msg);
    }

    protected function clear_notices()
    {
        global $woocommerce;

        if (function_exists('wc_clear_notices')) {
            wc_clear_notices();
        } else {
            $woocommerce->clear_messages();
        }
    }

    protected function add_notice($msg, $type = 'success')
    {
        global $woocommerce;

        if (function_exists('wc_add_notice')) {
            wc_add_notice($msg, $type);
        } else {
            if ($type == 'error') {
                $woocommerce->add_error($msg);
            } else {
                $woocommerce->add_message($msg);
            }
        }
    }

    public function epaync_add_order_email_payment_result($order, $sent_to_admin, $plain_text = false)
    {
        if (strpos(self::get_order_property($order, 'payment_method'), 'epaync') !== 0) {
            return;
        }

        $trans_id = get_post_meta((int) self::get_order_property($order, 'id'), 'Transaction ID', true);
        if (! $trans_id) {
            return;
        }

        $notes = self::get_order_notes(self::get_order_property($order, 'id'));
        foreach ($notes as $note) {
            if (strpos($note, $trans_id) !== false) {
                $epaync_order_note = $note;
                break;
            }
        }

        if (isset($epaync_order_note)) {
            if ($plain_text) {
                echo strtoupper(__('Payment', 'woo-epaync-payment')) . "\n\n" . $epaync_order_note . "\n\n";
            } else {
                echo '<h2>' . __('Payment', 'woo-epaync-payment') . '</h2><p>' . str_replace("\n", '<br >', $epaync_order_note) . '</p>';
            }
        }
    }

    /**
     * Get all notes of a specified order.
     * @return array[string]
     */
    public static function get_order_notes($order_id)
    {
        $exclude_fnc = class_exists('WC_Comments') ? array('WC_Comments', 'exclude_order_comments') :
            'woocommerce_exclude_order_comments';

        remove_filter('comments_clauses', $exclude_fnc, 10);

        $comments = get_comments(array(
            'post_id' => $order_id,
            'status' => 'approve',
            'type' => 'order_note'
        ));
        $notes = wp_list_pluck($comments, 'comment_content');

        add_filter('comments_clauses', $exclude_fnc, 10, 1);

        return $notes;
    }

    public static function get_order_property($order, $property_name)
    {
        $method = 'get_' . $property_name;

        if (method_exists($order, $method)) {
            return $order->$method();
        }

        return isset($order->$property_name) ? $order->$property_name : null;
    }

    public static function get_customer_property($customer, $property_name)
    {
        $method = 'get_' . $property_name;

        if (method_exists($customer, $method)) {
            return $customer->$method();
        }

        return isset($customer->$property_name) ? $customer->$property_name : null;
    }

    protected function epaync_redirect($url, $iframe = false)
    {
        if (! $iframe) {
            wp_redirect($url);
        } else {
            echo '<div style="text-align: center;">
                      <img src="' . esc_url(WC_EPAYNC_PLUGIN_URL . 'assets/images/loading_big.gif') . '">
                  </div>';

            echo '<script type="text/javascript">
                    var url = "' . $url . '";

                    if (window.top) {
                      window.top.location = url;
                    } else {
                      window.location = url;
                    }
                  </script>';
        }

        exit();
    }

    public static function epaync_add_order_note($epaync_response, $order)
    {
        $note = $epaync_response->getCompleteMessage("\n");

        if ($epaync_response->get('brand_management')) {
            $brand_info = json_decode($epaync_response->get('brand_management'));
            $msg_brand_choice = "\n";

            if (isset($brand_info->userChoice) && $brand_info->userChoice) {
                $msg_brand_choice .= __('Card brand chosen by buyer.', 'woo-epaync-payment');
            } else {
                $msg_brand_choice .= __('Default card brand used.', 'woo-epaync-payment');
            }

            $note .= $msg_brand_choice;
        }

        $order->add_order_note($note);

        // 3DS extra message.
        $note = __('3DS authentication: ', 'woo-epaync-payment');
        if ($status = $epaync_response->get('threeds_status')) {
            $note .= self::get_threeds_status($status);

            if ($threeds_cavv = $epaync_response->get('threeds_cavv')) {
                $note .= "\n";
                $note .= __('3DS certificate: ', 'woo-epaync-payment') . $threeds_cavv;
            }

            if ($threeds_auth_type = $epaync_response->get('threeds_auth_type')) {
                $note .= "\n";
                $note .= __('Authentication type: ', 'woo-epaync-payment') . $threeds_auth_type;
            }
        } else {
            $note .= 'UNAVAILABLE';
        }

        $order->add_order_note($note);

        $note = '';
        if (! $epaync_response->isCancelledPayment()) {
            $note .= sprintf(__('Transaction ID: %s.', 'woo-epaync-payment'), $epaync_response->get('trans_id'));

            if ($epaync_response->get('trans_uuid')) {
                $note .= "\n";
                $note .= sprintf(__('Transaction UUID: %s.', 'woo-epaync-payment'), $epaync_response->get('trans_uuid'));
            }
        }

        if ($epaync_response->getTransStatus()) {
            $note .= "\n";
            $note .= sprintf(__('Transaction status: %s.', 'woo-epaync-payment'), $epaync_response->getTransStatus());
        }

        $order->add_order_note($note);
    }

    private static function get_threeds_status($status)
    {
        switch ($status) {
            case 'Y':
                return 'SUCCESS';

            case 'N':
                return 'FAILED';

            case 'U':
                return 'UNAVAILABLE';

            case 'A':
                return 'ATTEMPT';

            default :
                return $status;
        }
    }

    public function epaync_notices($template_name, $template_path, $located, $args = array())
    {
        global $woocommerce;

        if ($template_name !== 'checkout/thankyou.php') {
            return;
        }

        if (! isset($args['order'])) {
            return;
        }

        if (strpos(self::get_order_property($args['order'], 'payment_method'), 'epaync') === 0) {
            if (function_exists('wc_print_notices')) {
                wc_print_notices();
            } else {
                $woocommerce->show_messages();
            }
        }
    }

    private static function get_success_order_statuses($default = false, $subscriptions_handler = false)
    {
        $statuses = array();

        if (function_exists('wc_get_order_statuses')) { // From WooCommerce 2.2.0.
            $other_statues = array('pending', 'cancelled', 'refunded', 'failed');

            foreach (wc_get_order_statuses() as $key => $value) {
                $status = substr($key, 3);

                if (in_array($status, $other_statues)) {
                    continue;
                }

                $statuses[$status] = $value;
            }
        } else {
            $statuses = array(
                'on-hold' => __('On Hold', 'woo-epaync-payment'),
                'processing' => __('Processing', 'woo-epaync-payment'),
                'completed' => __('Complete', 'woo-epaync-payment')
            );
        }

        if ($default) {
            $statuses = array('default' => __('Default', 'woo-epaync-payment')) + $statuses;
        }

        // Add success subscriptions statuses.
        $subscription_success_statuses = array('active');

        if ($subscriptions_handler) {
            foreach ($subscriptions_handler->get_subscription_statuses() as $key => $value) {
                $status = substr($key, 3);

                if (in_array($status, $subscription_success_statuses)) {
                    $statuses[$status] = $value;
                }
            }
        }

        return $statuses;
    }

    public static function is_successful_action($epaync_response)
    {
        if ($epaync_response->isAcceptedPayment()) {
            return true;
        }

        // This is a backward compatibility feature: it is used as a workarround as long as transcation
        // creation on REGISTER in not enabled on payment gateway.
        if ($epaync_response->get('subscription') && ($epaync_response->get('recurrence_status') === 'CREATED')) {
            return true;
        }

        if ($epaync_response->get('identifier') && (
            $epaync_response->get('identifier_status') == 'CREATED' /* page_action is REGISTER_PAY or ASK_REGISTER_PAY */ ||
            $epaync_response->get('identifier_status') == 'UPDATED' /* page_action is REGISTER_UPDATE_PAY */
        )) {
            return true;
        }

        return false;
    }

    private function epaync_save_identifier($order, $epaync_response, $force_update = false)
    {
        $cust_id = self::get_order_property($order, 'user_id');
        if (! $cust_id) {
            return;
        }

        if ($epaync_response->get('identifier') && ($force_update || (
            $epaync_response->get('identifier_status') == 'CREATED' /* page_action is REGISTER_PAY or ASK_REGISTER_PAY */ ||
            $epaync_response->get('identifier_status') == 'UPDATED' /* page_action is REGISTER_UPDATE_PAY */
        ))) {
            $this->log("Identifier for customer #{$cust_id} successfully created or updated on payment gateway. Let's save it and save masked card and expiry date.");

            $identifier_info = array(
                'identifier' => $epaync_response->get('identifier'),
                'active' => true
            );

            update_user_meta((int) $cust_id, self::get_order_property($order, 'payment_method') . '_identifier', json_encode($identifier_info));

            // Mask all card digits unless the last 4 ones.
            $number = $epaync_response->get('card_number');
            $masked = '';

            $matches = array();
            if (preg_match('#^([A-Z]{2}[0-9]{2}[A-Z0-9]{10,30})(_[A-Z0-9]{8,11})?$#i', $number, $matches)) {
                // IBAN(_BIC).
                $masked .= isset($matches[2]) ? str_replace('_', '', $matches[2]) . ' / ' : ''; // BIC.

                $iban = $matches[1];
                $masked .= substr($iban, 0, 4) . str_repeat('X', strlen($iban) - 8) . substr($iban, - 4);
            } elseif (strlen($number) > 4) {
                $masked = $epaync_response->get('card_brand') . '|' .  str_repeat('X', strlen($number) - 4) . substr($number, - 4);

                if ($epaync_response->get('expiry_month') && $epaync_response->get('expiry_year')) {
                    // Format card expiration data.
                    $masked .= ' (';
                    $masked .= str_pad($epaync_response->get('expiry_month'), 2, '0', STR_PAD_LEFT);
                    $masked .= '/';
                    $masked .= $epaync_response->get('expiry_year');
                    $masked .= ')';
                }
            }

            update_user_meta((int) $cust_id, self::get_order_property($order, 'payment_method') . '_masked_pan', $masked);

            $this->log("Identifier for customer #{$cust_id} and his masked PAN data are successfully saved.");
        }
    }

    private function epaync_save_recurrence($order, $epaync_response, $force_update = false)
    {
        $order_id = (int) self::get_order_property($order, 'id');

        if ($epaync_response->get('subscription') && ($force_update || $epaync_response->get('recurrence_status') === 'CREATED')) {
            $this->log("Subscription for order #{$order_id} successfully created on payment gateway. Let's save subscription information.");

            $currency_code = $epaync_response->get('sub_currency');

            delete_post_meta($order_id, 'Subscription ID');
            delete_post_meta($order_id, 'Subscription amount');
            delete_post_meta($order_id, 'Effect date');
            delete_post_meta($order_id, 'Initial amount');
            delete_post_meta($order_id, 'Initial amount count');

            // Store subscription details.
            update_post_meta($order_id, 'Subscription ID', $epaync_response->get('subscription'));
            update_post_meta($order_id, 'Subscription amount', self::display_amount($epaync_response->get('sub_amount'), $currency_code));
            update_post_meta($order_id, 'Effect date', preg_replace('#^(\d{4})(\d{2})(\d{2})$#', '\1-\2-\3', $epaync_response->get('sub_effect_date')));

            if ($epaync_response->get('sub_init_amount')) {
                update_post_meta($order_id, 'Initial amount', self::display_amount($epaync_response->get('sub_init_amount'), $currency_code));
                update_post_meta($order_id, 'Initial amount count', $epaync_response->get('sub_init_amount_number'));
            }

            $this->log("Subscription information for order #{$order_id} is successfully saved.");
        }
    }

    public static function display_amount($amount_in_cents, $currency_code)
    {
        if (! $amount_in_cents) {
            return '';
        }

        $currency = EpayncApi::findCurrencyByNumCode($currency_code);
        return $currency->convertAmountToFloat($amount_in_cents) . ' ' . $currency->getAlpha3();
    }

    private static function subscriptions_handler($method)
    {
        if ($method !== 'epayncsubscription') {
            return null;
        }

        $settings = get_option('woocommerce_epayncsubscription_settings', null);

        $handler = is_array($settings) && isset($settings['subscriptions']) ? $settings['subscriptions'] : null;
        return Epaync_Subscriptions_Loader::getInstance($handler);
    }

    public function epaync_delete_saved_card()
    {
        global $woocommerce;

        // Check if user is connected and user can delete only his own payment means.
        if (is_user_logged_in() && isset($_POST['id']) && ! empty($_POST['id'])) {
            $id = $_POST['id'];
            $cust_id = self::get_customer_property($woocommerce->customer, 'id');

            if (! $saved_identifier = $this->get_cust_identifier($cust_id, $id)) {
                $this->log("Error: Customer {$woocommerce->customer->get_billing_email()} doesn't have a saved identifier for \"{$id}\" submodule");
                return false;
            }

            try {
                $key = $this->testmode ? $this->get_general_option('test_private_key') : $this->get_general_option('prod_private_key');
                if ($key) {
                    $request_data = array(
                        'paymentMethodToken' => $saved_identifier
                    );

                    $client = new EpayncRest(
                        self::REST_URL,
                        $this->get_general_option('site_id'),
                        $key
                    );

                    $result = $client->post('V4/Token/Cancel', json_encode($request_data));
                    EpayncRestTools::checkResult($result);

                    // Payment identifier cancelled successfully.
                    $this->log("Payment identifier cancelled successfully on payment gateway by user: {$woocommerce->customer->get_billing_email()}, for " . $id . " submodule.");
                } else {
                    // Client has not configured private key in module backend.
                    $this->log("Identifier for customer {$woocommerce->customer->get_billing_email()}, for " . $id . " submodule, cannot be deleted on gateway: private key is not configured. Let's just delete it from WooCommerce.");
                }

                // Delete identifier from WooCommerce.
                $this->delete_identifier_attributes($cust_id, $id);
                return true;
            } catch (Exception $e) {
                $invalid_ident_codes = array('PSP_030', 'PSP_031', 'SP_561', 'PSP_607');

                if (in_array($e->getCode(), $invalid_ident_codes)) {
                    // The identifier is invalid or doesn't exist.
                    $this->log("Identifier for customer {$woocommerce->customer->get_billing_email()}, for " . $id . " submodule, is invalid or doesn't exist. Let's delete it from WooCommerce");

                    // Delete identifier from WooCommerce.
                    $this->delete_identifier_attributes($cust_id, $id);
                    return true;
                } else {
                    $this->log("Identifier for customer {$woocommerce->customer->get_billing_email()}, for " . $id . " submodule, couldn't be deleted on gateway. Error occurred: {$e->getMessage()}");
                    $this->add_notice(__('The stored means of payment could not be deleted.', 'woo-epaync-payment'), 'error');
                    return false;
                }
            }
        }
    }

    private function delete_identifier_attributes($cust_id, $id)
    {
        global $woocommerce;

        // Delete local saved means of payment and saved identifier.
        delete_user_meta((int) $cust_id, $id . '_identifier');
        delete_user_meta((int) $cust_id, $id . '_masked_pan');

        // Payment identifier cancelled successfully.
        $this->log("Payment identifier and masked card and expiry date were deleted successfully by user: {$woocommerce->customer->get_billing_email()} for " . $id . " submodule.");
        $this->add_notice(__('The stored means of payment was successfully deleted.', 'woo-epaync-payment'));
    }

    protected function get_cust_identifier($cust_id, $id = null)
    {
        $id = $id ? $id : $this->id;
        $saved_identifier = get_user_meta((int) $cust_id, $id . '_identifier', true);
        $saved_identifier_decode = json_decode($saved_identifier, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            return $saved_identifier_decode['identifier'];
        }

        return $saved_identifier;
    }

    protected function is_cust_identifier_active($cust_id, $id = null)
    {
        $id = $id ? $id : $this->id;
        $saved_identifier = get_user_meta((int) $cust_id, $id . '_identifier', true);
        $saved_identifier_decode = json_decode($saved_identifier, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            return $saved_identifier_decode['active'];
        }

        return true;
    }

    protected function update_custidentifier($cust_id, $identifier, $active, $id = null)
    {
        $id = $id ? $id : $this->id;
        $identifier_info = array(
            'identifier' => $identifier,
            'active' => $active
        );

        update_user_meta((int) $cust_id, $id . '_identifier', json_encode($identifier_info));
    }

    protected function check_identifier($cust_id, $id)
    {
        global $woocommerce;

        $identifier = $this->get_cust_identifier($cust_id);

        if (! $identifier) {
            // Customer has no saved identifier.
            return false;
        }

        try {
            $request_data = array(
                'paymentMethodToken' => $identifier
            );

            // Perform REST request to check identifier.
            $key = $this->testmode ? $this->get_general_option('test_private_key') : $this->get_general_option('prod_private_key');
            $client = new EpayncRest(
                self::REST_URL,
                $this->get_general_option('site_id'),
                $key
            );

            $result = $client->post('V4/Token/Get', json_encode($request_data));
            EpayncRestTools::checkResult($result);

            $cancellation_date = EpayncRestTools::getProperty($result['answer'], 'cancellationDate');
            if ($cancellation_date && (strtotime($cancellation_date) <= time())) {
                $this->log("Identifier for customer {$woocommerce->customer->get_billing_email()}, for {$id} submodule, is expired on payment gateway in date of: {$cancellation_date}.");

                // Update Customer identifier validity.
                $this->update_custidentifier($cust_id, $identifier, false, $id);
                return false;
            }

            // Update Customer identifier validity.
            $this->update_custidentifier($cust_id, $identifier, true, $id);
            return true;
        } catch (Exception $e) {
            $invalid_ident_codes = array('PSP_030', 'PSP_031', 'PSP_561', 'PSP_607');

            if (in_array($e->getCode(), $invalid_ident_codes, true)) {
                // The identifier is invalid or doesn't exist.
                $this->log("Identifier for customer {$woocommerce->customer->get_billing_email()}, for {$id} submodule, is invalid or doesn't exist: {$e->getMessage()}.");

                // Update Customer identifier validity.
                $this->update_custidentifier($cust_id, $identifier, false, $id);
                return false;
            }

            $this->log("Identifier for customer {$woocommerce->customer->get_billing_email()}, for " . $id . " submodule, couldn't be verified on gateway: {$e->getMessage()}.");
            return true;
        }
    }
}
