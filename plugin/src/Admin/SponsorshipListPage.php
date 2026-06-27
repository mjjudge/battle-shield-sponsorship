<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\CampaignService;
use BattleShieldSponsorship\Services\SponsorshipService;
use BattleShieldSponsorship\Services\ContactService;

defined( 'ABSPATH' ) || exit;

class SponsorshipListPage {

    private const NONCE_DELETE = 'bss_delete_sponsorship';

    public function __construct() {
        add_action( 'admin_post_bss_delete_sponsorship', [ $this, 'handle_delete' ] );
    }

    public function render(): void {
        RequestGuard::require_capability( 'bss_manage_sponsorships' );

        $campaign_id    = (int) ( $_GET['campaign_id'] ?? 0 );
        $payment_status = sanitize_text_field( wp_unslash( $_GET['payment_status'] ?? '' ) );
        $artwork_status = sanitize_text_field( wp_unslash( $_GET['artwork_status'] ?? '' ) );

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

        // Sort alphabetically by display name, falling back to contact name; blanks last.
        usort( $sponsorships, static function ( $a, $b ) {
            $nameA = (string) $a->display_name;
            $nameB = (string) $b->display_name;
            if ( '' === $nameA && '' !== $nameB ) { return 1; }
            if ( '' !== $nameA && '' === $nameB ) { return -1; }
            return strcasecmp( $nameA, $nameB );
        } );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Sponsorships', 'battle-shield-sponsorship' ) . '</h1>';

        if ( isset( $_GET['deleted'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Sponsorship deleted. Shields have been released back to available.', 'battle-shield-sponsorship' ) . '</p></div>';
        }

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
        echo '<th>' . esc_html__( 'Sponsor', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Contact', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Amount', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Payment', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Artwork', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Date', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'battle-shield-sponsorship' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $sponsorships ) ) {
            echo '<tr><td colspan="7">' . esc_html__( 'No sponsorships found.', 'battle-shield-sponsorship' ) . '</td></tr>';
        }

        $confirm_msg = esc_js( __( 'Permanently delete this sponsorship? The sponsored shields will be released back to available. This cannot be undone.', 'battle-shield-sponsorship' ) );

        foreach ( $sponsorships as $s ) {
            $sid     = (int) $s->id;
            $contact = $contact_service->get_by_id( (int) $s->contact_id );

            $artwork_ok = 'complete' === (string) $s->artwork_status;
            echo '<tr>';
            $sponsor_label = (string) $s->display_name ?: ( $contact ? (string) $contact->contact_name : '—' );
            echo '<td>' . esc_html( $sponsor_label ) . '</td>';
            echo '<td>' . esc_html( $contact ? (string) $contact->contact_name : '—' )
                . '<br><small>' . esc_html( $contact ? (string) $contact->email : '' ) . '</small></td>';
            echo '<td>£' . esc_html( number_format( (float) $s->total_amount, 2 ) ) . '</td>';
            echo '<td>' . esc_html( ucfirst( (string) $s->payment_status ) ) . '</td>';
            echo '<td>' . ( $artwork_ok
                ? '<span style="color:#2e7d32;">&#10003; ' . esc_html__( 'Complete', 'battle-shield-sponsorship' ) . '</span>'
                : '<span style="color:#c62828;">&#10007; ' . esc_html__( 'Incomplete', 'battle-shield-sponsorship' ) . '</span>' ) . '</td>';
            echo '<td>' . esc_html( date( 'd/m/Y', strtotime( (string) $s->created_at ) ) ) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=bss-sponsorship-view&id=' . $sid ) ) . '">'
                . esc_html__( 'Edit', 'battle-shield-sponsorship' ) . '</a>';
            echo ' &nbsp; ';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;" onsubmit="return confirm(\'' . $confirm_msg . '\')">';
            echo '<input type="hidden" name="action" value="bss_delete_sponsorship" />';
            echo '<input type="hidden" name="sponsorship_id" value="' . $sid . '" />';
            echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( self::NONCE_DELETE . '_' . $sid ) ) . '" />';
            echo '<button type="submit" class="button-link" style="color:#c62828;">' . esc_html__( 'Delete', 'battle-shield-sponsorship' ) . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function handle_delete(): void {
        RequestGuard::require_capability( 'bss_manage_sponsorships' );
        $id = (int) ( $_POST['sponsorship_id'] ?? 0 );
        RequestGuard::verify_admin_nonce( self::NONCE_DELETE . '_' . $id );

        if ( $id > 0 ) {
            ( new SponsorshipService() )->delete( $id );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'bss-sponsorships', 'deleted' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }
}
