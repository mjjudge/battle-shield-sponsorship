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

        $shield_ids = array_filter( array_map( 'intval', (array) ( $data['shield_ids'] ?? [] ) ) );
        $price_each = (float) ( $data['price_each'] ?? 0 );
        $total      = $price_each * count( $shield_ids );

        $sponsorship_id = $sponsorship_service->create_pending( [
            'campaign_id'    => (int) ( $data['campaign_id'] ?? 0 ),
            'contact_id'     => $contact_id,
            'display_name'   => $data['display_name'] ?? '',
            'sponsor_text'   => $data['sponsor_text'] ?? '',
            'logo_attachment_id' => $data['logo_attachment_id'] ?? null,
            'payment_method' => $data['payment_method'] ?? 'cash',
            'total_amount'   => $total,
            'gift_aid_declared' => $data['gift_aid_declared'] ?? false,
        ] );

        foreach ( $shield_ids as $shield_id ) {
            $sponsorship_service->add_item( $sponsorship_id, $shield_id, $price_each );
        }

        $sponsorship_service->mark_paid( $sponsorship_id );
        $sponsorship_service->refresh_artwork_status( $sponsorship_id );

        $upload_token_service->create_for_sponsorship( $sponsorship_id );

        Logger::log( 'manual_sponsorship_created', 'sponsorship', $sponsorship_id, null, [
            'contact_id'     => $contact_id,
            'shield_count'   => count( $shield_ids ),
            'payment_method' => $data['payment_method'] ?? 'cash',
        ] );

        return [
            'sponsorship_id' => $sponsorship_id,
            'contact_id'     => $contact_id,
        ];
    }
}
