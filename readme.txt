=== SaySwitch Payment Gateway for WooCommerce ===
Contributors: Your Name
Tags: woocommerce, payment gateway, sayswitch, nigeria, payments
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Accept payments on your WooCommerce store using SaySwitch. This plugin allows you to securely collect payments via the SaySwitch Standard Checkout.

= Features =
* **Secure Checkout:** Redirects customers to the secure SaySwitch payment page.
* **Automatic Verification:** Verifies transactions automatically upon redirect.
* **Webhook Support:** Background payment confirmation even if the customer closes the browser.
* **Debug Logging:** Track API requests and responses for easy troubleshooting.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or upload the .zip file via 'Plugins' > 'Add New'.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **WooCommerce > Settings > Payments**.
4. Click on **SaySwitch** to configure your Secret Key.
5. Copy the **Webhook URL** from the settings page and paste it into your SaySwitch Dashboard.

== Screenshots ==

1. The SaySwitch settings page in WooCommerce.
2. The checkout page with SaySwitch as a payment option.

== Changelog ==

= 1.2.0 =
* Added WC Logger for debug tracking.
* Added Webhook handler via WC-API.

= 1.1.0 =
* Initial release with transaction initialization and redirect verification.