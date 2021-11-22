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

class WC_Gateway_EpayncMulti extends WC_Gateway_EpayncStd
{
    public function __construct()
    {
        global $epaync_plugin_features;

        $this->id = 'epayncmulti';
        $this->icon = apply_filters('woocommerce_epayncmulti_icon', WC_EPAYNC_PLUGIN_URL . '/assets/images/epayncmulti.png');
        $this->has_fields = true;
        $this->method_title = self::GATEWAY_NAME . ' - ' . __('Payment in installments', 'woo-epaync-payment');

        // Init common vars.
        $this->epaync_init();

        // Load the form fields.
        $this->init_form_fields();

        // Load the module settings.
        $this->init_settings();

        // Define user set variables.
        $this->title = $this->get_title();
        $this->description = $this->get_description();
        $this->testmode = ($this->get_general_option('ctx_mode') == 'TEST');
        $this->debug = ($this->get_general_option('debug') == 'yes') ? true : false;

        if ($epaync_plugin_features['restrictmulti']) {
            $this->notices[] = __('ATTENTION: The payment in installments feature activation is subject to the prior agreement of Société Générale.<br />If you enable this feature while you have not the associated option, an error 10000 – INSTALLMENTS_NOT_ALLOWED or 07 - PAYMENT_CONFIG will occur and the buyer will not be able to pay.', 'woo-epaync-payment');
        }

        if ($this->epaync_is_section_loaded()) {
            // Reset multi payment admin form action.
            add_action('woocommerce_settings_start', array($this, 'epaync_reset_admin_options'));

            // Update multi payment admin form action.
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Adding style to admin form action.
            add_action('admin_head-woocommerce_page_' . $this->admin_page, array($this, 'epaync_admin_head_style'));

            // Adding JS to admin form action.
            add_action('admin_head-woocommerce_page_' . $this->admin_page, array($this, 'epaync_admin_head_script'));
        }

        // Generate multi payment form action.
        add_action('woocommerce_receipt_' . $this->id, array($this, 'epaync_generate_form'));
    }

