<?php

namespace BattleShieldSponsorship\Security;

defined( 'ABSPATH' ) || exit;

class RequestGuard {

    public static function require_capability( string $capability ): void {
        if ( ! current_user_can( $capability ) ) {
            wp_die( esc_html__( 'Access denied.', 'battle-shield-sponsorship' ) );
        }
    }

    public static function verify_admin_nonce( string $nonce_action, string $nonce_name = '_wpnonce' ): void {
        $nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
            wp_die( esc_html__( 'Security check failed.', 'battle-shield-sponsorship' ) );
        }
    }

    public static function verify_public_nonce( string $nonce_action, string $nonce_name = '_wpnonce' ): void {
        $nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
            wp_die( esc_html__( 'Security check failed.', 'battle-shield-sponsorship' ) );
        }
    }

    public static function passes_honeypot( string $field_name ): bool {
        $value = sanitize_text_field( wp_unslash( $_POST[ $field_name ] ?? '' ) );
        return '' === $value;
    }
}
