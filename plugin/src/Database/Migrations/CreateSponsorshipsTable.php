<?php

namespace BattleShieldSponsorship\Database\Migrations;

use BattleShieldSponsorship\Database\MigrationInterface;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class CreateSponsorshipsTable implements MigrationInterface {

    public function up(): void {
        $table   = Schema::table_name( 'sponsorships' );
        $collate = Schema::charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NOT NULL,
            contact_id BIGINT UNSIGNED NOT NULL,
            display_name VARCHAR(191) NOT NULL DEFAULT '',
            sponsor_text TEXT NULL,
            logo_attachment_id BIGINT UNSIGNED NULL,
            payment_method VARCHAR(30) NOT NULL DEFAULT 'stripe',
            stripe_session_id VARCHAR(191) NULL,
            stripe_payment_intent_id VARCHAR(191) NULL,
            stripe_charge_id VARCHAR(191) NULL,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            refund_status VARCHAR(20) NOT NULL DEFAULT 'none',
            gift_aid_declared TINYINT(1) NOT NULL DEFAULT 0,
            artwork_status VARCHAR(20) NOT NULL DEFAULT 'incomplete',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY campaign_id_idx (campaign_id),
            KEY contact_id_idx (contact_id),
            KEY payment_status_idx (payment_status),
            KEY artwork_status_idx (artwork_status),
            KEY stripe_session_id_idx (stripe_session_id(191))
        ) {$collate};";

        dbDelta( $sql );
    }
}