    /**
     * Initialise gateway settings form fields.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        unset($this->form_fields['payment_by_token']);

        // By default, disable multiple payment submodule.
        $this->form_fields['enabled']['default'] = 'no';
        $this->form_fields['enabled']['description'] = __('Enables / disables multiple payment.', 'woo-epaync-payment');

        $this->form_fields['title']['default'] = __('Payment by credit card in installments', 'woo-epaync-payment');

        // If WooCommecre Multilingual is not available (or installed version not allow gateways UI translation).
        // Let's suggest our translation feature.
        if (! class_exists('WCML_WC_Gateways')) {
            $this->form_fields['title']['default'] = array(
                'en_US' => 'Payment by credit card in installments',
                'en_GB' => 'Payment by credit card in installments',
                'fr_FR' => 'Paiement par carte bancaire en plusieurs fois',
                'de_DE' => 'Ratenzahlung mit EC-/Kreditkarte',
                'es_ES' => 'Pago con tarjeta de crédito en cuotas'
            );
        }

        $this->form_fields['card_data_mode'] = array(
            'title' => __('Card type selection', 'woo-epaync-payment'),
            'type' => 'select',
            'default' => 'DEFAULT',
            'options' => array(
                'DEFAULT' => __('On payment gateway', 'woo-epaync-payment'),
                'MERCHANT' => __('On merchant site', 'woo-epaync-payment')
            ),
            'description' =>sprintf(__('Select where card type will be selected by buyer.', 'woo-epaync-payment'), self::GATEWAY_NAME),
            'class' => 'wc-enhanced-select'
        );

        $this->form_fields['multi_options'] = array(
            'title' => __('MULTIPLE PAYMENT OPTIONS', 'woo-epaync-payment'),
            'type' => 'title'
        );

        // Multiple payment options.
        $descr = __('Click on "Add" button to configure one or more payment options.<br /><b>Label: </b>The option label to display on the frontend.<br /><b>Min amount: </b>Minimum amount to enable the payment option.<br /><b>Max amount: </b>Maximum amount to enable the payment option.<br /><b>Count: </b>Total number of payments.<br /><b>Period: </b>Delay (in days) between payments.<br /><b>1st payment: </b>Amount of first payment, in percentage of total amount. If empty, all payments will have the same amount.<br /><b>Do not forget to click on "Save" button to save your modifications.</b>', 'woo-epaync-payment');

        $cards = $this->get_supported_card_types();

        $columns = array();
        $columns['label'] = array(
            'title' => __('Label', 'woo-epaync-payment'),
            'width' => '154px'
        );

        $columns['amount_min'] = array(
            'title' => __('Min amount', 'woo-epaync-payment'),
            'width' => '92px'
        );

        $columns['amount_max'] = array(
            'title' => __('Max amount', 'woo-epaync-payment'),
            'width' => '92px'
        );

        if (isset($cards['CB'])) {
            $columns['contract'] = array(
                'title' => __('Contract', 'woo-epaync-payment'),
                'width' => '74px'
            );

            $descr = __('Click on "Add" button to configure one or more payment options.<br /><b>Label: </b>The option label to display on the frontend.<br /><b>Min amount: </b>Minimum amount to enable the payment option.<br /><b>Max amount: </b>Maximum amount to enable the payment option.<br /><b>Contract: </b>ID of the contract to use with the option (leave blank preferably).<br /><b>Count: </b>Total number of payments.<br /><b>Period: </b>Delay (in days) between payments.<br /><b>1st payment: </b>Amount of first payment, in percentage of total amount. If empty, all payments will have the same amount.<br /><b>Do not forget to click on "Save" button to save your modifications.</b>', 'woo-epaync-payment');
        }

        $columns['count'] = array(
            'title' => __('Count', 'woo-epaync-payment'),
            'width' => '74px'
        );

        $columns['period'] = array(
            'title' => __('Period', 'woo-epaync-payment'),
            'width' => '74px'
        );

        $columns['first'] = array(
            'title' => __('1st payment', 'woo-epaync-payment'),
            'width' => '84px'
        );

        $this->form_fields['payment_options'] = array(
            'title' => __('Payment options', 'woo-epaync-payment'),
            'type' => 'table',
            'columns' => $columns,
            'description' => $descr
        );
    }

    protected function get_rest_fields()
    {
        // REST API fields are not available for this payment.
    }

    protected function get_supported_card_types($codeInLabel = true)
    {
        $cards = parent::get_supported_card_types($codeInLabel);

        $multi_cards_keys = array(
            'AMEX', 'CB', 'DINERS', 'DISCOVER', 'E-CARTEBLEUE', 'JCB', 'MASTERCARD',
            'PRV_BDP', 'PRV_BDT', 'PRV_OPT', 'PRV_SOC', 'VISA', 'VISA_ELECTRON', 'VPAY'
        );

        $keys = array_keys($cards);
        foreach ($keys as $key) {
            if (! in_array($key, $multi_cards_keys)) {
                unset($cards[$key]);
            }
        }

        return $cards;
    }

    public function epaync_admin_head_script()
    {
        parent::epaync_admin_head_script();
?>
        <script type="text/javascript">
        //<!--
            function epayncAddOption(fieldName, record, key) {
                if (jQuery('#' + fieldName + '_table tbody tr').length == 1) {
                    jQuery('#' + fieldName + '_btn').css('display', 'none');
                    jQuery('#' + fieldName + '_table').css('display', '');
                }

                if (! key) {
                    // New line, generate key.
                    key = new Date().getTime();
                }

                var optionLine = '<tr id="' + fieldName + '_line_' + key + '">';
                jQuery.each(record, function(attr, value) {
                    var width = jQuery('#' + fieldName + '_table thead tr th.' + attr).width() - 5;
                    var inputName = fieldName + '[' + key + '][' + attr + ']';

                    optionLine += '<td style="padding: 0px;">';
                    optionLine += '<input class="input-text regular-input" style="width: ' + width + 'px;" name="' + inputName + '" id="' + inputName + '" type="text" value="' + value + '">';
                    optionLine += '</td>';
                });
                optionLine += '<td style="padding: 0px;"><input type="button" value="<?php echo __('Delete', 'woo-epaync-payment')?>" onclick="javascript: epayncDeleteOption(\'' + fieldName + '\', \'' + key + '\');"></td>';

                optionLine += '</tr>';

                jQuery(optionLine).insertBefore('#' + fieldName + '_add');
            }

            function epayncDeleteOption(fieldName, key) {
                jQuery('#' + fieldName + '_line_' + key).remove();

                if (jQuery('#' + fieldName + '_table tbody tr').length == 1) {
                    jQuery('#' + fieldName + '_btn').css('display', '');
                    jQuery('#' + fieldName + '_table').css('display', 'none');
                }
            }
        //-->
        </script>
<?php
    }

    public function validate_payment_options_field($key, $value = null)
    {
        $name = $this->plugin_id . $this->id . '_' . $key;
        $value = $value ? $value : (key_exists($name, $_POST) ? $_POST[$name] : array());

        foreach ($value as $code => $option) {
            if (! $option['label']
                    || ($option['amount_min'] && (! is_numeric($option['amount_min']) || $option['amount_min'] < 0))
                    || ($option['amount_max'] && (! is_numeric($option['amount_max']) || $option['amount_max'] < 0))
                    || ! is_numeric($option['count']) || $option['count'] < 1
                    || ! is_numeric($option['period']) || $option['period'] <= 0
                    || ($option['first'] && (! is_numeric($option['first']) || $option['first'] < 0 || $option['first'] > 100))) {
                unset($value[$code]); // Not save this option.
            } else {
                // Clean string.
                $fnc = function_exists('wc_clean') ? 'wc_clean' : 'woocommerce_clean';
                $value[$code] = array_map('esc_attr', array_map($fnc, (array) $option));
            }
        }

        return $value;
    }

    /**
     * Check if this gateway is enabled and available for the current cart.
     */
    public function is_available()
    {
        global $woocommerce;

        if (! parent::is_available()) {
            return false;
        }

        if ($woocommerce->cart) {
            // Check if any multi payment option is available.
            $available_options = $this->get_available_options();
            if (empty($available_options)) {
                return false;
            }
        }

        return true;
    }

