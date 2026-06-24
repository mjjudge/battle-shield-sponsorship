<?php

namespace BattleShieldSponsorship\BSPublic;

use BattleShieldSponsorship\Services\SponsorshipService;
use BattleShieldSponsorship\Services\UploadTokenService;
use BattleShieldSponsorship\Services\CampaignService;

defined( 'ABSPATH' ) || exit;

/**
 * Success and cancel shortcodes for Stripe redirect pages.
 *
 * [battle_shield_success] — shown after Stripe redirects back on success
 * [battle_shield_cancel]  — shown after Stripe redirects back on cancel
 */
class PaymentPagesHandler {

    public function register(): void {
        add_shortcode( 'battle_shield_success', [ $this, 'render_success' ] );
        add_shortcode( 'battle_shield_cancel', [ $this, 'render_cancel' ] );
    }

    public function render_success(): string {
        $sponsorship_id      = (int) ( $_GET['sponsorship_id'] ?? 0 );
        $sponsorship_service = new SponsorshipService();
        $sponsorship         = $sponsorship_id > 0 ? $sponsorship_service->get_by_id( $sponsorship_id ) : null;

        if ( ! $sponsorship ) {
            return '<p class="bss-notice">' . esc_html__( 'Thank you for your payment! You will receive a confirmation email shortly.', 'battle-shield-sponsorship' ) . '</p>';
        }

        $status   = (string) $sponsorship->payment_status;
        $settings = (array) get_option( 'bss_settings', [] );

        if ( 'pending' === $status && 'test_no_stripe' === ( $settings['stripe_mode'] ?? '' ) ) {
            $sponsorship_service->mark_paid( $sponsorship_id );
            $sponsorship = $sponsorship_service->get_by_id( $sponsorship_id );
            $status      = $sponsorship ? (string) $sponsorship->payment_status : 'paid';
        }

        if ( 'paid' !== $status ) {
            return '<p class="bss-notice">' . esc_html__( 'Your payment is being confirmed. Please wait a moment — a confirmation email will arrive shortly.', 'battle-shield-sponsorship' ) . '</p>';
        }

        $token_service = new UploadTokenService();
        $token         = $token_service->get_token_for_sponsorship( $sponsorship_id );
        $edit_url      = $token ? $token_service->edit_url( $token ) : '';

        ob_start();
        echo '<div class="bss-payment-success">';
        echo '<h2>' . esc_html__( 'Thank you for your sponsorship!', 'battle-shield-sponsorship' ) . '</h2>';

        $campaign = ( new CampaignService() )->get_by_id( (int) $sponsorship->campaign_id );
        if ( $campaign ) {
            echo '<p>' . sprintf( esc_html__( 'You are now a sponsor of %s.', 'battle-shield-sponsorship' ), esc_html( (string) $campaign->name ) ) . '</p>';
        }

        echo '<p>' . esc_html__( 'A confirmation email has been sent to you with further details.', 'battle-shield-sponsorship' ) . '</p>';

        if ( $edit_url ) {
            echo '<p>';
            printf(
                wp_kses(
                    __( 'You can upload your logo and sponsor message here: <a href="%1$s">%1$s</a>', 'battle-shield-sponsorship' ),
                    [ 'a' => [ 'href' => [] ] ]
                ),
                esc_url( $edit_url )
            );
            echo '</p>';
        }

        echo '</div>';
        return (string) ob_get_clean();
    }

    public function render_cancel(): string {
        $settings  = (array) get_option( 'bss_settings', [] );
        $shop_slug = (string) ( $settings['shop_page_slug'] ?? 'shield-sponsorship' );

        $shop_url = home_url( '/' . $shop_slug . '/' );

        ob_start();
        echo '<div class="bss-payment-cancel">';
        echo '<h2>' . esc_html__( 'Payment cancelled', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<p>' . esc_html__( 'Your payment was not completed. Your shield selection has been released.', 'battle-shield-sponsorship' ) . '</p>';
        echo '<p><a href="' . esc_url( $shop_url ) . '" class="bss-button">' . esc_html__( 'Return to shop', 'battle-shield-sponsorship' ) . '</a></p>';
        echo '</div>';
        return (string) ob_get_clean();
    }
}
