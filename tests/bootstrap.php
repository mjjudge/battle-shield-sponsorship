<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! defined( 'BSS_VERSION' ) ) {
    define( 'BSS_VERSION', '0.1.0' );
}

// Minimal WordPress function stubs for unit tests.

if ( ! function_exists( '__' ) ) {
    function __( string $text, string $domain = '' ): string {
        return $text;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $value ): string {
        return is_string( $value ) ? trim( $value ) : '';
    }
}

if ( ! function_exists( 'sanitize_email' ) ) {
    function sanitize_email( $value ): string {
        return is_string( $value ) ? strtolower( trim( $value ) ) : '';
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ): string {
        $key = is_string( $key ) ? strtolower( $key ) : '';
        return preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '';
    }
}

if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) {
        return $value;
    }
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( string $type, bool $gmt = false ) {
        return 'timestamp' === $type ? time() : gmdate( 'Y-m-d H:i:s' );
    }
}

if ( ! function_exists( 'home_url' ) ) {
    function home_url( string $path = '' ): string {
        return 'https://example.test' . $path;
    }
}

if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( array $args, string $url ): string {
        return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args );
    }
}

if ( ! function_exists( 'wp_generate_password' ) ) {
    function wp_generate_password( int $length = 24, bool $special_chars = true, bool $extra_special_chars = false ): string {
        return bin2hex( random_bytes( (int) ceil( $length / 2 ) ) );
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $value ): string|false {
        return json_encode( $value );
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( string $key, $default = false ) {
        return $default;
    }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( string $url ): string {
        return $url;
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( string $text ): string {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( string $text ): string {
        return $text;
    }
}

require_once __DIR__ . '/../plugin/src/Database/MigrationInterface.php';
require_once __DIR__ . '/../plugin/src/Database/Migrator.php';
require_once __DIR__ . '/../plugin/src/Database/Schema.php';
require_once __DIR__ . '/../plugin/src/Services/StripeWebhookProcessor.php';
