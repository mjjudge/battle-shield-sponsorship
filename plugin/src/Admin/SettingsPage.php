<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

    private const NONCE_ACTION = 'bss_save_settings';
    private const OPTION_KEY   = 'bss_settings';

    public function __construct() {
        add_action( 'admin_post_bss_save_settings', [ $this, 'handle_save' ] );
    }

    public function render(): void {
        RequestGuard::require_capability( 'bss_manage_settings' );

        if ( isset( $_GET['saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'battle-shield-sponsorship' ) . '</p></div>';
        }

        $settings = (array) get_option( self::OPTION_KEY, [] );

        $stripe_pk_live   = (string) ( $settings['stripe_publishable_key_live'] ?? '' );
        $stripe_sk_live   = (string) ( $settings['stripe_secret_key_live'] ?? '' );
        $stripe_wh_live   = (string) ( $settings['stripe_webhook_secret_live'] ?? '' );
        $stripe_pk_test   = (string) ( $settings['stripe_publishable_key_test'] ?? '' );
        $stripe_sk_test   = (string) ( $settings['stripe_secret_key_test'] ?? '' );
        $stripe_wh_test   = (string) ( $settings['stripe_webhook_secret_test'] ?? '' );
        $stripe_mode      = (string) ( $settings['stripe_mode'] ?? 'test' );
        $help_email       = (string) ( $settings['help_email'] ?? 'helpgrow@battleofevesham.co.uk' );
        $treasurer_email  = (string) ( $settings['treasurer_email'] ?? '' );
        $from_email       = (string) ( $settings['from_email'] ?? get_option( 'admin_email', '' ) );
        $from_name        = (string) ( $settings['from_name'] ?? get_option( 'blogname', '' ) );
        $shop_page_slug   = (string) ( $settings['shop_page_slug'] ?? 'shield-sponsorship' );
        $success_page_slug = (string) ( $settings['success_page_slug'] ?? 'shield-sponsorship-complete' );
        $cancel_page_slug  = (string) ( $settings['cancel_page_slug'] ?? 'shield-sponsorship-cancel' );
        $edit_page_slug    = (string) ( $settings['edit_page_slug'] ?? 'shield-sponsorship-edit' );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Battle Shield Sponsorship Settings', 'battle-shield-sponsorship' ) . '</h1>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bss_save_settings" />';
        wp_nonce_field( self::NONCE_ACTION );

        echo '<h2>' . esc_html__( 'Stripe Integration', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<table class="form-table">';

        echo '<tr><th><label for="stripe_mode">' . esc_html__( 'Mode', 'battle-shield-sponsorship' ) . '</label></th><td>';
        echo '<select name="stripe_mode" id="stripe_mode">';
        echo '<option value="test_no_stripe" ' . selected( $stripe_mode, 'test_no_stripe', false ) . '>' . esc_html__( 'Test – No Stripe', 'battle-shield-sponsorship' ) . '</option>';
        echo '<option value="test" ' . selected( $stripe_mode, 'test', false ) . '>' . esc_html__( 'Test – Stripe', 'battle-shield-sponsorship' ) . '</option>';
        echo '<option value="live" ' . selected( $stripe_mode, 'live', false ) . '>' . esc_html__( 'Live', 'battle-shield-sponsorship' ) . '</option>';
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Test – No Stripe: bypass Stripe entirely, payments confirmed automatically (for workflow testing). Test – Stripe: use Stripe test keys. Live: real payments.', 'battle-shield-sponsorship' ) . '</p>';
        echo '</td></tr>';

        $this->row( 'stripe_publishable_key_test', __( 'Test publishable key', 'battle-shield-sponsorship' ),
            '<input type="text" name="stripe_publishable_key_test" class="regular-text" value="' . esc_attr( $stripe_pk_test ) . '" />' );
        $this->row( 'stripe_secret_key_test', __( 'Test secret key', 'battle-shield-sponsorship' ),
            '<input type="password" name="stripe_secret_key_test" class="regular-text" value="' . esc_attr( $stripe_sk_test ) . '" autocomplete="new-password" />' );
        $this->row( 'stripe_webhook_secret_test', __( 'Test webhook secret', 'battle-shield-sponsorship' ),
            '<input type="password" name="stripe_webhook_secret_test" class="regular-text" value="' . esc_attr( $stripe_wh_test ) . '" autocomplete="new-password" />' );
        $this->row( 'stripe_publishable_key_live', __( 'Live publishable key', 'battle-shield-sponsorship' ),
            '<input type="text" name="stripe_publishable_key_live" class="regular-text" value="' . esc_attr( $stripe_pk_live ) . '" />' );
        $this->row( 'stripe_secret_key_live', __( 'Live secret key', 'battle-shield-sponsorship' ),
            '<input type="password" name="stripe_secret_key_live" class="regular-text" value="' . esc_attr( $stripe_sk_live ) . '" autocomplete="new-password" />' );
        $this->row( 'stripe_webhook_secret_live', __( 'Live webhook secret', 'battle-shield-sponsorship' ),
            '<input type="password" name="stripe_webhook_secret_live" class="regular-text" value="' . esc_attr( $stripe_wh_live ) . '" autocomplete="new-password" />'
            . '<p class="description">' . esc_html__( 'Webhook endpoint: ', 'battle-shield-sponsorship' )
            . '<code>' . esc_html( rest_url( 'bss/v1/stripe/webhook' ) ) . '</code></p>' );

        echo '</table>';

        echo '<h2>' . esc_html__( 'Email', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<table class="form-table">';
        $this->row( 'from_name', __( 'From name', 'battle-shield-sponsorship' ),
            '<input type="text" name="from_name" class="regular-text" value="' . esc_attr( $from_name ) . '" />' );
        $this->row( 'from_email', __( 'From email', 'battle-shield-sponsorship' ),
            '<input type="email" name="from_email" class="regular-text" value="' . esc_attr( $from_email ) . '" />' );
        $this->row( 'treasurer_email', __( 'Treasurer email', 'battle-shield-sponsorship' ),
            '<input type="email" name="treasurer_email" class="regular-text" value="' . esc_attr( $treasurer_email ) . '" />'
            . '<p class="description">' . esc_html__( 'Receives a notification with payment details immediately after each confirmed sponsorship. Leave blank to disable.', 'battle-shield-sponsorship' ) . '</p>' );
        $this->row( 'help_email', __( 'Help / contact email', 'battle-shield-sponsorship' ),
            '<input type="email" name="help_email" class="regular-text" value="' . esc_attr( $help_email ) . '" />'
            . '<p class="description">' . esc_html__( 'Shown to sponsors in email templates as {help_email}.', 'battle-shield-sponsorship' ) . '</p>' );
        echo '</table>';

        echo '<h2>' . esc_html__( 'Page Slugs', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'These must match WordPress pages you have created with the relevant shortcodes.', 'battle-shield-sponsorship' ) . '</p>';
        echo '<table class="form-table">';
        $this->row( 'shop_page_slug', __( 'Shop page slug', 'battle-shield-sponsorship' ),
            '<input type="text" name="shop_page_slug" class="regular-text" value="' . esc_attr( $shop_page_slug ) . '" />' );
        $this->row( 'success_page_slug', __( 'Payment success page slug', 'battle-shield-sponsorship' ),
            '<input type="text" name="success_page_slug" class="regular-text" value="' . esc_attr( $success_page_slug ) . '" />' );
        $this->row( 'cancel_page_slug', __( 'Payment cancel page slug', 'battle-shield-sponsorship' ),
            '<input type="text" name="cancel_page_slug" class="regular-text" value="' . esc_attr( $cancel_page_slug ) . '" />' );
        $this->row( 'edit_page_slug', __( 'Sponsor edit page slug', 'battle-shield-sponsorship' ),
            '<input type="text" name="edit_page_slug" class="regular-text" value="' . esc_attr( $edit_page_slug ) . '" />' );
        echo '</table>';

        submit_button( __( 'Save Settings', 'battle-shield-sponsorship' ) );
        echo '</form>';
        echo '</div>';
    }

    public function handle_save(): void {
        RequestGuard::require_capability( 'bss_manage_settings' );
        RequestGuard::verify_admin_nonce( self::NONCE_ACTION );

        $current  = (array) get_option( self::OPTION_KEY, [] );
        $new_data = [
            'stripe_mode'                  => in_array( wp_unslash( $_POST['stripe_mode'] ?? '' ), [ 'test_no_stripe', 'test', 'live' ], true ) ? wp_unslash( $_POST['stripe_mode'] ) : 'test_no_stripe',
            'stripe_publishable_key_test'  => sanitize_text_field( wp_unslash( $_POST['stripe_publishable_key_test'] ?? '' ) ),
            'stripe_secret_key_test'       => sanitize_text_field( wp_unslash( $_POST['stripe_secret_key_test'] ?? '' ) ),
            'stripe_webhook_secret_test'   => sanitize_text_field( wp_unslash( $_POST['stripe_webhook_secret_test'] ?? '' ) ),
            'stripe_publishable_key_live'  => sanitize_text_field( wp_unslash( $_POST['stripe_publishable_key_live'] ?? '' ) ),
            'stripe_secret_key_live'       => sanitize_text_field( wp_unslash( $_POST['stripe_secret_key_live'] ?? '' ) ),
            'stripe_webhook_secret_live'   => sanitize_text_field( wp_unslash( $_POST['stripe_webhook_secret_live'] ?? '' ) ),
            'from_name'                    => sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) ),
            'from_email'                   => sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) ),
            'treasurer_email'              => sanitize_email( wp_unslash( $_POST['treasurer_email'] ?? '' ) ),
            'help_email'                   => sanitize_email( wp_unslash( $_POST['help_email'] ?? '' ) ),
            'shop_page_slug'               => sanitize_title( wp_unslash( $_POST['shop_page_slug'] ?? '' ) ),
            'success_page_slug'            => sanitize_title( wp_unslash( $_POST['success_page_slug'] ?? '' ) ),
            'cancel_page_slug'             => sanitize_title( wp_unslash( $_POST['cancel_page_slug'] ?? '' ) ),
            'edit_page_slug'               => sanitize_title( wp_unslash( $_POST['edit_page_slug'] ?? '' ) ),
        ];

        // Blank password fields means "keep existing value" — don't overwrite keys with empty strings.
        foreach ( [ 'stripe_secret_key_test', 'stripe_webhook_secret_test', 'stripe_secret_key_live', 'stripe_webhook_secret_live' ] as $key ) {
            if ( '' === $new_data[ $key ] && isset( $current[ $key ] ) ) {
                $new_data[ $key ] = $current[ $key ];
            }
        }

        update_option( self::OPTION_KEY, array_merge( $current, $new_data ) );

        wp_safe_redirect( add_query_arg( [ 'page' => 'bss-settings', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private function row( string $id, string $label, string $field ): void {
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th>';
        echo '<td>' . $field . '</td>';
        echo '</tr>';
    }
}
