<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\ShieldService;

defined( 'ABSPATH' ) || exit;

class ShieldListPage {

    public function render(): void {
        RequestGuard::require_capability( 'bss_manage_shields' );

        $search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
        $side   = sanitize_text_field( wp_unslash( $_GET['side'] ?? '' ) );
        $state  = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );

        $filters = array_filter( [
            'search'         => $search,
            'side'           => $side,
            'physical_state' => $state,
        ] );

        $shields = ( new ShieldService() )->get_all( $filters );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Shields', 'battle-shield-sponsorship' ) . '</h1>';
        echo '<p>'
            . '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=bss-shield-edit' ) ) . '">'
            . esc_html__( 'Add Shield', 'battle-shield-sponsorship' ) . '</a> '
            . '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=bss-shield-import' ) ) . '">'
            . esc_html__( 'Import Shields (JSON)', 'battle-shield-sponsorship' ) . '</a>'
            . '</p>';

        if ( isset( $_GET['saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Shield saved.', 'battle-shield-sponsorship' ) . '</p></div>';
        }

        echo '<form method="get" style="margin-bottom:16px;">';
        echo '<input type="hidden" name="page" value="bss-shields" />';
        echo '<input type="search" name="search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search by name…', 'battle-shield-sponsorship' ) . '" style="margin-right:8px;" />';
        echo '<select name="side" style="margin-right:8px;">';
        echo '<option value="">' . esc_html__( 'All sides', 'battle-shield-sponsorship' ) . '</option>';
        foreach ( [ 'royals' => 'Royals', 'rebels' => 'Rebels' ] as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '" ' . selected( $side, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<select name="state" style="margin-right:8px;">';
        echo '<option value="">' . esc_html__( 'All states', 'battle-shield-sponsorship' ) . '</option>';
        foreach ( [ 'available' => 'Available', 'reserved' => 'Reserved', 'sponsored' => 'Sponsored', 'unavailable' => 'Unavailable' ] as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '" ' . selected( $state, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        submit_button( __( 'Filter', 'battle-shield-sponsorship' ), 'secondary', 'submit', false );
        echo '</form>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Image', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Name', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Side', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'State', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Suggested price', 'battle-shield-sponsorship' ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'battle-shield-sponsorship' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $shields ) ) {
            echo '<tr><td colspan="6">' . esc_html__( 'No shields found.', 'battle-shield-sponsorship' ) . '</td></tr>';
        }

        foreach ( $shields as $shield ) {
            $image_id  = (int) ( $shield->image_id ?? 0 );
            $thumb_url = $image_id > 0 ? (string) wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

            echo '<tr>';
            echo '<td>';
            if ( '' !== $thumb_url ) {
                echo '<img src="' . esc_url( $thumb_url ) . '" alt="" style="width:50px;height:50px;object-fit:cover;border:1px solid #ddd;" />';
            } else {
                echo '<span style="color:#aaa;">—</span>';
            }
            echo '</td>';
            echo '<td><strong>' . esc_html( (string) $shield->name ) . '</strong></td>';
            $side_labels = [ 'royals' => 'Royals', 'rebels' => 'Rebels' ];
            echo '<td>' . esc_html( $side_labels[ (string) $shield->side ] ?? ucfirst( (string) $shield->side ) ) . '</td>';
            echo '<td>' . esc_html( ucfirst( str_replace( '_', ' ', (string) $shield->physical_state ) ) ) . '</td>';
            echo '<td>£' . esc_html( number_format( (float) $shield->suggested_price, 2 ) ) . '</td>';
            echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=bss-shield-edit&id=' . (int) $shield->id ) ) . '">'
                . esc_html__( 'Edit', 'battle-shield-sponsorship' ) . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }
}
