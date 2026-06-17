<?php

namespace BattleShieldSponsorship\Services;

use BattleShieldSponsorship\Audit\Logger;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class ContactService {

    public function get_by_id( int $id ): ?object {
        global $wpdb;
        $table = Schema::table_name( 'contacts' );
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
        return $row ?: null;
    }

    public function find_by_email( string $email ): ?object {
        global $wpdb;
        $table = Schema::table_name( 'contacts' );
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s LIMIT 1", sanitize_email( $email ) ) );
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $filters  Supports: search, marketing_opt_in, gdpr_status
     * @return object[]
     */
    public function get_all( array $filters = [] ): array {
        global $wpdb;
        $table  = Schema::table_name( 'contacts' );
        $wheres = [ 'anonymised = 0' ];
        $params = [];

        if ( ! empty( $filters['search'] ) ) {
            $like     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
            $wheres[] = '(contact_name LIKE %s OR display_name LIKE %s OR email LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ( isset( $filters['marketing_opt_in'] ) ) {
            $wheres[] = 'marketing_opt_in = %d';
            $params[] = (int) $filters['marketing_opt_in'];
        }

        if ( ! empty( $filters['gdpr_status'] ) ) {
            $wheres[] = 'gdpr_status = %s';
            $params[] = $filters['gdpr_status'];
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $wheres );
        $sql       = "SELECT * FROM {$table} {$where_sql} ORDER BY contact_name ASC, id DESC";

        $rows = $params
            ? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) )
            : $wpdb->get_results( $sql );

        return is_array( $rows ) ? $rows : [];
    }

    /**
     * Find or create a contact by email. If found, updates name fields if they've changed.
     *
     * @param array<string, mixed> $data
     */
    public function find_or_create( array $data ): int {
        $email   = sanitize_email( $data['email'] ?? '' );
        $contact = $this->find_by_email( $email );

        if ( $contact ) {
            return (int) $contact->id;
        }

        return $this->create( $data );
    }

    /** @param array<string, mixed> $data */
    public function create( array $data ): int {
        global $wpdb;
        $table = Schema::table_name( 'contacts' );
        $now   = current_time( 'mysql', true );

        $opt_in = ! empty( $data['marketing_opt_in'] );
        $row    = [
            'contact_name'       => sanitize_text_field( $data['contact_name'] ?? '' ),
            'display_name'       => sanitize_text_field( $data['display_name'] ?? '' ),
            'email'              => sanitize_email( $data['email'] ?? '' ),
            'phone'              => sanitize_text_field( $data['phone'] ?? '' ) ?: null,
            'website_url'        => esc_url_raw( $data['website_url'] ?? '' ) ?: null,
            'marketing_opt_in'   => $opt_in ? 1 : 0,
            'marketing_opt_in_at' => $opt_in ? $now : null,
            'gdpr_status'        => 'active',
            'anonymised'         => 0,
            'created_at'         => $now,
            'updated_at'         => $now,
        ];

        $wpdb->insert( $table, $row );
        $id = (int) $wpdb->insert_id;

        Logger::log( 'contact_created', 'contact', $id, null, array_diff_key( $row, [ 'email' => true ] ) );

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update( int $id, array $data ): void {
        global $wpdb;
        $table  = Schema::table_name( 'contacts' );
        $before = (array) $this->get_by_id( $id );

        $opt_in_changed = isset( $data['marketing_opt_in'] ) && (bool) $data['marketing_opt_in'] !== (bool) ( $before['marketing_opt_in'] ?? false );
        $opt_in         = isset( $data['marketing_opt_in'] ) ? ! empty( $data['marketing_opt_in'] ) : (bool) ( $before['marketing_opt_in'] ?? false );

        $row = [
            'contact_name'       => sanitize_text_field( $data['contact_name'] ?? $before['contact_name'] ?? '' ),
            'display_name'       => sanitize_text_field( $data['display_name'] ?? $before['display_name'] ?? '' ),
            'email'              => sanitize_email( $data['email'] ?? $before['email'] ?? '' ),
            'phone'              => sanitize_text_field( $data['phone'] ?? $before['phone'] ?? '' ) ?: null,
            'website_url'        => esc_url_raw( $data['website_url'] ?? $before['website_url'] ?? '' ) ?: null,
            'marketing_opt_in'   => $opt_in ? 1 : 0,
            'marketing_opt_in_at' => ( $opt_in && $opt_in_changed ) ? current_time( 'mysql', true ) : ( $before['marketing_opt_in_at'] ?? null ),
            'updated_at'         => current_time( 'mysql', true ),
        ];

        $wpdb->update( $table, $row, [ 'id' => $id ] );
        Logger::log( 'contact_updated', 'contact', $id, array_diff_key( $before, [ 'email' => true ] ), array_diff_key( $row, [ 'email' => true ] ) );
    }

    /** @return object[] */
    public function get_opted_in(): array {
        global $wpdb;
        $table = Schema::table_name( 'contacts' );
        $rows  = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE marketing_opt_in = 1 AND anonymised = 0 ORDER BY contact_name ASC"
        );
        return is_array( $rows ) ? $rows : [];
    }
}
