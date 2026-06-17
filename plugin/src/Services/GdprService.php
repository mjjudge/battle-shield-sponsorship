<?php

namespace BattleShieldSponsorship\Services;

use BattleShieldSponsorship\Audit\Logger;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class GdprService {

    /**
     * Anonymise a contact's personal data while preserving sponsorship records.
     */
    public function anonymise_contact( int $contact_id ): bool {
        global $wpdb;

        $contacts_table = Schema::table_name( 'contacts' );
        $contact        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$contacts_table} WHERE id = %d", $contact_id ) );

        if ( ! $contact ) {
            return false;
        }

        if ( (int) $contact->anonymised === 1 ) {
            return true;
        }

        $before = (array) $contact;

        $wpdb->update(
            $contacts_table,
            [
                'contact_name'       => '',
                'email'              => '',
                'phone'              => null,
                'website_url'        => null,
                'marketing_opt_in'   => 0,
                'gdpr_status'        => 'anonymised',
                'anonymised'         => 1,
                'updated_at'         => current_time( 'mysql', true ),
            ],
            [ 'id' => $contact_id ]
        );

        Logger::log(
            'gdpr_anonymisation',
            'contact',
            $contact_id,
            [ 'had_email' => '' !== (string) $before['email'] ],
            [ 'anonymised' => true ]
        );

        return true;
    }

    /**
     * Export a contact's data as an array (for GDPR subject access requests).
     *
     * @return array<string, mixed>
     */
    public function export_contact_data( int $contact_id ): array {
        global $wpdb;

        $contact = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . Schema::table_name( 'contacts' ) . " WHERE id = %d",
            $contact_id
        ) );

        if ( ! $contact ) {
            return [];
        }

        $sponsorships = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . Schema::table_name( 'sponsorships' ) . " WHERE contact_id = %d ORDER BY created_at DESC",
            $contact_id
        ) );

        return [
            'contact'      => (array) $contact,
            'sponsorships' => is_array( $sponsorships ) ? $sponsorships : [],
        ];
    }
}
