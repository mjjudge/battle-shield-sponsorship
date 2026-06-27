<?php

namespace BattleShieldSponsorship\Admin;

use BattleShieldSponsorship\Security\RequestGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Email templates are stored as WP options (bss_email_tpl_{key}).
 * Each template has a subject and body with {tag} placeholders.
 */
class EmailTemplatesPage {

    private const NONCE_ACTION = 'bss_save_email_template';

    private const TEMPLATES = [
        'sponsorship_confirmation' => 'Sponsorship Confirmation',
        'artwork_reminder'         => 'Artwork Upload Reminder',
        'final_artwork_reminder'   => 'Final Artwork Reminder',
        'refund_confirmation'      => 'Refund Confirmation',
        'gdpr_removal'             => 'GDPR Removal Confirmation',
    ];

    private const TAGS = [
        '{sponsor_name}',
        '{display_name}',
        '{campaign_name}',
        '{shield_names}',
        '{cutoff_date}',
        '{edit_url}',
        '{total_amount}',
        '{payment_method}',
        '{help_email}',
    ];

    public function __construct() {
        add_action( 'admin_post_bss_save_email_template', [ $this, 'handle_save' ] );
    }

    public function render(): void {
        RequestGuard::require_capability( 'bss_manage_settings' );

        $active_key = sanitize_key( wp_unslash( $_GET['template'] ?? array_key_first( self::TEMPLATES ) ) );
        if ( ! array_key_exists( $active_key, self::TEMPLATES ) ) {
            $active_key = array_key_first( self::TEMPLATES );
        }

        if ( isset( $_GET['saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template saved.', 'battle-shield-sponsorship' ) . '</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Email Templates', 'battle-shield-sponsorship' ) . '</h1>';

        echo '<ul class="subsubsub">';
        foreach ( self::TEMPLATES as $key => $label ) {
            $url    = admin_url( 'admin.php?page=bss-email-templates&template=' . $key );
            $active = $key === $active_key ? ' class="current"' : '';
            echo '<li' . $active . '><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> | </li>';
        }
        echo '</ul>';
        echo '<br class="clear" />';

        $template_name = self::TEMPLATES[ $active_key ];
        $option_key    = 'bss_email_tpl_' . $active_key;
        $saved         = (array) get_option( $option_key, [] );

        $default_subject = $this->default_subject( $active_key );
        $default_body    = $this->default_body( $active_key );

        $subject = (string) ( $saved['subject'] ?? $default_subject );
        $body    = (string) ( $saved['body'] ?? $default_body );

        echo '<h2>' . esc_html( $template_name ) . '</h2>';
        echo '<p class="description"><strong>' . esc_html__( 'Available tags:', 'battle-shield-sponsorship' ) . '</strong><br />';
        echo '<code>' . implode( '</code> <code>', array_map( 'esc_html', self::TAGS ) ) . '</code>';
        echo '</p>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="bss_save_email_template" />';
        echo '<input type="hidden" name="template_key" value="' . esc_attr( $active_key ) . '" />';
        wp_nonce_field( self::NONCE_ACTION );

        echo '<table class="form-table">';
        echo '<tr><th><label for="tpl_subject">' . esc_html__( 'Subject', 'battle-shield-sponsorship' ) . '</label></th><td>';
        echo '<input type="text" name="tpl_subject" id="tpl_subject" class="large-text" value="' . esc_attr( $subject ) . '" required />';
        echo '</td></tr>';

        echo '<tr><th><label for="tpl_body">' . esc_html__( 'Body (HTML)', 'battle-shield-sponsorship' ) . '</label></th><td>';
        echo '<textarea name="tpl_body" id="tpl_body" class="large-text" rows="16">' . esc_textarea( $body ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'The body is wrapped in the site\'s email layout automatically.', 'battle-shield-sponsorship' ) . '</p>';
        echo '</td></tr>';
        echo '</table>';

        echo '<p>';
        submit_button( __( 'Save Template', 'battle-shield-sponsorship' ), 'primary', 'submit', false );
        echo '&nbsp;<button type="submit" name="reset_default" value="1" class="button">' . esc_html__( 'Reset to Default', 'battle-shield-sponsorship' ) . '</button>';
        echo '</p>';
        echo '</form>';
        echo '</div>';
    }

    public function handle_save(): void {
        RequestGuard::require_capability( 'bss_manage_settings' );
        RequestGuard::verify_admin_nonce( self::NONCE_ACTION );

        $key = sanitize_key( wp_unslash( $_POST['template_key'] ?? '' ) );
        if ( ! array_key_exists( $key, self::TEMPLATES ) ) {
            wp_die( esc_html__( 'Invalid template key.', 'battle-shield-sponsorship' ) );
        }

        $option_key = 'bss_email_tpl_' . $key;

        if ( isset( $_POST['reset_default'] ) ) {
            delete_option( $option_key );
        } else {
            update_option( $option_key, [
                'subject' => sanitize_text_field( wp_unslash( $_POST['tpl_subject'] ?? '' ) ),
                'body'    => wp_kses_post( wp_unslash( $_POST['tpl_body'] ?? '' ) ),
            ] );
        }

        wp_safe_redirect( add_query_arg( [ 'page' => 'bss-email-templates', 'template' => $key, 'saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private function default_subject( string $key ): string {
        $defaults = [
            'sponsorship_confirmation' => 'Your shield sponsorship — {campaign_name}',
            'artwork_reminder'         => 'Reminder: upload your artwork for {campaign_name}',
            'final_artwork_reminder'   => 'Final reminder: artwork needed for {campaign_name}',
            'refund_confirmation'      => 'Your refund for {campaign_name}',
            'gdpr_removal'             => 'Your data has been removed',
        ];
        return $defaults[ $key ] ?? '';
    }

    private function default_body( string $key ): string {
        $defaults = [
            'sponsorship_confirmation' =>
                "<p>Dear {sponsor_name},</p>\n<p>Thank you for sponsoring a shield at {campaign_name}!</p>\n<p>You have sponsored: {shield_names}</p>\n<p>Total paid: {total_amount}</p>\n<p>Please upload your logo and sponsorship text here: <a href=\"{edit_url}\">{edit_url}</a></p>\n<p>Thanks,<br/>The Battle of Evesham team</p>",
            'artwork_reminder' =>
                "<p>Dear {sponsor_name},</p>\n<p>This is a reminder to upload your artwork for {campaign_name}. The cut-off date is {cutoff_date}.</p>\n<p>Upload here: <a href=\"{edit_url}\">{edit_url}</a></p>\n<p>Thanks,<br/>The Battle of Evesham team</p>",
            'final_artwork_reminder' =>
                "<p>Dear {sponsor_name},</p>\n<p>This is your final reminder! The artwork deadline for {campaign_name} is <strong>{cutoff_date}</strong>.</p>\n<p>Upload now: <a href=\"{edit_url}\">{edit_url}</a></p>\n<p>If you need help, contact us at {help_email}.</p>",
            'refund_confirmation' =>
                "<p>Dear {sponsor_name},</p>\n<p>Your refund for {campaign_name} has been processed.</p>\n<p>Payment method: {payment_method}</p>\n<p>If you have questions, contact {help_email}.</p>",
            'gdpr_removal' =>
                "<p>Dear {sponsor_name},</p>\n<p>Your personal data has been removed from our systems in response to your request.</p>\n<p>Anonymised sponsorship records may be retained for accounting purposes.</p>",
        ];
        return $defaults[ $key ] ?? '';
    }
}
