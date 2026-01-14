<?php
/**
 * Plugin Name: SaySwitch WooCommerce Payment Gateway
 * Version: 1.6.0
 * Author: Inclusive Village
 * Description: Securely accept payments via SaySwitch with Block support and a custom Dashboard UI.
 * Text Domain: sayswitch.
 */
if (!defined('ABSPATH')) {
    exit;
}

/*
 * 1. Declare High-Performance Order Storage (HPOS) & Block Compatibility
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

/*
 * 2. Register the Gateway
 */
add_filter('woocommerce_payment_gateways', 'add_sayswitch_gateway');
function add_sayswitch_gateway($gateways)
{
    $gateways[] = 'WC_Gateway_SaySwitch';

    return $gateways;
}

/*
 * 3. Register the Blocks Integration
 */
add_action('woocommerce_blocks_loaded', 'sayswitch_register_blocks_support');
function sayswitch_register_blocks_support()
{
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }
    require_once plugin_dir_path(__FILE__).'class-sayswitch-blocks-support.php';
    add_action('woocommerce_blocks_payment_method_type_registration', function ($registry) {
        $registry->register(new SaySwitch_Blocks_Support());
    });
}

/*
 * 4. Custom Setting Field for Dashboard Image
 */
add_action('woocommerce_admin_field_sayswitch_dashboard_image', function ($value) {
    $image_url = plugins_url('dashboard.png', __FILE__);
    ?>
    <tr valign="top">
        <td colspan="2" style="padding: 0;">
            <div class="sayswitch-dashboard-banner" style="margin: 10px 0 25px 0; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
                <img src="<?php echo esc_url($image_url); ?>" style="width: 100%; display: block;" alt="SaySwitch Dashboard Preview">
            </div>
        </td>
    </tr>
    <?php
});

/*
 * 5. Initialize the Gateway Class
 */
add_action('plugins_loaded', 'sayswitch_gateway_init', 11);

function sayswitch_gateway_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_SaySwitch extends WC_Payment_Gateway
    {
        protected $logger;

        public function __construct()
        {
            $this->id = 'sayswitch';
            $this->has_fields = false;
            $this->method_title = 'SaySwitch';
            $this->method_description = 'Accept secure payments via the SaySwitch gateway.';

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->secret_key = $this->get_option('secret_key');
            $this->debug = 'yes' === $this->get_option('debug');

            add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_thankyou_'.$this->id, [$this, 'verify_sayswitch_transaction']);
            add_action('woocommerce_api_sayswitch', [$this, 'handle_webhook']);
            add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        }

        public function init_form_fields()
        {
            $this->form_fields = [
                'enabled' => [
                    'title' => __('Enable/Disable', 'sayswitch'),
                    'type' => 'checkbox',
                    'label' => __('Enable SaySwitch Payment', 'sayswitch'),
                    'default' => 'yes',
                ],
                'title' => [
                    'title' => __('Title', 'sayswitch'),
                    'type' => 'text',
                    'default' => __('Pay with SaySwitch', 'sayswitch'),
                ],
                'description' => [
                    'title' => __('Description', 'sayswitch'),
                    'type' => 'textarea',
                    'default' => __('Secure payment via SaySwitch.', 'sayswitch'),
                ],
                'secret_key' => [
                    'title' => __('Secret Key', 'sayswitch'),
                    'type' => 'password',
                    'description' => __('Enter your Secret Key from the SaySwitch Merchant Dashboard.', 'sayswitch'),
                ],
                'debug' => [
                    'title' => __('Debug Log', 'sayswitch'),
                    'type' => 'checkbox',
                    'label' => __('Enable Logging', 'sayswitch'),
                    'default' => 'no',
                ],
                'webhook_info' => [
                    'title' => __('Webhook URL', 'sayswitch'),
                    'type' => 'title',
                    'description' => __('Copy this to your SaySwitch Dashboard: <code>'.home_url('/wc-api/sayswitch/').'</code>', 'sayswitch'),
                ],
            ];
        }

        public function payment_scripts()
        {
            if (!is_checkout() && !isset($_GET['pay_for_order'])) {
                return;
            }
            $css = 'body .woocommerce-checkout #payment #place_order.sayswitch-btn-custom { background-color: #006400 !important; color: #fff !important; border:none; }';
            wp_add_inline_style('woocommerce-inline', $css);
            wc_enqueue_js("$(document.body).on('updated_checkout change', 'input[name=\"payment_method\"]', function() {
                if($('#payment_method_sayswitch').is(':checked')) { $('#place_order').addClass('sayswitch-btn-custom'); }
                else { $('#place_order').removeClass('sayswitch-btn-custom'); }
            });");
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $response = wp_remote_post('https://backendapi.sayswitchgroup.com/api/v1/transaction/initialize', [
                'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer '.$this->secret_key],
                'body' => json_encode([
                    'email' => $order->get_billing_email(),
                    'amount' => (string) $order->get_total(),
                    'currency' => get_woocommerce_currency(),
                    'callback' => $this->get_return_url($order),
                ]),
                'timeout' => 45,
            ]);

            if (is_wp_error($response)) {
                return;
            }
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['success']) && $body['success'] === true) {
                update_post_meta($order_id, '_sayswitch_ref', $body['data']['reference']);

                return ['result' => 'success', 'redirect' => $body['data']['authorization_url']];
            }
        }

        public function handle_webhook()
        {
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['data']['reference'])) {
                $this->verify_and_complete_order(sanitize_text_field($data['data']['reference']), 'Webhook');
            }
            status_header(200);
            exit;
        }

        public function verify_sayswitch_transaction($order_id)
        {
            $ref = isset($_GET['reference']) ? sanitize_text_field($_GET['reference']) : get_post_meta($order_id, '_sayswitch_ref', true);
            if ($ref) {
                $this->verify_and_complete_order($ref, 'Redirect');
            }
        }

        private function verify_and_complete_order($ref, $source)
{
    $resp = wp_remote_get('https://backendapi.sayswitchgroup.com/api/v1/transaction/verify/'.$ref, [
        'headers' => ['Authorization' => 'Bearer '.$this->secret_key],
        'timeout' => 45,
    ]);

    if (is_wp_error($resp)) {
        return;
    }

    $body = json_decode(wp_remote_retrieve_body($resp), true);
    $order_id = $this->get_order_id_by_reference($ref);
    
    if (!$order_id) {
        return;
    }

    $order = wc_get_order($order_id);

    // 1. Check if the API reported a successful status
    if (isset($body['data']['status']) && $body['data']['status'] === 'success') {
        
        $paid_amount = (float) $body['data']['amount'];
        $order_total = (float) $order->get_total();

        // 2. Validation: Status is success AND amount is sufficient
        if ($paid_amount >= $order_total) {
            if (!$order->has_status(['completed', 'processing'])) {
                $order->payment_complete($ref);
                $order->add_order_note("Verified via $source. Amount match: Success ($paid_amount). Reference: $ref");
            }
            // Success: WooCommerce will naturally handle the "Thank You" page if this is a standard return
        } else {
            // 3. Amount Mismatch (Fraud Protection)
            $order->update_status('failed', "Payment Failed: Amount mismatch. Paid $paid_amount, expected $order_total.");
            $this->redirect_to_failed_page($order);
        }
    } else {
        // 4. Status is NOT success (e.g., 'failed', 'pending', or 'cancelled')
        $api_msg = isset($body['message']) ? $body['message'] : 'No message provided';
        $order->update_status('failed', "Payment Failed via SaySwitch: Status was not success. Message: $api_msg");
        $this->redirect_to_failed_page($order);
    }
}

