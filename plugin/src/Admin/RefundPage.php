<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\SponsorshipService;
use BattleShieldSponsorship\Services\ContactService;
use BattleShieldSponsorship\Services\RefundService;

defined( 'ABSPATH' ) || exit;

class RefundPage {

    private const NONCE_ACTION = 'bss_process_refund';

    public function __construct() {
        add_action( 'admin_post_bss_process_refund', [ $this, 'handle_refund' ] );
    }

    public function render(): void {
        RequestGuard::require_capability( 'bss_process_refunds' );

        $sponsorship_id = (int) ( $_GET['sponsorship_id'] ?? 0 );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Refunds', 'battle-shield-sponsorship' ) . '</h1>';

        if ( isset( $_GET['refunded'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Refund processed successfully.', 'battle-shield-sponsorship' ) . '</p></div>';
        }
        if ( isset( $_GET['error'] ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Refund failed. Please check the logs.', 'battle-shield-sponsorship' ) . '</p></div>';
        }

        $sponsorship_service = new SponsorshipService();
        $contact_service     = new ContactService();

        if ( $sponsorship_id > 0 ) {
            $sponsorship = $sponsorship_service->get_by_id( $sponsorship_id );
            if ( $sponsorship && 'paid' === (string) $sponsorship->payment_status ) {
                $contact = $contact_service->get_by_id( (int) $sponsorship->contact_id );
                echo '<h2>' . sprintf( esc_html__( 'Refund Sponsorship #%d', 'battle-shield-sponsorship' ), $sponsorship_id ) . '</h2>';
                echo '<table class="form-table" style="max-width:500px;"><tbody>';
                echo '<tr><th>' . esc_html__( 'Sponsor', 'battle-shield-sponsorship' ) . '</th><td>' . esc_html( (string) $sponsorship->display_name ) . '</td></tr>';
                echo '<tr><th>' . esc_html__( 'Contact', 'battle-shield-sponsorship' ) . '</th><td>' . esc_html( $contact ? (string) $contact->email : '—' ) . '</td></tr>';
                echo '<tr><th>' . esc_html__( 'Amount', 'battle-shield-sponsorship' ) . '</th><td>£' . esc_html( number_format( (float) $sponsorship->total_amount, 2 ) ) . '</td></tr>';
                echo '<tr><th>' . esc_html__( 'Payment method', 'battle-shield-sponsorship' ) . '</th><td>' . esc_html( ucfirst( (string) $sponsorship->payment_method ) ) . '</td></tr>';
                echo '</tbody></table>';

                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Process refund for this sponsorship? This cannot be undone.', 'battle-shield-sponsorship' ) ) . '\')">';
                echo '<input type="hidden" name="action" value="bss_process_refund" />';
                echo '<input type="hidden" name="sponsorship_id" value="' . $sponsorship_id . '" />';
                wp_nonce_field( self::NONCE_ACTION );

                echo '<table class="form-table" style="max-width:500px;">';
                echo '<tr><th><label for="reason">' . esc_html__( 'Reason', 'battle-shield-sponsorship' ) . '</label></th><td>';
                echo '<textarea name="reason" id="reason" class="large-text" rows="3" required placeholder="' . esc_attr__( 'Reason for refund…', 'battle-shield-sponsorship' ) . '"></textarea></td></tr>';
                echo '</table>';

                submit_button( __( 'Process Refund', 'battle-shield-sponsorship' ), 'primary' );
                echo '</form>';
            } elseif ( $sponsorship ) {
                echo '<p>' . esc_html__( 'This sponsorship is not in a refundable state.', 'battle-shield-sponsorship' ) . '</p>';
            } else {
                echo '<p>' . esc_html__( 'Sponsorship not found.', 'battle-shield-sponsorship' ) . '</p>';
            }
            echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=bss-refunds' ) ) . '">&larr; ' . esc_html__( 'All refundable sponsorships', 'battle-shield-sponsorship' ) . '</a></p>';
        } else {
            $paid_sponsorships = $sponsorship_service->get_all( [ 'payment_status' => 'paid' ] );
            echo '<p>' . esc_html__( 'Select a paid sponsorship to process a refund.', 'battle-shield-sponsorship' ) . '</p>';

            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'ID', 'battle-shield-sponsorship' ) . '</th>';
            echo '<th>' . esc_html__( 'Sponsor', 'battle-shield-sponsorship' ) . '</th>';
            echo '<th>' . esc_html__( 'Amount', 'battle-shield-sponsorship' ) . '</th>';
            echo '<th>' . esc_html__( 'Method', 'battle-shield-sponsorship' ) . '</th>';
            echo '<th>' . esc_html__( 'Date', 'battle-shield-sponsorship' ) . '</th>';
            echo '<th>' . esc_html__( 'Action', 'battle-shield-sponsorship' ) . '</th>';
            echo '</tr></thead><tbody>';

            if ( empty( $paid_sponsorships ) ) {
                echo '<tr><td colspan="6">' . esc_html__( 'No paid sponsorships found.', 'battle-shield-sponsorship' ) . '</td></tr>';
            }

            foreach ( $paid_sponsorships as $s ) {
                echo '<tr>';
                echo '<td>' . (int) $s->id . '</td>';
                echo '<td>' . esc_html( (string) $s->display_name ) . '</td>';
                echo '<td>£' . esc_html( number_format( (float) $s->total_amount, 2 ) ) . '</td>';
                echo '<td>' . esc_html( ucfirst( (string) $s->payment_method ) ) . '</td>';
                echo '<td>' . esc_html( date( 'd/m/Y', strtotime( (string) $s->created_at ) ) ) . '</td>';
                echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=bss-refunds&sponsorship_id=' . (int) $s->id ) ) . '">'
                    . esc_html__( 'Refund', 'battle-shield-sponsorship' ) . '</a></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div>';
    }

    public function handle_refund(): void {
        RequestGuard::require_capability( 'bss_process_refunds' );
        RequestGuard::verify_admin_nonce( self::NONCE_ACTION );

        $id     = (int) ( $_POST['sponsorship_id'] ?? 0 );
        $reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );

        $result = ( new RefundService() )->process( $id, $reason );

        if ( $result ) {
            wp_safe_redirect( add_query_arg( [ 'page' => 'bss-refunds', 'refunded' => '1' ], admin_url( 'admin.php' ) ) );
        } else {
            wp_safe_redirect( add_query_arg( [ 'page' => 'bss-refunds', 'error' => '1', 'sponsorship_id' => $id ], admin_url( 'admin.php' ) ) );
        }
        exit;
    }
}
