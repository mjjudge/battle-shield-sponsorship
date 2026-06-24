<?php

namespace BattleShieldSponsorship\Database\Migrations;

use BattleShieldSponsorship\Database\MigrationInterface;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class UpdateShieldSides implements MigrationInterface {

    public function up(): void {
        global $wpdb;
        $table = Schema::table_name( 'shields' );
        // royalist => royals (Henry III's side), baron/other => rebels (de Montfort's side)
        $wpdb->query( "UPDATE {$table} SET side = 'royals' WHERE side = 'royalist'" );
        $wpdb->query( "UPDATE {$table} SET side = 'rebels' WHERE side IN ('baron', 'other')" );
    }
}
