<?php

namespace BattleShieldSponsorship\Database\Migrations;

use BattleShieldSponsorship\Database\MigrationInterface;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class CreateCampaignsTable implements MigrationInterface {

    public function up(): void {
        $table    = Schema::table_name( 'campaigns' );
        $collate  = Schema::charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            event_date DATE NULL,
            sales_open_date DATE NULL,
            artwork_cutoff_date DATE NULL,
            reminder_frequency_days TINYINT UNSIGNED NOT NULL DEFAULT 2,
            final_reminder_days_before TINYINT UNSIGNED NOT NULL DEFAULT 1,
            default_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            reservation_timeout_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
            gift_aid_enabled TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'inactive',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY status_idx (status),
            KEY event_date_idx (event_date)
        ) {$collate};";

        dbDelta( $sql );
    }
}
