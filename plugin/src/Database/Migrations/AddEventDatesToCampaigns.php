<?php

namespace BattleShieldSponsorship\Database\Migrations;

use BattleShieldSponsorship\Database\MigrationInterface;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class AddEventDatesToCampaigns implements MigrationInterface {

    public function up(): void {
        global $wpdb;
        $table   = Schema::table_name( 'campaigns' );
        $columns = $wpdb->get_col( "DESCRIBE {$table}", 0 );

        if ( ! in_array( 'event_start_date', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN event_start_date DATE NULL AFTER name" );
        }
        if ( ! in_array( 'event_end_date', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN event_end_date DATE NULL AFTER event_start_date" );
        }
    }
}
