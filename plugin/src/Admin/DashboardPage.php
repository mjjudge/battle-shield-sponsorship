<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\CampaignService;
use BattleShieldSponsorship\Services\ShieldService;
use BattleShieldSponsorship\Services\SponsorshipService;
use BattleShieldSponsorship\Services\ReportingService;

defined( 'ABSPATH' ) || exit;

class DashboardPage {

    public function render(): void {
        RequestGuard::require_capability( 'bss_access' );

        $campaign_service    = new CampaignService();
        $shield_service      = new ShieldService();
        $sponsorship_service = new SponsorshipService();

        $campaign     = $campaign_service->get_active();
        $shield_counts = $shield_service->count_by_state();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Shield Sponsorship — Dashboard', 'battle-shield-sponsorship' ) . '</h1>';

        if ( ! $campaign ) {
            echo '<div class="notice notice-warning"><p>'
                . esc_html__( 'No active campaign. Go to Campaigns to create or activate one.', 'battle-shield-sponsorship' )
                . ' <a href="' . esc_url( admin_url( 'admin.php?page=bss-campaigns' ) ) . '">'
                . esc_html__( 'Campaigns', 'battle-shield-sponsorship' ) . '</a></p></div>';
        } else {
            $summary     = ( new ReportingService() )->campaign_summary( (int) $campaign->id );
            $cutoff_date = (string) ( $campaign->artwork_cutoff_date ?? '' );

            echo '<h2>' . esc_html( (string) $campaign->name ) . '</h2>';

            if ( '' !== $cutoff_date ) {
                $days_left = (int) ceil( ( strtotime( $cutoff_date ) - time() ) / 86400 );
                $cutoff_str = date( 'd/m/Y', strtotime( $cutoff_date ) );
                if ( $days_left > 0 ) {
                    echo '<p>' . sprintf(
                        esc_html__( 'Artwork cut-off: %1$s (%2$d days to go)', 'battle-shield-sponsorship' ),
                        esc_html( $cutoff_str ),
                        $days_left
                    ) . '</p>';
                } else {
                    echo '<p><strong>' . esc_html__( 'Artwork cut-off has passed.', 'battle-shield-sponsorship' ) . '</strong></p>';
                }
            }

            echo '<table class="widefat" style="max-width:600px;">';
            echo '<thead><tr><th>' . esc_html__( 'Metric', 'battle-shield-sponsorship' ) . '</th><th>' . esc_html__( 'Value', 'battle-shield-sponsorship' ) . '</th></tr></thead>';
            echo '<tbody>';
            $this->stat_row( __( 'Total revenue', 'battle-shield-sponsorship' ), '£' . number_format( $summary['total_revenue'], 2 ) );
            $this->stat_row( __( 'Shields sponsored (complete artwork)', 'battle-shield-sponsorship' ), (string) $summary['paid_complete'] );
            $this->stat_row( __( 'Shields sponsored (artwork missing)', 'battle-shield-sponsorship' ), (string) $summary['paid_incomplete'] );
            $this->stat_row( __( 'Shields available', 'battle-shield-sponsorship' ), (string) $shield_counts->available );
            $this->stat_row( __( 'Shields reserved (in checkout)', 'battle-shield-sponsorship' ), (string) $shield_counts->reserved );
            $this->stat_row( __( 'Shields sponsored', 'battle-shield-sponsorship' ), (string) $shield_counts->sponsored );
            $this->stat_row( __( 'Shields unavailable/damaged', 'battle-shield-sponsorship' ), (string) $shield_counts->unavailable );
            echo '</tbody></table>';
        }

        echo '<h2>' . esc_html__( 'Quick Links', 'battle-shield-sponsorship' ) . '</h2>';
        echo '<ul>';
        $links = [
            admin_url( 'admin.php?page=bss-campaigns' )          => __( 'Campaigns', 'battle-shield-sponsorship' ),
            admin_url( 'admin.php?page=bss-shields' )             => __( 'Shields', 'battle-shield-sponsorship' ),
            admin_url( 'admin.php?page=bss-sponsorships' )        => __( 'Sponsorships', 'battle-shield-sponsorship' ),
            admin_url( 'admin.php?page=bss-manual-sponsorship' )  => __( 'Manual Sponsorship', 'battle-shield-sponsorship' ),
            admin_url( 'admin.php?page=bss-patches' )             => __( 'Patch Generator', 'battle-shield-sponsorship' ),
            admin_url( 'admin.php?page=bss-reports' )             => __( 'Reports', 'battle-shield-sponsorship' ),
        ];
        foreach ( $links as $url => $label ) {
            echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    private function stat_row( string $label, string $value ): void {
        echo '<tr><td>' . esc_html( $label ) . '</td><td><strong>' . esc_html( $value ) . '</strong></td></tr>';
    }
}
