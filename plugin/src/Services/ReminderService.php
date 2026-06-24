<?php

namespace BattleShieldSponsorship\Services;

use BattleShieldSponsorship\Mail\Mailer;
use BattleShieldSponsorship\Mail\TemplateRenderer;

defined( 'ABSPATH' ) || exit;

class ReminderService {

    public const CRON_HOOK = 'bss_send_artwork_reminders';

    /**
     * Send artwork upload reminders for all incomplete paid sponsorships across active campaigns.
     * Called by WP-Cron.
     */
    public function run_scheduled(): void {
        $campaign_service     = new CampaignService();
        $sponsorship_service  = new SponsorshipService();
        $contact_service      = new ContactService();
        $upload_token_service = new UploadTokenService();
        $mailer               = new Mailer();
        $renderer             = new TemplateRenderer();

        $campaigns = array_filter( $campaign_service->get_all(), fn( $c ) => 'active' === (string) $c->status );

        foreach ( $campaigns as $campaign ) {
            $campaign_id    = (int) $campaign->id;
            $cutoff_date    = (string) ( $campaign->artwork_cutoff_date ?? '' );
            $past_cutoff    = '' !== $cutoff_date && strtotime( $cutoff_date ) < time();
            $frequency_days = max( 1, (int) ( $campaign->reminder_frequency_days ?? 2 ) );
            $final_days     = max( 1, (int) ( $campaign->final_reminder_days_before ?? 1 ) );

            if ( $past_cutoff ) {
                continue;
            }

            $is_final_reminder_day = '' !== $cutoff_date
                && gmdate( 'Y-m-d', strtotime( "-{$final_days} days", strtotime( $cutoff_date ) ) ) === gmdate( 'Y-m-d' );

            $incomplete = $sponsorship_service->get_all( [
                'campaign_id'    => $campaign_id,
                'payment_status' => 'paid',
                'artwork_status' => 'incomplete',
            ] );

            foreach ( $incomplete as $sponsorship ) {
                $sponsorship_id = (int) $sponsorship->id;
                $contact        = $contact_service->get_by_id( (int) $sponsorship->contact_id );
                if ( ! $contact || '' === (string) $contact->email ) {
                    continue;
                }

                $should_send = $is_final_reminder_day || $this->is_due( $sponsorship_id, $frequency_days );
                if ( ! $should_send ) {
                    continue;
                }

                $token     = $upload_token_service->get_token_for_sponsorship( $sponsorship_id );
                $edit_url  = $token ? $upload_token_service->edit_url( $token ) : '';
                $items     = $sponsorship_service->get_items( $sponsorship_id );
                $shields   = array_map( fn( $item ) => (string) ( ( new ShieldService() )->get_by_id( (int) $item->shield_id )->name ?? '' ), $items );

                // Build outstanding items list for the email.
                $outstanding   = [];
                $print_warning = '';
                if ( empty( $sponsorship->display_name ) ) {
                    $outstanding[]  = __( 'Sponsor display name (required — patch cannot be printed without this)', 'battle-shield-sponsorship' );
                    $print_warning  = '<p><strong>' . esc_html__( 'Important: your patch cannot be printed until a sponsor display name is provided.', 'battle-shield-sponsorship' ) . '</strong></p>';
                }
                if ( empty( $sponsorship->logo_attachment_id ) && empty( $sponsorship->logo_not_needed ) ) {
                    $outstanding[] = __( 'Logo or image for the back of the shield', 'battle-shield-sponsorship' );
                }
                $outstanding_items = $outstanding
                    ? '<ul>' . implode( '', array_map( fn( $s ) => '<li>' . esc_html( $s ) . '</li>', $outstanding ) ) . '</ul>'
                    : '';

                $data = [
                    'sponsor_name'      => (string) $contact->contact_name,
                    'display_name'      => (string) $sponsorship->display_name,
                    'campaign_name'     => (string) $campaign->name,
                    'cutoff_date'       => $cutoff_date ? date( 'd/m/Y', strtotime( $cutoff_date ) ) : '',
                    'shield_names'      => implode( ', ', array_filter( $shields ) ),
                    'edit_url'          => $edit_url,
                    'is_final'          => $is_final_reminder_day,
                    'outstanding_items' => $outstanding_items,
                    'print_warning'     => $print_warning,
                ];

                $type    = $is_final_reminder_day ? 'final_artwork_reminder' : 'artwork_reminder';
                $subject = $renderer->render_subject( $type, $data );
                $body    = $renderer->render_body( $type, $data );

                $mailer->send( [
                    'to'             => (string) $contact->email,
                    'subject'        => $subject,
                    'body'           => $body,
                    'email_type'     => $type,
                    'campaign_id'    => $campaign_id,
                    'sponsorship_id' => $sponsorship_id,
                    'contact_id'     => (int) $contact->id,
                ] );
            }
        }
    }

    private function is_due( int $sponsorship_id, int $frequency_days ): bool {
        global $wpdb;
        $table    = \BattleShieldSponsorship\Database\Schema::table_name( 'email_log' );
        $last_sent = $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(sent_at) FROM {$table} WHERE sponsorship_id = %d AND email_type IN ('artwork_reminder','final_artwork_reminder') AND status = 'sent'",
            $sponsorship_id
        ) );

        if ( ! $last_sent ) {
            return true;
        }

        $next_due = strtotime( "+{$frequency_days} days", strtotime( (string) $last_sent ) );

        return time() >= $next_due;
    }

    public static function ensure_schedule(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    public static function clear_schedule(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }
}
