=== EpayNC for WooCommerce ===
Contributors: Lyra Network, Alsacréations
Tags: payment, EpayNC, gateway, checkout, credit card, bank card, e-commerce
Requires at least: 3.5
Tested up to: 5.8
WC requires at least: 2.0
WC tested up to: 5.7
Stable tag: 1.9.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin links your WordPress WooCommerce shop to the EpayNC payment gateway.

== Description ==

The payment plugin has the following features:
* Compatible with WooCommerce v 2.0.0 and above.
* Management of one-time payment and payment in installments.
* Possibility to define many options for payment in installments (2 times payment, 3 times payment,...).
* Can do automatic redirection to the shop at the end of the payment.
* Setting of a minimum / maximum amount to enable payment module.
* Selective 3D Secure depending on the order amount.
* Update orders after payment through a silent URL (Instant Payment Notification).
* Multi languages compliance.
* Multi currencies compliance.
* Possibility to enable / disable module logs.
* Possibility to configure order status on payment success.

== Installation ==

1. Upload the folder `woo-epaync-payment` to the `/wp-content/plugins/` directory
2. Activate the plugin through the `Plugins` menu in WordPress
3. To configure the plugin, go to the `WooCommerce > Settings` menu in WordPress then choose `Checkout` or `Payments` tab (depending on your WooCommerce version).

== Screenshots ==

1. EpayNC general configuration.
2. EpayNC standard payment configuration.
3. EpayNC payment in installments configuration.
4. EpayNC payment options in checkout page.
5. EpayNC payment page.

== Changelog ==

= 1.9.4, 2021-09-27 =
* Some minor fixes.
* [subscr] Bug fix: Fix subscription next payment date.
* [embedded] Bug fix: Fix wrapping payment result for embedded payment.

= 1.9.3, 2021-07-15 =
* [subscr] Bug fix: Fix subscription renewal process (create a renewal order).
* Display installments number in order details when it is available.

= 1.9.2, 2021-07-06 =
* Improve subscription cancellation process (cancel web service is called on buyer action).
* Display authorized amount in order details when it is available.

= 1.9.1, 2021-06-21 =
* Bug fix: Do not create two transactions when trial is disabled for a subscription.
* Bug fix: Fatal error when modifying payment for a subscription in My account > subscriptions.
* Bug fix: Propose dynamically added payment means in "Other payment means" section.
* Bug fix: Propose subscription payment method when client account creation during checkout is enabled.
* Bug fix: Adjust rrule for dates at the end of the month when creating subscriptions.
* Manage retrocompatibility with already validated orders (do not check order key) when processing subscriptions.
* Manage subscription creation from gateway Back Office.
* Improve error management on subscription actions (cancel and update).
* Send the relevant part of the current PHP version in vads_contrib field.
* Improve support e-mails display.

= 1.9.0, 2021-04-21 =
* [subscr] Manage subscriptions with WooCommerce Subscriptions (including subscription update and cancellation).
* Possibility to open support issue from the plugin configuration panel or an order details page.
* Reorganize plugin settings (REST API keys section moved to general configuration).
* Possibility to configure REST API URLs.
* Possibility to add payment means dynamically in "Other payment means" section.
* [embedded] Add pop-in choice to card data entry mode field.
* [embedded] Possibility to customize "Register my card" checkbox label.
* Possibility to configure description for popin and iframe modes.
* [alias] Display the brand of the registered means of payment in payment by alias.
* [alias] Added possibility to delete registered payment means.
* [alias] Check alias validity before proceeding to payment.
* Do not use vads_order_info* gateway parameter (use vads_ext_info* instead).
* Update 3DS management option description.

= 1.8.10, 2021-03-05 =
* Save 3DS authentication status and certificate as an order note.
* Use online payment means logos.

= 1.8.9, 2020-12-23 =
* Bug fix: Reorder dynamically added payment means wehen not grouped.
* Restore compatibility with WooCommerce 2.x versions.
* Display warning message on payment in iframe mode enabling.

= 1.8.8, 2020-12-16 =
* Bug fix: Error 500 due to obsolete function (get_magic_quotes_gpc) in PHP 7.4.

= 1.8.7, 2020-10-30 =
* [embedded] Bug fix: Force redirection when there is an error in payment form token creation.
* [embedded] Bug fix: Embedded payment fields not correctly displayed since the last gateway JS library delivery.
* Fix standard payment description management.

= 1.8.6, 2020-10-12 =
* Bug fix: Fix IPN management on cancellation notification for orders in on-hold status.

= 1.8.5, 2020-09-02 =
* [embedded] Bug fix: Error 500 due to riskControl modified format in REST response.
* [embedded] Bug fix: Compatibility of payment with embedded fields with Internet Explorer 11.
* [embedded] Bug fix: Error due to strongAuthenticationState field renaming in REST token creation.
* Update payment means logos.

= 1.8.4, 2020-06-14 =
* Improve plugin translations.

= 1.8.3, 2020-05-21 =
* [embedded] Bug fix: Payment by embedded fields error relative to new JavaScript client library.
* [embedded] Bug fix: Manage new metadata field format returned in REST API IPN.
* [subscr] Bug fix: Fatal error in subscription submodule before redirection.
* [alias] Display confirmation message on payment by token enabling.

= 1.8.2, 2020-03-16 =
* Bug fix: Manage products with zero amount in tax calculation.
* [alias] Bug fix: Payment by alias available only for logged in users.
* Bug fix: Skip confirmation alert after clicking on payment button with IFRAME and REST modes (on WooCommerce >= v3.9).
* Bug fix: Exit script after redirection to cart URL in error cases.
* Fix errors (NOTICE level) when retrieving some configuration fields.
* [embedded] Fix embedded payment fields display in WooCommerce v3.9 (relative to WooCommerce issue #24271).

= 1.8.1, 2019-12-23 =
* Bug fix: update order by IPN call when many attempts option is enabled.

= 1.8.0, 2019-11-20 =
* Possibility to dynamically propose new payment means (only by redirection).
* [embedded] Added feature embedded payment fields (directly on site or in a pop-in) using REST API.
* Improve plugin translations.
* Added support of payment by subscription with Subcriptio plugin in a new submodule (needs activation in source code).

= 1.7.1, 2019-04-01 =
* Fix some plugin translations.
* Do not use vads_order_info2 gateway parameter.
* Bug fix: cannot re-order after a cancelled payment in iframe mode.

= 1.7.0, 2019-02-04 =
* Fix error in shipping amount calculation (on some WooCommerce 2.x versions).
* Improve payment error display on order details and hide message in order email.
* Added payment by token (requires EpayNC payment by token option).
* Added possibility to restrict payment submodules to specific countries.
* Manage successful order statuses dynamically to support custom statuses.
* Redirect buyer to cart page (instead of checkout page) after a failed payment.
* Display error messages and notices in WooCommerce 3.5.
* Added API to manage subscriptions payment integration (for developpers).

= 1.6.2, 2018-11-26 =
* Fix new signature algorithm name (HMAC-SHA-256).
* Update payment means logos.
* [prodfaq] Fix notice about shifting the shop to production mode.
* Added Spanish translation.
* Improve iframe mode interface.
* Allow comma when entering amounts in configuration fields.
* Send shipping fees in vads_shipping_amount variable.

= 1.6.1, 2018-07-06 =
* [shatwo] Enable HMAC-SHA-256 signature algorithm by default.
* Ignore spaces at the beginning and end of certificates when calculating the return signature.

--------
Generated automatically from CHANGELOG.md.