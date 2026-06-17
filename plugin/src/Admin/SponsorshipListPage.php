<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\CampaignService;
use BattleShieldSponsorship\Services\SponsorshipService;
use BattleShieldSponsorship\Services\ContactService;

defined( 'ABSPATH' ) || exit;

class SponsorshipListPage {

    public function render(): void {
        RequestGuard::require_capability( 'bss_manage_sponsorships' );

        $campaign_id     = (int) ( $_GET['campaign_id'] ?? 0 );
        $payment_status  = sanitize_text_field( wp_unslash( $_GET['payment_status'] ?? '' ) );
        $artwork_status  = sanitize_text_field( wp_unslash( $_GET['artwork_status'] ?? '' ) );

        $campaign_service    = new CampaignService();
        $sponsorship_service = new SponsorshipService();
        $contact_service     = new ContactService();

        $active_campaign = $campaign_service->get_active();
        if ( 0 === $campaign_id && $active_campaign ) {
            $campaign_id = (int) $active_campaign->id;
        }

        $campaigns = $campaign_service->get_all();

        $filters = array_filter( [
            'campaign_id'    => $campaign_id ?: null,
            'payment_status' => $payment_status,
            'artwork_status' => $artwork_status,
        ] );

        $sponsorships = $sponsorship_service->get_all( $filters );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Sponsorships', 'battle-shield-sponsorship' ) . '</h1>';

        echo '<form method="get" style="margin-bottom:16px;">';
        echo '<input type="hidden" name="page" value="bss-sponsorships" />';

        echo '<select name="campaign_id" style="margin-right:8px;">';
        echo '<option value="">' . esc_html__( 'All campaigns', 'battle-shield-sponsorship' ) . '</option>';
        foreach ( $campaigns as $campaign ) {
            echo '<option value="' . (int) $campaign->id . '" ' . selected( $campaign_id, (int) $campaign->id, false ) . '>'
                . esc_html( (string) $campaign->name ) . '</option>';
        }
        echo '</select>';

        echo '<select name="payment_status" style="margin-right:8px;">';
        echo '<option value="">' . esc_html__( 'Any payment status', 'battle-shield-sponsorship' ) . '</option>';
        foreach ( [ 'pending', 'paid', 'failed', 'refunded', 'abandoned' ] as $s ) {
            echo '<option value="' . esc_attr( $s ) . '" ' . selected( $payment_status, $s, false ) . '>' . esc_html( ucfirst( $s ) ) . '</option>';
        }
        echo '</select>';

        echo '<select name="artwork_status" style="margin-right:8px;">';
        echo '<option value="">' . esc_html__( 'Any artwork status', 'battle-shield-sponsorship' ) . '</option>';
        echo '<option value="complete" ' . selected( $artwork_status, 'complete', false ) . '>' . esc_html__( 'Complete', 'battle-shield-sponsorship' ) . '</option>';
        echo '<option value="incomplete" ' . selected( $artwork_status, 'incomplete', false ) . '>' . esc_html__( 'Incomplete', 'battle-shield-sponsorship' ) . '</option>';
        echo '</select>';

        submit_button( __( 'Filter', 'battle-shield-sponsorship' ), 'secondary', 'submit', false );
        echo '</form>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'ID', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Sponsor', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Contact', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Amount', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Payment', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Artwork', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Date', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'battle-shield-sponsorship' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $sponsorships ) ) {
            echo '<tr><td colspan="8">' . esc_html__( 'No sponsorships found.', 'battle-shield-sponsorship' ) . '</td></tr>';
        }

        foreach ( $sponsorships as $s ) {
            $contact = $contact_service->get_by_id( (int) $s->contact_id );
            echo '<tr>';
            echo '<td>' . (int) $s->id . '</td>';
            echo '<td>' . esc_html( (string) $s->display_name ) . '</td>';
            echo '<td>' . esc_html( $contact ? (string) $contact->contact_name : '—' ) . '<br><small>' . esc_html( $contact ? (string) $contact->email : '' ) . '</small></td>';
            echo '<td>£' . esc_html( number_format( (float) $s->total_amount, 2 ) ) . '</td>';
            echo '<td>' . esc_html( ucfirst( (string) $s->payment_status ) ) . '</td>';
            $artwork_ok = 'complete' === (string) $s->artwork_status;
            echo '<td>' . ( $artwork_ok ? '<span style="color:#2e7d32;">&#10003; ' . esc_html__( 'Complete', 'battle-shield-sponsorship' ) . '</span>' : '<span style="color:#c62828;">&#10007; ' . esc_html__( 'Missing', 'battle-shield-sponsorship' ) . '</span>' ) . '</td>';
            echo '<td>' . esc_html( date( 'd/m/Y', strtotime( (string) $s->created_at ) ) ) . '</td>';
            echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=bss-sponsorship-view&id=' . (int) $s->id ) ) . '">'
                . esc_html__( 'View', 'battle-shield-sponsorship' ) . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }
}
