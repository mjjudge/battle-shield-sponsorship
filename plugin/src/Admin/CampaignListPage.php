<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\CampaignService;

defined( 'ABSPATH' ) || exit;

class CampaignListPage {

    private const ACTIVATE_NONCE = 'bss_activate_campaign';

    public function __construct() {
        add_action( 'admin_post_bss_activate_campaign', [ $this, 'handle_activate' ] );
    }

    public function render(): void {
        RequestGuard::require_capability( 'bss_manage_campaigns' );

        $campaigns = ( new CampaignService() )->get_all();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Events', 'battle-shield-sponsorship' ) . '</h1>';
        echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=bss-campaign-edit' ) ) . '">'
            . esc_html__( 'New Event', 'battle-shield-sponsorship' ) . '</a></p>';

        if ( isset( $_GET['activated'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Event activated.', 'battle-shield-sponsorship' ) . '</p></div>';
        }
        if ( isset( $_GET['saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Event saved.', 'battle-shield-sponsorship' ) . '</p></div>';
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Name', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Event dates', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Artwork cut-off', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'battle-shield-sponsorship' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $campaigns ) ) {
            echo '<tr><td colspan="5">' . esc_html__( 'No events yet.', 'battle-shield-sponsorship' ) . '</td></tr>';
        }

        foreach ( $campaigns as $campaign ) {
            $is_active = 'active' === (string) $campaign->status;
            echo '<tr>';
            echo '<td><strong>' . esc_html( (string) $campaign->name ) . '</strong></td>';
            $start = $this->fmt_date( (string) ( $campaign->event_start_date ?? $campaign->event_date ?? '' ) );
            $end   = $this->fmt_date( (string) ( $campaign->event_end_date ?? '' ) );
            $dates = ( '—' !== $end && $end !== $start ) ? $start . ' – ' . $end : $start;
            echo '<td>' . esc_html( $dates ) . '</td>';
            echo '<td>' . esc_html( $this->fmt_date( (string) ( $campaign->artwork_cutoff_date ?? '' ) ) ) . '</td>';
            echo '<td>' . ( $is_active ? '<span style="color:#2e7d32;font-weight:bold;">' . esc_html__( 'Active', 'battle-shield-sponsorship' ) . '</span>' : esc_html__( 'Inactive', 'battle-shield-sponsorship' ) ) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=bss-campaign-edit&id=' . (int) $campaign->id ) ) . '">' . esc_html__( 'Edit', 'battle-shield-sponsorship' ) . '</a>';
            if ( ! $is_active ) {
                echo ' | ';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;">';
                echo '<input type="hidden" name="action" value="bss_activate_campaign" />';
                echo '<input type="hidden" name="campaign_id" value="' . (int) $campaign->id . '" />';
                wp_nonce_field( self::ACTIVATE_NONCE );
                echo '<button type="submit" class="button-link">' . esc_html__( 'Set Active', 'battle-shield-sponsorship' ) . '</button>';
                echo '</form>';
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function handle_activate(): void {
        RequestGuard::require_capability( 'bss_manage_campaigns' );
        RequestGuard::verify_admin_nonce( self::ACTIVATE_NONCE );

        $id = (int) ( $_POST['campaign_id'] ?? 0 );
        if ( $id > 0 ) {
            ( new CampaignService() )->set_active( $id );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'bss-campaigns', 'activated' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private function fmt_date( string $value ): string {
        if ( '' === $value ) {
            return '—';
        }
        $ts = strtotime( $value );
        return false !== $ts ? date( 'd/m/Y', $ts ) : $value;
    }
}
