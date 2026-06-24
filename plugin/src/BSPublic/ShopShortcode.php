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
 * Session key stored in a cookie set before output via template_redirect.
 */
class ShopShortcode {

    private const COOKIE_NAME    = 'bss_session';
    private const NONCE_RESERVE  = 'bss_reserve_shield';
    private const NONCE_CHECKOUT = 'bss_checkout';

    private string $session_key = '';

    public function register(): void {
        add_shortcode( 'battle_shield_shop', [ $this, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'template_redirect', [ $this, 'prime_session' ] );
    }

    public function prime_session(): void {
        $settings  = (array) get_option( 'bss_settings', [] );
        $shop_slug = (string) ( $settings['shop_page_slug'] ?? 'shield-sponsorship' );
        if ( ! is_page( $shop_slug ) ) {
            return;
        }
        if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            $key = sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
            if ( 32 === strlen( $key ) ) {
                $this->session_key = $key;
                return;
            }
        }
        $key = wp_generate_password( 32, false );
        setcookie( self::COOKIE_NAME, $key, time() + ( 4 * HOUR_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        $this->session_key = $key;
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
        wp_enqueue_script( 'jquery' );
    }

    public function render(): string {
        $campaign_service = new CampaignService();
        $campaign         = $campaign_service->get_active();

        if ( ! $campaign ) {
            return '<p class="bss-notice">' . esc_html__( 'Shield sponsorships are not currently available.', 'battle-shield-sponsorship' ) . '</p>';
        }

        if ( $campaign_service->is_past_cutoff( (int) $campaign->id ) ) {
            return '<p class="bss-notice">' . esc_html__( 'The artwork deadline has passed. Sponsorships are now closed.', 'battle-shield-sponsorship' ) . '</p>';
        }

        $session_key         = $this->session_key ?: wp_generate_password( 32, false );
        $shield_service      = new ShieldService();
        $reservation_service = new ReservationService();

        $reserved_by_session = $reservation_service->get_session_shields( $session_key );
        $reserved_ids        = array_column( $reserved_by_session, 'shield_id' );
        $all_shields         = $shield_service->get_all( [ 'physical_state' => 'available' ] );

        ob_start();

        echo $this->modal_styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        echo '<div class="bss-shop">';

        $this->render_basket( $reserved_by_session, $shield_service, $campaign, $session_key );

        echo '<h2 class="bss-shop__heading">' . esc_html__( 'Available shields', 'battle-shield-sponsorship' ) . '</h2>';

        if ( empty( $all_shields ) ) {
            echo '<p class="bss-notice">' . esc_html__( 'All shields have been sponsored — thank you!', 'battle-shield-sponsorship' ) . '</p>';
        }

        $groups = [
            __( 'Royal Army', 'battle-shield-sponsorship' ) => array_filter( $all_shields, fn( $s ) => 'royals' === (string) $s->side ),
            __( 'Rebel Army', 'battle-shield-sponsorship' ) => array_filter( $all_shields, fn( $s ) => 'rebels' === (string) $s->side ),
            __( 'Other', 'battle-shield-sponsorship' )      => array_filter( $all_shields, fn( $s ) => 'other' === (string) $s->side ),
        ];

        foreach ( $groups as $group_label => $group ) {
            if ( empty( $group ) ) {
                continue;
            }
            echo '<h3 class="bss-shop__side-heading">' . esc_html( $group_label ) . '</h3>';
            echo '<div class="bss-shield-grid">';
            foreach ( $group as $shield ) {
                $this->render_shield_card( $shield, in_array( (int) $shield->id, array_map( 'intval', $reserved_ids ), true ), $session_key );
            }
            echo '</div>';
        }

        echo '</div>';

        echo $this->modal_script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

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

        // Required contact fields.
        echo '<h3 class="bss-form-section-heading">' . esc_html__( 'Your details', 'battle-shield-sponsorship' ) . '</h3>';

        echo '<div class="bss-form-row">';
        echo '<label for="bss_contact_name">' . esc_html__( 'Your full name', 'battle-shield-sponsorship' ) . ' <span aria-hidden="true">*</span></label>';
        echo '<input type="text" name="contact_name" id="bss_contact_name" class="bss-input" required />';
        echo '</div>';

        echo '<div class="bss-form-row">';
        echo '<label for="bss_contact_email">' . esc_html__( 'Your email address', 'battle-shield-sponsorship' ) . ' <span aria-hidden="true">*</span></label>';
        echo '<input type="email" name="contact_email" id="bss_contact_email" class="bss-input" required />';
        echo '<p class="bss-hint">' . esc_html__( 'We will send your artwork upload link to this address.', 'battle-shield-sponsorship' ) . '</p>';
        echo '</div>';

        // Optional UK address.
        echo '<div class="bss-form-row">';
        echo '<label for="bss_address_line1">' . esc_html__( 'Address line 1', 'battle-shield-sponsorship' ) . '</label>';
        echo '<input type="text" name="address_line1" id="bss_address_line1" class="bss-input" autocomplete="address-line1" />';
        echo '</div>';

        echo '<div class="bss-form-row">';
        echo '<label for="bss_address_line2">' . esc_html__( 'Address line 2', 'battle-shield-sponsorship' ) . '</label>';
        echo '<input type="text" name="address_line2" id="bss_address_line2" class="bss-input" autocomplete="address-line2" />';
        echo '</div>';

        echo '<div class="bss-form-row bss-form-row--half">';
        echo '<div>';
        echo '<label for="bss_city">' . esc_html__( 'Town / City', 'battle-shield-sponsorship' ) . '</label>';
        echo '<input type="text" name="city" id="bss_city" class="bss-input" autocomplete="address-level2" />';
        echo '</div>';
        echo '<div>';
        echo '<label for="bss_county">' . esc_html__( 'County', 'battle-shield-sponsorship' ) . '</label>';
        echo '<input type="text" name="county" id="bss_county" class="bss-input" />';
        echo '</div>';
        echo '</div>';

        echo '<div class="bss-form-row bss-form-row--half">';
        echo '<div>';
        echo '<label for="bss_postcode">' . esc_html__( 'Postcode', 'battle-shield-sponsorship' ) . '</label>';
        echo '<input type="text" name="postcode" id="bss_postcode" class="bss-input" autocomplete="postal-code" />';
        echo '</div>';
        echo '</div>';

        // Optional sponsor details — can be completed later.
        echo '<h3 class="bss-form-section-heading">' . esc_html__( 'Patch details', 'battle-shield-sponsorship' ) . '</h3>';
        echo '<p class="bss-hint">' . esc_html__( 'These can be completed later using the link we will email to you after payment.', 'battle-shield-sponsorship' ) . '</p>';

        echo '<div class="bss-form-row">';
        echo '<label for="bss_display_name">' . esc_html__( 'Sponsor display name', 'battle-shield-sponsorship' ) . '</label>';
        echo '<input type="text" name="display_name" id="bss_display_name" class="bss-input" />';
        echo '<p class="bss-hint">' . esc_html__( 'How you would like your name to appear on the shield patch.', 'battle-shield-sponsorship' ) . '</p>';
        echo '</div>';

        echo '<div class="bss-form-row">';
        echo '<label for="bss_sponsor_text">' . esc_html__( 'Sponsor message (optional)', 'battle-shield-sponsorship' ) . '</label>';
        echo '<textarea name="sponsor_text" id="bss_sponsor_text" class="bss-input" rows="3"></textarea>';
        echo '<p class="bss-hint">' . esc_html__( 'A short message or description to appear on your patch.', 'battle-shield-sponsorship' ) . '</p>';
        echo '</div>';

        echo '<p class="bss-hint">' . esc_html__( 'You can also upload your logo after payment using the link in your confirmation email.', 'battle-shield-sponsorship' ) . '</p>';

        // Gift Aid.
        if ( (int) $campaign->gift_aid_enabled ) {
            echo '<div class="bss-form-row">';
            echo '<label class="bss-gift-aid">';
            echo '<input type="checkbox" name="gift_aid_declared" value="1" /> ';
            esc_html_e( 'I am a UK taxpayer and I would like Gift Aid to be claimed on my sponsorship.', 'battle-shield-sponsorship' );
            echo '</label>';
            echo '</div>';
        }

        // Marketing opt-in.
        echo '<div class="bss-form-row">';
        echo '<label class="bss-checkbox-label">';
        echo '<input type="checkbox" name="marketing_opt_in" value="1" /> ';
        esc_html_e( 'I am happy to receive future communications from the Battle of Evesham about sponsorship opportunities and events.', 'battle-shield-sponsorship' );
        echo '</label>';
        echo '</div>';

        echo '<button type="submit" class="bss-button bss-button--primary">' . esc_html__( 'Proceed to payment', 'battle-shield-sponsorship' ) . '</button>';
        echo '</form>';
        echo '</div>';
    }

    private function render_shield_card( object $shield, bool $is_reserved_by_me, string $session_key ): void {
        $shield_id  = (int) $shield->id;
        $image_id   = (int) ( $shield->image_id ?? 0 );
        $desc       = trim( (string) ( $shield->description ?? '' ) );
        $words      = $desc ? preg_split( '/\s+/u', $desc, -1, PREG_SPLIT_NO_EMPTY ) : [];
        $short_desc = count( $words ) > 50 ? implode( ' ', array_slice( $words, 0, 50 ) ) . '…' : $desc;
        $has_more   = count( $words ) > 50;

        $modal_id = 'bss-modal-' . $shield_id;

        echo '<div class="bss-shield-card' . ( $is_reserved_by_me ? ' bss-shield-card--selected' : '' ) . '">';

        // Clicking the image or name opens the full detail modal.
        $open_attr = ' data-open-modal="' . esc_attr( $modal_id ) . '"';

        if ( $image_id > 0 ) {
            echo '<div class="bss-shield-card__image-wrap"' . $open_attr . '>';
            echo wp_get_attachment_image( $image_id, 'medium', false, [ 'class' => 'bss-shield-card__image' ] );
            echo '</div>';
        }

        echo '<h4 class="bss-shield-card__name"' . $open_attr . '>' . esc_html( (string) $shield->name ) . '</h4>';

        if ( $short_desc ) {
            echo '<p class="bss-shield-card__description">' . esc_html( $short_desc );
            if ( $has_more ) {
                echo ' <a href="#' . esc_attr( $modal_id ) . '" class="bss-read-more" data-open-modal="' . esc_attr( $modal_id ) . '">&hellip; '
                    . esc_html__( 'read more', 'battle-shield-sponsorship' ) . '</a>';
            }
            echo '</p>';
        }

        echo '<p class="bss-shield-card__price">£' . esc_html( number_format( (float) $shield->suggested_price, 2 ) ) . '</p>';

        if ( $is_reserved_by_me ) {
            echo '<p class="bss-shield-card__reserved">' . esc_html__( '✓ In your selection', 'battle-shield-sponsorship' ) . '</p>';
        } else {
            echo '<form class="bss-reserve-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="bss_reserve_shield" />';
            echo '<input type="hidden" name="shield_id" value="' . $shield_id . '" />';
            echo '<input type="hidden" name="session_key" value="' . esc_attr( $session_key ) . '" />';
            wp_nonce_field( self::NONCE_RESERVE, '_wpnonce_' . $shield_id );
            echo '<button type="submit" class="bss-button">' . esc_html__( 'Add to selection', 'battle-shield-sponsorship' ) . '</button>';
            echo '</form>';
        }

        // Full detail modal.
        $birth = trim( (string) ( $shield->birth_date ?? '' ) );
        $death = trim( (string) ( $shield->death_date ?? '' ) );

        echo '<div id="' . esc_attr( $modal_id ) . '" class="bss-shield-modal" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr( $modal_id ) . '-title" style="display:none;">';
        echo '<div class="bss-modal-overlay">';
        echo '<div class="bss-modal-content">';
        echo '<button class="bss-modal-close" type="button" aria-label="' . esc_attr__( 'Close', 'battle-shield-sponsorship' ) . '">&times;</button>';

        if ( $image_id > 0 ) {
            echo wp_get_attachment_image( $image_id, 'large', false, [ 'class' => 'bss-modal-image' ] );
        }

        echo '<h2 class="bss-modal-name" id="' . esc_attr( $modal_id ) . '-title">' . esc_html( (string) $shield->name ) . '</h2>';

        if ( $birth || $death ) {
            $date_parts = [];
            if ( $birth ) {
                $date_parts[] = esc_html__( 'Born:', 'battle-shield-sponsorship' ) . ' ' . esc_html( $birth );
            }
            if ( $death ) {
                $date_parts[] = esc_html__( 'Died:', 'battle-shield-sponsorship' ) . ' ' . esc_html( $death );
            }
            echo '<p class="bss-modal-dates">' . implode( ' &nbsp;|&nbsp; ', $date_parts ) . '</p>';
        }

        if ( $desc ) {
            echo '<p class="bss-modal-bio">' . nl2br( esc_html( $desc ) ) . '</p>';
        }

        echo '<p class="bss-modal-price"><strong>£' . esc_html( number_format( (float) $shield->suggested_price, 2 ) ) . '</strong></p>';

        if ( $is_reserved_by_me ) {
            echo '<p class="bss-shield-card__reserved">' . esc_html__( '✓ Already in your selection', 'battle-shield-sponsorship' ) . '</p>';
        } else {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="bss_reserve_shield" />';
            echo '<input type="hidden" name="shield_id" value="' . $shield_id . '" />';
            echo '<input type="hidden" name="session_key" value="' . esc_attr( $session_key ) . '" />';
            // Avoid duplicate `id` from wp_nonce_field by outputting the input manually.
            echo '<input type="hidden" name="_wpnonce_' . $shield_id . '" value="' . esc_attr( wp_create_nonce( self::NONCE_RESERVE ) ) . '" />';
            echo '<button type="submit" class="bss-button bss-button--primary">' . esc_html__( 'Add to selection', 'battle-shield-sponsorship' ) . '</button>';
            echo '</form>';
        }

        echo '</div>'; // .bss-modal-content
        echo '</div>'; // .bss-modal-overlay
        echo '</div>'; // .bss-shield-modal

        echo '</div>'; // .bss-shield-card
    }

    private function modal_styles(): string {
        return '<style>
.bss-shield-card__image-wrap, .bss-shield-card__name { cursor: pointer; }
.bss-shield-card__name:hover { text-decoration: underline; }
.bss-read-more { white-space: nowrap; }
.bss-shield-modal { display: none; }
.bss-shield-modal.is-open { display: block; }
.bss-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.72);
    z-index: 99999;
    overflow-y: auto;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 32px 16px;
}
.bss-modal-content {
    background: #fff;
    border-radius: 8px;
    padding: 32px 28px;
    max-width: 680px;
    width: 100%;
    position: relative;
    margin: auto;
}
.bss-modal-close {
    position: absolute; top: 14px; right: 18px;
    background: none; border: none;
    font-size: 30px; line-height: 1;
    cursor: pointer; color: #555;
    padding: 0;
}
.bss-modal-close:hover { color: #000; }
.bss-modal-image { max-width: 100%; height: auto; display: block; margin: 0 auto 20px; border-radius: 4px; }
.bss-modal-name { font-size: 1.6em; margin: 0 0 6px; }
.bss-modal-dates { color: #666; font-size: .95em; margin: 0 0 14px; }
.bss-modal-bio { line-height: 1.7; margin: 0 0 20px; }
.bss-modal-price { font-size: 1.1em; margin: 0 0 16px; }
body.bss-modal-open { overflow: hidden; }
.bss-form-section-heading { margin: 24px 0 8px; font-size: 1.1em; border-bottom: 1px solid #e0e0e0; padding-bottom: 6px; }
.bss-form-row--half { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
</style>';
    }

    private function modal_script(): string {
        return "<script>
(function($){
    function openModal(id) {
        var \$m = $('#' + id);
        if (!\$m.length) return;
        \$m.addClass('is-open');
        \$('body').addClass('bss-modal-open');
        \$m.find('.bss-modal-close').trigger('focus');
    }
    function closeAll() {
        \$('.bss-shield-modal').removeClass('is-open');
        \$('body').removeClass('bss-modal-open');
    }
    $(document).on('click', '[data-open-modal]', function(e) {
        e.preventDefault();
        openModal($(this).data('open-modal'));
    });
    $(document).on('click', '.bss-read-more', function(e) {
        e.preventDefault();
        openModal($(this).data('open-modal'));
    });
    $(document).on('click', '.bss-modal-close', function() { closeAll(); });
    $(document).on('click', '.bss-modal-overlay', function(e) {
        if (\$(e.target).hasClass('bss-modal-overlay')) closeAll();
    });
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') closeAll();
    });
}(jQuery));
</script>";
    }
}
