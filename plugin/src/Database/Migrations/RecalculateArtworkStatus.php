<?php

namespace BattleShieldSponsorship\Database\Migrations;

use BattleShieldSponsorship\Database\MigrationInterface;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Re-evaluates artwork_status for all paid sponsorships against the stricter
 * completion criteria introduced in BSS-147: both display_name AND
 * (logo_attachment_id OR logo_not_needed) must be set to reach 'complete'.
 */
class RecalculateArtworkStatus implements MigrationInterface {

    public function up(): void {
        global $wpdb;
        $table = Schema::table_name( 'sponsorships' );
        $wpdb->query(
            "UPDATE {$table}
             SET artwork_status = 'incomplete'
             WHERE payment_status = 'paid'
               AND artwork_status = 'complete'
               AND (
                 ( display_name IS NULL OR display_name = '' )
                 OR
                 ( logo_attachment_id IS NULL AND logo_not_needed = 0 )
               )"
        );
    }
}
