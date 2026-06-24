<?php

namespace BattleShieldSponsorship\Services;

use BattleShieldSponsorship\Audit\Logger;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class CampaignService {

    public function get_active(): ?object {
        global $wpdb;
        $table = Schema::table_name( 'campaigns' );
        $row   = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY event_start_date DESC, event_date DESC LIMIT 1", 'active' )
        );
        return $row ?: null;
    }

    public function get_by_id( int $id ): ?object {
        global $wpdb;
        $table = Schema::table_name( 'campaigns' );
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
        return $row ?: null;
    }

    /** @return object[] */
    public function get_all(): array {
        global $wpdb;
        $table = Schema::table_name( 'campaigns' );
        $rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY event_start_date DESC, event_date DESC" );
        return is_array( $rows ) ? $rows : [];
    }

    /** @param array<string, mixed> $data */
    public function create( array $data ): int {
        global $wpdb;
        $table = Schema::table_name( 'campaigns' );
        $now   = current_time( 'mysql', true );

        $row = [
            'name'                      => sanitize_text_field( $data['name'] ?? '' ),
            'event_start_date'          => sanitize_text_field( $data['event_start_date'] ?? '' ) ?: null,
            'event_end_date'            => sanitize_text_field( $data['event_end_date'] ?? '' ) ?: null,
            'event_date'                => sanitize_text_field( $data['event_date'] ?? '' ) ?: null,
            'sales_open_date'           => sanitize_text_field( $data['sales_open_date'] ?? '' ) ?: null,
            'artwork_cutoff_date'       => sanitize_text_field( $data['artwork_cutoff_date'] ?? '' ) ?: null,
            'reminder_frequency_days'   => max( 1, (int) ( $data['reminder_frequency_days'] ?? 2 ) ),
            'final_reminder_days_before' => max( 1, (int) ( $data['final_reminder_days_before'] ?? 1 ) ),
            'default_price'             => number_format( max( 0.0, (float) ( $data['default_price'] ?? 0 ) ), 2, '.', '' ),
            'reservation_timeout_minutes' => max( 5, (int) ( $data['reservation_timeout_minutes'] ?? 30 ) ),
            'gift_aid_enabled'          => empty( $data['gift_aid_enabled'] ) ? 0 : 1,
            'status'                    => in_array( $data['status'] ?? '', [ 'active', 'inactive' ], true ) ? $data['status'] : 'inactive',
            'created_at'                => $now,
            'updated_at'                => $now,
        ];

        $wpdb->insert( $table, $row );
        $id = (int) $wpdb->insert_id;

        Logger::log( 'campaign_created', 'campaign', $id, null, $row );

        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update( int $id, array $data ): void {
        global $wpdb;
        $table  = Schema::table_name( 'campaigns' );
        $before = (array) $this->get_by_id( $id );

        $row = [
            'name'                      => sanitize_text_field( $data['name'] ?? $before['name'] ?? '' ),
            'event_start_date'          => sanitize_text_field( $data['event_start_date'] ?? $before['event_start_date'] ?? '' ) ?: null,
            'event_end_date'            => sanitize_text_field( $data['event_end_date'] ?? $before['event_end_date'] ?? '' ) ?: null,
            'event_date'                => sanitize_text_field( $data['event_date'] ?? $before['event_date'] ?? '' ) ?: null,
            'sales_open_date'           => sanitize_text_field( $data['sales_open_date'] ?? $before['sales_open_date'] ?? '' ) ?: null,
            'artwork_cutoff_date'       => sanitize_text_field( $data['artwork_cutoff_date'] ?? $before['artwork_cutoff_date'] ?? '' ) ?: null,
            'reminder_frequency_days'   => max( 1, (int) ( $data['reminder_frequency_days'] ?? $before['reminder_frequency_days'] ?? 2 ) ),
            'final_reminder_days_before' => max( 1, (int) ( $data['final_reminder_days_before'] ?? $before['final_reminder_days_before'] ?? 1 ) ),
            'default_price'             => number_format( max( 0.0, (float) ( $data['default_price'] ?? $before['default_price'] ?? 0 ) ), 2, '.', '' ),
            'reservation_timeout_minutes' => max( 5, (int) ( $data['reservation_timeout_minutes'] ?? $before['reservation_timeout_minutes'] ?? 30 ) ),
            'gift_aid_enabled'          => isset( $data['gift_aid_enabled'] ) ? ( empty( $data['gift_aid_enabled'] ) ? 0 : 1 ) : (int) ( $before['gift_aid_enabled'] ?? 0 ),
            'status'                    => in_array( $data['status'] ?? '', [ 'active', 'inactive' ], true ) ? $data['status'] : (string) ( $before['status'] ?? 'inactive' ),
            'updated_at'                => current_time( 'mysql', true ),
        ];

        $wpdb->update( $table, $row, [ 'id' => $id ] );
        Logger::log( 'campaign_updated', 'campaign', $id, $before, $row );
    }

    public function set_active( int $id ): void {
        global $wpdb;
        $table = Schema::table_name( 'campaigns' );
        $wpdb->update( $table, [ 'status' => 'inactive', 'updated_at' => current_time( 'mysql', true ) ], [ 'status' => 'active' ] );
        $wpdb->update( $table, [ 'status' => 'active', 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => $id ] );
        Logger::log( 'campaign_activated', 'campaign', $id );
    }

    public function is_past_cutoff( int $campaign_id ): bool {
        $campaign = $this->get_by_id( $campaign_id );
        if ( ! $campaign || empty( $campaign->artwork_cutoff_date ) ) {
            return false;
        }
        return strtotime( (string) $campaign->artwork_cutoff_date ) < time();
    }
}
