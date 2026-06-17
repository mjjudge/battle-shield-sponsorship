<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\CampaignService;
use BattleShieldSponsorship\Services\ReportingService;

defined( 'ABSPATH' ) || exit;

class ReportingPage {

    private const NONCE_EXPORT = 'bss_export_report';

    public function __construct() {
        add_action( 'admin_post_bss_export_report', [ $this, 'handle_export' ] );
    }

    public function render(): void {
        RequestGuard::require_capability( 'bss_manage_campaigns' );

        $campaign_service = new CampaignService();
        $reporting        = new ReportingService();

        $active_campaign     = $campaign_service->get_active();
        $campaigns           = $campaign_service->get_all();
        $selected_campaign_id = (int) ( $_GET['campaign_id'] ?? ( $active_campaign ? (int) $active_campaign->id : 0 ) );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Reports', 'battle-shield-sponsorship' ) . '</h1>';

        echo '<form method="get" style="margin-bottom:16px;">';
        echo '<input type="hidden" name="page" value="bss-reports" />';
        echo '<select name="campaign_id" style="margin-right:8px;">';
        foreach ( $campaigns as $campaign ) {
            echo '<option value="' . (int) $campaign->id . '" ' . selected( $selected_campaign_id, (int) $campaign->id, false ) . '>'
                . esc_html( (string) $campaign->name ) . '</option>';
        }
        echo '</select>';
        submit_button( __( 'View', 'battle-shield-sponsorship' ), 'secondary', 'submit', false );
        echo '</form>';

        if ( $selected_campaign_id > 0 ) {
            $summary = $reporting->campaign_summary( $selected_campaign_id );

            echo '<h2>' . esc_html__( 'Campaign Summary', 'battle-shield-sponsorship' ) . '</h2>';
            echo '<table class="widefat" style="max-width:500px;">';
            echo '<tr><th>' . esc_html__( 'Total sponsorships (paid)', 'battle-shield-sponsorship' ) . '</th><td>' . (int) ( $summary['paid_count'] ?? 0 ) . '</td></tr>';
            echo '<tr><th>' . esc_html__( 'Total revenue', 'battle-shield-sponsorship' ) . '</th><td>£' . esc_html( number_format( (float) ( $summary['total_revenue'] ?? 0 ), 2 ) ) . '</td></tr>';
            echo '<tr><th>' . esc_html__( 'Shields sponsored', 'battle-shield-sponsorship' ) . '</th><td>' . (int) ( $summary['shields_sponsored'] ?? 0 ) . '</td></tr>';
            echo '<tr><th>' . esc_html__( 'Artwork complete', 'battle-shield-sponsorship' ) . '</th><td>' . (int) ( $summary['artwork_complete'] ?? 0 ) . '</td></tr>';
            echo '<tr><th>' . esc_html__( 'Artwork missing', 'battle-shield-sponsorship' ) . '</th><td>' . (int) ( $summary['artwork_missing'] ?? 0 ) . '</td></tr>';
            echo '<tr><th>' . esc_html__( 'Gift Aid declared', 'battle-shield-sponsorship' ) . '</th><td>' . (int) ( $summary['gift_aid_count'] ?? 0 ) . '</td></tr>';
            echo '</table>';

            echo '<h2>' . esc_html__( 'Exports', 'battle-shield-sponsorship' ) . '</h2>';

            foreach ( [ 'sponsorships' => __( 'Sponsorships CSV', 'battle-shield-sponsorship' ), 'contacts' => __( 'Opted-in Contacts CSV', 'battle-shield-sponsorship' ) ] as $type => $label ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;margin-right:12px;">';
                echo '<input type="hidden" name="action" value="bss_export_report" />';
                echo '<input type="hidden" name="campaign_id" value="' . $selected_campaign_id . '" />';
                echo '<input type="hidden" name="report_type" value="' . esc_attr( $type ) . '" />';
                wp_nonce_field( self::NONCE_EXPORT );
                echo '<button type="submit" class="button">' . esc_html( $label ) . '</button>';
                echo '</form>';
            }
        }

        echo '</div>';
    }

    public function handle_export(): void {
        RequestGuard::require_capability( 'bss_manage_campaigns' );
        RequestGuard::verify_admin_nonce( self::NONCE_EXPORT );

        $campaign_id = (int) ( $_POST['campaign_id'] ?? 0 );
        $report_type = sanitize_key( wp_unslash( $_POST['report_type'] ?? '' ) );

        $reporting = new ReportingService();

        if ( 'sponsorships' === $report_type ) {
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="sponsorships-campaign-' . $campaign_id . '-' . date( 'Ymd' ) . '.csv"' );
            ReportingService::output_csv( $reporting->sponsorships_csv( $campaign_id ) );
        } elseif ( 'contacts' === $report_type ) {
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="opted-in-contacts-' . date( 'Ymd' ) . '.csv"' );
            ReportingService::output_csv( $reporting->opted_in_contacts_csv() );
        }
        exit;
    }
}
