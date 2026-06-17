<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\CampaignService;
use BattleShieldSponsorship\Services\SponsorshipService;
use BattleShieldSponsorship\Services\PatchGenerationService;

defined( 'ABSPATH' ) || exit;

class PatchGeneratorPage {

    private const NONCE_GENERATE = 'bss_generate_patches';

    public function __construct() {
        add_action( 'admin_post_bss_generate_patches', [ $this, 'handle_generate' ] );
    }

    public function render(): void {
        RequestGuard::require_capability( 'bss_generate_patches' );

        $campaign_service    = new CampaignService();
        $sponsorship_service = new SponsorshipService();

        $active_campaign = $campaign_service->get_active();
        $campaigns       = $campaign_service->get_all();

        $selected_campaign_id = (int) ( $_GET['campaign_id'] ?? ( $active_campaign ? (int) $active_campaign->id : 0 ) );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Patch Generator', 'battle-shield-sponsorship' ) . '</h1>';

        echo '<form method="get" style="margin-bottom:16px;">';
        echo '<input type="hidden" name="page" value="bss-patches" />';
        echo '<select name="campaign_id" style="margin-right:8px;">';
        foreach ( $campaigns as $campaign ) {
            echo '<option value="' . (int) $campaign->id . '" ' . selected( $selected_campaign_id, (int) $campaign->id, false ) . '>'
                . esc_html( (string) $campaign->name ) . '</option>';
        }
        echo '</select>';
        submit_button( __( 'Load', 'battle-shield-sponsorship' ), 'secondary', 'submit', false );
        echo '</form>';

        if ( $selected_campaign_id > 0 ) {
            $paid_sponsorships = $sponsorship_service->get_all( [
                'campaign_id'    => $selected_campaign_id,
                'payment_status' => 'paid',
            ] );

            $complete   = array_filter( $paid_sponsorships, fn( $s ) => 'complete' === (string) $s->artwork_status );
            $incomplete = array_filter( $paid_sponsorships, fn( $s ) => 'incomplete' === (string) $s->artwork_status );

            echo '<p>';
            printf(
                esc_html__( '%1$d paid sponsorships — %2$d with complete artwork, %3$d still missing artwork.', 'battle-shield-sponsorship' ),
                count( $paid_sponsorships ),
                count( $complete ),
                count( $incomplete )
            );
            echo '</p>';

            if ( ! empty( $incomplete ) ) {
                echo '<div class="notice notice-warning inline"><p>';
                esc_html_e( 'Some sponsorships are missing artwork. Generated patches will use placeholder text only.', 'battle-shield-sponsorship' );
                echo '</p></div>';
            }

            if ( ! empty( $paid_sponsorships ) ) {
                echo '<p><strong>' . esc_html__( 'Batch download', 'battle-shield-sponsorship' ) . '</strong></p>';

                foreach ( [
                    [ 'label' => __( 'Download all patches (PDF)', 'battle-shield-sponsorship' ), 'scope' => 'all',      'format' => 'pdf' ],
                    [ 'label' => __( 'Download complete artwork only (PDF)', 'battle-shield-sponsorship' ), 'scope' => 'complete', 'format' => 'pdf' ],
                    [ 'label' => __( 'Download all patches (ZIP of individual PDFs)', 'battle-shield-sponsorship' ), 'scope' => 'all',      'format' => 'zip' ],
                    [ 'label' => __( 'Download complete artwork only (ZIP)', 'battle-shield-sponsorship' ), 'scope' => 'complete', 'format' => 'zip' ],
                ] as $btn ) {
                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;margin-right:8px;margin-bottom:8px;">';
                    echo '<input type="hidden" name="action" value="bss_generate_patches" />';
                    echo '<input type="hidden" name="campaign_id" value="' . $selected_campaign_id . '" />';
                    echo '<input type="hidden" name="scope" value="' . esc_attr( $btn['scope'] ) . '" />';
                    echo '<input type="hidden" name="format" value="' . esc_attr( $btn['format'] ) . '" />';
                    wp_nonce_field( self::NONCE_GENERATE );
                    echo '<button type="submit" class="button">' . esc_html( $btn['label'] ) . '</button>';
                    echo '</form>';
                }
            } else {
                echo '<p>' . esc_html__( 'No paid sponsorships to generate patches for.', 'battle-shield-sponsorship' ) . '</p>';
            }

            echo '<h2>' . esc_html__( 'Individual patches', 'battle-shield-sponsorship' ) . '</h2>';
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Sponsor', 'battle-shield-sponsorship' ) . '</th>';
            echo '<th>' . esc_html__( 'Artwork', 'battle-shield-sponsorship' ) . '</th>';
            echo '<th>' . esc_html__( 'Action', 'battle-shield-sponsorship' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $paid_sponsorships as $s ) {
                echo '<tr>';
                echo '<td>' . esc_html( (string) $s->display_name ) . '</td>';
                $complete_cell = 'complete' === (string) $s->artwork_status
                    ? '<span style="color:#2e7d32;">&#10003; ' . esc_html__( 'Complete', 'battle-shield-sponsorship' ) . '</span>'
                    : '<span style="color:#c62828;">&#10007; ' . esc_html__( 'Missing', 'battle-shield-sponsorship' ) . '</span>';
                echo '<td>' . $complete_cell . '</td>';

                echo '<td>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;">';
                echo '<input type="hidden" name="action" value="bss_generate_patches" />';
                echo '<input type="hidden" name="campaign_id" value="' . $selected_campaign_id . '" />';
                echo '<input type="hidden" name="scope" value="single" />';
                echo '<input type="hidden" name="sponsorship_id" value="' . (int) $s->id . '" />';
                wp_nonce_field( self::NONCE_GENERATE, '_wpnonce_' . (int) $s->id );
                echo '<button type="submit" class="button button-small">' . esc_html__( 'Download patch', 'battle-shield-sponsorship' ) . '</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }

    public function handle_generate(): void {
        RequestGuard::require_capability( 'bss_generate_patches' );

        $campaign_id = (int) ( $_POST['campaign_id'] ?? 0 );
        $scope       = sanitize_key( wp_unslash( $_POST['scope'] ?? 'all' ) );
        $format      = sanitize_key( wp_unslash( $_POST['format'] ?? 'pdf' ) );

        if ( 'single' === $scope ) {
            $sponsorship_id = (int) ( $_POST['sponsorship_id'] ?? 0 );
            $nonce_key      = '_wpnonce_' . $sponsorship_id;
            if ( ! isset( $_POST[ $nonce_key ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ $nonce_key ] ), self::NONCE_GENERATE ) ) {
                wp_die( esc_html__( 'Security check failed.', 'battle-shield-sponsorship' ) );
            }
        } else {
            RequestGuard::verify_admin_nonce( self::NONCE_GENERATE );
            $sponsorship_id = 0;
        }

        if ( ! class_exists( '\Mpdf\Mpdf' ) ) {
            wp_die( esc_html__( 'mPDF is not installed. Run composer install in the plugin directory.', 'battle-shield-sponsorship' ) );
        }

        $service       = new PatchGenerationService();
        $complete_only = 'complete' === $scope;

        if ( 'single' === $scope && $sponsorship_id > 0 ) {
            $service->generate_for_sponsorship( $sponsorship_id );
        } elseif ( 'zip' === $format ) {
            $service->generate_zip_for_campaign( $campaign_id, $complete_only );
        } else {
            $service->generate_for_campaign( $campaign_id, $complete_only );
        }
        exit;
    }
}
