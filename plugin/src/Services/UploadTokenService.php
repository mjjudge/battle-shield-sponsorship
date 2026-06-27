<?php

namespace BattleShieldSponsorship\Services;

use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class UploadTokenService {

    public function create_for_sponsorship( int $sponsorship_id ): string {
        global $wpdb;
        $table = Schema::table_name( 'upload_tokens' );
        $token = $this->generate_token();

        $wpdb->insert( $table, [
            'sponsorship_id' => $sponsorship_id,
            'token'          => $token,
            'created_at'     => current_time( 'mysql', true ),
        ] );

        return $token;
    }

    /** Returns the sponsorship_id if the token is valid, null otherwise. */
    public function validate( string $token ): ?int {
        global $wpdb;
        $table = Schema::table_name( 'upload_tokens' );
        $row   = $wpdb->get_row( $wpdb->prepare(
            "SELECT sponsorship_id FROM {$table} WHERE token = %s LIMIT 1",
            sanitize_text_field( $token )
        ) );

        if ( ! $row ) {
            return null;
        }

        $wpdb->update( $table, [ 'last_used_at' => current_time( 'mysql', true ) ], [ 'token' => $token ] );

        return (int) $row->sponsorship_id;
    }

    public function get_token_for_sponsorship( int $sponsorship_id ): ?string {
        global $wpdb;
        $table = Schema::table_name( 'upload_tokens' );
        $token = $wpdb->get_var( $wpdb->prepare(
            "SELECT token FROM {$table} WHERE sponsorship_id = %d ORDER BY created_at DESC LIMIT 1",
            $sponsorship_id
        ) );
        return $token ? (string) $token : null;
    }

    public function edit_url( string $token ): string {
        $settings  = (array) get_option( 'bss_settings', [] );
        $edit_slug = (string) ( $settings['edit_page_slug'] ?? 'shield-sponsorship-edit' );
        return add_query_arg( [ 'token' => $token ], home_url( '/' . $edit_slug . '/' ) );
    }

    private function generate_token(): string {
        return bin2hex( random_bytes( 32 ) );
    }
}
