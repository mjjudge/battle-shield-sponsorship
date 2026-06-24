<?php

namespace BattleShieldSponsorship\Database\Migrations;

use BattleShieldSponsorship\Database\MigrationInterface;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class AddContactCompanyAndAddress implements MigrationInterface {

    public function up(): void {
        global $wpdb;
        $table   = Schema::table_name( 'contacts' );
        $columns = $wpdb->get_col( "DESCRIBE {$table}", 0 );

        if ( ! in_array( 'company_name', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN company_name VARCHAR(191) NOT NULL DEFAULT '' AFTER contact_name" );
            // Seed company_name from existing display_name values.
            $wpdb->query( "UPDATE {$table} SET company_name = display_name WHERE company_name = '' AND display_name != ''" );
        }

        $address_cols = [
            'address_line1' => "VARCHAR(191) NULL",
            'address_line2' => "VARCHAR(191) NULL",
            'city'          => "VARCHAR(100) NULL",
            'county'        => "VARCHAR(100) NULL",
            'postcode'      => "VARCHAR(20) NULL",
            'country'       => "VARCHAR(100) NULL DEFAULT 'UK'",
        ];

        foreach ( $address_cols as $col => $def ) {
            if ( ! in_array( $col, $columns, true ) ) {
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN {$col} {$def}" );
            }
        }
    }
}
