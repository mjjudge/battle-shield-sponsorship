<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\CampaignService;
use BattleShieldSponsorship\Services\SponsorshipService;
use BattleShieldSponsorship\Services\ContactService;
use BattleShieldSponsorship\Services\PatchGenerationService;
use BattleShieldSponsorship\Database\Schema;

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
        $contact_service     = new ContactService();

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

            // Build per-shield rows sorted by shield name.
            $shield_rows = $this->get_shield_rows( $selected_campaign_id, $paid_sponsorships, $contact_service );

            echo '<h2>' . esc_html__( 'Individual patches', 'battle-shield-sponsorship' ) . '</h2>';
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Shield', 'battle-shield-sponsorship' ) . '</th>';
            echo '<th>' . esc_html__( 'Sponsor', 'battle-shield-sponsorship' ) . '</th>';
            echo '<th>' . esc_html__( 'Artwork', 'battle-shield-sponsorship' ) . '</th>';
            echo '<th>' . esc_html__( 'Action', 'battle-shield-sponsorship' ) . '</th>';
            echo '</tr></thead><tbody>';

            if ( empty( $shield_rows ) ) {
                echo '<tr><td colspan="4">' . esc_html__( 'No paid sponsorships to generate patches for.', 'battle-shield-sponsorship' ) . '</td></tr>';
            }

            foreach ( $shield_rows as $row ) {
                $sid          = (int) $row['sponsorship_id'];
                $artwork_ok   = $row['artwork_complete'];
                $complete_cell = $artwork_ok
                    ? '<span style="color:#2e7d32;">&#10003; ' . esc_html__( 'Complete', 'battle-shield-sponsorship' ) . '</span>'
                    : '<span style="color:#c62828;">&#10007; ' . esc_html__( 'Missing', 'battle-shield-sponsorship' ) . '</span>';

                echo '<tr>';
                echo '<td><strong>' . esc_html( $row['shield_name'] ) . '</strong></td>';
                echo '<td>' . esc_html( $row['sponsor_label'] ) . '</td>';
                echo '<td>' . $complete_cell . '</td>';
                echo '<td>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;">';
                echo '<input type="hidden" name="action" value="bss_generate_patches" />';
                echo '<input type="hidden" name="campaign_id" value="' . $selected_campaign_id . '" />';
                echo '<input type="hidden" name="scope" value="single" />';
                echo '<input type="hidden" name="sponsorship_id" value="' . $sid . '" />';
                wp_nonce_field( self::NONCE_GENERATE, '_wpnonce_' . $sid );
                echo '<button type="submit" class="button button-small">' . esc_html__( 'Download patch', 'battle-shield-sponsorship' ) . '</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div>';
    }

    /**
     * Returns one row per shield item across all paid sponsorships, sorted by shield name.
     *
     * @param object[] $paid_sponsorships
     * @return array<int, array{shield_name:string, sponsor_label:string, sponsorship_id:int, artwork_complete:bool}>
     */
    private function get_shield_rows( int $campaign_id, array $paid_sponsorships, ContactService $contact_service ): array {
        global $wpdb;

        $i_table = Schema::table_name( 'sponsorship_items' );
        $sh_table = Schema::table_name( 'shields' );
        $s_table  = Schema::table_name( 'sponsorships' );
        $c_table  = Schema::table_name( 'contacts' );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT sh.name AS shield_name,
                    s.id AS sponsorship_id,
                    s.display_name,
                    s.artwork_status,
                    c.contact_name
             FROM {$i_table} i
             JOIN {$sh_table} sh ON sh.id = i.shield_id
             JOIN {$s_table} s ON s.id = i.sponsorship_id
             LEFT JOIN {$c_table} c ON c.id = s.contact_id
             WHERE s.campaign_id = %d AND s.payment_status = 'paid'
             ORDER BY sh.name ASC",
            $campaign_id
        ) );

        if ( ! is_array( $rows ) ) {
            return [];
        }

        $result = [];
        foreach ( $rows as $row ) {
            $display_name  = (string) $row->display_name;
            $contact_name  = (string) ( $row->contact_name ?? '' );
            $sponsor_label = $display_name !== '' ? $display_name : ( $contact_name !== '' ? $contact_name : '—' );

            $result[] = [
                'shield_name'     => (string) $row->shield_name,
                'sponsor_label'   => $sponsor_label,
                'sponsorship_id'  => (int) $row->sponsorship_id,
                'artwork_complete' => 'complete' === (string) $row->artwork_status,
            ];
        }

        return $result;
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
