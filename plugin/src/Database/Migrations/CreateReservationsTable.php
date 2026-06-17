<?php

namespace BattleShieldSponsorship\Database\Migrations;

use BattleShieldSponsorship\Database\MigrationInterface;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class CreateReservationsTable implements MigrationInterface {

    public function up(): void {
        $table   = Schema::table_name( 'reservations' );
        $collate = Schema::charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            shield_id BIGINT UNSIGNED NOT NULL,
            session_key VARCHAR(64) NOT NULL,
            sponsorship_id BIGINT UNSIGNED NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY shield_id_idx (shield_id),
            KEY session_key_idx (session_key),
            KEY expires_at_idx (expires_at),
            KEY sponsorship_id_idx (sponsorship_id)
        ) {$collate};";

        dbDelta( $sql );
    }
}
