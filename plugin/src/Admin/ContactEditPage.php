<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\ContactService;
use BattleShieldSponsorship\Services\GdprService;

defined( 'ABSPATH' ) || exit;

class ContactEditPage {

    private const NONCE_SAVE = 'bss_save_contact';
    private const NONCE_ANON = 'bss_anonymise_contact';

    public function __construct() {
        add_action( 'admin_post_bss_save_contact', [ $this, 'handle_save' ] );
        add_action( 'admin_post_bss_anonymise_contact', [ $this, 'handle_anonymise' ] );
    }

    public function render(): void {
        RequestGuard::require_capability( 'bss_manage_contacts' );

        $id      = (int) ( $_GET['id'] ?? 0 );
        $service = new ContactService();
        $contact = $id > 0 ? $service->get_by_id( $id ) : null;

        if ( $id > 0 && ! $contact ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Contact not found.', 'battle-shield-sponsorship' ) . '</p></div>';
            return;
        }

        if ( $contact && (int) ( $contact->anonymised ?? 0 ) ) {
            echo '<div class="wrap"><p>' . esc_html__( 'This contact has been anonymised and cannot be edited.', 'battle-shield-sponsorship' ) . '</p>';
            echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=bss-contacts' ) ) . '">&larr; ' . esc_html__( 'Back to Contacts', 'battle-shield-sponsorship' ) . '</a></p></div>';
            return;
        }

        if ( isset( $_GET['saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Contact saved.', 'battle-shield-sponsorship' ) . '</p></div>';
        }

        $contact_name  = (string) ( $contact->contact_name ?? '' );
        $display_name  = (string) ( $contact->display_name ?? '' );
        $email         = (string) ( $contact->email ?? '' );
        $phone         = (string) ( $contact->phone ?? '' );
        $website       = (string) ( $contact->website_url ?? '' );
        $marketing     = ! empty( $contact->marketing_opt_in );
        $gdpr_status   = (string) ( $contact->gdpr_status ?? 'active' );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html( $id > 0 ? __( 'Edit Contact', 'battle-shield-sponsorship' ) : __( 'Add Contact', 'battle-shield-sponsorship' ) ) . '</h1>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bss_save_contact" />';
        echo '<input type="hidden" name="contact_id" value="' . $id . '" />';
        wp_nonce_field( self::NONCE_SAVE );

        echo '<table class="form-table">';
        $this->row( 'contact_name', __( 'Full name', 'battle-shield-sponsorship' ),
            '<input name="contact_name" id="contact_name" type="text" class="regular-text" required value="' . esc_attr( $contact_name ) . '" />' );
        $this->row( 'display_name', __( 'Display name', 'battle-shield-sponsorship' ),
            '<input name="display_name" id="display_name" type="text" class="regular-text" value="' . esc_attr( $display_name ) . '" />'
            . '<p class="description">' . esc_html__( 'Name shown publicly on shields (defaults to full name if blank).', 'battle-shield-sponsorship' ) . '</p>' );
        $this->row( 'email', __( 'Email', 'battle-shield-sponsorship' ),
            '<input name="email" id="email" type="email" class="regular-text" required value="' . esc_attr( $email ) . '" />' );
        $this->row( 'phone', __( 'Phone', 'battle-shield-sponsorship' ),
            '<input name="phone" id="phone" type="tel" class="regular-text" value="' . esc_attr( $phone ) . '" />' );
        $this->row( 'website_url', __( 'Website', 'battle-shield-sponsorship' ),
            '<input name="website_url" id="website_url" type="url" class="regular-text" value="' . esc_attr( $website ) . '" />' );
        $this->row( 'marketing_opt_in', __( 'Marketing opt-in', 'battle-shield-sponsorship' ),
            '<label><input name="marketing_opt_in" id="marketing_opt_in" type="checkbox" value="1" ' . checked( $marketing, true, false ) . ' /> '
            . esc_html__( 'Consented to marketing', 'battle-shield-sponsorship' ) . '</label>' );
        $this->row( 'gdpr_status', __( 'GDPR status', 'battle-shield-sponsorship' ),
            '<select name="gdpr_status" id="gdpr_status">'
            . '<option value="active" ' . selected( $gdpr_status, 'active', false ) . '>' . esc_html__( 'Active', 'battle-shield-sponsorship' ) . '</option>'
            . '<option value="removal_requested" ' . selected( $gdpr_status, 'removal_requested', false ) . '>' . esc_html__( 'Removal requested', 'battle-shield-sponsorship' ) . '</option>'
            . '</select>' );
        echo '</table>';

        submit_button( __( 'Save Contact', 'battle-shield-sponsorship' ) );
        echo '</form>';

        if ( $id > 0 && current_user_can( 'bss_manage_gdpr' ) ) {
            echo '<hr />';
            echo '<h2 style="color:#c62828;">' . esc_html__( 'GDPR Anonymisation', 'battle-shield-sponsorship' ) . '</h2>';
            echo '<p>' . esc_html__( 'Anonymising a contact permanently removes their personal data. Sponsorship records are preserved with an anonymised reference. This cannot be undone.', 'battle-shield-sponsorship' ) . '</p>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Permanently anonymise this contact? This cannot be undone.', 'battle-shield-sponsorship' ) ) . '\')">';
            echo '<input type="hidden" name="action" value="bss_anonymise_contact" />';
            echo '<input type="hidden" name="contact_id" value="' . $id . '" />';
            wp_nonce_field( self::NONCE_ANON );
            echo '<p><button type="submit" class="button button-secondary" style="border-color:#c62828;color:#c62828;">'
                . esc_html__( 'Anonymise Contact', 'battle-shield-sponsorship' ) . '</button></p>';
            echo '</form>';
        }

        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=bss-contacts' ) ) . '">&larr; '
            . esc_html__( 'Back to Contacts', 'battle-shield-sponsorship' ) . '</a></p>';
        echo '</div>';
    }

    public function handle_save(): void {
        RequestGuard::require_capability( 'bss_manage_contacts' );
        RequestGuard::verify_admin_nonce( self::NONCE_SAVE );

        $id      = (int) ( $_POST['contact_id'] ?? 0 );
        $service = new ContactService();

        $data = [
            'contact_name'   => sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) ),
            'display_name'   => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
            'email'          => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
            'phone'          => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
            'website_url'    => esc_url_raw( wp_unslash( $_POST['website_url'] ?? '' ) ),
            'marketing_opt_in' => isset( $_POST['marketing_opt_in'] ) ? 1 : 0,
            'gdpr_status'    => sanitize_key( wp_unslash( $_POST['gdpr_status'] ?? 'active' ) ),
        ];

        if ( $id > 0 ) {
            $service->update( $id, $data );
        } else {
            $id = $service->create( $data );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'bss-contact-edit', 'id' => $id, 'saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_anonymise(): void {
        RequestGuard::require_capability( 'bss_manage_gdpr' );
        RequestGuard::verify_admin_nonce( self::NONCE_ANON );

        $id = (int) ( $_POST['contact_id'] ?? 0 );
        if ( $id > 0 ) {
            ( new GdprService() )->anonymise_contact( $id );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'bss-contacts', 'anonymised' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private function row( string $id, string $label, string $field ): void {
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th>';
        echo '<td>' . $field . '</td>';
        echo '</tr>';
    }
}
