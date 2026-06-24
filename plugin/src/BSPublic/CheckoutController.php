<?php

namespace BattleShieldSponsorship\BSPublic;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\ReservationService;
use BattleShieldSponsorship\Services\SponsorshipService;
use BattleShieldSponsorship\Services\StripeService;
use BattleShieldSponsorship\Services\ContactService;
use BattleShieldSponsorship\Services\UploadTokenService;
use BattleShieldSponsorship\Services\ShieldService;
use BattleShieldSponsorship\Services\CampaignService;
use BattleShieldSponsorship\Mail\Mailer;
use BattleShieldSponsorship\Mail\TemplateRenderer;

defined( 'ABSPATH' ) || exit;

class CheckoutController {

    private const NONCE_RESERVE  = 'bss_reserve_shield';
    private const NONCE_CHECKOUT = 'bss_checkout';

    public function register(): void {
        add_action( 'admin_post_nopriv_bss_reserve_shield', [ $this, 'handle_reserve' ] );
        add_action( 'admin_post_bss_reserve_shield', [ $this, 'handle_reserve' ] );
        add_action( 'admin_post_nopriv_bss_checkout', [ $this, 'handle_checkout' ] );
        add_action( 'admin_post_bss_checkout', [ $this, 'handle_checkout' ] );
    }

    public function handle_reserve(): void {
        $shield_id   = (int) ( $_POST['shield_id'] ?? 0 );
        $session_key = sanitize_key( wp_unslash( $_POST['session_key'] ?? '' ) );

        $nonce_key = '_wpnonce_' . $shield_id;
        if ( ! isset( $_POST[ $nonce_key ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ $nonce_key ] ), self::NONCE_RESERVE ) ) {
            wp_die( esc_html__( 'Security check failed.', 'battle-shield-sponsorship' ) );
        }

        if ( $shield_id > 0 && 32 === strlen( $session_key ) ) {
            $campaign = ( new CampaignService() )->get_active();
            if ( $campaign ) {
                $timeout = (int) ( $campaign->reservation_timeout_minutes ?? 30 );
                ( new ReservationService() )->reserve( $shield_id, $session_key, $timeout );
            }
        }

        wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
        exit;
    }

    public function handle_checkout(): void {
        RequestGuard::verify_public_nonce( self::NONCE_CHECKOUT );

        $session_key       = sanitize_key( wp_unslash( $_POST['session_key'] ?? '' ) );
        $contact_name      = sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) );
        $contact_email     = sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) );
        $display_name      = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
        $sponsor_text      = sanitize_textarea_field( wp_unslash( $_POST['sponsor_text'] ?? '' ) );
        $address_line1     = sanitize_text_field( wp_unslash( $_POST['address_line1'] ?? '' ) );
        $address_line2     = sanitize_text_field( wp_unslash( $_POST['address_line2'] ?? '' ) );
        $city              = sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) );
        $county            = sanitize_text_field( wp_unslash( $_POST['county'] ?? '' ) );
        $postcode          = sanitize_text_field( wp_unslash( $_POST['postcode'] ?? '' ) );
        $marketing_opt_in  = isset( $_POST['marketing_opt_in'] ) ? 1 : 0;
        $gift_aid_declared = isset( $_POST['gift_aid_declared'] ) ? 1 : 0;

        if ( 32 !== strlen( $session_key ) ) {
            wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
            exit;
        }

        $campaign = ( new CampaignService() )->get_active();
        if ( ! $campaign ) {
            wp_safe_redirect( home_url( '/' ) );
            exit;
        }

        $reservation_service = new ReservationService();
        $reservations        = $reservation_service->get_session_shields( $session_key );

        if ( empty( $reservations ) ) {
            wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
            exit;
        }

        $shield_service  = new ShieldService();
        $contact_service = new ContactService();

        $line_items  = [];
        $shield_data = [];
        $total       = 0.0;

        foreach ( $reservations as $r ) {
            $shield = $shield_service->get_by_id( (int) $r->shield_id );
            if ( ! $shield ) {
                continue;
            }
            $price = (float) $r->price;
            $total += $price;
            $line_items[] = [
                'name'     => (string) $shield->name,
                'amount'   => (int) round( $price * 100 ),
                'currency' => 'gbp',
                'quantity' => 1,
            ];
            $shield_data[] = [
                'shield_id'  => (int) $r->shield_id,
                'price_paid' => $price,
            ];
        }

        $contact_id = 0;
        if ( $contact_email ) {
            $existing = $contact_service->find_by_email( $contact_email );
            if ( $existing ) {
                $contact_id = (int) $existing->id;
                $contact_service->update( $contact_id, [
                    'contact_name'    => $contact_name ?: (string) $existing->contact_name,
                    'address_line1'   => $address_line1 ?: (string) ( $existing->address_line1 ?? '' ),
                    'address_line2'   => $address_line2 ?: (string) ( $existing->address_line2 ?? '' ),
                    'city'            => $city ?: (string) ( $existing->city ?? '' ),
                    'county'          => $county ?: (string) ( $existing->county ?? '' ),
                    'postcode'        => $postcode ?: (string) ( $existing->postcode ?? '' ),
                    'marketing_opt_in' => $marketing_opt_in,
                ] );
            } else {
                $contact_id = $contact_service->create( [
                    'contact_name'    => $contact_name ?: $contact_email,
                    'email'           => $contact_email,
                    'address_line1'   => $address_line1,
                    'address_line2'   => $address_line2,
                    'city'            => $city,
                    'county'          => $county,
                    'postcode'        => $postcode,
                    'marketing_opt_in' => $marketing_opt_in,
                ] );
            }
        }

        $sponsorship_service = new SponsorshipService();
        $sponsorship_id = $sponsorship_service->create_pending( [
            'campaign_id'      => (int) $campaign->id,
            'contact_id'       => $contact_id ?: null,
            'display_name'     => $display_name,
            'sponsor_text'     => $sponsor_text,
            'payment_method'   => 'stripe',
            'total_amount'     => $total,
            'gift_aid_declared' => $gift_aid_declared,
        ] );

        foreach ( $shield_data as $item ) {
            $sponsorship_service->add_item( $sponsorship_id, $item['shield_id'], $item['price_paid'] );
        }
        $reservation_service->attach_sponsorship( $session_key, $sponsorship_id );

        $stripe          = new StripeService();
        $customer_email  = $contact_email ?: '';
        $settings        = (array) get_option( 'bss_settings', [] );
        $success_slug    = (string) ( $settings['success_page_slug'] ?? 'shield-sponsorship-complete' );
        $cancel_slug     = (string) ( $settings['cancel_page_slug'] ?? 'shield-sponsorship-cancel' );

        $result = $stripe->create_checkout_session( $sponsorship_id, $line_items, $customer_email, [
            'success_url' => add_query_arg( 'sponsorship_id', $sponsorship_id, home_url( '/' . $success_slug . '/' ) ),
            'cancel_url'  => add_query_arg( 'sponsorship_id', $sponsorship_id, home_url( '/' . $cancel_slug . '/' ) ),
        ] );

        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( 'error', '1', wp_get_referer() ?: home_url( '/' ) ) );
            exit;
        }

        $sponsorship_service->mark_stripe_session( $sponsorship_id, $result['session_id'] );

        wp_redirect( $result['checkout_url'] );
        exit;
    }
}
