<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\SponsorshipService;
use BattleShieldSponsorship\Services\ContactService;
use BattleShieldSponsorship\Services\ShieldService;
use BattleShieldSponsorship\Services\UploadTokenService;
use BattleShieldSponsorship\Services\CampaignService;

defined( 'ABSPATH' ) || exit;

class SponsorshipViewPage {

    private const NONCE_ARTWORK  = 'bss_admin_update_artwork';

    public function __construct() {
        add_action( 'admin_post_bss_admin_update_artwork', [ $this, 'handle_update_artwork' ] );
    }

    public function render(): void {
        RequestGuard::require_capability( 'bss_manage_sponsorships' );

        $id                  = (int) ( $_GET['id'] ?? 0 );
        $sponsorship_service = new SponsorshipService();
        $sponsorship         = $id > 0 ? $sponsorship_service->get_by_id( $id ) : null;

        if ( ! $sponsorship ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Sponsorship not found.', 'battle-shield-sponsorship' ) . '</p></div>';
            return;
        }

        $contact_service = new ContactService();
        $shield_service  = new ShieldService();
        $contact         = $contact_service->get_by_id( (int) $sponsorship->contact_id );
        $campaign        = ( new CampaignService() )->get_by_id( (int) $sponsorship->campaign_id );
        $items           = $sponsorship_service->get_items( $id );
        $token_service   = new UploadTokenService();
        $token           = $token_service->get_token_for_sponsorship( $id );
        $edit_url        = $token ? $token_service->edit_url( $token ) : '';

        if ( isset( $_GET['artwork_saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Artwork updated.', 'battle-shield-sponsorship' ) . '</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1>' . sprintf( esc_html__( 'Sponsorship #%d', 'battle-shield-sponsorship' ), $id ) . '</h1>';
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=bss-sponsorships' ) ) . '">&larr; '
            . esc_html__( 'Back to Sponsorships', 'battle-shield-sponsorship' ) . '</a></p>';

        echo '<h2>' . esc_html__( 'Details', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<table class="form-table"><tbody>';
        $this->detail_row( __( 'Campaign', 'battle-shield-sponsorship' ), $campaign ? (string) $campaign->name : '—' );
        $this->detail_row( __( 'Sponsor display name', 'battle-shield-sponsorship' ), (string) $sponsorship->display_name );
        $this->detail_row( __( 'Contact name', 'battle-shield-sponsorship' ), $contact ? (string) $contact->contact_name : '—' );
        $this->detail_row( __( 'Email', 'battle-shield-sponsorship' ), $contact ? (string) $contact->email : '—' );
        $this->detail_row( __( 'Total amount', 'battle-shield-sponsorship' ), '£' . number_format( (float) $sponsorship->total_amount, 2 ) );
        $this->detail_row( __( 'Payment method', 'battle-shield-sponsorship' ), ucfirst( str_replace( '_', ' ', (string) $sponsorship->payment_method ) ) );
        $this->detail_row( __( 'Payment status', 'battle-shield-sponsorship' ), ucfirst( (string) $sponsorship->payment_status ) );
        $this->detail_row( __( 'Artwork status', 'battle-shield-sponsorship' ), ucfirst( (string) $sponsorship->artwork_status ) );
        $this->detail_row( __( 'Gift Aid declared', 'battle-shield-sponsorship' ), (int) $sponsorship->gift_aid_declared ? 'Yes' : 'No' );
        $this->detail_row( __( 'Created', 'battle-shield-sponsorship' ), date( 'd/m/Y H:i', strtotime( (string) $sponsorship->created_at ) ) );
        if ( $edit_url ) {
            echo '<tr><th>' . esc_html__( 'Sponsor edit link', 'battle-shield-sponsorship' ) . '</th>';
            echo '<td><a href="' . esc_url( $edit_url ) . '" target="_blank">' . esc_html( $edit_url ) . '</a></td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2>' . esc_html__( 'Shields', 'battle-shield-sponsorship' ) . '</h2>';
        if ( empty( $items ) ) {
            echo '<p>' . esc_html__( 'No items.', 'battle-shield-sponsorship' ) . '</p>';
        } else {
            echo '<table class="widefat striped" style="max-width:600px;">';
            echo '<thead><tr><th>' . esc_html__( 'Shield', 'battle-shield-sponsorship' ) . '</th><th>' . esc_html__( 'Price paid', 'battle-shield-sponsorship' ) . '</th><th>' . esc_html__( 'Status', 'battle-shield-sponsorship' ) . '</th></tr></thead><tbody>';
            foreach ( $items as $item ) {
                $shield = $shield_service->get_by_id( (int) $item->shield_id );
                echo '<tr>';
                echo '<td>' . esc_html( $shield ? (string) $shield->name : '#' . (int) $item->shield_id ) . '</td>';
                echo '<td>£' . esc_html( number_format( (float) $item->price_paid, 2 ) ) . '</td>';
                echo '<td>' . esc_html( ucfirst( str_replace( '_', ' ', (string) $item->status ) ) ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        if ( 'paid' === (string) $sponsorship->payment_status ) {
            echo '<h2>' . esc_html__( 'Artwork', 'battle-shield-sponsorship' ) . '</h2>';
            wp_enqueue_media();

            $logo_id  = (int) ( $sponsorship->logo_attachment_id ?? 0 );
            $logo_url = $logo_id > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="bss_admin_update_artwork" />';
            echo '<input type="hidden" name="sponsorship_id" value="' . $id . '" />';
            wp_nonce_field( self::NONCE_ARTWORK );

            echo '<table class="form-table">';
            echo '<tr><th>' . esc_html__( 'Sponsor display name', 'battle-shield-sponsorship' ) . '</th><td>';
            echo '<input type="text" name="display_name" class="regular-text" value="' . esc_attr( (string) $sponsorship->display_name ) . '" /></td></tr>';

            echo '<tr><th>' . esc_html__( 'Sponsor text', 'battle-shield-sponsorship' ) . '</th><td>';
            echo '<textarea name="sponsor_text" class="large-text" rows="3">' . esc_textarea( (string) ( $sponsorship->sponsor_text ?? '' ) ) . '</textarea></td></tr>';

            echo '<tr><th>' . esc_html__( 'Logo', 'battle-shield-sponsorship' ) . '</th><td>';
            echo '<div id="bss-logo-preview">' . ( $logo_url ? '<img src="' . esc_url( $logo_url ) . '" style="max-width:200px;" />' : '' ) . '</div>';
            echo '<input type="hidden" name="logo_attachment_id" id="bss-logo-id" value="' . esc_attr( (string) $logo_id ) . '" />';
            echo '<button type="button" class="button" id="bss-select-logo">' . esc_html__( 'Select logo', 'battle-shield-sponsorship' ) . '</button>';
            echo '</td></tr>';
            echo '</table>';

            submit_button( __( 'Update Artwork', 'battle-shield-sponsorship' ) );
            echo '</form>';

            if ( current_user_can( 'bss_process_refunds' ) ) {
                echo '<h2>' . esc_html__( 'Actions', 'battle-shield-sponsorship' ) . '</h2>';
                echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=bss-refunds&sponsorship_id=' . $id ) ) . '">'
                    . esc_html__( 'Process Refund', 'battle-shield-sponsorship' ) . '</a></p>';
            }
        }

        echo '</div>';
        ?>
        <script>
        jQuery(function($) {
            var frame;
            $('#bss-select-logo').on('click', function(e) {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({ title: 'Select Logo', button: { text: 'Use this logo' }, multiple: false });
                frame.on('select', function() {
                    var a = frame.state().get('selection').first().toJSON();
                    $('#bss-logo-id').val(a.id);
                    var url = a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url;
                    $('#bss-logo-preview').html('<img src="' + url + '" style="max-width:200px;" />');
                });
                frame.open();
            });
        });
        </script>
        <?php
    }

    public function handle_update_artwork(): void {
        RequestGuard::require_capability( 'bss_manage_sponsorships' );
        RequestGuard::verify_admin_nonce( self::NONCE_ARTWORK );

        $id = (int) ( $_POST['sponsorship_id'] ?? 0 );
        if ( $id > 0 ) {
            ( new SponsorshipService() )->update_artwork( $id, [
                'display_name'       => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
                'sponsor_text'       => sanitize_textarea_field( wp_unslash( $_POST['sponsor_text'] ?? '' ) ),
                'logo_attachment_id' => (int) ( $_POST['logo_attachment_id'] ?? 0 ) ?: null,
            ] );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'bss-sponsorship-view', 'id' => $id, 'artwork_saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private function detail_row( string $label, string $value ): void {
        echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
    }
}
