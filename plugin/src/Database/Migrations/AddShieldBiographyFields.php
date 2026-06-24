<?php

namespace BattleShieldSponsorship\Database\Migrations;

use BattleShieldSponsorship\Database\MigrationInterface;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class AddShieldBiographyFields implements MigrationInterface {

    public function up(): void {
        global $wpdb;
        $table   = Schema::table_name( 'shields' );
        $columns = $wpdb->get_col( "DESCRIBE {$table}", 0 );

        if ( ! in_array( 'birth_date', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN birth_date VARCHAR(100) NULL AFTER description" );
        }
        if ( ! in_array( 'death_date', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN death_date VARCHAR(100) NULL AFTER birth_date" );
        }
        if ( ! in_array( 'source_url', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN source_url VARCHAR(500) NULL AFTER death_date" );
        }
    }
}
