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

        // Show what's still outstanding so the sponsor knows what's needed.
        $outstanding = [];
        if ( empty( $sponsorship->display_name ) ) {
            $outstanding[] = __( 'Sponsor display name — required before your patch can be printed', 'battle-shield-sponsorship' );
        }
        if ( empty( $sponsorship->logo_attachment_id ) && empty( $sponsorship->logo_not_needed ) ) {
            $outstanding[] = __( 'Logo or image for the back of the shield', 'battle-shield-sponsorship' );
        }

        if ( $outstanding ) {
            echo '<div class="bss-notice bss-notice--warning">';
            echo '<p><strong>' . esc_html__( 'Still needed from you:', 'battle-shield-sponsorship' ) . '</strong></p>';
            echo '<ul>';
            foreach ( $outstanding as $item ) {
                echo '<li>' . esc_html( $item ) . '</li>';
            }
            echo '</ul></div>';
        } else {
            echo '<div class="bss-notice bss-notice--success"><p>' . esc_html__( 'All details received — thank you!', 'battle-shield-sponsorship' ) . '</p></div>';
        }

        if ( $campaign && $campaign->artwork_cutoff_date ) {
            echo '<p><strong>' . esc_html__( 'Artwork deadline:', 'battle-shield-sponsorship' ) . '</strong> '
                . esc_html( date( 'd/m/Y', strtotime( (string) $campaign->artwork_cutoff_date ) ) ) . '</p>';
        }

        echo '<form class="bss-sponsor-edit__form" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bss_sponsor_save" />';
        echo '<input type="hidden" name="token" value="' . esc_attr( $token ) . '" />';
        wp_nonce_field( self::NONCE_SAVE );

        echo '<div class="bss-form-row">';
        echo '<label for="bss_display_name">' . esc_html__( 'Display name (shown on shield)', 'battle-shield-sponsorship' ) . ' <span aria-hidden="true">*</span></label>';
        echo '<input type="text" name="display_name" id="bss_display_name" class="bss-input" value="' . esc_attr( (string) $sponsorship->display_name ) . '" required />';
        echo '</div>';

        echo '<div class="bss-form-row">';
        echo '<label for="bss_sponsor_text">' . esc_html__( 'Sponsor message / strapline (optional — shown on patch)', 'battle-shield-sponsorship' ) . '</label>';
        echo '<textarea name="sponsor_text" id="bss_sponsor_text" class="bss-input" rows="3">' . esc_textarea( (string) ( $sponsorship->sponsor_text ?? '' ) ) . '</textarea>';
        echo '</div>';

        echo '<div class="bss-form-row">';
        echo '<label for="bss_sponsor_url">' . esc_html__( 'Website URL (optional — shown on patch)', 'battle-shield-sponsorship' ) . '</label>';
        echo '<input type="text" name="sponsor_url" id="bss_sponsor_url" class="bss-input" value="' . esc_attr( (string) ( $sponsorship->sponsor_url ?? '' ) ) . '" placeholder="e.g. www.example.com" />';
        echo '</div>';

        echo '<div class="bss-form-row">';
        echo '<label for="bss_sponsor_phone">' . esc_html__( 'Phone number (optional — shown on patch)', 'battle-shield-sponsorship' ) . '</label>';
        echo '<input type="tel" name="sponsor_phone" id="bss_sponsor_phone" class="bss-input" value="' . esc_attr( (string) ( $sponsorship->sponsor_phone ?? '' ) ) . '" />';
        echo '</div>';

        echo '<div class="bss-form-row">';
        echo '<label>' . esc_html__( 'Logo (optional)', 'battle-shield-sponsorship' ) . '</label>';
        if ( $logo_url ) {
            echo '<img src="' . esc_url( $logo_url ) . '" style="max-width:200px;display:block;margin-bottom:8px;" />';
            echo '<p class="bss-hint">' . esc_html__( 'A logo is already uploaded. Choose a new file below to replace it.', 'battle-shield-sponsorship' ) . '</p>';
        }
        echo '<input type="hidden" name="logo_attachment_id" value="' . esc_attr( (string) $logo_id ) . '" />';
        echo '<input type="file" name="logo_file" id="bss_logo_file" class="bss-input" accept="image/jpeg,image/png,image/gif,image/svg+xml,image/webp" />';
        echo '<p class="bss-hint">' . esc_html__( 'Accepted: JPG, PNG, SVG, WebP. Recommended minimum 300×300px.', 'battle-shield-sponsorship' ) . '</p>';
        echo '</div>';

        echo '<div class="bss-form-row">';
        echo '<input type="hidden" name="logo_not_needed" value="0" />';
        echo '<label>';
        echo '<input type="checkbox" name="logo_not_needed" value="1" ' . checked( ! empty( $sponsorship->logo_not_needed ), true, false ) . ' /> ';
        echo esc_html__( 'I do not plan to upload a logo or image for the back of the shield', 'battle-shield-sponsorship' );
        echo '</label>';
        echo '</div>';

        echo '<div class="bss-form-row">';
        echo '<button type="submit" class="bss-button bss-button--primary">' . esc_html__( 'Save my details', 'battle-shield-sponsorship' ) . '</button>';
        echo '</div>';

        echo '</form>';
        echo '</div>';

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

        $logo_attachment_id = (int) ( $_POST['logo_attachment_id'] ?? 0 ) ?: null;
        $uploaded_id        = $this->upload_logo_file();
        if ( null !== $uploaded_id ) {
            $logo_attachment_id = $uploaded_id;
        }

        ( new SponsorshipService() )->update_artwork( $sponsorship_id, [
            'display_name'       => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
            'sponsor_text'       => sanitize_textarea_field( wp_unslash( $_POST['sponsor_text'] ?? '' ) ),
            'sponsor_url'        => sanitize_url( wp_unslash( $_POST['sponsor_url'] ?? '' ) ),
            'sponsor_phone'      => sanitize_text_field( wp_unslash( $_POST['sponsor_phone'] ?? '' ) ),
            'logo_attachment_id' => $logo_attachment_id,
            'logo_not_needed'    => (int) ( $_POST['logo_not_needed'] ?? 0 ),
        ] );

        $settings  = (array) get_option( 'bss_settings', [] );
        $edit_slug = (string) ( $settings['edit_page_slug'] ?? 'shield-sponsorship-edit' );
        wp_safe_redirect( add_query_arg( [ 'token' => $token, 'saved' => '1' ], home_url( '/' . $edit_slug . '/' ) ) );
        exit;
    }

    /**
     * Handle an uploaded logo file without using WP's media-library admin functions.
     * Works for non-authenticated (public) users. Returns the attachment ID on success,
     * null if no file was submitted or the file is invalid.
     */
    private function upload_logo_file(): ?int {
        $file = $_FILES['logo_file'] ?? null;

        if ( ! is_array( $file ) || empty( $file['name'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
            return null;
        }

        $tmp = (string) $file['tmp_name'];
        if ( ! is_uploaded_file( $tmp ) ) {
            return null;
        }

        // Validate MIME from actual file content (not the browser-supplied type).
        $mime    = function_exists( 'finfo_file' )
            ? (string) finfo_file( finfo_open( FILEINFO_MIME_TYPE ), $tmp )
            : (string) mime_content_type( $tmp );
        $allowed = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml' ];
        if ( ! in_array( $mime, $allowed, true ) ) {
            return null;
        }

        $upload_dir = wp_upload_dir();
        if ( ! empty( $upload_dir['error'] ) ) {
            return null;
        }

        $safe_name = sanitize_file_name( (string) $file['name'] );
        $filename  = wp_unique_filename( $upload_dir['path'], $safe_name );
        $dest      = $upload_dir['path'] . '/' . $filename;

        if ( ! move_uploaded_file( $tmp, $dest ) ) {
            return null;
        }

        // Create the WP attachment record (wp_insert_attachment has no capability check).
        $attachment_id = wp_insert_attachment( [
            'post_mime_type' => $mime,
            'post_title'     => $filename,
            'post_content'   => '',
            'post_status'    => 'inherit',
            'guid'           => $upload_dir['url'] . '/' . $filename,
        ], $dest );

        if ( is_wp_error( $attachment_id ) ) {
            return null;
        }

        // Generate image metadata (dimensions, thumbnails) for non-SVG images.
        if ( 'image/svg+xml' !== $mime ) {
            if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }
            wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $dest ) );
        }

        return (int) $attachment_id;
    }
}
