<?php

namespace BattleShieldSponsorship\Database\Migrations;

use BattleShieldSponsorship\Database\MigrationInterface;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class CreateContactsTable implements MigrationInterface {

    public function up(): void {
        $table   = Schema::table_name( 'contacts' );
        $collate = Schema::charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_name VARCHAR(191) NOT NULL DEFAULT '',
            display_name VARCHAR(191) NOT NULL DEFAULT '',
            email VARCHAR(191) NOT NULL DEFAULT '',
            phone VARCHAR(50) NULL,
            website_url VARCHAR(500) NULL,
            marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0,
            marketing_opt_in_at DATETIME NULL,
            gdpr_status VARCHAR(20) NOT NULL DEFAULT 'active',
            anonymised TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY email_idx (email(191)),
            KEY gdpr_status_idx (gdpr_status),
            KEY anonymised_idx (anonymised)
        ) {$collate};";

        dbDelta( $sql );
    }
}
