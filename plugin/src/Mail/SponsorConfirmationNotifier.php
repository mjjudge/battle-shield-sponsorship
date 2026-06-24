<?php

namespace BattleShieldSponsorship\Mail;

use BattleShieldSponsorship\Services\CampaignService;
use BattleShieldSponsorship\Services\ContactService;
use BattleShieldSponsorship\Services\ShieldService;
use BattleShieldSponsorship\Services\SponsorshipService;
use BattleShieldSponsorship\Services\UploadTokenService;

defined( 'ABSPATH' ) || exit;

class SponsorConfirmationNotifier {

    public function send( int $sponsorship_id ): void {
        $sponsorship_service  = new SponsorshipService();
        $upload_token_service = new UploadTokenService();

        $sponsorship = $sponsorship_service->get_by_id( $sponsorship_id );
        if ( ! $sponsorship ) {
            return;
        }

        $contact = ( new ContactService() )->get_by_id( (int) $sponsorship->contact_id );
        if ( ! $contact || '' === trim( (string) $contact->email ) ) {
            return;
        }

        $campaign = ( new CampaignService() )->get_by_id( (int) $sponsorship->campaign_id );
        if ( ! $campaign ) {
            return;
        }

        // Ensure an upload token exists; create one if not (covers online Stripe flow).
        $token = $upload_token_service->get_token_for_sponsorship( $sponsorship_id );
        if ( ! $token ) {
            $token = $upload_token_service->create_for_sponsorship( $sponsorship_id );
        }
        $edit_url = $upload_token_service->edit_url( $token );

        $items       = $sponsorship_service->get_items( $sponsorship_id );
        $shield_svc  = new ShieldService();
        $shields     = array_map(
            fn( $item ) => (string) ( $shield_svc->get_by_id( (int) $item->shield_id )->name ?? '' ),
            $items
        );
        $cutoff_date = (string) ( $campaign->artwork_cutoff_date ?? '' );

        $data = [
            'sponsor_name'   => (string) $contact->contact_name,
            'display_name'   => (string) $sponsorship->display_name,
            'campaign_name'  => (string) $campaign->name,
            'shield_names'   => implode( ', ', array_filter( $shields ) ),
            'cutoff_date'    => $cutoff_date ? date( 'd/m/Y', strtotime( $cutoff_date ) ) : '',
            'edit_url'       => $edit_url,
            'total_amount'   => number_format( (float) $sponsorship->total_amount, 2 ),
            'payment_method' => (string) $sponsorship->payment_method,
        ];

        $renderer = new TemplateRenderer();
        $mailer   = new Mailer();

        $mailer->send( [
            'to'             => (string) $contact->email,
            'subject'        => $renderer->render_subject( 'sponsorship_confirmation', $data ),
            'body'           => $renderer->render_body( 'sponsorship_confirmation', $data ),
            'email_type'     => 'sponsorship_confirmation',
            'campaign_id'    => (int) $campaign->id,
            'sponsorship_id' => $sponsorship_id,
            'contact_id'     => (int) $contact->id,
        ] );
    }
}