    private function get_available_options()
    {
        global $woocommerce;

        $amount = $woocommerce->cart->total;

        $options = $this->get_option('payment_options');
        $enabled_options = array();

        if (isset($options) && is_array($options) && ! empty($options)) {
            foreach ($options as $code => $option) {
                if ((! $option['amount_min'] || $amount >= $option['amount_min']) && (! $option['amount_max'] || $amount <= $option['amount_max'])) {
                    $enabled_options[$code] = $option;
                }
            }
        }

        return apply_filters("woocommerce_epayncmulti_enabled_options", $enabled_options);
    }

    /**
     * Display payment fields and show method description if set.
     *
     * @access public
     * @return void
     */
    public function payment_fields()
    {
        parent::payment_fields();

        $options = $this->get_available_options();

        if (empty($options)) {
            // Should not happens for multi payment.
            return;
        }

        echo '<ul>';

        if (count($options) == 1) {
            $option = reset($options); // The option itself.
            $key = key($options); // The option key in options array.
            echo '<span style="font-weight: bold;">' . __('Your payment option', 'woo-epaync-payment') . '</span>';
            echo '<li style="list-style-type: none;">
                    <input type="hidden" id="epayncmulti_option_' . $key . '" value="' . $key . '" name="epayncmulti_option">
                    <label style="display: inline;">' . $option['label'] . '</label>
                  </li>';
        } else {
            $first = true;
            echo '<span style="font-weight: bold;">' . __('Choose your payment option', 'woo-epaync-payment') . '</span>';
            foreach ($options as $key => $option) {
                echo '<li style="list-style-type: none;">
                        <input class="radio" type="radio"'. ($first == true ? ' checked="checked"' : '') . ' id="epayncmulti_option_' . $key . '" value="' . $key . '" name="epayncmulti_option">
                        <label for="epayncmulti_option_' . $key . '" style="display: inline;">' . $option['label'] . '</label>
                      </li>';
                $first = false;
            }
        }

        echo '</ul>';
    }

    /**
     * Process the payment and return the result.
     **/
    public function process_payment($order_id)
    {
        global $woocommerce;

        if ($this->get_option('card_data_mode') == 'MERCHANT') {
            $this->save_selected_card($order_id);
        }

        $options = $this->get_available_options();
        $option = $options[$_POST['epayncmulti_option']];

        // Save selected payment option into session...
        set_transient('epayncmulti_option_' . $order_id, $option);

        // ... and into DB.
        $order = new WC_Order($order_id);
        update_post_meta(self::get_order_property($order, 'id'), '_payment_method_title', self::get_order_property($order, 'payment_method_title') . " ({$option['count']} x)");

        if (version_compare($woocommerce->version, '2.1.0', '<')) {
            $pay_url = add_query_arg('order', self::get_order_property($order, 'id'), add_query_arg('key', self::get_order_property($order, 'order_key'), get_permalink(woocommerce_get_page_id('pay'))));
        } else {
            $pay_url = $order->get_checkout_payment_url(true);
        }

        return array(
            'result' => 'success',
            'redirect' => $pay_url
        );
    }

    /**
     * Prepare form params to send to payment gateway.
     **/
    protected function epaync_fill_request($order)
    {
        parent::epaync_fill_request($order);

        $option = get_transient('epayncmulti_option_' . self::get_order_property($order, 'id'));

        // Multiple payment options.
        $amount = $this->epaync_request->get('amount');
        $first = $option['first'] ? round(($option['first'] / 100) * $amount) : null;
        $this->epaync_request->setMultiPayment($amount, $first, $option['count'], $option['period']);
        $this->epaync_request->set('contracts', (isset($option['contract']) && $option['contract']) ? 'CB='.$option['contract'] : null);

        delete_transient('epayncmulti_option_' . self::get_order_property($order, 'id'));
    }
}
