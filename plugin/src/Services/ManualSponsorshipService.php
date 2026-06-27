<?php

namespace BattleShieldSponsorship\Services;

use BattleShieldSponsorship\Audit\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Handles manually-entered sponsorships (cash, bank transfer, card in person, other).
 * These bypass Stripe and are recorded directly by administrators.
 */
class ManualSponsorshipService {

    /**
     * Create a complete (already paid) manual sponsorship.
     *
     * @param array<string, mixed> $data
     * @return array{sponsorship_id:int, contact_id:int}
     */
    public function create( array $data ): array {
        $contact_service     = new ContactService();
        $sponsorship_service = new SponsorshipService();
        $reservation_service = new ReservationService();
        $upload_token_service = new UploadTokenService();

        $contact_id = $contact_service->find_or_create( [
            'contact_name'     => $data['contact_name'] ?? '',
            'display_name'     => $data['display_name'] ?? '',
            'email'            => $data['email'] ?? '',
            'phone'            => $data['phone'] ?? '',
            'website_url'      => $data['website_url'] ?? '',
            'marketing_opt_in' => $data['marketing_opt_in'] ?? false,
        ] );

        // Accepts: shields => [ ['shield_id' => int, 'price_paid' => float], ... ]
        $shields = array_filter( (array) ( $data['shields'] ?? [] ), fn( $s ) => ! empty( $s['shield_id'] ) );
        $total   = array_sum( array_column( $shields, 'price_paid' ) );

        $allowed_methods = [ 'stripe', 'bank_transfer', 'cash', 'cheque', 'other' ];
        $payment_method  = in_array( $data['payment_method'] ?? 'cash', $allowed_methods, true )
            ? $data['payment_method']
            : 'cash';

        $sponsorship_id = $sponsorship_service->create_pending( [
            'campaign_id'       => (int) ( $data['campaign_id'] ?? 0 ),
            'contact_id'        => $contact_id,
            'display_name'      => $data['display_name'] ?? '',
            'sponsor_text'      => $data['sponsor_text'] ?? '',
            'sponsor_url'       => $data['sponsor_url'] ?? '',
            'sponsor_phone'     => $data['sponsor_phone'] ?? '',
            'logo_attachment_id' => $data['logo_attachment_id'] ?? null,
            'payment_method'    => $payment_method,
            'total_amount'      => $total,
            'gift_aid_declared' => $data['gift_aid_declared'] ?? false,
        ] );

        foreach ( $shields as $item ) {
            $sponsorship_service->add_item( $sponsorship_id, (int) $item['shield_id'], (float) ( $item['price_paid'] ?? 0 ) );
        }

        // Create the token before mark_paid so the bss_payment_confirmed hook
        // (SponsorConfirmationNotifier) finds it and includes the upload link in the email.
        $upload_token_service->create_for_sponsorship( $sponsorship_id );

        $sponsorship_service->mark_paid( $sponsorship_id );

        // Admin-set display_name must not suppress the sponsor artwork reminder workflow.
        $sponsorship_service->mark_artwork_incomplete( $sponsorship_id );

        Logger::log( 'manual_sponsorship_created', 'sponsorship', $sponsorship_id, null, [
            'contact_id'     => $contact_id,
            'shield_count'   => count( $shields ),
            'payment_method' => $payment_method,
            'total_amount'   => $total,
        ] );

        return [
            'sponsorship_id' => $sponsorship_id,
            'contact_id'     => $contact_id,
        ];
    }
}
