<?php

namespace BattleShieldSponsorship\Database;

defined( 'ABSPATH' ) || exit;

class Schema {

    public static function table_name( string $suffix ): string {
        global $wpdb;

        return $wpdb->prefix . 'bss_' . $suffix;
    }

    public static function charset_collate(): string {
        global $wpdb;

        return $wpdb->get_charset_collate();
    }
}
