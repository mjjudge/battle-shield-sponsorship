<?php

namespace BattleShieldSponsorship\BSPublic;

use BattleShieldSponsorship\Services\CampaignService;
use BattleShieldSponsorship\Services\ShieldService;
use BattleShieldSponsorship\Services\ReservationService;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the public shield shop.
 *
 * Shortcode: [battle_shield_shop]
 *
 * Session key stored in a secure cookie (no PHP session extension needed).
 */
class ShopShortcode {

    private const COOKIE_NAME    = 'bss_session';
    private const NONCE_RESERVE  = 'bss_reserve_shield';
    private const NONCE_CHECKOUT = 'bss_checkout';

    public function register(): void {
        add_shortcode( 'battle_shield_shop', [ $this, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets(): void {
        if ( ! is_page() ) {
            return;
        }
        $settings  = (array) get_option( 'bss_settings', [] );
        $shop_slug = (string) ( $settings['shop_page_slug'] ?? 'shield-sponsorship' );
        if ( ! is_page( $shop_slug ) ) {
            return;
        }
        wp_enqueue_style( 'bss-shop', BSS_PLUGIN_URL . 'assets/css/shop.css', [], BSS_VERSION );
    }

    public function render(): string {
        $campaign_service = new CampaignService();
        $campaign         = $campaign_service->get_active();

        if ( ! $campaign ) {
            return '<p class="bss-notice">' . esc_html__( 'Shield sponsorships are not currently available.', 'battle-shield-sponsorship' ) . '</p>';
        }

        if ( $campaign_service->is_past_cutoff( $campaign ) ) {
            return '<p class="bss-notice">' . esc_html__( 'The artwork deadline has passed. Sponsorships are now closed.', 'battle-shield-sponsorship' ) . '</p>';
        }

        $session_key         = $this->get_or_create_session_key();
        $shield_service      = new ShieldService();
        $reservation_service = new ReservationService();

        $reserved_by_session = $reservation_service->get_session_shields( $session_key );
        $reserved_ids        = array_column( $reserved_by_session, 'shield_id' );
        $all_shields         = $shield_service->get_all( [ 'physical_state' => 'available' ] );

        ob_start();
        echo '<div class="bss-shop">';

        $this->render_basket( $reserved_by_session, $shield_service, $campaign, $session_key );

        echo '<h2 class="bss-shop__heading">' . esc_html__( 'Available shields', 'battle-shield-sponsorship' ) . '</h2>';

        if ( empty( $all_shields ) ) {
            echo '<p class="bss-notice">' . esc_html__( 'All shields have been sponsored — thank you!', 'battle-shield-sponsorship' ) . '</p>';
        }

        $groups = [
            __( 'Baron', 'battle-shield-sponsorship' )     => array_filter( $all_shields, fn( $s ) => 'baron' === (string) $s->side ),
            __( 'Royalist', 'battle-shield-sponsorship' )  => array_filter( $all_shields, fn( $s ) => 'royalist' === (string) $s->side ),
            __( 'Other', 'battle-shield-sponsorship' )     => array_filter( $all_shields, fn( $s ) => 'other' === (string) $s->side ),
        ];

        foreach ( $groups as $group_label => $group ) {
            if ( empty( $group ) ) {
                continue;
            }
            echo '<h3 class="bss-shop__side-heading">' . esc_html( $group_label ) . '</h3>';
            echo '<div class="bss-shield-grid">';
            foreach ( $group as $shield ) {
                $this->render_shield_card( $shield, in_array( (int) $shield->id, $reserved_ids, true ), $session_key );
            }
            echo '</div>';
        }

        echo '</div>';
        return (string) ob_get_clean();
    }

    private function render_basket( array $reserved, ShieldService $shield_service, object $campaign, string $session_key ): void {
        if ( empty( $reserved ) ) {
            return;
        }

        $total = array_reduce( $reserved, fn( $carry, $r ) => $carry + (float) $r->price, 0.0 );

        echo '<div class="bss-basket">';
        echo '<h2 class="bss-basket__heading">' . esc_html__( 'Your selection', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<ul class="bss-basket__list">';
        foreach ( $reserved as $r ) {
            $shield = $shield_service->get_by_id( (int) $r->shield_id );
            echo '<li>' . esc_html( $shield ? (string) $shield->name : '#' . (int) $r->shield_id )
                . ' — £' . esc_html( number_format( (float) $r->price, 2 ) ) . '</li>';
        }
        echo '</ul>';
        echo '<p class="bss-basket__total"><strong>' . esc_html__( 'Total:', 'battle-shield-sponsorship' ) . ' £' . esc_html( number_format( $total, 2 ) ) . '</strong></p>';

        echo '<form class="bss-checkout-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bss_checkout" />';
        echo '<input type="hidden" name="session_key" value="' . esc_attr( $session_key ) . '" />';
        wp_nonce_field( self::NONCE_CHECKOUT );

        if ( (int) $campaign->gift_aid_enabled ) {
            echo '<label class="bss-gift-aid">';
            echo '<input type="checkbox" name="gift_aid_declared" value="1" /> ';
            esc_html_e( 'I am a UK taxpayer and I would like Gift Aid to be claimed on my sponsorship.', 'battle-shield-sponsorship' );
            echo '</label>';
        }

        echo '<button type="submit" class="bss-button bss-button--primary">' . esc_html__( 'Proceed to payment', 'battle-shield-sponsorship' ) . '</button>';
        echo '</form>';
        echo '</div>';
    }

    private function render_shield_card( object $shield, bool $is_reserved_by_me, string $session_key ): void {
        $shield_id  = (int) $shield->id;
        $image_id   = (int) ( $shield->image_id ?? 0 );

        echo '<div class="bss-shield-card' . ( $is_reserved_by_me ? ' bss-shield-card--selected' : '' ) . '">';

        if ( $image_id > 0 ) {
            echo wp_get_attachment_image( $image_id, 'medium', false, [ 'class' => 'bss-shield-card__image' ] );
        }

        echo '<h4 class="bss-shield-card__name">' . esc_html( (string) $shield->name ) . '</h4>';

        if ( $shield->description ) {
            echo '<p class="bss-shield-card__description">' . esc_html( (string) $shield->description ) . '</p>';
        }

        echo '<p class="bss-shield-card__price">£' . esc_html( number_format( (float) $shield->suggested_price, 2 ) ) . '</p>';

        if ( ! $is_reserved_by_me ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="bss_reserve_shield" />';
            echo '<input type="hidden" name="shield_id" value="' . $shield_id . '" />';
            echo '<input type="hidden" name="session_key" value="' . esc_attr( $session_key ) . '" />';
            wp_nonce_field( self::NONCE_RESERVE, '_wpnonce_' . $shield_id );
            echo '<button type="submit" class="bss-button">' . esc_html__( 'Sponsor this shield', 'battle-shield-sponsorship' ) . '</button>';
            echo '</form>';
        } else {
            echo '<p class="bss-shield-card__reserved">' . esc_html__( 'In your selection', 'battle-shield-sponsorship' ) . '</p>';
        }

        echo '</div>';
    }

    private function get_or_create_session_key(): string {
        if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            $key = sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
            if ( 32 === strlen( $key ) ) {
                return $key;
            }
        }
        $key = wp_generate_password( 32, false );
        setcookie( self::COOKIE_NAME, $key, time() + ( 4 * HOUR_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        return $key;
    }
}
