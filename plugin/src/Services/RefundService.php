<?php

namespace BattleShieldSponsorship\Services;

use BattleShieldSponsorship\Audit\Logger;

defined( 'ABSPATH' ) || exit;

class RefundService {

    /**
     * Process a refund. Calls Stripe for online payments; marks directly for manual ones.
     *
     * @return array{ok:bool, error?:string}
     */
    public function process( int $sponsorship_id, string $reason = '' ): array {
        $service     = new SponsorshipService();
        $sponsorship = $service->get_by_id( $sponsorship_id );

        if ( ! is_object( $sponsorship ) ) {
            return [ 'ok' => false, 'error' => 'sponsorship_not_found' ];
        }

        if ( 'paid' !== (string) $sponsorship->payment_status ) {
            return [ 'ok' => false, 'error' => 'not_refundable' ];
        }

        $is_stripe = 'stripe' === (string) $sponsorship->payment_method;

        if ( $is_stripe ) {
            $result = $this->stripe_refund( $sponsorship );
            if ( ! $result['ok'] ) {
                return $result;
            }
            Logger::log( 'refund_issued', 'sponsorship', $sponsorship_id, null, [ 'stripe_refund_id' => $result['refund_id'] ?? '', 'reason' => $reason ] );
        }

        $service->mark_refunded( $sponsorship_id );

        if ( $is_stripe ) {
            Logger::log( 'stripe_refund_processed', 'sponsorship', $sponsorship_id );
        } else {
            Logger::log( 'manual_refund_processed', 'sponsorship', $sponsorship_id, null, [ 'reason' => $reason ] );
        }

        return [ 'ok' => true ];
    }

    /**
     * @param object $sponsorship
     * @return array{ok:bool, refund_id?:string, error?:string}
     */
    private function stripe_refund( object $sponsorship ): array {
        $intent_id = (string) ( $sponsorship->stripe_payment_intent_id ?? '' );
        $charge_id = (string) ( $sponsorship->stripe_charge_id ?? '' );

        if ( '' === $intent_id && '' === $charge_id ) {
            return [ 'ok' => false, 'error' => 'no_stripe_identifier' ];
        }

        $refund_id = ( new StripeService() )->refund( $intent_id, $charge_id );

        if ( '' === $refund_id ) {
            return [ 'ok' => false, 'error' => 'stripe_refund_failed' ];
        }

        return [ 'ok' => true, 'refund_id' => $refund_id ];
    }
}
