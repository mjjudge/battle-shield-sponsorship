<?php

namespace BattleShieldSponsorship\Mail;

use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class Mailer {

    /**
     * @param array<string, mixed> $args  Required: to, subject, body. Optional: email_type, campaign_id, sponsorship_id, contact_id.
     */
    public function send( array $args ): bool {
        $to      = sanitize_email( (string) ( $args['to'] ?? '' ) );
        $subject = sanitize_text_field( (string) ( $args['subject'] ?? '' ) );
        $body    = (string) ( $args['body'] ?? '' );
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        $settings   = (array) get_option( 'bss_settings', [] );
        $from_email = sanitize_email( (string) ( $settings['contact_email'] ?? '' ) );
        $from_name  = sanitize_text_field( (string) ( $settings['organisation_name'] ?? 'Battle of Evesham' ) );

        $from_email_filter = '' !== $from_email ? static fn() => $from_email : null;
        $from_name_filter  = '' !== $from_email && '' !== $from_name ? static fn() => $from_name : null;

        if ( $from_email_filter ) {
            add_filter( 'wp_mail_from', $from_email_filter );
        }
        if ( $from_name_filter ) {
            add_filter( 'wp_mail_from_name', $from_name_filter );
        }

        $ok    = false;
        $error = '';

        if ( '' !== $to && '' !== $subject && '' !== trim( wp_strip_all_tags( $body ) ) ) {
            $ok = wp_mail( $to, $subject, $body, $headers );
            if ( ! $ok ) {
                $error = 'wp_mail_failed';
            }
        } else {
            $error = 'invalid_email_payload';
        }

        if ( $from_email_filter ) {
            remove_filter( 'wp_mail_from', $from_email_filter );
        }
        if ( $from_name_filter ) {
            remove_filter( 'wp_mail_from_name', $from_name_filter );
        }

        $this->log(
            $to,
            sanitize_text_field( (string) ( $args['email_type'] ?? 'generic' ) ),
            isset( $args['campaign_id'] ) ? (int) $args['campaign_id'] : null,
            isset( $args['sponsorship_id'] ) ? (int) $args['sponsorship_id'] : null,
            isset( $args['contact_id'] ) ? (int) $args['contact_id'] : null,
            $ok ? 'sent' : 'failed',
            $error,
            $subject,
            $body
        );

        return $ok;
    }

    private function log(
        string $recipient,
        string $email_type,
        ?int $campaign_id,
        ?int $sponsorship_id,
        ?int $contact_id,
        string $status,
        string $error,
        string $subject,
        string $body
    ): void {
        global $wpdb;

        $table  = Schema::table_name( 'email_log' );
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            return;
        }

        $wpdb->insert( $table, [
            'recipient'      => $recipient,
            'email_type'     => $email_type,
            'campaign_id'    => $campaign_id,
            'sponsorship_id' => $sponsorship_id,
            'contact_id'     => $contact_id,
            'status'         => $status,
            'error_message'  => '' !== $error ? $error : null,
            'subject'        => $subject,
            'body_preview'   => mb_substr( wp_strip_all_tags( $body ), 0, 500 ),
            'sent_at'        => 'sent' === $status ? current_time( 'mysql', true ) : null,
            'created_at'     => current_time( 'mysql', true ),
        ] );
    }
}