/**
 * Helper function to handle the redirection to the failed payment page
 */
private function redirect_to_failed_page($order) {
    // Generate the URL for the checkout page where the user can try again
    $failure_url = $order->get_checkout_payment_url(false);
    
    // Clear any existing notices and add a fresh error message for the user
    wc_clear_notices();
    wc_add_notice(__('Your payment was unsuccessful. Please try again or choose a different method.', 'your-text-domain'), 'error');

    wp_safe_redirect($failure_url);
    exit;
}

        private function get_order_id_by_reference($ref)
        {
            global $wpdb;

            return $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_sayswitch_ref' AND meta_value = %s", $ref));
        }
    }
}

/*
 * 6. Plugin Action Links & Custom Admin Styling
 */
add_filter('plugin_action_links_'.plugin_basename(__FILE__), function ($links) {
    $custom = [
        '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=sayswitch').'">Plugin Settings</a>',
        '<a href="https://sayswitch-documentation.vercel.app/" target="_blank">API Docs</a>',
        '<a href="https://merchant.sayswitchgroup.com/login" target="_blank">Get API Keys</a>',
    ];

    return array_merge($custom, $links);
});

/*
 * 7. Enhanced UI Styling with Padding
 */
add_action('admin_head', function () {
    if (isset($_GET['section']) && $_GET['section'] == 'sayswitch') {
        $dashboard_img = plugins_url('dashboard.png', __FILE__);
        echo '<style>
            /* Create the Split Layout */
            .woocommerce form > table.form-table {
                display: flex !important;
                flex-direction: row;
                background: #ffffff;
                border: 1px solid #d1d5db;
                border-radius: 16px;
                overflow: hidden;
                max-width: 1100px;
                padding: 0 !important;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            }

            /* Left Side: The Form */
            .woocommerce table.form-table tbody {
                flex: 1;
                padding: 40px;
            }

            /* Right Side: The Image */
            .woocommerce table.form-table::after {
                content: "";
                flex: 1;
                background-image: url("' . esc_url($dashboard_img) . '");
                background-size: cover;
                background-position: center;
                border-left: 1px solid #d1d5db;
                min-height: 500px;
            }

            /* Responsive adjustments */
            .woocommerce table.form-table tr { display: block; margin-bottom: 20px; }
            .woocommerce table.form-table th { display: block; width: 100%; padding: 0 0 8px 0; }
            .woocommerce table.form-table td { display: block; width: 100%; padding: 0; }

            /* Branding & Elements */
            .button-primary { background: #006400 !important; border: none !important; padding: 12px 30px !important; border-radius: 8px !important; height: auto !important; }
            code { background: #f3f4f6 !important; padding: 10px !important; border-radius: 6px !important; display: block; border: 1px solid #e5e7eb !important; }
        </style>';
    }
});
