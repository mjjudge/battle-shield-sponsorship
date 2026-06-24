<?php

namespace BattleShieldSponsorship\Services;

use BattleShieldSponsorship\Audit\Logger;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class ShieldService {

    public function get_by_id( int $id ): ?object {
        global $wpdb;
        $table = Schema::table_name( 'shields' );
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $filters  Supports: side, physical_state, search
     * @return object[]
     */
    public function get_all( array $filters = [] ): array {
        global $wpdb;
        $table  = Schema::table_name( 'shields' );
        $wheres = [];
        $params = [];

        if ( ! empty( $filters['side'] ) ) {
            $wheres[] = 'side = %s';
            $params[] = $filters['side'];
        }

        if ( ! empty( $filters['physical_state'] ) ) {
            $wheres[] = 'physical_state = %s';
            $params[] = $filters['physical_state'];
        }

        if ( ! empty( $filters['search'] ) ) {
            $wheres[] = '(name LIKE %s OR description LIKE %s)';
            $like     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = $wheres ? 'WHERE ' . implode( ' AND ', $wheres ) : '';
        $sql       = "SELECT * FROM {$table} {$where_sql} ORDER BY name ASC";

        $rows = $params
            ? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) )
            : $wpdb->get_results( $sql );

        return is_array( $rows ) ? $rows : [];
    }

    /** @param array<string, mixed> $data */
    public function create( array $data ): int {
        global $wpdb;
        $table = Schema::table_name( 'shields' );
        $now   = current_time( 'mysql', true );

        $row = $this->sanitize_shield_data( $data );
        $row['created_at'] = $now;
        $row['updated_at'] = $now;

        $wpdb->insert( $table, $row );
        $id = (int) $wpdb->insert_id;

        Logger::log( 'shield_created', 'shield', $id, null, $row );

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update( int $id, array $data ): void {
        global $wpdb;
        $table  = Schema::table_name( 'shields' );
        $before = (array) $this->get_by_id( $id );

        $row              = $this->sanitize_shield_data( array_merge( $before, $data ) );
        $row['updated_at'] = current_time( 'mysql', true );

        $wpdb->update( $table, $row, [ 'id' => $id ] );
        Logger::log( 'shield_updated', 'shield', $id, $before, $row );
    }

    public function set_physical_state( int $id, string $state ): void {
        global $wpdb;
        $valid_states = [ 'available', 'reserved', 'sponsored', 'unavailable' ];
        if ( ! in_array( $state, $valid_states, true ) ) {
            return;
        }

        $table  = Schema::table_name( 'shields' );
        $before = (array) $this->get_by_id( $id );
        $wpdb->update( $table, [ 'physical_state' => $state, 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => $id ] );
        Logger::log( 'shield_state_changed', 'shield', $id, $before, [ 'physical_state' => $state ] );
    }

    public function count_by_state(): object {
        global $wpdb;
        $table = Schema::table_name( 'shields' );
        $rows  = $wpdb->get_results(
            "SELECT physical_state, COUNT(*) AS total FROM {$table} GROUP BY physical_state"
        );

        $counts = (object) [
            'available'   => 0,
            'reserved'    => 0,
            'sponsored'   => 0,
            'unavailable' => 0,
        ];

        foreach ( ( is_array( $rows ) ? $rows : [] ) as $row ) {
            $state          = (string) $row->physical_state;
            $counts->$state = (int) $row->total;
        }

        return $counts;
    }

    public function find_by_name_and_side( string $name, string $side ): ?object {
        global $wpdb;
        $table = Schema::table_name( 'shields' );
        $row   = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE name = %s AND side = %s LIMIT 1",
            $name,
            $side
        ) );
        return $row ?: null;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function sanitize_shield_data( array $data ): array {
        $valid_sides  = [ 'royals', 'rebels', 'baron', 'royalist', 'other' ];
        $valid_states = [ 'available', 'reserved', 'sponsored', 'unavailable' ];

        return [
            'name'           => sanitize_text_field( $data['name'] ?? '' ),
            'side'           => in_array( $data['side'] ?? '', $valid_sides, true ) ? $data['side'] : 'royals',
            'description'    => sanitize_textarea_field( $data['description'] ?? '' ),
            'birth_date'     => sanitize_text_field( $data['birth_date'] ?? '' ) ?: null,
            'death_date'     => sanitize_text_field( $data['death_date'] ?? '' ) ?: null,
            'suggested_price' => number_format( max( 0.0, (float) ( $data['suggested_price'] ?? 0 ) ), 2, '.', '' ),
            'image_id'       => ! empty( $data['image_id'] ) ? (int) $data['image_id'] : null,
            'physical_state' => in_array( $data['physical_state'] ?? '', $valid_states, true ) ? $data['physical_state'] : 'available',
            'notes'          => sanitize_textarea_field( $data['notes'] ?? '' ),
        ];
    }
}
