<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;
use BattleShieldSponsorship\Services\ShieldService;

defined( 'ABSPATH' ) || exit;

class ShieldEditPage {

    private const NONCE_ACTION = 'bss_save_shield';

    public function __construct() {
        add_action( 'admin_post_bss_save_shield', [ $this, 'handle_save' ] );
    }

    public function render(): void {
        RequestGuard::require_capability( 'bss_manage_shields' );

        $id      = (int) ( $_GET['id'] ?? 0 );
        $service = new ShieldService();
        $shield  = $id > 0 ? $service->get_by_id( $id ) : null;

        $name           = (string) ( $shield->name ?? '' );
        $side           = (string) ( $shield->side ?? 'baron' );
        $description    = (string) ( $shield->description ?? '' );
        $suggested_price = number_format( (float) ( $shield->suggested_price ?? 0 ), 2 );
        $image_id       = (int) ( $shield->image_id ?? 0 );
        $physical_state = (string) ( $shield->physical_state ?? 'available' );
        $notes          = (string) ( $shield->notes ?? '' );

        $title = $id > 0 ? __( 'Edit Shield', 'battle-shield-sponsorship' ) : __( 'Add Shield', 'battle-shield-sponsorship' );

        wp_enqueue_media();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html( $title ) . '</h1>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bss_save_shield" />';
        echo '<input type="hidden" name="shield_id" value="' . $id . '" />';
        wp_nonce_field( self::NONCE_ACTION );

        echo '<table class="form-table" role="presentation">';

        $this->row( 'name', __( 'Baron / Royalist name', 'battle-shield-sponsorship' ),
            '<input name="name" id="name" type="text" class="regular-text" required value="' . esc_attr( $name ) . '" />' );

        echo '<tr><th scope="row"><label for="side">' . esc_html__( 'Side', 'battle-shield-sponsorship' ) . '</label></th><td>';
        echo '<select name="side" id="side">';
        foreach ( [ 'baron' => 'Baron', 'royalist' => 'Royalist', 'other' => 'Other' ] as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '" ' . selected( $side, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';

        $this->row( 'description', __( 'Short historical description', 'battle-shield-sponsorship' ),
            '<textarea name="description" id="description" class="large-text" rows="4">' . esc_textarea( $description ) . '</textarea>' );

        $this->row( 'suggested_price', __( 'Suggested price (£)', 'battle-shield-sponsorship' ),
            '<input name="suggested_price" id="suggested_price" type="number" step="0.01" min="0" class="small-text" value="' . esc_attr( $suggested_price ) . '" />' );

        $thumb_url = $image_id > 0 ? (string) wp_get_attachment_image_url( $image_id, 'medium' ) : '';
        echo '<tr><th scope="row"><label>' . esc_html__( 'Shield image', 'battle-shield-sponsorship' ) . '</label></th><td>';
        echo '<div id="shield-image-preview" style="margin-bottom:8px;">';
        if ( '' !== $thumb_url ) {
            echo '<img src="' . esc_url( $thumb_url ) . '" style="max-width:200px;max-height:200px;" />';
        }
        echo '</div>';
        echo '<input type="hidden" name="image_id" id="shield-image-id" value="' . esc_attr( (string) $image_id ) . '" />';
        echo '<button type="button" class="button" id="bss-select-image">' . esc_html__( 'Select image', 'battle-shield-sponsorship' ) . '</button>';
        if ( $image_id > 0 ) {
            echo ' <button type="button" class="button" id="bss-remove-image">' . esc_html__( 'Remove', 'battle-shield-sponsorship' ) . '</button>';
        }
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="physical_state">' . esc_html__( 'Physical state', 'battle-shield-sponsorship' ) . '</label></th><td>';
        echo '<select name="physical_state" id="physical_state">';
        $states = [ 'available' => 'Available', 'reserved' => 'Reserved', 'sponsored' => 'Sponsored', 'unavailable' => 'Unavailable/Damaged' ];
        foreach ( $states as $val => $label ) {
            echo '<option value="' . esc_attr( $val ) . '" ' . selected( $physical_state, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';

        $this->row( 'notes', __( 'Admin notes', 'battle-shield-sponsorship' ),
            '<textarea name="notes" id="notes" class="large-text" rows="3">' . esc_textarea( $notes ) . '</textarea>'
            . '<p class="description">' . esc_html__( 'Internal notes — not shown publicly.', 'battle-shield-sponsorship' ) . '</p>' );

        echo '</table>';
        submit_button( $id > 0 ? __( 'Save Shield', 'battle-shield-sponsorship' ) : __( 'Add Shield', 'battle-shield-sponsorship' ) );
        echo '</form>';

        if ( $id > 0 ) {
            echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=bss-shields' ) ) . '">&larr; '
                . esc_html__( 'Back to Shields', 'battle-shield-sponsorship' ) . '</a></p>';
        }

        echo '</div>';

        $this->enqueue_media_script();
    }

    public function handle_save(): void {
        RequestGuard::require_capability( 'bss_manage_shields' );
        RequestGuard::verify_admin_nonce( self::NONCE_ACTION );

        $id      = (int) ( $_POST['shield_id'] ?? 0 );
        $service = new ShieldService();

        $data = [
            'name'            => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
            'side'            => sanitize_text_field( wp_unslash( $_POST['side'] ?? 'baron' ) ),
            'description'     => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'suggested_price' => (float) ( $_POST['suggested_price'] ?? 0 ),
            'image_id'        => (int) ( $_POST['image_id'] ?? 0 ) ?: null,
            'physical_state'  => sanitize_text_field( wp_unslash( $_POST['physical_state'] ?? 'available' ) ),
            'notes'           => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
        ];

        if ( $id > 0 ) {
            $service->update( $id, $data );
        } else {
            $service->create( $data );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'bss-shields', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private function row( string $id, string $label, string $field ): void {
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th>';
        echo '<td>' . $field . '</td>';
        echo '</tr>';
    }

    private function enqueue_media_script(): void {
        ?>
        <script>
        jQuery(function($) {
            var frame;
            $('#bss-select-image').on('click', function(e) {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({ title: 'Select Shield Image', button: { text: 'Use this image' }, multiple: false });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#shield-image-id').val(attachment.id);
                    var preview = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                    $('#shield-image-preview').html('<img src="' + preview + '" style="max-width:200px;max-height:200px;" />');
                });
                frame.open();
            });
            $('#bss-remove-image').on('click', function(e) {
                e.preventDefault();
                $('#shield-image-id').val('');
                $('#shield-image-preview').html('');
            });
        });
        </script>
        <?php
    }
}
