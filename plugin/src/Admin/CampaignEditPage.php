<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\CampaignService;

defined( 'ABSPATH' ) || exit;

class CampaignEditPage {

    private const NONCE_ACTION = 'bss_save_campaign';

    public function __construct() {
        add_action( 'admin_post_bss_save_campaign', [ $this, 'handle_save' ] );
    }

    public function render(): void {
        RequestGuard::require_capability( 'bss_manage_campaigns' );

        $id       = (int) ( $_GET['id'] ?? 0 );
        $service  = new CampaignService();
        $campaign = $id > 0 ? $service->get_by_id( $id ) : null;

        $name          = (string) ( $campaign->name ?? '' );
        $event_date    = (string) ( $campaign->event_date ?? '' );
        $sales_open    = (string) ( $campaign->sales_open_date ?? '' );
        $cutoff        = (string) ( $campaign->artwork_cutoff_date ?? '' );
        $freq_days     = (int) ( $campaign->reminder_frequency_days ?? 2 );
        $final_days    = (int) ( $campaign->final_reminder_days_before ?? 1 );
        $default_price = number_format( (float) ( $campaign->default_price ?? 0 ), 2 );
        $timeout       = (int) ( $campaign->reservation_timeout_minutes ?? 30 );
        $gift_aid      = ! empty( $campaign->gift_aid_enabled );
        $status        = (string) ( $campaign->status ?? 'inactive' );

        $title = $id > 0 ? __( 'Edit Campaign', 'battle-shield-sponsorship' ) : __( 'New Campaign', 'battle-shield-sponsorship' );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html( $title ) . '</h1>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bss_save_campaign" />';
        echo '<input type="hidden" name="campaign_id" value="' . $id . '" />';
        wp_nonce_field( self::NONCE_ACTION );

        echo '<table class="form-table" role="presentation">';
        $this->row( 'name', __( 'Campaign name', 'battle-shield-sponsorship' ),
            '<input name="name" id="name" type="text" class="regular-text" required value="' . esc_attr( $name ) . '" />' );
        $this->row( 'event_date', __( 'Event date', 'battle-shield-sponsorship' ),
            '<input name="event_date" id="event_date" type="date" value="' . esc_attr( $event_date ) . '" />' );
        $this->row( 'sales_open_date', __( 'Sales open date', 'battle-shield-sponsorship' ),
            '<input name="sales_open_date" id="sales_open_date" type="date" value="' . esc_attr( $sales_open ) . '" />' );
        $this->row( 'artwork_cutoff_date', __( 'Artwork cut-off date', 'battle-shield-sponsorship' ),
            '<input name="artwork_cutoff_date" id="artwork_cutoff_date" type="date" value="' . esc_attr( $cutoff ) . '" />' );
        $this->row( 'default_price', __( 'Default price per shield (£)', 'battle-shield-sponsorship' ),
            '<input name="default_price" id="default_price" type="number" step="0.01" min="0" class="small-text" value="' . esc_attr( $default_price ) . '" />' );
        $this->row( 'reminder_frequency_days', __( 'Reminder frequency (days)', 'battle-shield-sponsorship' ),
            '<input name="reminder_frequency_days" id="reminder_frequency_days" type="number" min="1" max="30" class="small-text" value="' . esc_attr( (string) $freq_days ) . '" />'
            . '<p class="description">' . esc_html__( 'How often to send artwork upload reminders.', 'battle-shield-sponsorship' ) . '</p>' );
        $this->row( 'final_reminder_days_before', __( 'Final reminder (days before cut-off)', 'battle-shield-sponsorship' ),
            '<input name="final_reminder_days_before" id="final_reminder_days_before" type="number" min="1" max="14" class="small-text" value="' . esc_attr( (string) $final_days ) . '" />' );
        $this->row( 'reservation_timeout_minutes', __( 'Reservation timeout (minutes)', 'battle-shield-sponsorship' ),
            '<input name="reservation_timeout_minutes" id="reservation_timeout_minutes" type="number" min="5" max="120" class="small-text" value="' . esc_attr( (string) $timeout ) . '" />' );
        $this->row( 'gift_aid_enabled', __( 'Gift Aid', 'battle-shield-sponsorship' ),
            '<label><input name="gift_aid_enabled" id="gift_aid_enabled" type="checkbox" value="1" ' . checked( $gift_aid, true, false ) . ' /> '
            . esc_html__( 'Enable Gift Aid declaration on checkout', 'battle-shield-sponsorship' ) . '</label>' );
        $this->row( 'status', __( 'Status', 'battle-shield-sponsorship' ),
            '<select name="status" id="status">'
            . '<option value="inactive" ' . selected( $status, 'inactive', false ) . '>' . esc_html__( 'Inactive', 'battle-shield-sponsorship' ) . '</option>'
            . '<option value="active" ' . selected( $status, 'active', false ) . '>' . esc_html__( 'Active', 'battle-shield-sponsorship' ) . '</option>'
            . '</select>' );
        echo '</table>';

        submit_button( $id > 0 ? __( 'Save Campaign', 'battle-shield-sponsorship' ) : __( 'Create Campaign', 'battle-shield-sponsorship' ) );
        echo '</form>';

        if ( $id > 0 ) {
            echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=bss-campaigns' ) ) . '">&larr; '
                . esc_html__( 'Back to Campaigns', 'battle-shield-sponsorship' ) . '</a></p>';
        }

        echo '</div>';
    }

    public function handle_save(): void {
        RequestGuard::require_capability( 'bss_manage_campaigns' );
        RequestGuard::verify_admin_nonce( self::NONCE_ACTION );

        $id      = (int) ( $_POST['campaign_id'] ?? 0 );
        $service = new CampaignService();

        $data = [
            'name'                      => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
            'event_date'                => sanitize_text_field( wp_unslash( $_POST['event_date'] ?? '' ) ),
            'sales_open_date'           => sanitize_text_field( wp_unslash( $_POST['sales_open_date'] ?? '' ) ),
            'artwork_cutoff_date'       => sanitize_text_field( wp_unslash( $_POST['artwork_cutoff_date'] ?? '' ) ),
            'reminder_frequency_days'   => (int) ( $_POST['reminder_frequency_days'] ?? 2 ),
            'final_reminder_days_before' => (int) ( $_POST['final_reminder_days_before'] ?? 1 ),
            'default_price'             => (float) ( $_POST['default_price'] ?? 0 ),
            'reservation_timeout_minutes' => (int) ( $_POST['reservation_timeout_minutes'] ?? 30 ),
            'gift_aid_enabled'          => isset( $_POST['gift_aid_enabled'] ),
            'status'                    => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'inactive' ) ),
        ];

        if ( $id > 0 ) {
            $service->update( $id, $data );
        } else {
            $id = $service->create( $data );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'bss-campaigns', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private function row( string $id, string $label, string $field ): void {
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th>';
        echo '<td>' . $field . '</td>';
        echo '</tr>';
    }
}
