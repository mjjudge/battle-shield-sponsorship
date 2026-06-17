<?php

namespace BattleShieldSponsorship\Database\Migrations;

use BattleShieldSponsorship\Database\MigrationInterface;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class CreateEmailLogTable implements MigrationInterface {

    public function up(): void {
        $table   = Schema::table_name( 'email_log' );
        $collate = Schema::charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            recipient VARCHAR(191) NOT NULL,
            email_type VARCHAR(50) NOT NULL,
            campaign_id BIGINT UNSIGNED NULL,
            sponsorship_id BIGINT UNSIGNED NULL,
            contact_id BIGINT UNSIGNED NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'pending',
            error_message VARCHAR(500) NULL,
            subject VARCHAR(500) NOT NULL DEFAULT '',
            body_preview TEXT NULL,
            sent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY email_type_idx (email_type),
            KEY sponsorship_id_idx (sponsorship_id),
            KEY campaign_id_idx (campaign_id),
            KEY sent_at_idx (sent_at)
        ) {$collate};";

        dbDelta( $sql );
    }
}
