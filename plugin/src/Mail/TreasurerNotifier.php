<?php

namespace BattleShieldSponsorship\Mail;

use BattleShieldSponsorship\Services\SponsorshipService;
use BattleShieldSponsorship\Services\ShieldService;
use BattleShieldSponsorship\Services\ContactService;
use BattleShieldSponsorship\Services\CampaignService;

defined( 'ABSPATH' ) || exit;

class TreasurerNotifier {

    public function send( int $sponsorship_id ): void {
        $settings        = (array) get_option( 'bss_settings', [] );
        $treasurer_email = sanitize_email( (string) ( $settings['treasurer_email'] ?? '' ) );

        if ( '' === $treasurer_email ) {
            return;
        }

        $sponsorship_service = new SponsorshipService();
        $sponsorship         = $sponsorship_service->get_by_id( $sponsorship_id );

        if ( ! $sponsorship ) {
            return;
        }

        $contact  = ( new ContactService() )->get_by_id( (int) $sponsorship->contact_id );
        $campaign = ( new CampaignService() )->get_by_id( (int) $sponsorship->campaign_id );
        $items    = $sponsorship_service->get_items( $sponsorship_id );

        $shield_service = new ShieldService();
        $shield_names   = [];
        foreach ( $items as $item ) {
            $shield = $shield_service->get_by_id( (int) $item->shield_id );
            if ( $shield ) {
                $shield_names[] = (string) $shield->name;
            }
        }

        $sponsor_name   = (string) $sponsorship->display_name;
        $contact_email  = $contact ? (string) $contact->email : '—';
        $campaign_name  = $campaign ? (string) $campaign->name : '—';
        $amount         = '£' . number_format( (float) $sponsorship->total_amount, 2 );
        $method         = ucfirst( str_replace( '_', ' ', (string) $sponsorship->payment_method ) );
        $gift_aid       = (int) $sponsorship->gift_aid_declared ? 'Yes' : 'No';
        $admin_url      = admin_url( 'admin.php?page=bss-sponsorship-view&id=' . $sponsorship_id );

        $subject = sprintf(
            __( 'New shield sponsorship payment — %s', 'battle-shield-sponsorship' ),
            $campaign_name
        );

        $body = sprintf(
            '<p>A new shield sponsorship payment has been received.</p>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;font-family:sans-serif;">
  <tr><th align="left">Sponsor</th><td>%s</td></tr>
  <tr><th align="left">Email</th><td>%s</td></tr>
  <tr><th align="left">Campaign</th><td>%s</td></tr>
  <tr><th align="left">Shield(s)</th><td>%s</td></tr>
  <tr><th align="left">Amount</th><td>%s</td></tr>
  <tr><th align="left">Payment method</th><td>%s</td></tr>
  <tr><th align="left">Gift Aid</th><td>%s</td></tr>
</table>
<p><a href="%s">View sponsorship in admin</a></p>',
            esc_html( $sponsor_name ),
            esc_html( $contact_email ),
            esc_html( $campaign_name ),
            esc_html( implode( ', ', $shield_names ) ),
            esc_html( $amount ),
            esc_html( $method ),
            esc_html( $gift_aid ),
            esc_url( $admin_url )
        );

        ( new Mailer() )->send( [
            'to'             => $treasurer_email,
            'subject'        => $subject,
            'body'           => $body,
            'email_type'     => 'treasurer_notification',
            'sponsorship_id' => $sponsorship_id,
            'campaign_id'    => $campaign ? (int) $campaign->id : null,
        ] );
    }
}
