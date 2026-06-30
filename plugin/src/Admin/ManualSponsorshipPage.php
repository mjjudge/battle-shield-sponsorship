<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Database\Schema;
use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\CampaignService;
use BattleShieldSponsorship\Services\ShieldService;
use BattleShieldSponsorship\Services\ManualSponsorshipService;

defined( 'ABSPATH' ) || exit;

class ManualSponsorshipPage {

    private const NONCE_ACTION  = 'bss_create_manual_sponsorship';
    private const LOOKUP_ACTION = 'bss_lookup_contact_by_email';

    public function __construct() {
        add_action( 'admin_post_bss_create_manual_sponsorship', [ $this, 'handle_create' ] );
        add_action( 'wp_ajax_' . self::LOOKUP_ACTION, [ $this, 'handle_lookup' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'bss-manual-sponsorship' ) === false ) {
            return;
        }
        wp_enqueue_script(
            'bss-manual-sponsorship',
            BSS_PLUGIN_URL . 'assets/js/manual-sponsorship.js',
            [ 'jquery' ],
            BSS_VERSION,
            true
        );
        wp_localize_script( 'bss-manual-sponsorship', 'bssManual', [
            'nonce' => wp_create_nonce( self::LOOKUP_ACTION ),
        ] );
    }

    public function handle_lookup(): void {
        check_ajax_referer( self::LOOKUP_ACTION );

        if ( ! current_user_can( 'bss_manage_sponsorships' ) ) {
            wp_send_json_error( [ 'found' => false ], 403 );
        }

        $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        if ( ! $email ) {
            wp_send_json_success( [ 'found' => false ] );
        }

        global $wpdb;
        $contacts_table = Schema::table_name( 'contacts' );
        $contact = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$contacts_table} WHERE email = %s AND anonymised = 0 LIMIT 1",
            $email
        ) );

        if ( ! $contact ) {
            wp_send_json_success( [ 'found' => false ] );
        }

        // Check whether this contact has declared gift aid in any previous sponsorship.
        $sponsorships_table = Schema::table_name( 'sponsorships' );
        $gift_aid = (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT gift_aid_declared FROM {$sponsorships_table}
             WHERE contact_id = %d AND gift_aid_declared = 1 LIMIT 1",
            (int) $contact->id
        ) );

        wp_send_json_success( [
            'found'          => true,
            'contact_name'   => $contact->contact_name,
            'phone'          => $contact->phone ?? '',
            'address_line1'  => $contact->address_line1 ?? '',
            'address_line2'  => $contact->address_line2 ?? '',
            'city'           => $contact->city ?? '',
            'county'         => $contact->county ?? '',
            'postcode'       => $contact->postcode ?? '',
            'country'        => $contact->country ?? '',
            'marketing_opt_in' => (bool) $contact->marketing_opt_in,
            'gift_aid_declared' => $gift_aid,
        ] );
    }

    public function render(): void {
        RequestGuard::require_capability( 'bss_manage_sponsorships' );

        $campaign_service = new CampaignService();
        $shield_service   = new ShieldService();

        $active_campaign = $campaign_service->get_active();
        $campaigns       = $campaign_service->get_all();
        $available_shields = $shield_service->get_all( [ 'physical_state' => 'available' ] );

        if ( isset( $_GET['created'] ) ) {
            $new_id = (int) $_GET['created'];
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(
                wp_kses(
                    __( 'Manual sponsorship created. <a href="%s">View sponsorship</a>', 'battle-shield-sponsorship' ),
                    [ 'a' => [ 'href' => [] ] ]
                ),
                esc_url( admin_url( 'admin.php?page=bss-sponsorship-view&id=' . $new_id ) )
            );
            echo '</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Add Manual Sponsorship', 'battle-shield-sponsorship' ) . '</h1>';
        echo '<p>' . esc_html__( 'Record a sponsorship taken outside the online shop (e.g. phone, post, in-person). Payment is marked as complete immediately.', 'battle-shield-sponsorship' ) . '</p>';

        if ( empty( $campaigns ) ) {
            echo '<p>' . esc_html__( 'No events exist. Please create an event first.', 'battle-shield-sponsorship' ) . '</p></div>';
            return;
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bss_create_manual_sponsorship" />';
        wp_nonce_field( self::NONCE_ACTION );

        echo '<table class="form-table">';

        echo '<tr><th><label for="campaign_id">' . esc_html__( 'Event', 'battle-shield-sponsorship' ) . '</label></th><td>';
        echo '<select name="campaign_id" id="campaign_id" required>';
        foreach ( $campaigns as $campaign ) {
            $sel = $active_campaign && (int) $active_campaign->id === (int) $campaign->id ? ' selected' : '';
            echo '<option value="' . (int) $campaign->id . '"' . $sel . '>' . esc_html( (string) $campaign->name ) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th>' . esc_html__( 'Contact', 'battle-shield-sponsorship' ) . '</th><td>';
        echo '<p class="description">' . esc_html__( 'Enter the sponsor\'s details. An existing contact with the same email will be reused.', 'battle-shield-sponsorship' ) . '</p>';
        echo '</td></tr>';

        $this->row( 'email', __( 'Email', 'battle-shield-sponsorship' ),
            '<input name="email" id="email" type="email" class="regular-text" required />'
            . '<span id="bss-contact-found" style="display:none;margin-left:8px;color:#2e7d32;font-weight:600;">&#10003; '
            . esc_html__( 'Existing contact found — details pre-filled.', 'battle-shield-sponsorship' ) . '</span>' );

        $this->row( 'contact_name', __( 'Full name', 'battle-shield-sponsorship' ),
            '<input name="contact_name" id="contact_name" type="text" class="regular-text" required />' );

        $this->row( 'phone', __( 'Phone', 'battle-shield-sponsorship' ),
            '<input name="phone" id="phone" type="tel" class="regular-text" />' );

        // Address fields (all optional).
        echo '<tr><th>' . esc_html__( 'Address', 'battle-shield-sponsorship' ) . '</th><td>';
        echo '<p class="description">' . esc_html__( 'Optional. Pre-filled from existing contact record if email matches.', 'battle-shield-sponsorship' ) . '</p>';
        echo '</td></tr>';

        $this->row( 'address_line1', __( 'Address line 1', 'battle-shield-sponsorship' ),
            '<input name="address_line1" id="address_line1" type="text" class="regular-text" />' );
        $this->row( 'address_line2', __( 'Address line 2', 'battle-shield-sponsorship' ),
            '<input name="address_line2" id="address_line2" type="text" class="regular-text" />' );
        $this->row( 'city', __( 'City', 'battle-shield-sponsorship' ),
            '<input name="city" id="city" type="text" class="regular-text" />' );
        $this->row( 'county', __( 'County', 'battle-shield-sponsorship' ),
            '<input name="county" id="county" type="text" class="regular-text" />' );
        $this->row( 'postcode', __( 'Postcode', 'battle-shield-sponsorship' ),
            '<input name="postcode" id="postcode" type="text" style="width:10em;" />' );
        $this->row( 'country', __( 'Country', 'battle-shield-sponsorship' ),
            '<input name="country" id="country" type="text" class="regular-text" />' );

        $this->row( 'display_name', __( 'Sponsor display name', 'battle-shield-sponsorship' ),
            '<input name="display_name" id="display_name" type="text" class="regular-text" />'
            . '<p class="description">' . esc_html__( 'Shown on the shield. Defaults to full name if blank.', 'battle-shield-sponsorship' ) . '</p>' );

        $this->row( 'sponsor_url', __( 'Sponsor website URL', 'battle-shield-sponsorship' ),
            '<input name="sponsor_url" id="sponsor_url" type="text" class="regular-text" placeholder="e.g. www.example.com" />'
            . '<p class="description">' . esc_html__( 'Optional — shown on the patch artwork.', 'battle-shield-sponsorship' ) . '</p>' );

        $this->row( 'sponsor_phone', __( 'Sponsor phone number', 'battle-shield-sponsorship' ),
            '<input name="sponsor_phone" id="sponsor_phone" type="tel" class="regular-text" />'
            . '<p class="description">' . esc_html__( 'Optional — shown on the patch artwork.', 'battle-shield-sponsorship' ) . '</p>' );

        echo '<tr><th><label for="payment_method">' . esc_html__( 'Payment method', 'battle-shield-sponsorship' ) . '</label></th><td>';
        echo '<select name="payment_method" id="payment_method">';
        foreach ( [ 'cheque' => 'Cheque', 'cash' => 'Cash', 'bank_transfer' => 'Bank Transfer', 'other' => 'Other' ] as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th><label>' . esc_html__( 'Gift Aid', 'battle-shield-sponsorship' ) . '</label></th><td>';
        echo '<label><input name="gift_aid_declared" id="gift_aid_declared" type="checkbox" value="1" /> '
            . esc_html__( 'Sponsor has declared Gift Aid', 'battle-shield-sponsorship' ) . '</label></td></tr>';

        echo '<tr><th><label>' . esc_html__( 'Marketing consent', 'battle-shield-sponsorship' ) . '</label></th><td>';
        echo '<label><input name="marketing_opt_in" id="marketing_opt_in" type="checkbox" value="1" /> '
            . esc_html__( 'Sponsor has given consent to ongoing battle event related marketing communication', 'battle-shield-sponsorship' ) . '</label></td></tr>';

        echo '<tr><th><label>' . esc_html__( 'Shields', 'battle-shield-sponsorship' ) . '</label></th><td>';
        if ( empty( $available_shields ) ) {
            echo '<p>' . esc_html__( 'No available shields.', 'battle-shield-sponsorship' ) . '</p>';
        } else {
            echo '<p class="description">' . esc_html__( 'Select shields and set price for each.', 'battle-shield-sponsorship' ) . '</p>';
            echo '<table style="border-collapse:collapse;">';
            echo '<thead><tr><th style="text-align:left;padding:4px 8px;">' . esc_html__( 'Select', 'battle-shield-sponsorship' ) . '</th>';
            echo '<th style="text-align:left;padding:4px 8px;">' . esc_html__( 'Shield', 'battle-shield-sponsorship' ) . '</th>';
            echo '<th style="text-align:left;padding:4px 8px;">' . esc_html__( 'Donation (£)', 'battle-shield-sponsorship' ) . '</th></tr></thead><tbody>';
            foreach ( $available_shields as $shield ) {
                $sid = (int) $shield->id;
                echo '<tr>';
                echo '<td style="padding:4px 8px;"><input type="checkbox" name="shield_ids[]" value="' . $sid . '" id="shield_' . $sid . '" /></td>';
                $side_labels = [ 'royals' => 'Royals', 'rebels' => 'Rebels' ];
                $side_label  = $side_labels[ (string) $shield->side ] ?? ucfirst( (string) $shield->side );
                echo '<td style="padding:4px 8px;"><label for="shield_' . $sid . '">' . esc_html( (string) $shield->name ) . ' (' . esc_html( $side_label ) . ')</label></td>';
                echo '<td style="padding:4px 8px;"><input type="number" name="shield_price[' . $sid . ']" step="0.01" min="0" class="small-text" value="' . esc_attr( number_format( (float) $shield->suggested_price, 2 ) ) . '" /></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</td></tr>';

        echo '</table>';
        submit_button( __( 'Create Manual Sponsorship', 'battle-shield-sponsorship' ) );
        echo '</form>';
        echo '</div>';
    }

    public function handle_create(): void {
        RequestGuard::require_capability( 'bss_manage_sponsorships' );
        RequestGuard::verify_admin_nonce( self::NONCE_ACTION );

        $shield_ids = array_map( 'intval', (array) ( $_POST['shield_ids'] ?? [] ) );
        if ( empty( $shield_ids ) ) {
            wp_safe_redirect( add_query_arg( [ 'page' => 'bss-manual-sponsorship', 'error' => 'no_shields' ], admin_url( 'admin.php' ) ) );
            exit;
        }

        $shield_prices = [];
        foreach ( (array) ( $_POST['shield_price'] ?? [] ) as $sid => $price ) {
            $shield_prices[ (int) $sid ] = (float) $price;
        }

        $shields = [];
        foreach ( $shield_ids as $sid ) {
            $shields[] = [
                'shield_id'  => $sid,
                'price_paid' => $shield_prices[ $sid ] ?? 0.0,
            ];
        }

        $service_class = new ManualSponsorshipService();
        $result = $service_class->create( [
            'campaign_id'       => (int) ( $_POST['campaign_id'] ?? 0 ),
            'contact_name'      => sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) ),
            'email'             => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
            'phone'             => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
            'address_line1'     => sanitize_text_field( wp_unslash( $_POST['address_line1'] ?? '' ) ),
            'address_line2'     => sanitize_text_field( wp_unslash( $_POST['address_line2'] ?? '' ) ),
            'city'              => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
            'county'            => sanitize_text_field( wp_unslash( $_POST['county'] ?? '' ) ),
            'postcode'          => sanitize_text_field( wp_unslash( $_POST['postcode'] ?? '' ) ),
            'country'           => sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) ),
            'display_name'      => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
            'sponsor_url'       => sanitize_url( wp_unslash( $_POST['sponsor_url'] ?? '' ) ),
            'sponsor_phone'     => sanitize_text_field( wp_unslash( $_POST['sponsor_phone'] ?? '' ) ),
            'payment_method'    => sanitize_key( wp_unslash( $_POST['payment_method'] ?? 'other' ) ),
            'gift_aid_declared' => isset( $_POST['gift_aid_declared'] ) ? 1 : 0,
            'marketing_opt_in'  => isset( $_POST['marketing_opt_in'] ) ? 1 : 0,
            'shields'           => $shields,
        ] );

        wp_safe_redirect( add_query_arg( [ 'page' => 'bss-manual-sponsorship', 'created' => $result['sponsorship_id'] ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private function row( string $id, string $label, string $field ): void {
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th>';
        echo '<td>' . $field . '</td>';
        echo '</tr>';
    }
}
