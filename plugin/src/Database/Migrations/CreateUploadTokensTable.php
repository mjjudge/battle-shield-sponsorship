<?php

namespace BattleShieldSponsorship\Database\Migrations;

use BattleShieldSponsorship\Database\MigrationInterface;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class CreateUploadTokensTable implements MigrationInterface {

    public function up(): void {
        $table   = Schema::table_name( 'upload_tokens' );
        $collate = Schema::charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sponsorship_id BIGINT UNSIGNED NOT NULL,
            token VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            last_used_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_unique (token),
            KEY sponsorship_id_idx (sponsorship_id)
        ) {$collate};";

        dbDelta( $sql );
    }
}
