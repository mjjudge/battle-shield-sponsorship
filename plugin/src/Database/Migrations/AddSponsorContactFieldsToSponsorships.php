<?php

namespace BattleShieldSponsorship\Database\Migrations;

use BattleShieldSponsorship\Database\MigrationInterface;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class AddSponsorContactFieldsToSponsorships implements MigrationInterface {

    public function up(): void {
        global $wpdb;
        $table   = Schema::table_name( 'sponsorships' );
        $columns = $wpdb->get_col( "DESCRIBE {$table}", 0 );

        if ( ! in_array( 'sponsor_url', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN sponsor_url VARCHAR(255) NULL DEFAULT NULL AFTER sponsor_text" );
        }

        if ( ! in_array( 'sponsor_phone', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN sponsor_phone VARCHAR(50) NULL DEFAULT NULL AFTER sponsor_url" );
        }
    }
}
