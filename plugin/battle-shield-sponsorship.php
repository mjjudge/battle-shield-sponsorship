<?php
/**
 * Plugin Name: Battle Shield Sponsorship
 * Plugin URI:  https://github.com/mjjudge/battle-shield-sponsorship
 * Description: A standalone WordPress plugin for managing Battle of Evesham shield sponsorships.
 * Version:     0.1.8
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Author:      Battle of Evesham
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: battle-shield-sponsorship
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// Composer autoloader (mPDF and other vendor libraries).
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

define( 'BSS_VERSION', '0.1.8' );
define( 'BSS_PLUGIN_FILE', __FILE__ );
define( 'BSS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BSS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Maps BattleShieldSponsorship\Foo\Bar to plugin/src/Foo/Bar.php.
spl_autoload_register( static function ( string $class ): void {
    if ( ! str_starts_with( $class, 'BattleShieldSponsorship\\' ) ) {
        return;
    }

    $file = BSS_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', substr( $class, 24 ) ) . '.php';
    if ( is_readable( $file ) ) {
        require $file;
    }
} );

register_activation_hook( __FILE__, [ BattleShieldSponsorship\Core\Installer::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ BattleShieldSponsorship\Core\Installer::class, 'deactivate' ] );

add_action( 'plugins_loaded', static function (): void {
    ( new BattleShieldSponsorship\Core\Plugin() )->boot();
} );
