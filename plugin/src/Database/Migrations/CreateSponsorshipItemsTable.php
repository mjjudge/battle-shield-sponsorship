<?php

namespace BattleShieldSponsorship\Database\Migrations;

use BattleShieldSponsorship\Database\MigrationInterface;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class CreateSponsorshipItemsTable implements MigrationInterface {

    public function up(): void {
        $table   = Schema::table_name( 'sponsorship_items' );
        $collate = Schema::charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sponsorship_id BIGINT UNSIGNED NOT NULL,
            shield_id BIGINT UNSIGNED NOT NULL,
            price_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(30) NOT NULL DEFAULT 'reserved',
            patch_data_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY sponsorship_id_idx (sponsorship_id),
            KEY shield_id_idx (shield_id),
            KEY status_idx (status)
        ) {$collate};";

        dbDelta( $sql );
    }
}
