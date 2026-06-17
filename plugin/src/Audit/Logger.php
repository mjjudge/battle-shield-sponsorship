<?php

namespace BattleShieldSponsorship\Audit;

use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class Logger {

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     * @param array<string, mixed>      $context
     */
    public static function log(
        string $event_type,
        string $entity_type,
        ?int $entity_id = null,
        ?array $before = null,
        ?array $after = null,
        array $context = []
    ): void {
        global $wpdb;

        $table  = Schema::table_name( 'audit_log' );
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            return;
        }

        $wpdb->insert(
            $table,
            [
                'event_type'   => sanitize_text_field( $event_type ),
                'entity_type'  => sanitize_text_field( $entity_type ),
                'entity_id'    => $entity_id,
                'actor_user_id' => get_current_user_id() ?: null,
                'before_json'  => null !== $before ? wp_json_encode( $before ) : null,
                'after_json'   => null !== $after ? wp_json_encode( $after ) : null,
                'context_json' => ! empty( $context ) ? wp_json_encode( $context ) : null,
                'created_at'   => current_time( 'mysql', true ),
            ]
        );
    }
}
