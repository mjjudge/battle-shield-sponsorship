<?php

namespace BattleShieldSponsorship\BSPublic;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\UploadTokenService;
use BattleShieldSponsorship\Services\SponsorshipService;
use BattleShieldSponsorship\Services\ShieldService;
use BattleShieldSponsorship\Services\CampaignService;
use BattleShieldSponsorship\Mail\Mailer;
use BattleShieldSponsorship\Mail\TemplateRenderer;

defined( 'ABSPATH' ) || exit;

/**
 * Sponsor-facing artwork upload page.
 *
 * URL: /{edit_page_slug}/?token=<token>
 * Shortcode: [battle_shield_edit]
 */
class EditTokenPage {

    private const NONCE_SAVE = 'bss_sponsor_save';

    public function register(): void {
        add_shortcode( 'battle_shield_edit', [ $this, 'render' ] );
        add_action( 'admin_post_nopriv_bss_sponsor_save', [ $this, 'handle_save' ] );
        add_action( 'admin_post_bss_sponsor_save', [ $this, 'handle_save' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets(): void {
        if ( ! is_page() ) {
            return;
        }
        $settings  = (array) get_option( 'bss_settings', [] );
        $edit_slug = (string) ( $settings['edit_page_slug'] ?? 'shield-sponsorship-edit' );
        if ( ! is_page( $edit_slug ) ) {
            return;
        }
        wp_enqueue_script( 'jquery' );
        wp_enqueue_media();
        wp_enqueue_style( 'bss-sponsor-edit', BSS_PLUGIN_URL . 'assets/css/sponsor-edit.css', [], BSS_VERSION );
    }

    public function render(): string {
        $token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );

        if ( empty( $token ) ) {
            return '<p class="bss-notice">' . esc_html__( 'Invalid or missing link. Please use the link from your confirmation email.', 'battle-shield-sponsorship' ) . '</p>';
        }

        $token_service = new UploadTokenService();
        $sponsorship_id = $token_service->validate( $token );

        if ( ! $sponsorship_id ) {
            return '<p class="bss-notice">' . esc_html__( 'This link has expired or is invalid. Please contact us for help.', 'battle-shield-sponsorship' ) . '</p>';
        }

        $sponsorship_service = new SponsorshipService();
        $sponsorship         = $sponsorship_service->get_by_id( $sponsorship_id );

        if ( ! $sponsorship || 'paid' !== (string) $sponsorship->payment_status ) {
            return '<p class="bss-notice">' . esc_html__( 'Your payment has not been confirmed yet. Please wait a few moments and refresh.', 'battle-shield-sponsorship' ) . '</p>';
        }

        $items   = $sponsorship_service->get_items( $sponsorship_id );
        $shields = [];
        $shield_service = new ShieldService();
        foreach ( $items as $item ) {
            $shield = $shield_service->get_by_id( (int) $item->shield_id );
            if ( $shield ) {
                $shields[] = (string) $shield->name;
            }
        }

        $campaign = ( new CampaignService() )->get_by_id( (int) $sponsorship->campaign_id );

        $saved     = isset( $_GET['saved'] );
        $logo_id   = (int) ( $sponsorship->logo_attachment_id ?? 0 );
        $logo_url  = $logo_id > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

        ob_start();

        if ( $saved ) {
            echo '<div class="bss-notice bss-notice--success">' . esc_html__( 'Your details have been saved. Thank you!', 'battle-shield-sponsorship' ) . '</div>';
        }

        echo '<div class="bss-sponsor-edit">';
        echo '<h2>' . esc_html__( 'Your Shield Sponsorship', 'battle-shield-sponsorship' ) . '</h2>';

        if ( $campaign ) {
            echo '<p>' . sprintf( esc_html__( 'Campaign: %s', 'battle-shield-sponsorship' ), esc_html( (string) $campaign->name ) ) . '</p>';
        }

        if ( ! empty( $shields ) ) {
            echo '<p>' . esc_html__( 'Shield(s):', 'battle-shield-sponsorship' ) . ' ' . esc_html( implode( ', ', $shields ) ) . '</p>';
        }

        echo '<p>' . esc_html__( 'Please provide your display name, any sponsor message, and optionally a logo to appear on your shield.', 'battle-shield-sponsorship' ) . '</p>';

        if ( $campaign && $campaign->artwork_cutoff_date ) {
            echo '<p><strong>' . esc_html__( 'Artwork deadline:', 'battle-shield-sponsorship' ) . '</strong> '
                . esc_html( date( 'd/m/Y', strtotime( (string) $campaign->artwork_cutoff_date ) ) ) . '</p>';
        }

        echo '<form class="bss-sponsor-edit__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bss_sponsor_save" />';
        echo '<input type="hidden" name="token" value="' . esc_attr( $token ) . '" />';
        wp_nonce_field( self::NONCE_SAVE );

        echo '<div class="bss-form-row">';
        echo '<label for="bss_display_name">' . esc_html__( 'Display name (shown on shield)', 'battle-shield-sponsorship' ) . '</label>';
        echo '<input type="text" name="display_name" id="bss_display_name" class="bss-input" value="' . esc_attr( (string) $sponsorship->display_name ) . '" required />';
        echo '</div>';

        echo '<div class="bss-form-row">';
        echo '<label for="bss_sponsor_text">' . esc_html__( 'Sponsor message (optional)', 'battle-shield-sponsorship' ) . '</label>';
        echo '<textarea name="sponsor_text" id="bss_sponsor_text" class="bss-input" rows="3">' . esc_textarea( (string) ( $sponsorship->sponsor_text ?? '' ) ) . '</textarea>';
        echo '</div>';

        echo '<div class="bss-form-row">';
        echo '<label>' . esc_html__( 'Logo (optional)', 'battle-shield-sponsorship' ) . '</label>';
        echo '<div id="bss-logo-preview">';
        if ( $logo_url ) {
            echo '<img src="' . esc_url( $logo_url ) . '" style="max-width:200px;display:block;margin-bottom:8px;" />';
        }
        echo '</div>';
        echo '<input type="hidden" name="logo_attachment_id" id="bss-logo-id" value="' . esc_attr( (string) $logo_id ) . '" />';
        echo '<button type="button" class="bss-button bss-button--secondary" id="bss-select-logo">' . esc_html__( 'Choose logo image', 'battle-shield-sponsorship' ) . '</button>';
        echo '<p class="bss-hint">' . esc_html__( 'Accepted: JPG, PNG, SVG. Recommended minimum 300×300px.', 'battle-shield-sponsorship' ) . '</p>';
        echo '</div>';

        echo '<div class="bss-form-row">';
        echo '<button type="submit" class="bss-button bss-button--primary">' . esc_html__( 'Save my details', 'battle-shield-sponsorship' ) . '</button>';
        echo '</div>';

        echo '</form>';
        echo '</div>';

        ?>
        <script>
        jQuery(function($) {
            var frame;
            $('#bss-select-logo').on('click', function(e) {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({ title: '<?php echo esc_js( __( 'Choose Your Logo', 'battle-shield-sponsorship' ) ); ?>', button: { text: '<?php echo esc_js( __( 'Use this logo', 'battle-shield-sponsorship' ) ); ?>' }, multiple: false });
                frame.on('select', function() {
                    var a = frame.state().get('selection').first().toJSON();
                    $('#bss-logo-id').val(a.id);
                    var url = a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url;
                    $('#bss-logo-preview').html('<img src="' + url + '" style="max-width:200px;display:block;margin-bottom:8px;" />');
                });
                frame.open();
            });
        });
        </script>
        <?php

        return (string) ob_get_clean();
    }

    public function handle_save(): void {
        $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
        RequestGuard::verify_public_nonce( self::NONCE_SAVE );

        $token_service  = new UploadTokenService();
        $sponsorship_id = $token_service->validate( $token );

        if ( ! $sponsorship_id ) {
            wp_die( esc_html__( 'Invalid or expired token.', 'battle-shield-sponsorship' ) );
        }

        ( new SponsorshipService() )->update_artwork( $sponsorship_id, [
            'display_name'       => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
            'sponsor_text'       => sanitize_textarea_field( wp_unslash( $_POST['sponsor_text'] ?? '' ) ),
            'logo_attachment_id' => (int) ( $_POST['logo_attachment_id'] ?? 0 ) ?: null,
        ] );

        $settings  = (array) get_option( 'bss_settings', [] );
        $edit_slug = (string) ( $settings['edit_page_slug'] ?? 'shield-sponsorship-edit' );
        wp_safe_redirect( add_query_arg( [ 'token' => $token, 'saved' => '1' ], home_url( '/' . $edit_slug . '/' ) ) );
        exit;
    }
}
