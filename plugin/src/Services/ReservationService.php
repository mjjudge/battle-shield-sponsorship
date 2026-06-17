<?php

namespace BattleShieldSponsorship\Services;

use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class ReservationService {

    /**
     * Reserve a shield for the given session key.
     * Returns true on success, false if the shield is already reserved or sponsored.
     */
    public function reserve( int $shield_id, string $session_key, int $timeout_minutes = 30 ): bool {
        global $wpdb;

        $this->expire_stale();

        $reservations_table = Schema::table_name( 'reservations' );
        $shields_table      = Schema::table_name( 'shields' );

        $shield = $wpdb->get_row( $wpdb->prepare(
            "SELECT physical_state FROM {$shields_table} WHERE id = %d",
            $shield_id
        ) );

        if ( ! $shield || 'available' !== (string) $shield->physical_state ) {
            return false;
        }

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$reservations_table} WHERE shield_id = %d AND expires_at > %s LIMIT 1",
            $shield_id,
            current_time( 'mysql', true )
        ) );

        if ( $existing ) {
            return false;
        }

        $expires_at = gmdate( 'Y-m-d H:i:s', time() + ( $timeout_minutes * 60 ) );

        $wpdb->insert( $reservations_table, [
            'shield_id'   => $shield_id,
            'session_key' => sanitize_text_field( $session_key ),
            'expires_at'  => $expires_at,
            'created_at'  => current_time( 'mysql', true ),
        ] );

        $wpdb->update( $shields_table, [ 'physical_state' => 'reserved', 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => $shield_id ] );

        return true;
    }

    public function release( int $shield_id, string $session_key ): void {
        global $wpdb;

        $reservations_table = Schema::table_name( 'reservations' );
        $shields_table      = Schema::table_name( 'shields' );

        $wpdb->delete( $reservations_table, [
            'shield_id'   => $shield_id,
            'session_key' => $session_key,
        ] );

        $still_reserved = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$reservations_table} WHERE shield_id = %d AND expires_at > %s LIMIT 1",
            $shield_id,
            current_time( 'mysql', true )
        ) );

        if ( ! $still_reserved ) {
            $wpdb->update( $shields_table, [ 'physical_state' => 'available', 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => $shield_id ] );
        }
    }

    public function attach_sponsorship( string $session_key, int $sponsorship_id ): void {
        global $wpdb;
        $table = Schema::table_name( 'reservations' );
        $wpdb->update( $table, [ 'sponsorship_id' => $sponsorship_id ], [ 'session_key' => $session_key ] );
    }

    /** @return int[] Shield IDs reserved by this session */
    public function get_session_shields( string $session_key ): array {
        global $wpdb;
        $table = Schema::table_name( 'reservations' );
        $ids   = $wpdb->get_col( $wpdb->prepare(
            "SELECT shield_id FROM {$table} WHERE session_key = %s AND expires_at > %s",
            $session_key,
            current_time( 'mysql', true )
        ) );
        return array_map( 'intval', is_array( $ids ) ? $ids : [] );
    }

    public function expire_stale(): void {
        global $wpdb;

        $reservations_table = Schema::table_name( 'reservations' );
        $shields_table      = Schema::table_name( 'shields' );

        $expired_shield_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT shield_id FROM {$reservations_table} WHERE expires_at <= %s",
            current_time( 'mysql', true )
        ) );

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$reservations_table} WHERE expires_at <= %s",
            current_time( 'mysql', true )
        ) );

        foreach ( $expired_shield_ids as $shield_id ) {
            $still_active = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$reservations_table} WHERE shield_id = %d LIMIT 1",
                (int) $shield_id
            ) );

            if ( ! $still_active ) {
                $current_state = $wpdb->get_var( $wpdb->prepare(
                    "SELECT physical_state FROM {$shields_table} WHERE id = %d",
                    (int) $shield_id
                ) );

                if ( 'reserved' === (string) $current_state ) {
                    $wpdb->update( $shields_table, [ 'physical_state' => 'available', 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => (int) $shield_id ] );
                }
            }
        }
    }
}
