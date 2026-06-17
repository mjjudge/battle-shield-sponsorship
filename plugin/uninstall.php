<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Autoloader needed for Roles class.
require_once __DIR__ . '/battle-shield-sponsorship.php';

use BattleShieldSponsorship\Core\Roles;

// Remove custom roles.
Roles::remove();

// Remove all plugin options.
$option_keys = [
    'bss_db_version',
    'bss_settings',
];
foreach ( $option_keys as $key ) {
    delete_option( $key );
}

// Remove email template options.
$template_keys = [ 'sponsorship_confirmation', 'artwork_reminder', 'final_artwork_reminder', 'refund_confirmation', 'gdpr_removal' ];
foreach ( $template_keys as $key ) {
    delete_option( 'bss_email_tpl_' . $key );
}

// Remove plugin tables — only if the constant BSS_UNINSTALL_DELETE_DATA is defined as true.
// This prevents accidental data loss; to enable, define the constant in wp-config.php.
if ( defined( 'BSS_UNINSTALL_DELETE_DATA' ) && BSS_UNINSTALL_DELETE_DATA ) {
    global $wpdb;
    $tables = [
        'bss_campaigns',
        'bss_shields',
        'bss_contacts',
        'bss_sponsorships',
        'bss_sponsorship_items',
        'bss_reservations',
        'bss_upload_tokens',
        'bss_email_log',
        'bss_audit_log',
    ];
    foreach ( $tables as $table ) {
        $full = $wpdb->prefix . $table;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS {$full}" );
    }
}
