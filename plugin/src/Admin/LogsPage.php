<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Database\Schema;

defined( 'ABSPATH' ) || exit;

class LogsPage {

    public function render(): void {
        RequestGuard::require_capability( 'bss_manage_settings' );

        global $wpdb;

        $tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'audit' ) );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Logs', 'battle-shield-sponsorship' ) . '</h1>';

        echo '<nav class="nav-tab-wrapper">';
        echo '<a class="nav-tab ' . ( 'audit' === $tab ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=bss-logs&tab=audit' ) ) . '">' . esc_html__( 'Audit Log', 'battle-shield-sponsorship' ) . '</a>';
        echo '<a class="nav-tab ' . ( 'email' === $tab ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=bss-logs&tab=email' ) ) . '">' . esc_html__( 'Email Log', 'battle-shield-sponsorship' ) . '</a>';
        echo '</nav>';

        if ( 'audit' === $tab ) {
            $this->render_audit_log( $wpdb );
        } else {
            $this->render_email_log( $wpdb );
        }

        echo '</div>';
    }

    private function render_audit_log( \wpdb $wpdb ): void {
        $table  = Schema::table_name( 'audit_log' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200" );

        echo '<h2>' . esc_html__( 'Audit Log (last 200 entries)', 'battle-shield-sponsorship' ) . '</h2>';
        // Scrollable container — ~20 rows visible (each row ~34 px).
        echo '<div style="max-height:680px; overflow-y:auto; border:1px solid #c3c4c7;">';
        echo '<table class="widefat striped" style="margin:0;">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Date', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Event', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Entity', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Actor', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Context', 'battle-shield-sponsorship' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $rows ) ) {
            echo '<tr><td colspan="5">' . esc_html__( 'No audit entries yet.', 'battle-shield-sponsorship' ) . '</td></tr>';
        }

        foreach ( $rows as $row ) {
            $actor = (int) $row->actor_user_id ? get_user_by( 'id', (int) $row->actor_user_id ) : null;
            echo '<tr>';
            echo '<td><small>' . esc_html( date( 'd/m/Y H:i', strtotime( (string) $row->created_at ) ) ) . '</small></td>';
            echo '<td>' . esc_html( (string) $row->event_type ) . '</td>';
            echo '<td>' . esc_html( (string) $row->entity_type ) . ' #' . (int) $row->entity_id . '</td>';
            echo '<td>' . esc_html( $actor ? (string) $actor->user_login : ( $row->actor_user_id ? '#' . (int) $row->actor_user_id : 'system' ) ) . '</td>';
            echo '<td><small>' . esc_html( wp_trim_words( (string) ( $row->context_json ?? '' ), 10 ) ) . '</small></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private function render_email_log( \wpdb $wpdb ): void {
        $table = Schema::table_name( 'email_log' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200" );

        echo '<h2>' . esc_html__( 'Email Log (last 200 entries)', 'battle-shield-sponsorship' ) . '</h2>';
        // Scrollable container — ~20 rows visible.
        echo '<div style="max-height:680px; overflow-y:auto; border:1px solid #c3c4c7;">';
        echo '<table class="widefat striped" style="margin:0;">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Date', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Recipient', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Type', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Subject', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'battle-shield-sponsorship' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $rows ) ) {
            echo '<tr><td colspan="5">' . esc_html__( 'No emails logged yet.', 'battle-shield-sponsorship' ) . '</td></tr>';
        }

        foreach ( $rows as $row ) {
            $status_colour = 'sent' === (string) $row->status ? '#2e7d32' : '#c62828';
            echo '<tr>';
            echo '<td><small>' . esc_html( date( 'd/m/Y H:i', strtotime( (string) $row->created_at ) ) ) . '</small></td>';
            echo '<td>' . esc_html( (string) $row->recipient ) . '</td>';
            echo '<td>' . esc_html( str_replace( '_', ' ', (string) $row->email_type ) ) . '</td>';
            echo '<td>' . esc_html( (string) $row->subject ) . '</td>';
            echo '<td style="color:' . esc_attr( $status_colour ) . ';">' . esc_html( ucfirst( (string) $row->status ) ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }
}
