<?php

namespace BattleShieldSponsorship\Mail;

defined( 'ABSPATH' ) || exit;

class TemplateRenderer {

    /**
     * @param array<string, mixed> $data
     */
    public function render_subject( string $template_key, array $data ): string {
        $settings = (array) get_option( 'bss_settings', [] );

        $default = match ( $template_key ) {
            'sponsorship_confirmation' => 'Your Battle of Evesham shield sponsorship — {campaign_name}',
            'artwork_reminder'         => 'Reminder: please upload your shield artwork — {campaign_name}',
            'final_artwork_reminder'   => 'Final reminder: artwork deadline tomorrow — {campaign_name}',
            'refund_confirmation'      => 'Your shield sponsorship refund — {campaign_name}',
            'gdpr_removal'             => 'Your data removal request has been processed',
            default                    => 'Battle of Evesham Sponsorship Update',
        };

        $subject = (string) ( $settings[ 'email_' . $template_key . '_subject' ] ?? $default );

        return $this->replace_tags( $subject, $data );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render_body( string $template_key, array $data ): string {
        $settings = (array) get_option( 'bss_settings', [] );

        $default = match ( $template_key ) {
            'sponsorship_confirmation' => $this->default_confirmation_body(),
            'artwork_reminder'         => $this->default_reminder_body( false ),
            'final_artwork_reminder'   => $this->default_reminder_body( true ),
            'refund_confirmation'      => $this->default_refund_body(),
            'gdpr_removal'             => '<p>Dear {sponsor_name},</p><p>Your personal data has been removed from our records as requested. Thank you for supporting the Battle of Evesham.</p>',
            default                    => '<p>Dear {sponsor_name},</p><p>Thank you for supporting the Battle of Evesham.</p>',
        };

        $body = (string) ( $settings[ 'email_' . $template_key . '_body' ] ?? $default );
        $body = $this->replace_tags( $body, $data );
        $body = $this->wrap_in_layout( $body );

        return wp_kses_post( $body );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function replace_tags( string $text, array $data ): string {
        $replacements = [
            '{sponsor_name}'    => (string) ( $data['sponsor_name'] ?? '' ),
            '{display_name}'    => (string) ( $data['display_name'] ?? '' ),
            '{campaign_name}'   => (string) ( $data['campaign_name'] ?? '' ),
            '{shield_names}'    => (string) ( $data['shield_names'] ?? '' ),
            '{cutoff_date}'     => (string) ( $data['cutoff_date'] ?? '' ),
            '{edit_url}'        => esc_url( (string) ( $data['edit_url'] ?? '' ) ),
            '{total_amount}'    => (string) ( $data['total_amount'] ?? '' ),
            '{payment_method}'  => (string) ( $data['payment_method'] ?? '' ),
            '{help_email}'      => (string) ( ( (array) get_option( 'bss_settings', [] ) )['contact_email'] ?? 'helpgrow@battleofevesham.co.uk' ),
        ];

        return strtr( $text, $replacements );
    }

    private function wrap_in_layout( string $body_content ): string {
        $settings  = (array) get_option( 'bss_settings', [] );
        $logo_url  = esc_url( (string) ( $settings['email_logo_url'] ?? '' ) );
        $org_name  = esc_html( (string) ( $settings['organisation_name'] ?? 'Battle of Evesham' ) );

        $logo_html = '' !== $logo_url
            ? '<p style="text-align:center;"><img src="' . $logo_url . '" alt="' . $org_name . '" style="max-height:80px;width:auto;" /></p>'
            : '<p style="text-align:center;font-weight:bold;">' . $org_name . '</p>';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">'
            . $logo_html
            . $body_content
            . '<hr style="margin-top:30px;"><p style="font-size:12px;color:#666;">Battle of Evesham &mdash; <a href="https://battleofevesham.co.uk">battleofevesham.co.uk</a></p>'
            . '</body></html>';
    }

    private function default_confirmation_body(): string {
        return '<p>Dear {sponsor_name},</p>
<p>Thank you for sponsoring the following shields for <strong>{campaign_name}</strong>:</p>
<p><strong>{shield_names}</strong></p>
<p>Total paid: <strong>£{total_amount}</strong></p>
<p>Please upload your logo and display text using the secure link below before the artwork deadline of <strong>{cutoff_date}</strong>:</p>
<p><a href="{edit_url}">Upload your artwork</a></p>
<p>If you have any questions please contact us at <a href="mailto:{help_email}">{help_email}</a>.</p>
<p>Thank you for your support!</p>
<p>The Battle of Evesham Team</p>';
    }

    private function default_reminder_body( bool $is_final ): string {
        $opening = $is_final
            ? '<p>Dear {sponsor_name},</p><p>This is your <strong>final reminder</strong> — the artwork deadline for <strong>{campaign_name}</strong> is <strong>tomorrow ({cutoff_date})</strong>.</p>'
            : '<p>Dear {sponsor_name},</p><p>This is a friendly reminder that we are still waiting for your artwork for <strong>{campaign_name}</strong>.</p>';

        return $opening . '
<p>Shield(s) sponsored: <strong>{shield_names}</strong></p>
<p>Artwork deadline: <strong>{cutoff_date}</strong></p>
<p>Please upload your logo and display text using the secure link below:</p>
<p><a href="{edit_url}">Upload your artwork</a></p>
<p>If you have already submitted your artwork, please ignore this message.</p>
<p>After the deadline, updates cannot be guaranteed to appear on the printed shield patches.</p>
<p>Need help? Contact <a href="mailto:{help_email}">{help_email}</a>.</p>
<p>The Battle of Evesham Team</p>';
    }

    private function default_refund_body(): string {
        return '<p>Dear {sponsor_name},</p>
<p>We have processed a refund for your shield sponsorship of <strong>{campaign_name}</strong>.</p>
<p>Shield(s): {shield_names}</p>
<p>If you have any questions please contact <a href="mailto:{help_email}">{help_email}</a>.</p>
<p>The Battle of Evesham Team</p>';
    }
}
