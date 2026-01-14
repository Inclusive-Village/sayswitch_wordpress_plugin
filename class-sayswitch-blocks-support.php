<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class SaySwitch_Blocks_Support extends AbstractPaymentMethodType
{
    protected $name = 'sayswitch'; // Must match your Gateway ID

    public function initialize()
    {
        $this->settings = get_option('woocommerce_sayswitch_settings', []);
    }

    public function is_active()
    {
        return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    public function get_payment_method_script_handles()
    {
        // We register a tiny JS file to handle the frontend display
        wp_register_script(
            'sayswitch-blocks-integration',
            plugin_dir_url(__FILE__).'checkout.js',
            ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n'],
            null,
            true
        );

        return ['sayswitch-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
        ];
    }
}
