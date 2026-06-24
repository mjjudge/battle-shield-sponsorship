<?php

namespace BattleShieldSponsorship\Database\Migrations;

use BattleShieldSponsorship\Database\MigrationInterface;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class AddLogoNotNeededToSponsorships implements MigrationInterface {

    public function up(): void {
        global $wpdb;
        $table   = Schema::table_name( 'sponsorships' );
        $columns = $wpdb->get_col( "DESCRIBE {$table}", 0 );
        if ( ! in_array( 'logo_not_needed', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN logo_not_needed TINYINT(1) NOT NULL DEFAULT 0 AFTER logo_attachment_id" );
        }
    }
}
