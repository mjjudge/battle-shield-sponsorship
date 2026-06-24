<?php

namespace BattleShieldSponsorship\Database\Migrations;

use BattleShieldSponsorship\Database\MigrationInterface;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class SetDefaultShieldPrice implements MigrationInterface {

    public function up(): void {
        global $wpdb;
        $table = Schema::table_name( 'shields' );
        // Set any shields that still have a £0 suggested price to the £100 default.
        $wpdb->query( "UPDATE {$table} SET suggested_price = '100.00' WHERE suggested_price = '0.00' OR suggested_price IS NULL" );
    }
}
