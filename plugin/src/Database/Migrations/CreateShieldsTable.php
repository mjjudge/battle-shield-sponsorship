<?php

namespace BattleShieldSponsorship\Database\Migrations;

use BattleShieldSponsorship\Database\MigrationInterface;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class CreateShieldsTable implements MigrationInterface {

    public function up(): void {
        $table   = Schema::table_name( 'shields' );
        $collate = Schema::charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            side VARCHAR(20) NOT NULL DEFAULT 'baron',
            description TEXT NULL,
            suggested_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            image_id BIGINT UNSIGNED NULL,
            physical_state VARCHAR(20) NOT NULL DEFAULT 'available',
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY physical_state_idx (physical_state),
            KEY side_idx (side)
        ) {$collate};";

        dbDelta( $sql );
    }
}
