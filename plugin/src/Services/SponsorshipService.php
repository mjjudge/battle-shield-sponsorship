<?php

namespace BattleShieldSponsorship\Services;

use BattleShieldSponsorship\Audit\Logger;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class SponsorshipService {

    public function get_by_id( int $id ): ?object {
        global $wpdb;
        $table = Schema::table_name( 'sponsorships' );
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
        return $row ?: null;
    }

    public function get_by_stripe_session( string $session_id ): ?object {
        global $wpdb;
        $table = Schema::table_name( 'sponsorships' );
        $row   = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE stripe_session_id = %s LIMIT 1",
            $session_id
        ) );
        return $row ?: null;
    }

    public function get_by_stripe_charge( string $charge_id ): ?object {
        global $wpdb;
        $table = Schema::table_name( 'sponsorships' );
        $row   = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE stripe_charge_id = %s LIMIT 1",
            $charge_id
        ) );
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $filters  Supports: campaign_id, contact_id, payment_status, artwork_status
     * @return object[]
     */
    public function get_all( array $filters = [] ): array {
        global $wpdb;
        $table  = Schema::table_name( 'sponsorships' );
        $wheres = [];
        $params = [];

        if ( ! empty( $filters['campaign_id'] ) ) {
            $wheres[] = 'campaign_id = %d';
            $params[] = (int) $filters['campaign_id'];
        }

        if ( ! empty( $filters['contact_id'] ) ) {
            $wheres[] = 'contact_id = %d';
            $params[] = (int) $filters['contact_id'];
        }

        if ( ! empty( $filters['payment_status'] ) ) {
            $wheres[] = 'payment_status = %s';
            $params[] = $filters['payment_status'];
        }

        if ( ! empty( $filters['artwork_status'] ) ) {
            $wheres[] = 'artwork_status = %s';
            $params[] = $filters['artwork_status'];
        }

        $where_sql = $wheres ? 'WHERE ' . implode( ' AND ', $wheres ) : '';
        $sql       = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC";

        $rows = $params
            ? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) )
            : $wpdb->get_results( $sql );

        return is_array( $rows ) ? $rows : [];
    }

    /**
     * Creates a pending sponsorship record and returns its ID.
     *
     * @param array<string, mixed> $data
     */
    public function create_pending( array $data ): int {
        global $wpdb;
        $table = Schema::table_name( 'sponsorships' );
        $now   = current_time( 'mysql', true );

        $row = [
            'campaign_id'    => (int) $data['campaign_id'],
            'contact_id'     => (int) $data['contact_id'],
            'display_name'   => sanitize_text_field( $data['display_name'] ?? '' ),
            'sponsor_text'   => sanitize_textarea_field( $data['sponsor_text'] ?? '' ) ?: null,
            'logo_attachment_id' => ! empty( $data['logo_attachment_id'] ) ? (int) $data['logo_attachment_id'] : null,
            'payment_method' => in_array( $data['payment_method'] ?? 'stripe', [ 'stripe', 'bank_transfer', 'cash', 'other' ], true ) ? $data['payment_method'] : 'stripe',
            'total_amount'   => number_format( max( 0.0, (float) ( $data['total_amount'] ?? 0 ) ), 2, '.', '' ),
            'payment_status' => 'pending',
            'refund_status'  => 'none',
            'gift_aid_declared' => empty( $data['gift_aid_declared'] ) ? 0 : 1,
            'artwork_status' => 'incomplete',
            'created_at'     => $now,
            'updated_at'     => $now,
        ];

        $wpdb->insert( $table, $row );
        $id = (int) $wpdb->insert_id;

        Logger::log( 'sponsorship_created', 'sponsorship', $id, null, $row );

        return $id;
    }

    public function mark_paid( int $id, string $payment_intent_id = '', string $charge_id = '' ): void {
        global $wpdb;
        $table  = Schema::table_name( 'sponsorships' );
        $before = (array) $this->get_by_id( $id );

        $update = [
            'payment_status'             => 'paid',
            'stripe_payment_intent_id'   => $payment_intent_id ?: ( $before['stripe_payment_intent_id'] ?? null ),
            'stripe_charge_id'           => $charge_id ?: ( $before['stripe_charge_id'] ?? null ),
            'updated_at'                 => current_time( 'mysql', true ),
        ];

        $wpdb->update( $table, $update, [ 'id' => $id ] );

        // Mark the shield items as paid.
        $this->update_items_status( $id, 'paid_complete' );
        $this->refresh_artwork_status( $id );

        Logger::log( 'payment_received', 'sponsorship', $id, $before, $update );

        do_action( 'bss_payment_confirmed', $id );
    }

    public function mark_stripe_session( int $id, string $session_id ): void {
        global $wpdb;
        $wpdb->update(
            Schema::table_name( 'sponsorships' ),
            [ 'stripe_session_id' => $session_id, 'updated_at' => current_time( 'mysql', true ) ],
            [ 'id' => $id ]
        );
    }

    public function mark_failed( int $id ): void {
        global $wpdb;
        $table = Schema::table_name( 'sponsorships' );
        $wpdb->update( $table, [ 'payment_status' => 'failed', 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => $id ] );
        $this->update_items_status( $id, 'refunded' );
        Logger::log( 'payment_failed', 'sponsorship', $id );
    }

    public function mark_abandoned( int $id ): void {
        global $wpdb;
        $table = Schema::table_name( 'sponsorships' );
        $wpdb->update( $table, [ 'payment_status' => 'abandoned', 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => $id ] );
        $this->update_items_status( $id, 'refunded' );
        Logger::log( 'payment_abandoned', 'sponsorship', $id );
    }

    public function mark_refunded( int $id ): void {
        global $wpdb;
        $table = Schema::table_name( 'sponsorships' );
        $wpdb->update( $table, [ 'payment_status' => 'refunded', 'refund_status' => 'full', 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => $id ] );
        $this->update_items_status( $id, 'refunded' );
        Logger::log( 'refund_issued', 'sponsorship', $id );
    }

    /** @param array<string, mixed> $data */
    public function update_artwork( int $id, array $data ): void {
        global $wpdb;
        $table  = Schema::table_name( 'sponsorships' );
        $before = (array) $this->get_by_id( $id );

        $update = [
            'display_name' => sanitize_text_field( $data['display_name'] ?? (string) ( $before['display_name'] ?? '' ) ),
            'sponsor_text' => sanitize_textarea_field( $data['sponsor_text'] ?? (string) ( $before['sponsor_text'] ?? '' ) ) ?: null,
            'logo_attachment_id' => ! empty( $data['logo_attachment_id'] ) ? (int) $data['logo_attachment_id'] : ( $before['logo_attachment_id'] ?? null ),
            'updated_at'   => current_time( 'mysql', true ),
        ];

        $wpdb->update( $table, $update, [ 'id' => $id ] );
        $this->refresh_artwork_status( $id );

        Logger::log( 'sponsorship_artwork_updated', 'sponsorship', $id, $before, $update );
    }

    public function refresh_artwork_status( int $id ): void {
        global $wpdb;
        $table        = Schema::table_name( 'sponsorships' );
        $sponsorship  = $this->get_by_id( $id );

        if ( ! $sponsorship ) {
            return;
        }

        $is_complete = (
            ! empty( $sponsorship->display_name )
        );

        $status = $is_complete ? 'complete' : 'incomplete';
        $wpdb->update( $table, [ 'artwork_status' => $status, 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => $id ] );
    }

    /** @return object[] */
    public function get_items( int $sponsorship_id ): array {
        global $wpdb;
        $table = Schema::table_name( 'sponsorship_items' );
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE sponsorship_id = %d",
            $sponsorship_id
        ) );
        return is_array( $rows ) ? $rows : [];
    }

    public function add_item( int $sponsorship_id, int $shield_id, float $price ): int {
        global $wpdb;
        $table = Schema::table_name( 'sponsorship_items' );
        $now   = current_time( 'mysql', true );

        $wpdb->insert( $table, [
            'sponsorship_id' => $sponsorship_id,
            'shield_id'      => $shield_id,
            'price_paid'     => number_format( $price, 2, '.', '' ),
            'status'         => 'reserved',
            'created_at'     => $now,
            'updated_at'     => $now,
        ] );

        return (int) $wpdb->insert_id;
    }

    private function update_items_status( int $sponsorship_id, string $status ): void {
        global $wpdb;
        $table = Schema::table_name( 'sponsorship_items' );
        $wpdb->update(
            $table,
            [ 'status' => $status, 'updated_at' => current_time( 'mysql', true ) ],
            [ 'sponsorship_id' => $sponsorship_id ]
        );

        if ( in_array( $status, [ 'paid_complete', 'paid_incomplete' ], true ) ) {
            $shield_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT shield_id FROM {$table} WHERE sponsorship_id = %d",
                $sponsorship_id
            ) );
            $shields_table = Schema::table_name( 'shields' );
            foreach ( ( is_array( $shield_ids ) ? $shield_ids : [] ) as $shield_id ) {
                $wpdb->update( $shields_table, [ 'physical_state' => 'sponsored', 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => (int) $shield_id ] );
            }
        }

        if ( 'refunded' === $status ) {
            $shield_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT shield_id FROM {$table} WHERE sponsorship_id = %d",
                $sponsorship_id
            ) );
            $shields_table = Schema::table_name( 'shields' );
            foreach ( ( is_array( $shield_ids ) ? $shield_ids : [] ) as $shield_id ) {
                $wpdb->update( $shields_table, [ 'physical_state' => 'available', 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => (int) $shield_id ] );
            }
        }
    }
}
