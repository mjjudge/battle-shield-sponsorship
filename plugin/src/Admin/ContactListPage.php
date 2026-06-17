<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\ContactService;

defined( 'ABSPATH' ) || exit;

class ContactListPage {

    public function render(): void {
        RequestGuard::require_capability( 'bss_manage_contacts' );

        $search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
        $anon   = sanitize_text_field( wp_unslash( $_GET['anonymised'] ?? '' ) );

        $filters = array_filter( [
            'search'     => $search,
            'anonymised' => '' !== $anon ? (int) $anon : null,
        ], fn( $v ) => null !== $v && '' !== $v );

        $contacts = ( new ContactService() )->get_all( $filters );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Contacts', 'battle-shield-sponsorship' ) . '</h1>';

        echo '<form method="get" style="margin-bottom:16px;">';
        echo '<input type="hidden" name="page" value="bss-contacts" />';
        echo '<input type="search" name="search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Name or email…', 'battle-shield-sponsorship' ) . '" style="margin-right:8px;" />';
        echo '<select name="anonymised" style="margin-right:8px;">';
        echo '<option value="">' . esc_html__( 'All contacts', 'battle-shield-sponsorship' ) . '</option>';
        echo '<option value="0" ' . selected( $anon, '0', false ) . '>' . esc_html__( 'Active', 'battle-shield-sponsorship' ) . '</option>';
        echo '<option value="1" ' . selected( $anon, '1', false ) . '>' . esc_html__( 'Anonymised', 'battle-shield-sponsorship' ) . '</option>';
        echo '</select>';
        submit_button( __( 'Filter', 'battle-shield-sponsorship' ), 'secondary', 'submit', false );
        echo '</form>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Name', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Email', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Phone', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Marketing', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'GDPR', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'battle-shield-sponsorship' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $contacts ) ) {
            echo '<tr><td colspan="6">' . esc_html__( 'No contacts found.', 'battle-shield-sponsorship' ) . '</td></tr>';
        }

        foreach ( $contacts as $contact ) {
            $is_anon = (int) ( $contact->anonymised ?? 0 );
            echo '<tr>';
            echo '<td>' . esc_html( (string) $contact->contact_name ) . ( $is_anon ? ' <em style="color:#aaa;">(' . esc_html__( 'anonymised', 'battle-shield-sponsorship' ) . ')</em>' : '' ) . '</td>';
            echo '<td>' . esc_html( (string) $contact->email ) . '</td>';
            echo '<td>' . esc_html( (string) ( $contact->phone ?? '' ) ) . '</td>';
            echo '<td>' . ( $contact->marketing_opt_in ? '<span style="color:#2e7d32;">&#10003;</span>' : '<span style="color:#aaa;">—</span>' ) . '</td>';
            echo '<td>' . esc_html( ucfirst( (string) ( $contact->gdpr_status ?? 'active' ) ) ) . '</td>';
            if ( ! $is_anon ) {
                echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=bss-contact-edit&id=' . (int) $contact->id ) ) . '">'
                    . esc_html__( 'Edit', 'battle-shield-sponsorship' ) . '</a></td>';
            } else {
                echo '<td>—</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }
}
